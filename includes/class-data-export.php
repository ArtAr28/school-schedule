<?php
/**
 * SS_Data_Export — „Mano duomenys": surenka VISĄ informaciją, kurią sistema
 * saugo apie prisijungusio vartotojo paskyrą (ir, jei įmanoma, kitų įskiepių —
 * Ątspėk / Wordle ir WP Statistics — duomenis), ir grąžina struktūrą, iš kurios
 * naršyklė (jsPDF) suformuoja PDF.
 *
 * Visi išoriniai šaltiniai tikrinami gynybiškai (class_exists / lentelės
 * egzistavimas), kad trūkstamas įskiepis niekada nesukeltų klaidos.
 */
if (!defined('ABSPATH')) exit;

class SS_Data_Export {

    public static function init(): void {
        add_action('wp_ajax_ss_settings_export_data', [__CLASS__, 'ajax_export']);
    }

    public static function ajax_export(): void {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Nesate prisijungęs.']);
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('data_export', ['kind' => 'self_account', 'user_id' => get_current_user_id()]);
        }
        wp_send_json_success(self::collect(get_current_user_id()));
    }

    /* ── Pagrindinis rinkėjas ──────────────────────────────────────────── */
    public static function collect(int $uid): array {
        $user = get_userdata($uid);
        if (!$user) return ['sections' => []];

        $sections = [];
        $add = function (array $sec) use (&$sections) {
            // Praleidžiam tuščius (lentelės be eilučių / sąrašai be elementų).
            if ($sec['kind'] === 'table' && empty($sec['rows'])) return;
            if ($sec['kind'] === 'list'  && empty($sec['items'])) return;
            $sections[] = $sec;
        };

        $add(self::section_account($user));
        $add(self::section_classes($uid));
        $add(self::section_prefs($uid));
        $add(self::section_sessions($uid));
        $add(self::section_devices($uid));
        $add(self::section_personal($uid));
        $add(self::section_eurovizija($uid));
        $add(self::section_imp($uid));
        $add(self::section_surveys($uid));
        $add(self::section_audit($uid));
        $add(self::section_atspek($uid));
        // WP Statistics — visada įdedam (jei nesusieta, su pastaba). „extra"
        // (aplankyti puslapiai) išskleidžiam į atskirą skyrių, kad naršyklės
        // atvaizdavimas liktų paprastas.
        $ws    = self::section_wpstatistics($uid);
        $extra = $ws['extra'] ?? null;
        unset($ws['extra']);
        $sections[] = $ws;
        if ($extra) $sections[] = $extra;

        $logo = (class_exists('SS_Admin') && method_exists('SS_Admin', 'get_logo_for_pdf'))
            ? SS_Admin::get_logo_for_pdf() : null;

        return [
            'meta' => [
                'school'    => stripslashes(get_option('ss_school_name', defined('SS_SCHOOL_NAME') ? SS_SCHOOL_NAME : '')),
                'name'      => $user->display_name,
                'username'  => $user->user_login,
                'generated' => SS_Time::date('Y-m-d H:i'),
                'title'     => 'Mano duomenys',
            ],
            'fonts'    => self::fonts(),
            'logo'     => $logo ? ['b64' => $logo['b64'], 'mime' => $logo['mime']] : null,
            'sections' => $sections,
        ];
    }

    /* ── Skyriai ───────────────────────────────────────────────────────── */

    private static function section_account($user): array {
        $uid   = (int) $user->ID;
        $roles = (array) $user->roles;
        $role  = current_user_can('manage_options') ? 'Administratorius'
               : (in_array('ss_teacher', $roles, true)  ? 'Mokytojas'
               : (in_array('ss_employee', $roles, true) ? 'Darbuotojas' : 'Mokinys'));
        $reg = $user->user_registered
            ? (function ($s) { $dt = new DateTime($s, new DateTimeZone('UTC')); $dt->setTimezone(SS_Time::tz()); return $dt->format('Y-m-d H:i'); })($user->user_registered)
            : '—';
        $rows = [
            ['Vardas, pavardė', $user->display_name],
            ['El. paštas',      $user->user_email],
            ['Vartotojo vardas', $user->user_login],
            ['Rolė',            $role],
            ['Registracijos data', $reg],
        ];
        $phone = (string) get_user_meta($uid, 'ss_phone', true);
        if ($phone !== '') $rows[] = ['Telefonas', $phone];
        $lvl = (string) get_user_meta($uid, 'ss_class_level', true);
        $ltr = (string) get_user_meta($uid, 'ss_class_letter', true);
        if ($lvl !== '') $rows[] = ['Klasė (I–IV)', trim($lvl . $ltr)];
        return ['title' => 'Paskyra', 'kind' => 'kv', 'rows' => $rows];
    }

    private static function section_classes(int $uid): array {
        $items = [];
        if (class_exists('SS_Database')) {
            foreach ((array) SS_Database::get_user_classes($uid) as $r) {
                $c = SS_Database::get_class((int) $r->class_id);
                if ($c) $items[] = $c->name;
            }
        }
        return ['title' => 'Tvarkaraščiai (klasės)', 'kind' => 'list', 'items' => $items];
    }

    private static function section_prefs(int $uid): array {
        $yn = fn($v) => $v ? 'Taip' : 'Ne';
        $rows = [];
        if (class_exists('SS_ChangeNotify')) {
            $cn = SS_ChangeNotify::get_settings($uid);
            $rows[] = ['Pakeitimų pranešimai el. paštu', $cn['on']
                ? ('Įjungta · ' . ($cn['mode'] === 'time' ? ('nurodytu laiku ' . $cn['time']) : 'iškart po paskelbimo'))
                : 'Išjungta'];
        }
        $rows[] = ['Slėpti vardą viktorinose', $yn(get_user_meta($uid, 'ss_anon_quiz', true))];
        $rows[] = ['Pranešimai apie naują įrenginį', $yn(get_user_meta($uid, 'ss_user_new_device_notify', true) !== '0')];
        $am = get_user_meta($uid, 'ss_atspek_mode', true) ?: 'day';
        $ah = (int) (get_user_meta($uid, 'ss_atspek_hours', true) ?: 24);
        $rows[] = ['Ątspėk reklama', $am === 'off' ? 'Išjungta' : ($am === 'hours' ? ('Po ' . $ah . ' val.') : 'Kitą dieną')];
        $rows[] = ['QR prisijungimas leistas', $yn(get_user_meta($uid, 'ss_qr_login_allowed', true))];
        $rows[] = ['Dviejų žingsnių patvirtinimas (2FA)', $yn(class_exists('SS_2FA') && SS_2FA::is_enabled($uid))];
        $gmail = (string) get_user_meta($uid, 'ss_google_email', true);
        if ($gmail !== '') $rows[] = ['Susieta Google paskyra', $gmail];
        $rows[] = ['Profilio nuotrauka įkelta', $yn((int) get_user_meta($uid, 'ss_avatar_id', true))];
        return ['title' => 'Nustatymai ir privatumas', 'kind' => 'kv', 'rows' => $rows];
    }

    private static function section_sessions(int $uid): array {
        $rows = [];
        if (class_exists('SS_Accounts')) {
            foreach (SS_Accounts::get_sessions($uid) as $s) {
                $rows[] = [
                    (string) ($s['ip'] ?? ''),
                    (string) ($s['device'] ?: $s['ua']),
                    (string) ($s['geo'] ?? ''),
                    (string) ($s['login_fmt'] ?? ''),
                    (string) ($s['exp_fmt'] ?? ''),
                ];
            }
        }
        return ['title' => 'Aktyvios sesijos', 'kind' => 'table',
                'head' => ['IP', 'Įrenginys', 'Vieta', 'Prisijungta', 'Galioja iki'], 'rows' => $rows];
    }

    private static function section_devices(int $uid): array {
        $rows = [];
        if (class_exists('SS_Accounts')) {
            foreach (SS_Accounts::get_devices($uid) as $d) {
                $rows[] = [
                    (string) ($d['last_fmt'] ?? ''),
                    (string) ($d['ip'] ?? ''),
                    (string) ($d['device'] ?: $d['ua']),
                    (string) ($d['geo'] ?? ''),
                    (string) ($d['method'] ?? ''),
                ];
            }
        }
        return ['title' => 'Prisijungimų istorija (įrenginiai)', 'kind' => 'table',
                'head' => ['Data', 'IP', 'Įrenginys', 'Vieta', 'Būdas'], 'rows' => $rows];
    }

    private static function section_personal(int $uid): array {
        $days = ['', 'Pirmadienis', 'Antradienis', 'Trečiadienis', 'Ketvirtadienis', 'Penktadienis', 'Šeštadienis', 'Sekmadienis'];
        $rows = [];
        if (class_exists('SS_Database')) {
            foreach ((array) SS_Database::get_personal_items($uid) as $p) {
                $rows[] = [
                    $days[(int) $p->day_of_week] ?? '',
                    substr((string) $p->start_time, 0, 5) . '–' . substr((string) $p->end_time, 0, 5),
                    (string) $p->subject,
                    (string) ($p->notes ?? ''),
                ];
            }
        }
        return ['title' => 'Asmeniniai įrašai tvarkaraštyje', 'kind' => 'table',
                'head' => ['Diena', 'Laikas', 'Pavadinimas', 'Pastabos'], 'rows' => $rows];
    }

    private static function section_eurovizija(int $uid): array {
        $items = [];
        if (class_exists('SS_Eurovizija')) {
            $names = [];
            foreach (SS_Eurovizija::get_candidates() as $c) $names[(int) $c->id] = $c->name;
            foreach (SS_Eurovizija::get_user_votes($uid) as $v) {
                $nm = $names[(int) $v->candidate_id] ?? ('#' . (int) $v->candidate_id);
                $g  = (string) $v->group_label;
                $items[] = $g !== '' ? ($nm . ' (' . $g . ')') : $nm;
            }
        }
        return ['title' => 'Ąžuolyno Eurovizija — Jūsų balsai', 'kind' => 'list', 'items' => $items];
    }

    private static function section_imp(int $uid): array {
        if (!class_exists('SS_IMP')) return ['title' => 'IMP', 'kind' => 'list', 'items' => []];
        $sub = SS_IMP::get_user_submission($uid);
        if (!$sub) return ['title' => 'Individualus ugdymo planas (IMP)', 'kind' => 'list', 'items' => []];
        $sel  = json_decode($sub->data ?? '', true);
        $rows = [];
        $resolved = (is_array($sel) && !empty($sel['resolved'])) ? $sel['resolved'] : [];
        foreach ((array) $resolved as $it) {
            $rows[] = [
                (string) ($it['name'] ?? ''),
                (string) ($it['level'] ?? ''),
                (string) ((int) ($it['h3'] ?? 0)),
                (string) ((int) ($it['h4'] ?? 0)),
            ];
        }
        $statusMap = ['submitted' => 'Pateikta', 'approved' => 'Patvirtinta', 'rejected' => 'Atmesta'];
        $head = 'Būsena: ' . ($statusMap[$sub->status] ?? $sub->status)
              . ' · Atnaujinta: ' . (string) $sub->updated_at;
        return ['title' => 'Individualus ugdymo planas (IMP)', 'kind' => 'table',
                'note' => $head, 'head' => ['Dalykas / modulis', 'Kursas', 'Val. III', 'Val. IV'], 'rows' => $rows];
    }

    private static function section_surveys(int $uid): array {
        global $wpdb;
        $rows = [];
        $p = $wpdb->prefix;
        $res = $wpdb->get_results($wpdb->prepare(
            "SELECT r.score, r.max_score, r.submitted_at, s.title
             FROM {$p}ss_survey_responses r
             LEFT JOIN {$p}ss_surveys s ON s.id = r.survey_id
             WHERE r.user_id = %d ORDER BY r.submitted_at DESC", $uid
        ));
        foreach ((array) $res as $r) {
            $rows[] = [
                (string) ($r->title ?: '—'),
                ((int) $r->max_score > 0) ? ((int) $r->score . ' / ' . (int) $r->max_score) : '—',
                (string) $r->submitted_at,
            ];
        }
        return ['title' => 'Apklausos ir viktorinos', 'kind' => 'table',
                'head' => ['Pavadinimas', 'Rezultatas', 'Pateikta'], 'rows' => $rows];
    }

    private static function section_audit(int $uid): array {
        $rows = [];
        if (class_exists('SS_AuditLog')) {
            foreach (SS_AuditLog::query(['user_id' => $uid], 200, 0) as $e) {
                $rows[] = [
                    (string) $e->created_at,
                    (string) $e->event,
                    (string) $e->ip,
                    (string) $e->method,
                ];
            }
        }
        return ['title' => 'Saugumo žurnalas (paskutiniai 200 įvykių)', 'kind' => 'table',
                'head' => ['Laikas', 'Įvykis', 'IP', 'Būdas'], 'rows' => $rows];
    }

    /* ── Ątspėk (Wordle) — atskiras įskiepis output-plugin/ ─────────────── */
    private static function section_atspek(int $uid): array {
        global $wpdb;
        $t = $wpdb->prefix . 'wordle_lt_scores';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) !== $t) {
            return ['title' => 'Ątspėk žaidimas', 'kind' => 'list', 'items' => []];
        }
        $rows = [];
        foreach ((array) $wpdb->get_results($wpdb->prepare(
            "SELECT word, attempts, won, seconds, points, played_at
             FROM {$t} WHERE user_id = %d ORDER BY played_at DESC LIMIT 500", $uid)) as $r) {
            $rows[] = [
                (string) $r->word,
                (string) (int) $r->attempts,
                ((int) $r->won === 1) ? 'Taip' : 'Ne',
                (string) (int) $r->seconds . ' s',
                (string) (int) $r->points,
                (string) $r->played_at,
            ];
        }
        return ['title' => 'Ątspėk žaidimas', 'kind' => 'table',
                'head' => ['Žodis', 'Bandymai', 'Laimėta', 'Laikas', 'Taškai', 'Žaista'], 'rows' => $rows];
    }

    /* ── WP Statistics (veronalabs) — gynybiškai, schema priklauso nuo versijos ── */
    private static function section_wpstatistics(int $uid): array {
        global $wpdb;
        $title = 'Jūsų apsilankymai';
        $vt = $wpdb->prefix . 'statistics_visitor';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $vt)) !== $vt) {
            return ['title' => $title, 'kind' => 'note',
                    'text' => 'Statistics sistemoje neaptiktas.'];
        }
        $cols = (array) $wpdb->get_col("SHOW COLUMNS FROM {$vt}");
        if (!in_array('user_id', $cols, true)) {
            return ['title' => $title, 'kind' => 'note',
                    'text' => 'Statistics duomenys sistemoje nesusieti su paskyromis.'];
        }

        // Renkam tik egzistuojančius stulpelius — pavadinimai skiriasi tarp versijų.
        $want = [
            'last_counter' => 'Data',  'ip' => 'IP', 'agent' => 'Naršyklė',
            'platform' => 'Platforma', 'model' => 'Įrenginys', 'location' => 'Šalis',
            'city' => 'Miestas', 'referred' => 'Iš kur', 'hits' => 'Užklausų',
        ];
        $sel = [];
        $head = [];
        foreach ($want as $col => $label) {
            if (in_array($col, $cols, true)) { $sel[] = $col; $head[] = $label; }
        }
        if (!$sel) return ['title' => $title, 'kind' => 'note', 'text' => 'Statistics duomenų nerasta.'];

        $order = in_array('last_counter', $cols, true) ? 'last_counter' : $sel[0];
        $sql = 'SELECT ' . implode(',', array_map(static fn($c) => "`$c`", $sel))
             . " FROM {$vt} WHERE user_id = %d ORDER BY `$order` DESC LIMIT 500";
        $rows = [];
        foreach ((array) $wpdb->get_results($wpdb->prepare($sql, $uid), ARRAY_N) as $r) {
            $rows[] = array_map(static fn($v) => (string) $v, $r);
        }

        // Aplankyti puslapiai (jei yra ryšių lentelė + puslapių lentelė).
        $page_rows = [];
        $rel = $wpdb->prefix . 'statistics_visitor_relationships';
        $pg  = $wpdb->prefix . 'statistics_pages';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rel)) === $rel
            && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $pg)) === $pg) {
            $page_rows = (array) $wpdb->get_results($wpdb->prepare(
                "SELECT p.uri, p.date
                 FROM {$rel} r
                 JOIN {$vt} v ON v.ID = r.visitor_id
                 JOIN {$pg} p ON p.page_id = r.page_id
                 WHERE v.user_id = %d ORDER BY p.date DESC LIMIT 500", $uid), ARRAY_N);
        }

        if ($rows) {
            $out = ['title' => $title, 'kind' => 'table', 'head' => $head, 'rows' => $rows];
            if ($page_rows) {
                // Pridedam aplankytų puslapių sąrašą kaip atskirą skyrių per „extra".
                $out['extra'] = ['title' => 'Jūsų aplankyti puslapiai', 'kind' => 'table',
                                 'head' => ['Puslapis', 'Data'], 'rows' => array_map(static fn($r) => array_map('strval', $r), $page_rows)];
            }
            return $out;
        }
        return ['title' => $title, 'kind' => 'note', 'text' => 'Apsilankymų, susietų su Jūsų paskyra, nerasta.'];
    }

    /* ── PDF šriftai (NotoSans — lietuviškoms raidėms) ─────────────────── */
    private static function fonts(): array {
        $dir = SS_PLUGIN_DIR . 'public/fonts/';
        return [
            'reg'  => file_exists($dir . 'NotoSans-Regular.ttf') ? base64_encode(file_get_contents($dir . 'NotoSans-Regular.ttf')) : null,
            'bold' => file_exists($dir . 'NotoSans-Bold.ttf')    ? base64_encode(file_get_contents($dir . 'NotoSans-Bold.ttf'))    : null,
        ];
    }
}
