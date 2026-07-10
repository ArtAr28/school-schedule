<?php
/**
 * SS_Social — draugai, pokalbiai (asmeniniai + grupiniai) ir profilio duomenys.
 *
 * SAUGUMAS (pokalbiai): kiekvienas žinučių skaitymo/rašymo galinis taškas
 * PIRMIAUSIA patikrina, ar prisijungęs vartotojas yra to pokalbio narys
 * (is_member). Ne nariams jokia žinutė niekada negrąžinama. Tai serverio pusės
 * prieigos kontrolė (ne end-to-end šifravimas).
 *
 * Rašyti galima tik DRAUGAMS (asmeninis pokalbis kuriamas tik tarp draugų;
 * į grupę pridedami tik pridedančiojo draugai).
 */
if (!defined('ABSPATH')) exit;

class SS_Social {

    const MSG_MAX = 4000;

    /* ── Lentelės ───────────────────────────────────────────────────── */
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        $p = $wpdb->prefix;

        dbDelta("CREATE TABLE {$p}ss_friends (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_lo BIGINT(20) UNSIGNED NOT NULL,
            user_hi BIGINT(20) UNSIGNED NOT NULL,
            requested_by BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY pair (user_lo, user_hi),
            KEY user_hi (user_hi),
            KEY status (status)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}ss_chats (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(10) NOT NULL DEFAULT 'direct',
            title VARCHAR(120) NOT NULL DEFAULT '',
            settings LONGTEXT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            KEY updated_at (updated_at)
        ) {$c};");
        $ccols = $wpdb->get_col("SHOW COLUMNS FROM {$p}ss_chats", 0);
        if (is_array($ccols) && !in_array('settings', $ccols, true)) {
            $wpdb->query("ALTER TABLE {$p}ss_chats ADD COLUMN settings LONGTEXT NULL AFTER title");
        }

        dbDelta("CREATE TABLE {$p}ss_chat_members (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            chat_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(10) NOT NULL DEFAULT 'member',
            last_read_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            joined_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY chat_user (chat_id, user_id),
            KEY user_id (user_id)
        ) {$c};");

        dbDelta("CREATE TABLE {$p}ss_chat_messages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            chat_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            type VARCHAR(16) NOT NULL DEFAULT 'text',
            body TEXT NOT NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            edited TINYINT(1) NOT NULL DEFAULT 0,
            deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY chat_id (chat_id, id)
        ) {$c};");

        // Balsavimų balsai (viena eilutė = vieno vartotojo vienas pasirinkimas).
        dbDelta("CREATE TABLE {$p}ss_chat_poll_votes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            opt_idx SMALLINT(5) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY vote (message_id, user_id, opt_idx),
            KEY message_id (message_id)
        ) {$c};");

        // Reakcijos (emoji) į žinutes.
        dbDelta("CREATE TABLE {$p}ss_chat_reactions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            emoji VARCHAR(16) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY react (message_id, user_id, emoji),
            KEY message_id (message_id)
        ) {$c};");

        // Saugikliai senesnėms instaliacijoms: užtikrinam naujus stulpelius.
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$p}ss_chat_messages", 0);
        if (is_array($cols)) {
            if (!in_array('edited', $cols, true)) $wpdb->query("ALTER TABLE {$p}ss_chat_messages ADD COLUMN edited TINYINT(1) NOT NULL DEFAULT 0 AFTER created_at");
            if (!in_array('type', $cols, true))   $wpdb->query("ALTER TABLE {$p}ss_chat_messages ADD COLUMN type VARCHAR(16) NOT NULL DEFAULT 'text' AFTER user_id");
            if (!in_array('meta', $cols, true))   $wpdb->query("ALTER TABLE {$p}ss_chat_messages ADD COLUMN meta LONGTEXT NULL AFTER body");
        }
    }

    /* ── AJAX registracija ──────────────────────────────────────────── */
    public static function init(): void {
        $actions = [
            // draugai
            'friend_request','friend_accept','friend_decline','friend_remove',
            'friend_list','friend_requests','user_search',
            // pokalbiai
            'chat_list','chat_open','chat_poll','chat_send','chat_edit','chat_create',
            'chat_add_member','chat_remove_member','chat_rename','chat_leave',
            'chat_unread_count','chat_contacts',
            'chat_upload','chat_sticker','chat_poll_create','chat_poll_vote','chat_media','chat_react','chat_typing',
            'chat_set_bg','chat_upload_avatar',
            // profilis / privatumas
            'save_profile_cover','save_privacy',
        ];
        foreach ($actions as $a) {
            add_action("wp_ajax_ss_{$a}", [__CLASS__, "ajax_{$a}"]);
        }

        // Neperskaitytų žinučių priminimas el. paštu (praėjus ~1 val.).
        add_filter('cron_schedules', [__CLASS__, 'cron_add_schedule']);
        add_action('ss_social_unread_digest', [__CLASS__, 'cron_unread_digest']);
        if (!wp_next_scheduled('ss_social_unread_digest')) {
            wp_schedule_event(time() + 900, 'ss_fifteen_min', 'ss_social_unread_digest');
        }
    }
    public static function cron_add_schedule(array $s): array {
        if (empty($s['ss_fifteen_min'])) {
            $s['ss_fifteen_min'] = ['interval' => 900, 'display' => 'Kas 15 min. (Ąžuolyno žinutės)'];
        }
        return $s;
    }
    public static function unschedule(): void {
        $ts = wp_next_scheduled('ss_social_unread_digest');
        if ($ts) wp_unschedule_event($ts, 'ss_social_unread_digest');
    }

    /**
     * CRON: pranešam el. paštu vartotojams, kurie turi neperskaitytų žinučių,
     * seniausia iš kurių paskelbta prieš ≥1 val., ir apie kurias dar nepranešta.
     * „ss_unread_notified_id" meta saugo aukščiausią jau praneštos žinutės ID,
     * kad tas pats paketas nesikartotų.
     */
    public static function cron_unread_digest(): void {
        global $wpdb;
        self::purge_expired_media(); // pakeliui išvalom pasenusius (7 d.) medijos failus
        // created_at saugomas WP vietine zona (current_time('mysql')), todėl ir ribą
        // skaičiuojam vietinėje zonoje.
        $threshold = date('Y-m-d H:i:s', current_time('timestamp') - HOUR_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT mem.user_id AS uid, COUNT(*) AS cnt, MAX(msg.id) AS maxid, MIN(msg.created_at) AS oldest
             FROM {$wpdb->prefix}ss_chat_members mem
             JOIN {$wpdb->prefix}ss_chat_messages msg
               ON msg.chat_id = mem.chat_id
              AND msg.id > mem.last_read_id
              AND msg.user_id <> mem.user_id
              AND msg.deleted = 0
              AND msg.type <> 'system'
             GROUP BY mem.user_id
             HAVING MIN(msg.created_at) <= %s
             LIMIT 500", $threshold));
        if (!$rows) return;

        // Pirmą kartą po įdiegimo — tik „primename" esamą sankaupą, kad iškart
        // neišsiųstume masinio laiškų srauto apie senas žinutes.
        $primed = (bool) get_option('ss_social_digest_primed', 0);

        foreach ($rows as $r) {
            $uid   = (int) $r->uid;
            $maxid = (int) $r->maxid;
            $cnt   = (int) $r->cnt;
            if (!$primed) { update_user_meta($uid, 'ss_unread_notified_id', $maxid); continue; }
            $notified = (int) get_user_meta($uid, 'ss_unread_notified_id', true);
            if ($maxid <= $notified) continue;                 // apie šį paketą jau pranešta
            if (get_user_meta($uid, 'ss_priv_msg_email', true) === '0') { // išjungta — tik pažymim
                update_user_meta($uid, 'ss_unread_notified_id', $maxid);
                continue;
            }
            $u = get_userdata($uid);
            if ($u && is_email($u->user_email) && class_exists('SS_Mail')) {
                $word = self::plural_lt($cnt, 'neperskaitytą žinutę', 'neperskaitytas žinutes', 'neperskaitytų žinučių');
                SS_Mail::send($u->user_email, 'Turite ' . $cnt . ' ' . $word, [
                    'title'     => 'Naujos žinutės',
                    'subtitle'  => 'Turite ' . $cnt . ' ' . $word . '.',
                    'body'      => '<p style="margin:0 0 8px;">Sveiki, ' . esc_html($u->display_name) . '!</p>'
                                 . '<p style="margin:0;">Jūsų pokalbiuose laukia neperskaitytų žinučių. '
                                 . 'Paspauskite nuorodą apačioje, kad jas peržiūrėtumėte.</p>',
                    'button'    => ['text' => 'Atidaryti žinutes', 'url' => self::messages_url()],
                    'note'      => 'Nenorite tokių priminimų? Išjunkite juos nustatymų skiltyje „Privatumas".',
                    'note_type' => 'info',
                ]);
            }
            update_user_meta($uid, 'ss_unread_notified_id', $maxid);
        }
        if (!$primed) update_option('ss_social_digest_primed', 1, false);
    }
    /** Paprasta lietuviška daugiskaita (1 / 2-9 / kt.). */
    private static function plural_lt(int $n, string $one, string $few, string $many): string {
        $n10 = $n % 10; $n100 = $n % 100;
        if ($n10 === 1 && $n100 !== 11) return $one;
        if ($n10 >= 2 && $n10 <= 9 && ($n100 < 10 || $n100 >= 20)) return $few;
        return $many;
    }

    /* ── Bendri ─────────────────────────────────────────────────────── */
    private static function guard(): int {
        check_ajax_referer('ss_front_nonce', 'nonce');
        $uid = get_current_user_id();
        if (!$uid) wp_send_json_error(['message' => 'Neprisijungta.']);
        self::touch_online((int) $uid);
        return (int) $uid;
    }
    /** Atnaujina „paskutinį kartą aktyvus" (ne dažniau kaip kartą per 60 s). */
    private static function touch_online(int $uid): void {
        $now = time();
        if ($now - (int) get_user_meta($uid, 'ss_last_active', true) >= 60) {
            update_user_meta($uid, 'ss_last_active', $now);
        }
    }
    /**
     * Buvimo statusas žiūrinčiajam. „Prisijungęs" matomas visiems.
     * Kam LEIDŽIA privatumas — tikslus laikas (min. / val. / diena).
     * Kam neleidžia — tik apytikris („Buvo neseniai" / „Buvo seniai").
     */
    public static function presence_for(int $owner, int $viewer): array {
        $allowed = self::can_see_last_seen($viewer, $owner);
        $last = (int) get_user_meta($owner, 'ss_last_active', true);
        if (!$last) return ['status' => 'away', 'label' => $allowed ? 'Nebuvo prisijungęs' : 'Buvo seniai'];
        $d = time() - $last;
        if ($d <= 120) return ['status' => 'online', 'label' => 'Prisijungęs']; // aktyvus dabar
        $status = ($d <= 7 * DAY_IN_SECONDS) ? 'recent' : 'away';
        if ($allowed) {
            if ($d < HOUR_IN_SECONDS)     $label = 'prieš ' . max(1, (int) round($d / 60)) . ' min.';
            elseif ($d < DAY_IN_SECONDS)  $label = 'prieš ' . (int) floor($d / HOUR_IN_SECONDS) . ' val.';
            else                          $label = wp_date(get_option('date_format'), $last);
            $label = 'Buvo ' . $label;
        } else {
            $label = ($d <= 7 * DAY_IN_SECONDS) ? 'Buvo neseniai' : 'Buvo seniai';
        }
        return ['status' => $status, 'label' => $label];
    }
    public static function role_label(int $uid): string {
        $u = get_userdata($uid);
        if (!$u) return '';
        $r = (array) $u->roles;
        if (in_array('administrator', $r, true)) return 'Administratorius';
        if (in_array('ss_teacher', $r, true))    return 'Mokytojas';
        if (in_array('ss_employee', $r, true))   return 'Darbuotojas';
        return 'Mokinys';
    }
    public static function user_card(int $uid): array {
        $u = get_userdata($uid);
        return [
            'id'     => $uid,
            'name'   => $u ? $u->display_name : ('#' . $uid),
            'avatar' => get_avatar_url($uid, ['size' => 96]) ?: '',
            'role'   => self::role_label($uid),
        ];
    }
    public static function profile_url(int $uid = 0): string {
        $pid = (int) get_option('ss_profile_page_id');
        $url = $pid ? get_permalink($pid) : home_url('/');
        return $uid ? add_query_arg('u', $uid, $url) : $url;
    }
    public static function messages_url(int $chat_id = 0): string {
        $pid = (int) get_option('ss_messages_page_id');
        $url = $pid ? get_permalink($pid) : home_url('/');
        return $chat_id ? add_query_arg('c', $chat_id, $url) : $url;
    }

    /* ── Dashicons (vietoj emoji — profilyje ir pokalbiuose) ───────── */
    public static function icon(string $name, int $size = 18): string {
        $icon = esc_attr(self::dashicon_name($name));
        $s = (int) $size;
        return '<span class="dashicons dashicons-' . $icon . ' ss-ic" aria-hidden="true"'
             . ' style="font-size:' . $s . 'px;width:' . $s . 'px;height:' . $s . 'px;"></span>';
    }
    /** Vardų atitikmenys WP Dashicons klasėms. */
    public static function dashicon_name(string $name): string {
        $map = [
            'target' => 'star-filled', 'trophy' => 'awards', 'users' => 'groups', 'groups' => 'groups',
            'chat'   => 'format-chat', 'gear'  => 'admin-generic', 'pencil' => 'edit',
            'back'   => 'arrow-left-alt2', 'send' => 'arrow-right-alt2', 'image' => 'format-image',
            'plus'   => 'plus-alt2', 'check' => 'yes', 'checks' => 'yes-alt', 'info' => 'info-outline',
            'trash'  => 'trash', 'leave' => 'migrate', 'search' => 'search', 'close' => 'no-alt',
            'smiley' => 'smiley', 'poll' => 'chart-bar', 'paperclip' => 'paperclip',
            'channel' => 'megaphone', 'react' => 'smiley', 'camera' => 'camera',
        ];
        return $map[$name] ?? 'marker';
    }

    /* ── Profilio fonai (presetai) ──────────────────────────────────── */
    public static function covers(): array {
        return [
            'g1' => 'linear-gradient(135deg,#4F8952,#245326)',
            'g2' => 'linear-gradient(135deg,#224768,#012A36)',
            'g3' => 'linear-gradient(135deg,#D05155,#7a1f22)',
            'g4' => 'linear-gradient(135deg,#e6a700,#7a5c00)',
            'g5' => 'linear-gradient(135deg,#6A5ACD,#2e2a66)',
            'g6' => 'linear-gradient(135deg,#0ea5a5,#0f3d3d)',
            'g7' => 'linear-gradient(135deg,#ec4899,#831843)',
            'g8' => 'linear-gradient(135deg,#334155,#0f172a)',
        ];
    }
    public static function cover_css(int $uid): string {
        $covers = self::covers();
        $key = (string) get_user_meta($uid, 'ss_profile_cover', true);
        return $covers[$key] ?? $covers['g1'];
    }

    /* ════════════════════════════════════════════════════════════════
       DRAUGAI
       ════════════════════════════════════════════════════════════════ */
    private static function pair(int $a, int $b): array {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
    public static function friendship(int $a, int $b) {
        if ($a === $b) return null;
        global $wpdb;
        [$lo, $hi] = self::pair($a, $b);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_friends WHERE user_lo=%d AND user_hi=%d", $lo, $hi));
    }
    public static function are_friends(int $a, int $b): bool {
        $f = self::friendship($a, $b);
        return $f && $f->status === 'accepted';
    }
    public static function friends_of(int $uid): array {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT IF(user_lo=%d, user_hi, user_lo) FROM {$wpdb->prefix}ss_friends
             WHERE (user_lo=%d OR user_hi=%d) AND status='accepted'", $uid, $uid, $uid));
        return array_map('intval', (array) $ids);
    }
    public static function friends_count(int $uid): int {
        return count(self::friends_of($uid));
    }
    public static function mutual_count(int $a, int $b): int {
        return count(array_intersect(self::friends_of($a), self::friends_of($b)));
    }
    /** Draugystės būsena $viewer atžvilgiu su $target: none|pending_out|pending_in|friends|self */
    public static function rel_status(int $viewer, int $target): string {
        if ($viewer === $target) return 'self';
        $f = self::friendship($viewer, $target);
        if (!$f) return 'none';
        if ($f->status === 'accepted') return 'friends';
        return ((int) $f->requested_by === $viewer) ? 'pending_out' : 'pending_in';
    }

    public static function ajax_friend_request(): void {
        $uid = self::guard();
        $target = (int) ($_POST['user_id'] ?? 0);
        if (!$target || $target === $uid || !get_userdata($target)) {
            wp_send_json_error(['message' => 'Neteisingas vartotojas.']);
        }
        global $wpdb;
        $f = self::friendship($uid, $target);
        if ($f && $f->status === 'accepted') wp_send_json_error(['message' => 'Jau draugai.']);
        // Jei jau yra atvirkštinis kvietimas — priimam iškart.
        if ($f && $f->status === 'pending' && (int) $f->requested_by === $target) {
            $wpdb->update("{$wpdb->prefix}ss_friends", ['status' => 'accepted', 'updated_at' => current_time('mysql')], ['id' => $f->id]);
            self::after_friend_change($uid, $target);
            wp_send_json_success(['status' => 'friends']);
        }
        if ($f) wp_send_json_error(['message' => 'Kvietimas jau išsiųstas.']);

        [$lo, $hi] = self::pair($uid, $target);
        $wpdb->insert("{$wpdb->prefix}ss_friends", [
            'user_lo' => $lo, 'user_hi' => $hi, 'requested_by' => $uid, 'status' => 'pending',
            'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
        ]);
        self::maybe_friend_request_email($uid, $target);
        wp_send_json_success(['status' => 'pending_out']);
    }
    /** Praneša gavėjui el. paštu apie naują draugystės kvietimą (jei jis neišjungė). */
    private static function maybe_friend_request_email(int $from, int $to): void {
        if (get_user_meta($to, 'ss_priv_friend_email', true) === '0') return; // numatyta: siųsti
        if (!class_exists('SS_Mail')) return;
        $tu = get_userdata($to); $fu = get_userdata($from);
        if (!$tu || !$fu || !is_email($tu->user_email)) return;
        SS_Mail::send($tu->user_email, 'Naujas draugystės kvietimas', [
            'title'     => 'Naujas draugystės kvietimas',
            'subtitle'  => $fu->display_name . ' nori pridėti Jus į draugus.',
            'body'      => '<p style="margin:0 0 8px;">Sveiki, ' . esc_html($tu->display_name) . '!</p>'
                         . '<p style="margin:0;">' . esc_html($fu->display_name)
                         . ' išsiuntė Jums draugystės kvietimą. '
                         . 'Kvietimą galite priimti arba atmesti savo profilyje paspaudus ant nuorodos žemiau.</p>',
            'button'    => ['text' => 'Atidaryti profilį', 'url' => self::profile_url()],
            'note'      => 'Nenorite tokių laiškų? Juos galite išjungti nustatymų skiltyje „Privatumas".',
            'note_type' => 'info',
        ]);
    }
    public static function ajax_friend_accept(): void {
        $uid = self::guard();
        $target = (int) ($_POST['user_id'] ?? 0);
        $f = self::friendship($uid, $target);
        if (!$f || $f->status !== 'pending' || (int) $f->requested_by === $uid) {
            wp_send_json_error(['message' => 'Kvietimo nėra.']);
        }
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}ss_friends", ['status' => 'accepted', 'updated_at' => current_time('mysql')], ['id' => $f->id]);
        self::after_friend_change($uid, $target);
        wp_send_json_success(['status' => 'friends']);
    }
    public static function ajax_friend_decline(): void {
        $uid = self::guard();
        $target = (int) ($_POST['user_id'] ?? 0);
        $f = self::friendship($uid, $target);
        if ($f && $f->status === 'pending') {
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}ss_friends", ['id' => $f->id]);
        }
        wp_send_json_success(['status' => 'none']);
    }
    public static function ajax_friend_remove(): void {
        $uid = self::guard();
        $target = (int) ($_POST['user_id'] ?? 0);
        $f = self::friendship($uid, $target);
        if ($f) {
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}ss_friends", ['id' => $f->id]);
            self::after_friend_change($uid, $target);
        }
        wp_send_json_success(['status' => 'none']);
    }
    private static function after_friend_change(int $a, int $b): void {
        if (class_exists('SS_Achievements')) {
            SS_Achievements::sync($a, true);
            SS_Achievements::sync($b, true);
        }
    }
    public static function ajax_friend_list(): void {
        self::guard();
        $target = (int) ($_POST['user_id'] ?? 0) ?: get_current_user_id();
        $out = [];
        foreach (self::friends_of($target) as $fid) $out[] = self::user_card($fid);
        usort($out, fn($x, $y) => strcasecmp($x['name'], $y['name']));
        wp_send_json_success(['friends' => $out]);
    }
    public static function ajax_friend_requests(): void {
        $uid = self::guard();
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT requested_by FROM {$wpdb->prefix}ss_friends
             WHERE (user_lo=%d OR user_hi=%d) AND status='pending' AND requested_by<>%d",
            $uid, $uid, $uid));
        $out = [];
        foreach ($rows as $r) $out[] = self::user_card((int) $r->requested_by);
        wp_send_json_success(['requests' => $out]);
    }
    public static function ajax_user_search(): void {
        $uid = self::guard();
        $q = sanitize_text_field($_POST['q'] ?? '');
        if (mb_strlen($q) < 2) wp_send_json_success(['users' => []]);
        $users = get_users([
            'search'         => '*' . $q . '*',
            'search_columns' => ['display_name', 'user_login', 'user_email'],
            'number'         => 20,
            'exclude'        => [$uid],
            'orderby'        => 'display_name',
        ]);
        $out = [];
        foreach ($users as $u) {
            $card = self::user_card((int) $u->ID);
            $card['rel'] = self::rel_status($uid, (int) $u->ID);
            $out[] = $card;
        }
        wp_send_json_success(['users' => $out]);
    }

    /* ════════════════════════════════════════════════════════════════
       KONTAKTAI IR TEISĖS (kam galima rašyti / ką galima pridėti į grupę)
       ─────────────────────────────────────────────────────────────────
       Taisyklės:
         • Administratorius   — bet kam.
         • Draugai            — visada.
         • Mokytojas          — visiems savo mokiniams (kurių klases jis moko).
         • Mokinys            — savo mokytojams (kurie moko jo klasę).
         • Klasiokai          — tarpusavyje (tos pačios klasės nariai).
       ════════════════════════════════════════════════════════════════ */
    private static function has_role(int $uid, string $role): bool {
        $u = get_userdata($uid);
        return $u && in_array($role, (array) $u->roles, true);
    }
    public static function is_admin_user(int $uid): bool { return self::has_role($uid, 'administrator'); }

    /** Klasių ID, kurioms priklauso mokinys. */
    private static function student_class_ids(int $uid): array {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT class_id FROM {$wpdb->prefix}ss_user_classes WHERE user_id=%d", $uid));
        return array_map('intval', (array) $ids);
    }
    /** Klasiokai (kitų tų pačių klasių nariai). */
    private static function classmates(int $uid): array {
        $cids = self::student_class_ids($uid);
        if (!$cids) return [];
        global $wpdb;
        $in = implode(',', array_map('intval', $cids));
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$wpdb->prefix}ss_user_classes
             WHERE class_id IN ($in) AND user_id<>%d", $uid));
        return array_map('intval', (array) $ids);
    }
    /** Tvarkaraščio mokytojo įrašo ID pagal WP vartotoją. */
    private static function teacher_record_id(int $uid): int {
        if (!class_exists('SS_Database')) return 0;
        $t = SS_Database::get_teacher_by_user_id($uid);
        return $t ? (int) $t->id : 0;
    }
    /** Klasių ID, kurias moko mokytojas. */
    private static function teacher_class_ids(int $uid): array {
        $tid = self::teacher_record_id($uid);
        if (!$tid) return [];
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT cs.class_id
             FROM {$wpdb->prefix}ss_class_schedule cs
             JOIN {$wpdb->prefix}ss_lessons l ON cs.lesson_id=l.id
             WHERE l.teacher_id=%d", $tid));
        return array_map('intval', (array) $ids);
    }
    /** Mokytojo mokinių WP ID (visų jo mokomų klasių nariai). */
    private static function teacher_student_ids(int $uid): array {
        $cids = self::teacher_class_ids($uid);
        if (!$cids) return [];
        global $wpdb;
        $in = implode(',', array_map('intval', $cids));
        $ids = $wpdb->get_col(
            "SELECT DISTINCT user_id FROM {$wpdb->prefix}ss_user_classes WHERE class_id IN ($in)");
        return array_map('intval', (array) $ids);
    }
    /** Mokinio mokytojų WP ID (kurie moko jo klasę ir turi paskyrą). */
    private static function student_teacher_user_ids(int $uid): array {
        $cids = self::student_class_ids($uid);
        if (!$cids) return [];
        global $wpdb;
        $in = implode(',', array_map('intval', $cids));
        $ids = $wpdb->get_col(
            "SELECT DISTINCT t.user_id
             FROM {$wpdb->prefix}ss_class_schedule cs
             JOIN {$wpdb->prefix}ss_lessons l ON cs.lesson_id=l.id
             JOIN {$wpdb->prefix}ss_teachers t ON l.teacher_id=t.id
             WHERE cs.class_id IN ($in) AND t.user_id>0");
        return array_map('intval', (array) $ids);
    }

    /* ── Auditorijos (kelių parinkčių privatumas) ─────────────────────── */
    const AUD_TOKENS  = ['everyone', 'friends', 'classmates', 'my_teachers', 'my_students', 'all_teachers'];
    const AUD_DEFAULT = ['friends', 'classmates', 'my_teachers', 'my_students'];

    public static function audience_labels(): array {
        return [
            'everyone'     => 'Visi',
            'friends'      => 'Draugai',
            'classmates'   => 'Bendraklasiai',
            'my_teachers'  => 'Mano mokytojai',
            'my_students'  => 'Mano mokiniai',
            'all_teachers' => 'Visi mokytojai',
        ];
    }
    /** Nuskaito auditoriją; palaiko senąjį string formatą (all/friends/nobody). */
    private static function read_audience(int $uid, string $key, array $default): array {
        $raw = get_user_meta($uid, $key, true);
        if (is_array($raw)) return $raw;
        if ($raw === '' || $raw === false || $raw === null) return $default;
        if ($raw === 'all')     return ['everyone'];
        if ($raw === 'friends') return ['friends'];
        if ($raw === 'nobody')  return [];
        $dec = json_decode((string) $raw, true);
        return is_array($dec) ? $dec : $default;
    }
    private static function sanitize_audience($raw): array {
        $out = [];
        foreach ((array) $raw as $t) { $t = sanitize_key((string) $t); if (in_array($t, self::AUD_TOKENS, true)) $out[] = $t; }
        return array_values(array_unique($out));
    }
    /** Ar $other patenka į $owner nustatytą auditoriją. */
    private static function in_audience(int $owner, int $other, array $tokens): bool {
        if (!$tokens) return false;
        if (in_array('everyone', $tokens, true)) return true;
        if (in_array('friends', $tokens, true) && self::are_friends($owner, $other)) return true;
        if (in_array('classmates', $tokens, true) && in_array($other, self::classmates($owner), true)) return true;
        if (in_array('my_teachers', $tokens, true) && in_array($other, self::student_teacher_user_ids($owner), true)) return true;
        if (in_array('my_students', $tokens, true) && self::has_role($owner, 'ss_teacher') && in_array($other, self::teacher_student_ids($owner), true)) return true;
        if (in_array('all_teachers', $tokens, true) && self::has_role($other, 'ss_teacher')) return true;
        return false;
    }

    /**
     * Ar $me gali INICIJUOTI kontaktą su $target.
     * $ctx: 'msg' (rašyti žinutę) arba 'group' (pridėti į grupę).
     * Adminas ir mokinio mokytojas (mokyklinis ryšys) visada praeina.
     */
    public static function can_contact(int $me, int $target, string $ctx = 'msg'): bool {
        if ($me === $target || !get_userdata($target)) return false;
        if (self::is_admin_user($me)) return true;
        if (self::has_role($me, 'ss_teacher') && in_array($target, self::teacher_student_ids($me), true)) return true;
        $key = $ctx === 'group' ? 'ss_priv_group' : 'ss_priv_msg';
        return self::in_audience($target, $me, self::read_audience($target, $key, self::AUD_DEFAULT));
    }
    /** Ar $viewer gali matyti $owner buvimo statusą. */
    public static function can_see_last_seen(int $viewer, int $owner): bool {
        if ($viewer === $owner || self::is_admin_user($viewer)) return true;
        return self::in_audience($owner, $viewer, self::read_audience($owner, 'ss_priv_seen', self::AUD_DEFAULT));
    }

    /** Vartotojo privatumo nustatymai (auditorijos + el. laiškų perjungikliai). */
    public static function privacy_settings(int $uid): array {
        return [
            'msg'          => self::read_audience($uid, 'ss_priv_msg', self::AUD_DEFAULT),
            'group'        => self::read_audience($uid, 'ss_priv_group', self::AUD_DEFAULT),
            'seen'         => self::read_audience($uid, 'ss_priv_seen', self::AUD_DEFAULT),
            'friend_email' => get_user_meta($uid, 'ss_priv_friend_email', true) !== '0', // numatyta: siųsti
            'msg_email'    => get_user_meta($uid, 'ss_priv_msg_email', true) !== '0',    // numatyta: siųsti
        ];
    }
    public static function ajax_save_privacy(): void {
        $uid    = self::guard();
        $femail = !empty($_POST['friend_email']) ? '1' : '0';
        $memail = !empty($_POST['msg_email']) ? '1' : '0';
        update_user_meta($uid, 'ss_priv_msg',   self::sanitize_audience($_POST['priv_msg'] ?? []));
        update_user_meta($uid, 'ss_priv_group', self::sanitize_audience($_POST['priv_group'] ?? []));
        update_user_meta($uid, 'ss_priv_seen',  self::sanitize_audience($_POST['priv_seen'] ?? []));
        update_user_meta($uid, 'ss_priv_friend_email', $femail);
        update_user_meta($uid, 'ss_priv_msg_email', $memail);
        wp_send_json_success(['ok' => 1]);
    }
    /** Ar dviese gali susirašinėti (bet kuris gali inicijuoti) — vartojama esamame pokalbyje. */
    public static function can_converse(int $a, int $b): bool {
        return self::can_contact($a, $b, 'msg') || self::can_contact($b, $a, 'msg');
    }

    /**
     * Vartotojai, kuriems $uid gali rašyti / kuriuos gali pridėti į grupę,
     * su kategorija ('cat') sekcijoms. $exclude_chat — praleisti esamus grupės narius.
     */
    public static function contactable(int $uid, int $exclude_chat = 0, string $ctx = 'msg'): array {
        $friends    = self::friends_of($uid);
        $isTeacher  = self::has_role($uid, 'ss_teacher');
        $isAdmin    = self::is_admin_user($uid);
        $classmates = self::classmates($uid);
        $myStudents = $isTeacher ? self::teacher_student_ids($uid) : [];
        $myTeachers = self::student_teacher_user_ids($uid);

        if (self::is_admin_user($uid)) {
            $all = get_users(['fields' => ['ID'], 'number' => 2000, 'exclude' => [$uid]]);
            $ids = array_map(fn($u) => (int) $u->ID, $all);
        } else {
            $ids = array_map('intval', array_merge($friends, $classmates, $myStudents, $myTeachers));
            $ids = array_values(array_unique($ids));
        }
        $exclude = $exclude_chat ? self::chat_members($exclude_chat) : [];

        $fSet = array_flip($friends); $cSet = array_flip($classmates);
        $sSet = array_flip($myStudents); $tSet = array_flip($myTeachers);
        $xSet = array_flip($exclude);

        $out = [];
        foreach ($ids as $id) {
            if ($id === $uid || isset($xSet[$id])) continue;
            $u = get_userdata($id); if (!$u) continue;
            // Gavėjo privatumas pagal auditoriją. Ryšiai simetriški — vertinam per
            // savo (žiūrinčiojo) aibes, kad nereiktų brangių užklausų kiekvienam.
            $ok = $isAdmin || isset($sSet[$id]); // adminas arba aš – jų mokytojas
            if (!$ok) {
                $aud = self::read_audience($id, $ctx === 'group' ? 'ss_priv_group' : 'ss_priv_msg', self::AUD_DEFAULT);
                $ok = in_array('everyone', $aud, true)
                   || (in_array('friends', $aud, true) && isset($fSet[$id]))
                   || (in_array('classmates', $aud, true) && isset($cSet[$id]))
                   || (in_array('my_teachers', $aud, true) && isset($sSet[$id]))
                   || (in_array('my_students', $aud, true) && isset($tSet[$id]))
                   || (in_array('all_teachers', $aud, true) && $isTeacher);
            }
            if (!$ok) continue;
            $roles = (array) $u->roles;
            if     (isset($fSet[$id]))                        $cat = 'friend';
            elseif (isset($sSet[$id]))                        $cat = 'my_student';
            elseif (isset($tSet[$id]))                        $cat = 'teacher';
            elseif (isset($cSet[$id]))                        $cat = 'classmate';
            elseif (in_array('ss_teacher', $roles, true))     $cat = 'teacher';
            elseif (in_array('ss_employee', $roles, true))    $cat = 'employee';
            elseif (in_array('administrator', $roles, true))  $cat = 'admin';
            elseif (in_array('ss_student', $roles, true))     $cat = 'student';
            else                                              $cat = 'other';
            $out[] = [
                'id'     => $id,
                'name'   => $u->display_name,
                'avatar' => get_avatar_url($id, ['size' => 96]) ?: '',
                'role'   => self::role_label($id),
                'cat'    => $cat,
            ];
        }
        usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    public static function ajax_chat_contacts(): void {
        $uid = self::guard();
        $exclude = (int) ($_POST['exclude_chat'] ?? 0);
        // Naujam pokalbiui exclude=0; pridedant į grupę — tik grupės administratorius.
        if ($exclude && self::member_role($exclude, $uid) !== 'admin') $exclude = 0;
        $ctx = $exclude ? 'group' : 'msg';
        wp_send_json_success(['contacts' => self::contactable($uid, $exclude, $ctx)]);
    }

    /* ════════════════════════════════════════════════════════════════
       POKALBIAI
       ════════════════════════════════════════════════════════════════ */
    public static function is_member(int $chat_id, int $uid): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM {$wpdb->prefix}ss_chat_members WHERE chat_id=%d AND user_id=%d", $chat_id, $uid));
    }
    private static function chat_type(int $chat_id): string {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT type FROM {$wpdb->prefix}ss_chats WHERE id=%d", $chat_id));
    }
    /** Ar $uid gali rašyti į pokalbį (kanale — tik administratorius). */
    private static function can_post(int $chat_id, int $uid): bool {
        if (self::chat_type($chat_id) === 'channel') return self::member_role($chat_id, $uid) === 'admin';
        return true;
    }
    private static function member_role(int $chat_id, int $uid): string {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM {$wpdb->prefix}ss_chat_members WHERE chat_id=%d AND user_id=%d", $chat_id, $uid));
    }
    private static function chat_members(int $chat_id): array {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}ss_chat_members WHERE chat_id=%d", $chat_id));
        return array_map('intval', (array) $ids);
    }
    /** Pokalbio antraštė žiūrinčiajam: grupei/kanalui — title; asmeniniam — kito nario vardas. */
    private static function chat_display(object $chat, int $uid): array {
        if ($chat->type === 'group' || $chat->type === 'channel') {
            return ['title' => $chat->title ?: ($chat->type === 'channel' ? 'Kanalas' : 'Grupė'), 'avatar' => '', 'peer' => 0];
        }
        $peer = 0;
        foreach (self::chat_members((int) $chat->id) as $m) if ($m !== $uid) { $peer = $m; break; }
        $c = $peer ? self::user_card($peer) : ['name' => 'Pokalbis', 'avatar' => ''];
        return ['title' => $c['name'], 'avatar' => $c['avatar'], 'peer' => $peer];
    }
    private static function msg_json(object $m, int $viewer = 0): array {
        $type = (string) ($m->type ?? 'text');
        $meta = (isset($m->meta) && $m->meta !== null && $m->meta !== '') ? json_decode((string) $m->meta, true) : null;
        if (!is_array($meta)) $meta = null;
        $out = [
            'id'      => (int) $m->id,
            'user_id' => (int) $m->user_id,
            'type'    => $type,
            'name'    => ($u = get_userdata((int) $m->user_id)) ? $u->display_name : '—',
            'avatar'  => get_avatar_url((int) $m->user_id, ['size' => 64]) ?: '',
            'body'    => (string) $m->body,
            'meta'    => $meta,
            'edited'  => (int) ($m->edited ?? 0),
            'time'    => self::fmt_hm((string) $m->created_at),
            'date'    => mysql2date('Y-m-d', $m->created_at),
        ];
        if ($type === 'poll')    $out['poll']    = self::poll_state((int) $m->id, is_array($meta) ? $meta : [], $viewer);
        if ($type === 'media')   $out['media']   = self::media_state((int) $m->id, is_array($meta) ? $meta : []);
        if ($type === 'sticker') $out['sticker'] = ['key' => (string) ($meta['sticker'] ?? ''), 'url' => self::sticker_url((string) ($meta['sticker'] ?? ''))];
        if ($type !== 'system')  $out['reactions'] = self::reactions_state((int) $m->id, $viewer);
        return $out;
    }
    /* ── Reakcijos (emoji) ─────────────────────────────────────────────── */
    public static function reaction_set(): array {
        return ['👍', '❤️', '😂', '😮', '😢', '🎉'];
    }
    private static function reactions_state(int $mid, int $viewer): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT emoji, COUNT(*) c, SUM(CASE WHEN user_id=%d THEN 1 ELSE 0 END) mine
             FROM {$wpdb->prefix}ss_chat_reactions WHERE message_id=%d GROUP BY emoji ORDER BY c DESC, emoji ASC",
            $viewer, $mid));
        $out = [];
        foreach ((array) $rows as $r) {
            $out[] = ['emoji' => (string) $r->emoji, 'count' => (int) $r->c, 'mine' => ((int) $r->mine) > 0];
        }
        return $out;
    }
    public static function ajax_chat_react(): void {
        $uid   = self::guard();
        $mid   = (int) ($_POST['message_id'] ?? 0);
        $emoji = (string) wp_unslash($_POST['emoji'] ?? '');
        if (!in_array($emoji, self::reaction_set(), true)) wp_send_json_error(['message' => 'Netinkama reakcija.']);
        global $wpdb;
        $m = $wpdb->get_row($wpdb->prepare("SELECT chat_id FROM {$wpdb->prefix}ss_chat_messages WHERE id=%d AND deleted=0", $mid));
        if (!$m) wp_send_json_error(['message' => 'Žinutė nerasta.']);
        if (!self::is_member((int) $m->chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        $t = "{$wpdb->prefix}ss_chat_reactions";
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE message_id=%d AND user_id=%d AND emoji=%s", $mid, $uid, $emoji));
        if ($existing) $wpdb->delete($t, ['id' => (int) $existing]);
        else $wpdb->insert($t, ['message_id' => $mid, 'user_id' => $uid, 'emoji' => $emoji, 'created_at' => current_time('mysql')]);
        wp_send_json_success(['reactions' => self::reactions_state($mid, $uid)]);
    }
    /** Sisteminė žinutė (pvz. „X prisijungė"). */
    private static function system_msg(int $chat_id, string $text): void {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert("{$wpdb->prefix}ss_chat_messages", [
            'chat_id' => $chat_id, 'user_id' => 0, 'type' => 'system',
            'body' => $text, 'created_at' => $now, 'deleted' => 0,
        ]);
        $wpdb->update("{$wpdb->prefix}ss_chats", ['updated_at' => $now], ['id' => $chat_id]);
    }
    private static function uname(int $uid): string {
        $u = get_userdata($uid);
        return $u ? $u->display_name : ('#' . $uid);
    }
    /**
     * Suformatuoja DB laiką (saugotą WP vietine zona per current_time('mysql'))
     * į Vilniaus laiką „HH:MM". Teisinga nepriklausomai nuo to, ar WP zona yra
     * Europe/Vilnius, ar UTC — įrašą interpretuojam saugojimo zonoje ir verčiam
     * į Vilnių.
     */
    private static function fmt_hm(string $mysql): string {
        try {
            $store_tz = class_exists('SS_Time') ? SS_Time::tz() : wp_timezone();
            $dt = new DateTime($mysql, $store_tz);
            $dt->setTimezone(new DateTimeZone('Europe/Vilnius'));
            return $dt->format('H:i');
        } catch (Exception $e) {
            return mysql2date('H:i', $mysql);
        }
    }
    /** Kiekvieno nario (išskyrus save) paskutinės perskaitytos žinutės ID — „galvutėms" / „perskaitė". */
    private static function chat_reads(int $chat_id, int $self): array {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT user_id, last_read_id FROM {$wpdb->prefix}ss_chat_members
             WHERE chat_id=%d AND user_id<>%d", $chat_id, $self));
        $out = [];
        foreach ((array) $rows as $r) {
            $out[] = [
                'id'      => (int) $r->user_id,
                'read_id' => (int) $r->last_read_id,
                'name'    => ($u = get_userdata((int) $r->user_id)) ? $u->display_name : '—',
                'avatar'  => get_avatar_url((int) $r->user_id, ['size' => 48]) ?: '',
            ];
        }
        return $out;
    }

    public static function ajax_chat_list(): void {
        $uid = self::guard();
        global $wpdb;
        $chats = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, m.last_read_id
             FROM {$wpdb->prefix}ss_chats c
             JOIN {$wpdb->prefix}ss_chat_members m ON m.chat_id=c.id AND m.user_id=%d
             ORDER BY c.updated_at DESC LIMIT 100", $uid));
        $out = [];
        foreach ($chats as $c) {
            $disp = self::chat_display($c, $uid);
            $last = $wpdb->get_row($wpdb->prepare(
                "SELECT id, user_id, body, created_at FROM {$wpdb->prefix}ss_chat_messages
                 WHERE chat_id=%d AND deleted=0 ORDER BY id DESC LIMIT 1", $c->id));
            $unread = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ss_chat_messages
                 WHERE chat_id=%d AND id>%d AND user_id<>%d AND deleted=0 AND type<>'system'", $c->id, (int) $c->last_read_id, $uid));
            $cset = self::parse_settings($c->settings ?? null);
            $out[] = [
                'id'       => (int) $c->id,
                'type'     => $c->type,
                'title'    => $disp['title'],
                'avatar'   => $disp['avatar'],
                'avatar_img'=> $cset['avatar'],
                'peer'     => $disp['peer'],
                'members'  => count(self::chat_members((int) $c->id)),
                'last'     => $last ? mb_substr(wp_strip_all_tags($last->body), 0, 60) : '',
                'last_ts'  => $last ? $last->created_at : $c->updated_at,
                'unread'   => $unread,
            ];
        }
        wp_send_json_success(['chats' => $out]);
    }

    public static function ajax_chat_open(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        if (!self::is_member($chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        global $wpdb;
        $chat = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_chats WHERE id=%d", $chat_id));
        if (!$chat) wp_send_json_error(['message' => 'Pokalbis nerastas.']);

        $rows = array_reverse((array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_chat_messages WHERE chat_id=%d AND deleted=0 ORDER BY id DESC LIMIT 60", $chat_id)));
        $messages = array_map(fn($r) => self::msg_json($r, $uid), $rows);

        $all_members = [];
        foreach (self::chat_members($chat_id) as $m) {
            $card = self::user_card($m);
            $card['role'] = self::member_role($chat_id, $m);
            $all_members[] = $card;
        }
        $member_count = count($all_members);
        // Kanale paprasti nariai mato tik administratorius + prenumeratorių skaičių;
        // administratorius mato VISUS narius.
        $sub_count = 0;
        $members = $all_members;
        $viewer_is_admin = self::member_role($chat_id, $uid) === 'admin';
        if ($chat->type === 'channel' && !$viewer_is_admin) {
            $members = array_values(array_filter($all_members, fn($c) => $c['role'] === 'admin'));
            $sub_count = $member_count - count($members);
        }
        // Pažymim perskaitytus
        $maxid = $messages ? end($messages)['id'] : 0;
        if ($maxid) $wpdb->update("{$wpdb->prefix}ss_chat_members", ['last_read_id' => $maxid], ['chat_id' => $chat_id, 'user_id' => $uid]);

        $disp = self::chat_display($chat, $uid);
        wp_send_json_success([
            'chat' => [
                'id'          => $chat_id,
                'type'        => $chat->type,
                'title'       => $disp['title'],
                'avatar'      => $disp['avatar'],
                'peer'        => $disp['peer'],
                'is_admin'    => self::member_role($chat_id, $uid) === 'admin',
                'can_post'    => self::can_post($chat_id, $uid),
                'member_count'=> $member_count,
                'sub_count'   => $sub_count,
                'settings'    => self::parse_settings($chat->settings ?? null),
                'presence'    => ($chat->type === 'direct' && $disp['peer']) ? self::presence_for((int) $disp['peer'], $uid) : null,
            ],
            'members'  => $members,
            'messages' => $messages,
            'reads'    => self::chat_reads($chat_id, $uid),
        ]);
    }

    public static function ajax_chat_poll(): void {
        $uid = self::guard();
        $chat_id  = (int) ($_POST['chat_id'] ?? 0);
        $since_id = (int) ($_POST['since_id'] ?? 0);
        if (!self::is_member($chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_chat_messages
             WHERE chat_id=%d AND id>%d AND deleted=0 ORDER BY id ASC LIMIT 100", $chat_id, $since_id));
        $messages = array_map(fn($r) => self::msg_json($r, $uid), (array) $rows);
        if ($messages) {
            $maxid = end($messages)['id'];
            $wpdb->update("{$wpdb->prefix}ss_chat_members", ['last_read_id' => $maxid], ['chat_id' => $chat_id, 'user_id' => $uid]);
        }
        // „reads" grąžinam visada — kad varnelės / „perskaitė" atsinaujintų net be naujų žinučių.
        // Asmeniniuose pokalbiuose taip pat atnaujinam pašnekovo buvimo statusą.
        $typing = self::typing_users($chat_id, $uid);
        $presence = null;
        $chat = $wpdb->get_row($wpdb->prepare("SELECT type FROM {$wpdb->prefix}ss_chats WHERE id=%d", $chat_id));
        if ($chat && $chat->type === 'direct') {
            $peer = 0;
            foreach (self::chat_members($chat_id) as $m) if ($m !== $uid) { $peer = $m; break; }
            if ($peer) $presence = self::presence_for($peer, $uid);
        }
        wp_send_json_success(['messages' => $messages, 'reads' => self::chat_reads($chat_id, $uid), 'presence' => $presence, 'typing' => $typing]);
    }
    private static function typing_users(int $chat_id, int $self): array {
        $names = [];
        foreach (self::chat_members($chat_id) as $m) {
            if ($m === $self) continue;
            if (get_transient("ss_typing_{$chat_id}_{$m}")) {
                $u = get_userdata($m);
                if ($u) $names[] = $u->display_name;
            }
        }
        return $names;
    }
    public static function ajax_chat_typing(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        if (!self::is_member($chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        // Kanale „rašo" rodom tik tada, jei vartotojas apskritai gali rašyti (administratorius).
        if (self::can_post($chat_id, $uid)) set_transient("ss_typing_{$chat_id}_{$uid}", 1, 6);
        wp_send_json_success(['ok' => 1]);
    }

    public static function ajax_chat_send(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        $body    = trim((string) wp_unslash($_POST['body'] ?? ''));
        if (!self::is_member($chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        if (!self::can_post($chat_id, $uid)) wp_send_json_error(['message' => 'Kanale rašo tik administratorius.']);
        if ($body === '') wp_send_json_error(['message' => 'Tuščia žinutė.']);
        $body = sanitize_textarea_field($body);
        if (mb_strlen($body) > self::MSG_MAX) $body = mb_substr($body, 0, self::MSG_MAX);
        // Rate limit: 30 žinučių per minutę
        if (class_exists('SS_RateLimit') && !SS_RateLimit::hit('chat_' . $uid, 30, MINUTE_IN_SECONDS)) {
            wp_send_json_error(['message' => 'Per daug žinučių. Palaukite.']);
        }
        global $wpdb;
        $chat = $wpdb->get_row($wpdb->prepare("SELECT type FROM {$wpdb->prefix}ss_chats WHERE id=%d", $chat_id));
        // Asmeniniame pokalbyje rašyti galima tik jei bent vienas gali inicijuoti kontaktą.
        if ($chat && $chat->type === 'direct') {
            $peer = 0;
            foreach (self::chat_members($chat_id) as $m) if ($m !== $uid) { $peer = $m; break; }
            if ($peer && !self::can_converse($uid, $peer)) {
                wp_send_json_error(['message' => 'Šiam vartotojui rašyti negalima.']);
            }
        }
        $now = current_time('mysql');
        $wpdb->insert("{$wpdb->prefix}ss_chat_messages", [
            'chat_id' => $chat_id, 'user_id' => $uid, 'body' => $body, 'created_at' => $now, 'deleted' => 0,
        ]);
        $mid = (int) $wpdb->insert_id;
        delete_transient("ss_typing_{$chat_id}_{$uid}"); // nustojo rašyti
        $wpdb->update("{$wpdb->prefix}ss_chats", ['updated_at' => $now], ['id' => $chat_id]);
        $wpdb->update("{$wpdb->prefix}ss_chat_members", ['last_read_id' => $mid], ['chat_id' => $chat_id, 'user_id' => $uid]);
        if (class_exists('SS_Achievements')) SS_Achievements::bump($uid, 'msg');

        $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_chat_messages WHERE id=%d", $mid));
        wp_send_json_success(['message' => self::msg_json($m, $uid)]);
    }

    public static function ajax_chat_edit(): void {
        $uid = self::guard();
        $mid  = (int) ($_POST['message_id'] ?? 0);
        $body = trim((string) wp_unslash($_POST['body'] ?? ''));
        global $wpdb;
        $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_chat_messages WHERE id=%d", $mid));
        if (!$m || (int) $m->deleted) wp_send_json_error(['message' => 'Žinutė nerasta.']);
        if ((int) $m->user_id !== $uid) wp_send_json_error(['message' => 'Redaguoti galima tik savo žinutes.']);
        if (!self::is_member((int) $m->chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        if ($body === '') wp_send_json_error(['message' => 'Tuščia žinutė.']);
        $body = sanitize_textarea_field($body);
        if (mb_strlen($body) > self::MSG_MAX) $body = mb_substr($body, 0, self::MSG_MAX);
        $wpdb->update("{$wpdb->prefix}ss_chat_messages", ['body' => $body, 'edited' => 1], ['id' => $mid]);
        $m->body = $body; $m->edited = 1;
        wp_send_json_success(['message' => self::msg_json($m, $uid)]);
    }

    public static function ajax_chat_create(): void {
        $uid = self::guard();
        $kind = sanitize_key($_POST['kind'] ?? 'direct');
        global $wpdb;

        if ($kind === 'direct') {
            $target = (int) ($_POST['user_id'] ?? 0);
            if (!$target || !self::can_contact($uid, $target)) {
                wp_send_json_error(['message' => 'Šiam vartotojui rašyti negalima.']);
            }
            // Esamas asmeninis pokalbis?
            $existing = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT c.id FROM {$wpdb->prefix}ss_chats c
                 JOIN {$wpdb->prefix}ss_chat_members a ON a.chat_id=c.id AND a.user_id=%d
                 JOIN {$wpdb->prefix}ss_chat_members b ON b.chat_id=c.id AND b.user_id=%d
                 WHERE c.type='direct' LIMIT 1", $uid, $target));
            if ($existing) wp_send_json_success(['chat_id' => $existing]);
            $chat_id = self::create_chat('direct', '', $uid, [$uid, $target]);
            wp_send_json_success(['chat_id' => $chat_id]);
        }

        // grupė arba kanalas (vienpusis — rašo tik administratorius)
        $is_channel = ($kind === 'channel');
        $title = sanitize_text_field($_POST['title'] ?? '');
        if ($title === '') $title = $is_channel ? 'Kanalas' : 'Nauja grupė';
        $ids = array_filter(array_map('intval', (array) ($_POST['members'] ?? [])));
        $members = [$uid];
        foreach ($ids as $mid) {
            if ($mid !== $uid && self::can_contact($uid, $mid, 'group')) $members[] = $mid;
        }
        $members = array_values(array_unique($members));
        if (count($members) < 2) wp_send_json_error(['message' => 'Pasirinkite bent vieną vartotoją.']);
        $chat_id = self::create_chat($is_channel ? 'channel' : 'group', mb_substr($title, 0, 120), $uid, $members, $uid);
        wp_send_json_success(['chat_id' => $chat_id]);
    }

    private static function create_chat(string $type, string $title, int $creator, array $members, int $admin = 0): int {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert("{$wpdb->prefix}ss_chats", [
            'type' => $type, 'title' => $title, 'created_by' => $creator,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $chat_id = (int) $wpdb->insert_id;
        foreach ($members as $m) {
            $wpdb->insert("{$wpdb->prefix}ss_chat_members", [
                'chat_id' => $chat_id, 'user_id' => $m,
                'role' => ($m === $admin) ? 'admin' : 'member',
                'last_read_id' => 0, 'joined_at' => $now,
            ]);
        }
        return $chat_id;
    }

    public static function ajax_chat_add_member(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        $target  = (int) ($_POST['user_id'] ?? 0);
        if (self::member_role($chat_id, $uid) !== 'admin') wp_send_json_error(['message' => 'Tik grupės administratorius gali pridėti.']);
        global $wpdb;
        $chat = $wpdb->get_row($wpdb->prepare("SELECT type FROM {$wpdb->prefix}ss_chats WHERE id=%d", $chat_id));
        if (!$chat || $chat->type !== 'group') wp_send_json_error(['message' => 'Netinka.']);
        if (!self::can_contact($uid, $target, 'group')) wp_send_json_error(['message' => 'Šio vartotojo pridėti negalima.']);
        if (self::is_member($chat_id, $target)) wp_send_json_error(['message' => 'Jau grupėje.']);
        $wpdb->insert("{$wpdb->prefix}ss_chat_members", [
            'chat_id' => $chat_id, 'user_id' => $target, 'role' => 'member',
            'last_read_id' => 0, 'joined_at' => current_time('mysql'),
        ]);
        self::system_msg($chat_id, self::uname($target) . ' prisijungė prie grupės.');
        wp_send_json_success(['ok' => 1]);
    }
    public static function ajax_chat_remove_member(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        $target  = (int) ($_POST['user_id'] ?? 0);
        if (self::member_role($chat_id, $uid) !== 'admin') wp_send_json_error(['message' => 'Tik administratorius.']);
        if ($target === $uid) wp_send_json_error(['message' => 'Naudokite „Palikti grupę".']);
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}ss_chat_members", ['chat_id' => $chat_id, 'user_id' => $target]);
        self::system_msg($chat_id, self::uname($target) . ' pašalintas (-a) iš grupės.');
        wp_send_json_success(['ok' => 1]);
    }
    public static function ajax_chat_rename(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        $title   = sanitize_text_field($_POST['title'] ?? '');
        if (self::member_role($chat_id, $uid) !== 'admin') wp_send_json_error(['message' => 'Tik administratorius.']);
        if ($title === '') wp_send_json_error(['message' => 'Įveskite pavadinimą.']);
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}ss_chats", ['title' => mb_substr($title, 0, 120)], ['id' => $chat_id]);
        wp_send_json_success(['title' => $title]);
    }
    public static function ajax_chat_leave(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        if (!self::is_member($chat_id, $uid)) wp_send_json_error(['message' => 'Nesate narys.']);
        global $wpdb;
        $was_admin = self::member_role($chat_id, $uid) === 'admin';
        $wpdb->delete("{$wpdb->prefix}ss_chat_members", ['chat_id' => $chat_id, 'user_id' => $uid]);
        $remaining = self::chat_members($chat_id);
        if (!$remaining) {
            // Tuščias pokalbis — išvalom.
            $wpdb->delete("{$wpdb->prefix}ss_chats", ['id' => $chat_id]);
            $wpdb->delete("{$wpdb->prefix}ss_chat_messages", ['chat_id' => $chat_id]);
        } else {
            if ($was_admin) {
                // Perduodam administravimą pirmam likusiam.
                $wpdb->update("{$wpdb->prefix}ss_chat_members", ['role' => 'admin'], ['chat_id' => $chat_id, 'user_id' => $remaining[0]]);
            }
            self::system_msg($chat_id, self::uname($uid) . ' paliko grupę.');
        }
        wp_send_json_success(['ok' => 1]);
    }
    public static function ajax_chat_unread_count(): void {
        $uid = self::guard();
        wp_send_json_success(['count' => self::unread_total($uid)]);
    }
    public static function unread_total(int $uid): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ss_chat_messages msg
             JOIN {$wpdb->prefix}ss_chat_members mem ON mem.chat_id=msg.chat_id AND mem.user_id=%d
             WHERE msg.id>mem.last_read_id AND msg.user_id<>%d AND msg.deleted=0 AND msg.type<>'system'", $uid, $uid));
    }

    /* ════════════════════════════════════════════════════════════════
       PRIEDAI: lipdukai, medijos failai, balsavimai („+" pokalbyje)
       ════════════════════════════════════════════════════════════════ */
    /**
     * Bendras lipdukų rinkinys — automatiškai aptinkamas iš public/stickers/.
     * Palaikomi .svg ir .png. Norint pridėti lipduką — tiesiog įdėkite failą.
     */
    public static function sticker_set(): array {
        static $keys = null;
        if ($keys !== null) return $keys;
        $keys = [];
        $dir = SS_PLUGIN_DIR . 'public/stickers/';
        foreach (['svg', 'png'] as $ext) {
            foreach ((array) glob($dir . '*.' . $ext) as $f) {
                $k = sanitize_file_name(basename($f, '.' . $ext));
                if ($k !== '' && !in_array($k, $keys, true)) $keys[] = $k;
            }
        }
        sort($keys);
        return $keys;
    }
    public static function sticker_url(string $key): string {
        if (!in_array($key, self::sticker_set(), true)) return '';
        foreach (['svg', 'png'] as $ext) {
            if (file_exists(SS_PLUGIN_DIR . 'public/stickers/' . $key . '.' . $ext)) {
                return SS_PLUGIN_URL . 'public/stickers/' . $key . '.' . $ext;
            }
        }
        return '';
    }

    /** Bendra dalis naujai žinutei įrašyti ir grąžinti. */
    private static function insert_message(int $chat_id, int $uid, string $type, string $body, ?array $meta = null): int {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert("{$wpdb->prefix}ss_chat_messages", [
            'chat_id' => $chat_id, 'user_id' => $uid, 'type' => $type,
            'body' => $body, 'meta' => $meta ? wp_json_encode($meta) : null,
            'created_at' => $now, 'deleted' => 0,
        ]);
        $mid = (int) $wpdb->insert_id;
        $wpdb->update("{$wpdb->prefix}ss_chats", ['updated_at' => $now], ['id' => $chat_id]);
        $wpdb->update("{$wpdb->prefix}ss_chat_members", ['last_read_id' => $mid], ['chat_id' => $chat_id, 'user_id' => $uid]);
        return $mid;
    }
    private static function reply_with_message(int $mid, int $uid): void {
        global $wpdb;
        $m = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_chat_messages WHERE id=%d", $mid));
        wp_send_json_success(['message' => self::msg_json($m, $uid)]);
    }

    public static function ajax_chat_sticker(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        $key = sanitize_file_name((string) ($_POST['sticker'] ?? ''));
        if (!self::is_member($chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        if (!self::can_post($chat_id, $uid)) wp_send_json_error(['message' => 'Kanale rašo tik administratorius.']);
        if (!in_array($key, self::sticker_set(), true)) wp_send_json_error(['message' => 'Nėra tokio lipduko.']);
        if (class_exists('SS_RateLimit') && !SS_RateLimit::hit('chat_' . $uid, 30, MINUTE_IN_SECONDS)) {
            wp_send_json_error(['message' => 'Per daug žinučių. Palaukite.']);
        }
        $mid = self::insert_message($chat_id, $uid, 'sticker', '[lipdukas]', ['sticker' => $key]);
        if (class_exists('SS_Achievements')) SS_Achievements::bump($uid, 'msg');
        self::reply_with_message($mid, $uid);
    }

    /* ── Medijos failai (iki 10 MB, saugomi 7 d.) ─────────────────────── */
    public static function ajax_chat_upload(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        if (!self::is_member($chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        if (!self::can_post($chat_id, $uid)) wp_send_json_error(['message' => 'Kanale rašo tik administratorius.']);
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'] ?? '')) {
            wp_send_json_error(['message' => 'Failas nepateiktas.']);
        }
        $f = $_FILES['file'];
        if ((int) $f['error'] !== UPLOAD_ERR_OK) wp_send_json_error(['message' => 'Įkėlimo klaida.']);
        if ((int) $f['size'] > 10 * MB_IN_BYTES) wp_send_json_error(['message' => 'Failas per didelis (iki 10 MB).']);
        if (class_exists('SS_RateLimit') && !SS_RateLimit::hit('chatup_' . $uid, 20, MINUTE_IN_SECONDS)) {
            wp_send_json_error(['message' => 'Per daug įkėlimų. Palaukite.']);
        }
        $check = wp_check_filetype_and_ext($f['tmp_name'], $f['name']);
        $ext = strtolower((string) ($check['ext'] ?? ''));
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                    'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
        if (!$ext || !isset($allowed[$ext])) wp_send_json_error(['message' => 'Netinkamas failo tipas (leidžiama: nuotraukos, PDF).']);
        $mime = $allowed[$ext];
        $is_image = strpos($mime, 'image/') === 0;

        $updir = wp_upload_dir();
        $dir = $updir['basedir'] . '/ss-chat';
        if (!is_dir($dir)) wp_mkdir_p($dir);
        if (!file_exists($dir . '/index.php')) @file_put_contents($dir . '/index.php', "<?php // Silence is golden.");
        $fname = wp_generate_password(24, false, false) . '.' . $ext;
        $dest = $dir . '/' . $fname;
        if (!@move_uploaded_file($f['tmp_name'], $dest)) wp_send_json_error(['message' => 'Nepavyko išsaugoti failo.']);
        @chmod($dest, 0644);

        $w = 0; $h = 0;
        if ($is_image && ($sz = @getimagesize($dest))) { $w = (int) $sz[0]; $h = (int) $sz[1]; }
        $meta = [
            'file' => 'ss-chat/' . $fname, 'name' => sanitize_file_name($f['name']),
            'size' => (int) $f['size'], 'mime' => $mime, 'is_image' => $is_image, 'w' => $w, 'h' => $h,
        ];
        $body = $is_image ? '[nuotrauka]' : ('[failas] ' . $meta['name']);
        $mid = self::insert_message($chat_id, $uid, 'media', $body, $meta);
        if (class_exists('SS_Achievements')) SS_Achievements::bump($uid, 'msg');
        self::reply_with_message($mid, $uid);
    }
    private static function media_state(int $mid, array $meta): array {
        if (!empty($meta['expired'])) {
            return ['expired' => true, 'name' => (string) ($meta['name'] ?? 'failas'), 'is_image' => !empty($meta['is_image'])];
        }
        return [
            'is_image' => !empty($meta['is_image']),
            'name'     => (string) ($meta['name'] ?? 'failas'),
            'size'     => (int) ($meta['size'] ?? 0),
            'mime'     => (string) ($meta['mime'] ?? ''),
            'w'        => (int) ($meta['w'] ?? 0),
            'h'        => (int) ($meta['h'] ?? 0),
            'url'      => add_query_arg(['action' => 'ss_chat_media', 'id' => $mid], admin_url('admin-ajax.php')),
        ];
    }
    /** Saugus failo tiekimas — tik pokalbio nariams (ne JSON). */
    public static function ajax_chat_media(): void {
        if (!is_user_logged_in()) { status_header(403); exit; }
        $uid = get_current_user_id();
        $mid = (int) ($_GET['id'] ?? 0);
        global $wpdb;
        $m = $wpdb->get_row($wpdb->prepare("SELECT chat_id, meta FROM {$wpdb->prefix}ss_chat_messages WHERE id=%d AND type='media' AND deleted=0", $mid));
        if (!$m) { status_header(404); exit; }
        if (!self::is_member((int) $m->chat_id, $uid)) { status_header(403); exit; }
        $meta = json_decode((string) $m->meta, true);
        if (!is_array($meta) || empty($meta['file']) || !empty($meta['expired'])) { status_header(410); exit; }
        $updir = wp_upload_dir();
        $path = realpath($updir['basedir'] . '/' . $meta['file']);
        $base = realpath($updir['basedir'] . '/ss-chat');
        if (!$path || !$base || strpos($path, $base) !== 0 || !is_file($path)) { status_header(404); exit; }
        nocache_headers();
        header('Content-Type: ' . (string) ($meta['mime'] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        $disp = !empty($meta['is_image']) ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disp . '; filename="' . rawurlencode((string) ($meta['name'] ?? 'failas')) . '"');
        readfile($path);
        exit;
    }
    /** CRON: pašalinam medijos failus, senesnius nei 7 d. */
    private static function purge_expired_media(): void {
        global $wpdb;
        $cut = date('Y-m-d H:i:s', current_time('timestamp') - 7 * DAY_IN_SECONDS);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, meta FROM {$wpdb->prefix}ss_chat_messages WHERE type='media' AND created_at < %s LIMIT 200", $cut));
        if (!$rows) return;
        $updir = wp_upload_dir();
        $base = realpath($updir['basedir'] . '/ss-chat');
        foreach ($rows as $r) {
            $meta = json_decode((string) $r->meta, true);
            if (!is_array($meta) || !empty($meta['expired'])) continue;
            if (!empty($meta['file'])) {
                $p = realpath($updir['basedir'] . '/' . $meta['file']);
                if ($p && $base && strpos($p, $base) === 0 && is_file($p)) @unlink($p);
            }
            $wpdb->update("{$wpdb->prefix}ss_chat_messages", [
                'meta' => wp_json_encode(['expired' => 1, 'name' => (string) ($meta['name'] ?? 'failas'), 'is_image' => !empty($meta['is_image'])]),
                'body' => '[failas nebegalioja]',
            ], ['id' => (int) $r->id]);
        }
    }

    /* ── Balsavimai (tik grupėse) ─────────────────────────────────────── */
    public static function ajax_chat_poll_create(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        if (!self::is_member($chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        global $wpdb;
        $chat = $wpdb->get_row($wpdb->prepare("SELECT type FROM {$wpdb->prefix}ss_chats WHERE id=%d", $chat_id));
        if (!$chat || $chat->type !== 'group') wp_send_json_error(['message' => 'Balsavimai galimi tik grupėse.']);
        $q = sanitize_text_field(wp_unslash((string) ($_POST['question'] ?? '')));
        $multi = !empty($_POST['multi']);
        $opts = [];
        foreach ((array) ($_POST['options'] ?? []) as $o) {
            $o = sanitize_text_field(wp_unslash((string) $o));
            if ($o !== '') $opts[] = mb_substr($o, 0, 120);
        }
        $opts = array_values(array_slice($opts, 0, 10));
        if ($q === '') wp_send_json_error(['message' => 'Įveskite klausimą.']);
        if (count($opts) < 2) wp_send_json_error(['message' => 'Reikia bent 2 atsakymų.']);
        $meta = ['q' => mb_substr($q, 0, 200), 'options' => $opts, 'multi' => $multi ? 1 : 0];
        $mid = self::insert_message($chat_id, $uid, 'poll', '[balsavimas] ' . $q, $meta);
        if (class_exists('SS_Achievements')) SS_Achievements::bump($uid, 'msg');
        self::reply_with_message($mid, $uid);
    }
    public static function ajax_chat_poll_vote(): void {
        $uid = self::guard();
        $mid = (int) ($_POST['message_id'] ?? 0);
        global $wpdb;
        $m = $wpdb->get_row($wpdb->prepare("SELECT chat_id, meta FROM {$wpdb->prefix}ss_chat_messages WHERE id=%d AND type='poll' AND deleted=0", $mid));
        if (!$m) wp_send_json_error(['message' => 'Balsavimas nerastas.']);
        if (!self::is_member((int) $m->chat_id, $uid)) wp_send_json_error(['message' => 'Neturite teisės.']);
        $meta = json_decode((string) $m->meta, true);
        $optCount = (is_array($meta) && isset($meta['options']) && is_array($meta['options'])) ? count($meta['options']) : 0;
        $opt = (int) ($_POST['opt'] ?? -1);
        if ($opt < 0 || $opt >= $optCount) wp_send_json_error(['message' => 'Netinkamas pasirinkimas.']);
        $t = "{$wpdb->prefix}ss_chat_poll_votes";
        if (!empty($meta['multi'])) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE message_id=%d AND user_id=%d AND opt_idx=%d", $mid, $uid, $opt));
            if ($existing) $wpdb->delete($t, ['id' => (int) $existing]);
            else $wpdb->insert($t, ['message_id' => $mid, 'user_id' => $uid, 'opt_idx' => $opt, 'created_at' => current_time('mysql')]);
        } else {
            $wpdb->delete($t, ['message_id' => $mid, 'user_id' => $uid]);
            $wpdb->insert($t, ['message_id' => $mid, 'user_id' => $uid, 'opt_idx' => $opt, 'created_at' => current_time('mysql')]);
        }
        wp_send_json_success(['poll' => self::poll_state($mid, is_array($meta) ? $meta : [], $uid)]);
    }
    private static function poll_state(int $mid, array $meta, int $viewer): array {
        global $wpdb;
        $opts = (isset($meta['options']) && is_array($meta['options'])) ? array_values($meta['options']) : [];
        $counts = array_fill(0, count($opts), 0);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT opt_idx, COUNT(*) c FROM {$wpdb->prefix}ss_chat_poll_votes WHERE message_id=%d GROUP BY opt_idx", $mid));
        foreach ((array) $rows as $r) { $i = (int) $r->opt_idx; if (isset($counts[$i])) $counts[$i] = (int) $r->c; }
        $my = [];
        if ($viewer) {
            $my = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
                "SELECT opt_idx FROM {$wpdb->prefix}ss_chat_poll_votes WHERE message_id=%d AND user_id=%d", $mid, $viewer)));
        }
        $voters = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ss_chat_poll_votes WHERE message_id=%d", $mid));
        $options = [];
        foreach ($opts as $i => $t) $options[] = ['text' => (string) $t, 'votes' => (int) $counts[$i]];
        return ['q' => (string) ($meta['q'] ?? ''), 'multi' => !empty($meta['multi']), 'options' => $options, 'my' => $my, 'voters' => $voters];
    }

    /* ════════════════════════════════════════════════════════════════
       POKALBIO NUSTATYMAI: fonas + paveikslėlis (grupė / kanalas)
       ════════════════════════════════════════════════════════════════ */
    public static function chat_backgrounds(): array {
        return [
            'default' => '',
            'b1' => '#eef2f6',
            'b2' => '#f4efe6',
            'b3' => '#eaf3ec',
            'b4' => 'linear-gradient(160deg,#eef4ff,#f6eefc)',
            'b5' => 'linear-gradient(160deg,#fef6ec,#fdeef0)',
            'b6' => '#e6ebf0',
        ];
    }
    public static function chat_bg_css(string $key): string {
        $b = self::chat_backgrounds();
        return $b[$key] ?? '';
    }
    private static function parse_settings($raw): array {
        $s = $raw ? json_decode((string) $raw, true) : null;
        if (!is_array($s)) $s = [];
        $bg = isset($s['bg']) ? (string) $s['bg'] : 'default';
        return ['bg' => $bg, 'bg_css' => self::chat_bg_css($bg), 'avatar' => isset($s['avatar']) ? (string) $s['avatar'] : ''];
    }
    private static function chat_settings_data(int $chat_id): array {
        global $wpdb;
        return self::parse_settings($wpdb->get_var($wpdb->prepare("SELECT settings FROM {$wpdb->prefix}ss_chats WHERE id=%d", $chat_id)));
    }
    private static function save_chat_settings(int $chat_id, array $patch): void {
        global $wpdb;
        $raw = $wpdb->get_var($wpdb->prepare("SELECT settings FROM {$wpdb->prefix}ss_chats WHERE id=%d", $chat_id));
        $s = $raw ? json_decode((string) $raw, true) : [];
        if (!is_array($s)) $s = [];
        $wpdb->update("{$wpdb->prefix}ss_chats", ['settings' => wp_json_encode(array_merge($s, $patch))], ['id' => $chat_id]);
    }
    public static function ajax_chat_set_bg(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        $bg = sanitize_key($_POST['bg'] ?? 'default');
        if (self::member_role($chat_id, $uid) !== 'admin') wp_send_json_error(['message' => 'Tik administratorius.']);
        if (!isset(self::chat_backgrounds()[$bg])) $bg = 'default';
        self::save_chat_settings($chat_id, ['bg' => $bg]);
        wp_send_json_success(['bg' => $bg, 'bg_css' => self::chat_bg_css($bg)]);
    }
    public static function ajax_chat_upload_avatar(): void {
        $uid = self::guard();
        $chat_id = (int) ($_POST['chat_id'] ?? 0);
        if (self::member_role($chat_id, $uid) !== 'admin') wp_send_json_error(['message' => 'Tik administratorius.']);
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'] ?? '')) wp_send_json_error(['message' => 'Failas nepateiktas.']);
        $f = $_FILES['file'];
        if ((int) $f['error'] !== UPLOAD_ERR_OK) wp_send_json_error(['message' => 'Įkėlimo klaida.']);
        if ((int) $f['size'] > 5 * MB_IN_BYTES) wp_send_json_error(['message' => 'Nuotrauka per didelė (iki 5 MB).']);
        $check = wp_check_filetype_and_ext($f['tmp_name'], $f['name']);
        $ext = strtolower((string) ($check['ext'] ?? ''));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) wp_send_json_error(['message' => 'Netinkamas formatas (reikia nuotraukos).']);
        $updir = wp_upload_dir();
        $dir = $updir['basedir'] . '/ss-chat-av';
        if (!is_dir($dir)) wp_mkdir_p($dir);
        $fname = 'c' . $chat_id . '_' . wp_generate_password(12, false, false) . '.' . $ext;
        if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $fname)) wp_send_json_error(['message' => 'Nepavyko išsaugoti.']);
        @chmod($dir . '/' . $fname, 0644);
        $url = $updir['baseurl'] . '/ss-chat-av/' . $fname;
        self::save_chat_settings($chat_id, ['avatar' => $url]);
        wp_send_json_success(['avatar' => $url]);
    }

    /* ── Profilio fonas ─────────────────────────────────────────────── */
    public static function ajax_save_profile_cover(): void {
        $uid = self::guard();
        $key = sanitize_key($_POST['cover'] ?? '');
        if (!isset(self::covers()[$key])) wp_send_json_error(['message' => 'Neteisingas fonas.']);
        update_user_meta($uid, 'ss_profile_cover', $key);
        if (class_exists('SS_Achievements')) SS_Achievements::bump($uid, 'cover');
        wp_send_json_success(['cover' => $key, 'css' => self::covers()[$key]]);
    }
}
