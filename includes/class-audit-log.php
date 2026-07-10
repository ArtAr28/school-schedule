<?php
/**
 * SS_AuditLog — saugumo įvykių žurnalas + įsilaužimų aptikimas (IDS).
 *
 * Rašo visus svarbius saugumo įvykius į atskirą lentelę ss_security_log.
 * NIEKADA nelogina slaptažodžių ar žetonų — tik faktus ir metaduomenis.
 */
if (!defined('ABSPATH')) exit;

class SS_AuditLog {

    /* ── Lentelės sukūrimas ─────────────────────────────────────────── */
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        $t = $wpdb->prefix . 'ss_security_log';
        dbDelta("CREATE TABLE $t (
          id bigint(20) NOT NULL AUTO_INCREMENT,
          event varchar(40) NOT NULL DEFAULT '',
          severity varchar(10) NOT NULL DEFAULT 'info',
          user_id bigint(20) NOT NULL DEFAULT 0,
          target_id bigint(20) NOT NULL DEFAULT 0,
          username_tried varchar(60) NOT NULL DEFAULT '',
          method varchar(20) NOT NULL DEFAULT '',
          ip varchar(45) NOT NULL DEFAULT '',
          user_agent text NOT NULL,
          extra longtext NOT NULL,
          created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
          PRIMARY KEY  (id),
          KEY event (event),
          KEY severity (severity),
          KEY user_id (user_id),
          KEY ip (ip),
          KEY created_at (created_at)
        ) $c;");
    }

    /* ── Įrašyti įvykį ──────────────────────────────────────────────── */
    public static function write(string $event, array $ctx = []): void {
        // Audito žurnalas visada įjungtas (žr. SS_Security::ALWAYS_ON)
        global $wpdb;
        $event    = substr(sanitize_text_field($event), 0, 40);
        $ip       = sanitize_text_field($ctx['ip'] ?? SS_RateLimit::get_client_ip());
        $user_id  = (int) ($ctx['user_id'] ?? get_current_user_id());   // actor
        $target_id= (int) ($ctx['target_id'] ?? 0);                     // affected user (actions-on-others)
        $username = sanitize_text_field($ctx['username'] ?? '');
        // Įrašome paskyros prisijungimo vardą momentinę kopiją, kad įvykis liktų
        // priskirtas net jei paskyra vėliau ištrinama (pvz. mokslo metų pabaiga /
        // laidos baigimas). Kitaip žurnale liktų „—" be jokios informacijos.
        if ($username === '' && $user_id > 0) {
            $u = get_userdata($user_id);
            if ($u) $username = $u->user_login;
        }
        $severity = sanitize_text_field($ctx['severity'] ?? self::severity_for($event));
        if (!in_array($severity, ['info', 'notice', 'warning', 'critical'], true)) $severity = 'info';
        $wpdb->insert($wpdb->prefix . 'ss_security_log', [
            'event'          => $event,
            'severity'       => $severity,
            'user_id'        => $user_id,
            'target_id'      => $target_id,
            'username_tried' => substr($username, 0, 60),
            'method'         => substr(sanitize_text_field($ctx['method'] ?? ''), 0, 20),
            'ip'             => substr($ip, 0, 45),
            'user_agent'     => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'extra'          => wp_json_encode(array_diff_key($ctx, array_flip(['user_id','ip','method','username','severity','target_id']))),
            'created_at'     => current_time('mysql'),
        ]);
    }

    /** Default severity for an event when the caller doesn't pass one. */
    public static function severity_for(string $event): string {
        static $map = [
            'ids_alert' => 'critical', 'google_token_invalid' => 'critical',
            'turnstile_block' => 'warning', 'turnstile_fail' => 'notice', 'turnstile_pass' => 'info',
            'login_blocked' => 'warning', 'login_failed' => 'warning', '2fa_failed' => 'warning',
            'account_blocked' => 'warning', 'device_blocked' => 'warning',
            'session_kicked' => 'warning', '2fa_disabled' => 'warning',
            'settings_saved' => 'notice', 'schedule_edited' => 'notice', 'entity_changed' => 'notice',
            'changes_published' => 'notice', 'survey_status' => 'notice',
            'data_import' => 'notice', 'data_export' => 'notice',
            'role_changed' => 'notice', 'caps_changed' => 'notice', 'account_updated' => 'notice',
            'account_unblocked' => 'notice', 'device_unblocked' => 'notice',
            'year_rollover' => 'notice', 'password_changed' => 'notice', '2fa_enabled' => 'notice',
        ];
        return $map[$event] ?? 'info';
    }

    /** Neutralise spreadsheet formula injection for CSV cells (shared with exports). */
    public static function csv_cell($v): string {
        $v = (string) $v;
        if ($v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) $v = "'" . $v;
        return $v;
    }

    /* ── Skaityti įrašus ────────────────────────────────────────────── */
    /**
     * Build a "[where_sql, args]" pair from a filter array. Supported keys:
     * event, severity, ip, user_id (matches actor OR target), date_from, date_to (Y-m-d), search.
     */
    private static function build_filters(array $f): array {
        global $wpdb;
        $where = []; $args = [];
        if (!empty($f['event']))     { $where[] = 'event = %s';    $args[] = $f['event']; }
        if (!empty($f['severity']))  { $where[] = 'severity = %s'; $args[] = $f['severity']; }
        if (!empty($f['ip']))        { $where[] = 'ip = %s';       $args[] = $f['ip']; }
        if (!empty($f['user_id']))   { $where[] = '(user_id = %d OR target_id = %d)'; $args[] = (int)$f['user_id']; $args[] = (int)$f['user_id']; }
        if (!empty($f['date_from'])) { $where[] = 'created_at >= %s'; $args[] = $f['date_from'] . ' 00:00:00'; }
        if (!empty($f['date_to']))   { $where[] = 'created_at <= %s'; $args[] = $f['date_to']   . ' 23:59:59'; }
        if (!empty($f['search']))    {
            $like = '%' . $wpdb->esc_like($f['search']) . '%';
            $where[] = '(username_tried LIKE %s OR ip LIKE %s OR extra LIKE %s)';
            $args[] = $like; $args[] = $like; $args[] = $like;
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $args];
    }

    /** Filtered, paginated log read (newest first). */
    public static function query(array $filters = [], int $limit = 50, int $offset = 0): array {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_security_log';
        [$where, $args] = self::build_filters($filters);
        $args[] = max(1, $limit); $args[] = max(0, $offset);
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $t$where ORDER BY id DESC LIMIT %d OFFSET %d", $args)) ?: [];
    }

    /** Total rows matching the same filters (for pagination). */
    public static function count_filtered(array $filters = []): int {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_security_log';
        [$where, $args] = self::build_filters($filters);
        $sql = "SELECT COUNT(*) FROM $t$where";
        return (int) ($args ? $wpdb->get_var($wpdb->prepare($sql, $args)) : $wpdb->get_var($sql));
    }

    /** Back-compat wrapper. */
    public static function get_recent(int $limit = 200, string $event_filter = '', string $ip_filter = ''): array {
        return self::query(['event' => $event_filter, 'ip' => $ip_filter], $limit, 0);
    }

    public static function count_all(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ss_security_log");
    }

    /** Statistika paskutinėms 24 val. */
    public static function stats_24h(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_security_log';
        $cutoff = date('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT event, COUNT(*) as c FROM $t WHERE created_at >= %s GROUP BY event", $cutoff
        )) ?: [];
        $out = [];
        foreach ($rows as $r) $out[$r->event] = (int) $r->c;
        return $out;
    }

    /** Išvalyti senesnius nei N dienų įrašus (cron). */
    public static function prune(int $days = 90): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}ss_security_log WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            max(1, $days)
        ));
    }

    public static function clear_all(): void {
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}ss_security_log");
    }

    /* ════════════════════════════════════════════════════════════════
       ĮSILAUŽIMŲ APTIKIMAS (IDS)
       ════════════════════════════════════════════════════════════════ */

    /**
     * Po login_failed — patikrina ar vienas IP atakuoja daug paskyrų
     * (credential stuffing) ir, jei taip, perspėja administratorių.
     */
    public static function maybe_alert_admin(string $ip): void {
        if (!get_option('ss_sec_ids_enabled', 1)) return;

        $throttle_key = 'ss_ids_alerted_' . md5($ip);
        if (get_transient($throttle_key)) return;

        global $wpdb;
        $t       = $wpdb->prefix . 'ss_security_log';
        $minutes = max(1, (int) get_option('ss_sec_ids_window_min', 15));
        $thresh  = max(2, (int) get_option('ss_sec_ids_threshold', 10));
        $cutoff  = date('Y-m-d H:i:s', time() - $minutes * MINUTE_IN_SECONDS);

        $unique = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT username_tried) FROM $t
              WHERE ip=%s AND event='login_failed' AND created_at >= %s",
            $ip, $cutoff
        ));

        if ($unique >= $thresh) {
            set_transient($throttle_key, 1, 30 * MINUTE_IN_SECONDS);
            self::write('ids_alert', ['ip' => $ip, 'unique_accounts' => $unique]);

            $admin = get_option('ss_admin_email', get_option('admin_email'));
            if ($admin && class_exists('SS_Mail')) {
                $geo_label = self::geo_label($ip);
                $geo_line  = $geo_label ? '<p style="margin:0 0 12px;">Apytikslė vieta: <strong>' . esc_html($geo_label) . '</strong></p>' : '';
                SS_Mail::send($admin, 'Galima ataka į sistemą', [
                    'title'     => 'Saugumo įspėjimas',
                    'accent'    => SS_Mail::RED,
                    'icon'      => '!',
                    'body'      => '<p style="margin:0 0 12px;">IP adresas <strong>' . esc_html($ip) . '</strong> per pastarąsias '
                                 . (int) $minutes . ' min. bandė prisijungti prie <strong>' . (int) $unique . '</strong> skirtingų paskyrų.</p>'
                                 . $geo_line
                                 . '<p style="margin:0;">Tai gali būti automatizuota žodyno ataka (credential stuffing).</p>',
                    'note'      => 'IP adresas automatiškai ribojamas brute-force apsaugos.',
                    'note_type' => 'warn',
                ]);
            }
        }
    }

    /**
     * Po sėkmingo prisijungimo — aptinka naują įrenginį ir perspėja vartotoją.
     */
    public static function check_new_device(int $user_id, string $ip, string $method = 'password'): void {
        if (!get_option('ss_sec_new_device_enabled', 1)) return;

        $ua          = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $fingerprint = self::current_device_fp($ip);

        $known = (array) get_user_meta($user_id, 'ss_known_devices', true);

        if (!in_array($fingerprint, $known, true)) {
            // Per-user opt-out (default: enabled). Device tracking + history still happen.
            $user_wants = (get_user_meta($user_id, 'ss_user_new_device_notify', true) !== '0');
            $user = get_user_by('id', $user_id);
            if ($user_wants && $user && !empty($known) && class_exists('SS_Mail')) {
                SS_Mail::send($user->user_email, 'Naujas prisijungimas', [
                    'title'     => 'Prisijungimas iš naujo įrenginio',
                    'icon'      => '!',
                    'body'      => '<p style="margin:0 0 4px;">Prie Jūsų paskyros prisijungta iš naujo, dar nematyto įrenginio. Žemiau — prisijungimo informacija.</p>',
                    'extra'     => self::context_email_html($ip, $ua, $method),
                    'note'      => 'Jei tai buvote ne Jūs — nedelsdami pakeiskite slaptažodį ir įjunkite 2FA.',
                    'note_type' => 'warn',
                ]);
            }
            $known[] = $fingerprint;
            update_user_meta($user_id, 'ss_known_devices', array_slice(array_values(array_unique($known)), -10));
        }

        // Visada fiksuojamas prisijungimas istorijoje (nepriklausomai nuo to, ar įrenginys naujas)
        self::record_login_history($user_id, $ip, $method);
    }

    /**
     * Fiksuoja prisijungimą vartotojo istorijoje (ss_login_history).
     * Saugo paskutinius 30 įrašų, naujausius pirmus.
     * Šį metodą kvieskite TIESIAI tik jei check_new_device nevykdomas (pvz. Google auth).
     */
    public static function record_login_history(int $user_id, string $ip, string $method = 'password'): void {
        $ua          = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $fingerprint = self::current_device_fp($ip);
        $entry       = [
            'ts'      => time(),
            'ip'      => substr($ip, 0, 45),
            'ua'      => substr($ua, 0, 300),
            'method'  => substr(sanitize_text_field($method), 0, 30),
            'fp'      => $fingerprint,
            // Tik iš talpyklos — neuždelsti login. Jei dar nepaskaičiuota, geo
            // bus apskaičiuota tinginio principu rodymo metu (nustatymuose / admin).
            'geo'     => self::geo_label_cached($ip),
        ];
        $history = (array) get_user_meta($user_id, 'ss_login_history', true);
        array_unshift($history, $entry);                       // naujausi pirmi
        $history = array_slice($history, 0, 30);              // max 30 įrašų
        update_user_meta($user_id, 'ss_login_history', $history);
    }

    /* ════════════════════════════════════════════════════════════════
       GEO + ĮRENGINIO ATPAŽINIMAS (bendros pagalbinės funkcijos)
       Naudojama: el. laiškuose, vartotojo nustatymuose, admin žurnale.
       ════════════════════════════════════════════════════════════════ */

    /**
     * Grąžina miestą + šalį pagal IP (su 12 val. talpykla per transient).
     * Privatūs / lokalūs IP grąžina tuščią rezultatą be tinklo užklausos.
     * @return array{city:string,country:string}
     */
    public static function geo_for_ip(string $ip): array {
        $empty = ['city' => '', 'country' => ''];
        $ip    = trim(explode(',', $ip)[0]);
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) return $empty;
        // Praleisti lokalius / privačius adresus — geo lookup beprasmis
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $empty;
        }
        $key    = 'ss_geo_' . md5($ip);
        $cached = get_transient($key);
        if (is_array($cached)) return $cached;

        $out = $empty;
        $resp = wp_remote_get("https://ipapi.co/{$ip}/json/", ['timeout' => 3]);
        if (!is_wp_error($resp)) {
            $b = json_decode(wp_remote_retrieve_body($resp), true);
            if (is_array($b) && empty($b['error'])) {
                $out = [
                    'city'    => sanitize_text_field($b['city']         ?? ''),
                    'country' => sanitize_text_field($b['country_name'] ?? ''),
                ];
            }
        }
        set_transient($key, $out, 12 * HOUR_IN_SECONDS);
        return $out;
    }

    /** „Vilnius, Lietuva" arba "" jei nežinoma. */
    public static function geo_label(string $ip): string {
        $g = self::geo_for_ip($ip);
        return trim(implode(', ', array_filter([$g['city'], $g['country']])));
    }

    /**
     * Tas pats kaip geo_label(), bet TIK iš talpyklos — jokios tinklo užklausos.
     * Naudojama prisijungimo kelyje, kad geo paieška neuždelstų login atsako.
     */
    public static function geo_label_cached(string $ip): string {
        $ip = trim(explode(',', $ip)[0]);
        if (!$ip) return '';
        $cached = get_transient('ss_geo_' . md5($ip));
        if (!is_array($cached)) return '';
        return trim(implode(', ', array_filter([$cached['city'] ?? '', $cached['country'] ?? ''])));
    }

    /** Naršyklė + OS iš User-Agent, pvz. „Chrome 120 · Windows". */
    public static function device_label(string $ua): string {
        if (!$ua) return '';
        if (class_exists('SS_Shortcodes')) {
            $browser = SS_Shortcodes::qr_browser($ua);
            $os      = SS_Shortcodes::qr_os($ua);
            $parts   = [];
            if ($browser && $browser !== 'Naršyklė') $parts[] = $browser;
            if ($os && $os !== 'OS')                  $parts[] = $os;
            if ($parts) return implode(' · ', $parts);
        }
        return mb_substr($ua, 0, 48);
    }

    /**
     * HTML eilutės el. laiškui su prisijungimo kontekstu (įrenginys, vieta, IP, laikas).
     * Naudoja SS_Mail::info_list() jei galima.
     */
    public static function context_email_html(string $ip, string $ua = '', string $method = ''): string {
        $ua     = $ua ?: sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $device = self::device_label($ua);
        $geo    = self::geo_label($ip);
        $rows   = [];
        if ($device)              $rows[] = ['label' => 'Įrenginys',   'value' => esc_html($device)];
        if ($geo)                 $rows[] = ['label' => 'Vieta',       'value' => esc_html($geo)];
        if ($ip)                  $rows[] = ['label' => 'IP adresas',  'value' => esc_html($ip)];
        $method_lbl = self::method_label($method);
        if ($method_lbl)          $rows[] = ['label' => 'Būdas',       'value' => esc_html($method_lbl)];
        $rows[] = ['label' => 'Laikas', 'value' => esc_html(wp_date('Y-m-d H:i'))];

        if (class_exists('SS_Mail')) return SS_Mail::info_list($rows);
        // Atsarginis variantas be SS_Mail
        $html = '<table style="width:100%;border-collapse:collapse;margin:16px 0">';
        foreach ($rows as $r) {
            $html .= '<tr><td style="padding:6px 10px;color:#555;font-weight:700">' . $r['label']
                   . '</td><td style="padding:6px 10px">' . $r['value'] . '</td></tr>';
        }
        return $html . '</table>';
    }

    /** Žmogui suprantamas prisijungimo būdo pavadinimas. */
    public static function method_label(string $method): string {
        $map = [
            'password'    => 'Slaptažodis',
            'qr'          => 'QR kodas',
            '2fa'         => 'Slaptažodis + 2FA',
            'backup_code' => '2FA atsarginis kodas',
            'google_auth' => 'Google paskyra',
        ];
        return $map[$method] ?? '';
    }

    /**
     * Grąžina dabartinio užklausos įrenginio kontrolinę sumą (fingerprint).
     * Naudojama tiek blokavimo tikrinimui, tiek vartotojo sąsajai.
     */
    public static function current_device_fp(string $ip): string {
        $ua        = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        $ip_prefix = implode('.', array_slice(explode('.', $ip), 0, 2));
        return hash('sha256', $ua . '|' . $ip_prefix);
    }

    /**
     * Grąžina true, jei dabartinio užklausos įrenginio kontrolinė suma
     * yra vartotojo užblokuotų įrenginių sąraše (ss_blocked_devices).
     */
    public static function is_device_blocked(int $user_id, string $ip): bool {
        $blocked = (array) get_user_meta($user_id, 'ss_blocked_devices', true);
        if (empty($blocked)) return false;
        return in_array(self::current_device_fp($ip), $blocked, true);
    }
}
