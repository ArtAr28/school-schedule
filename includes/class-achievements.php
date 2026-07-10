<?php
/**
 * SS_Achievements — pasiekimų sistema.
 *
 * Kiekvienas pasiekimas turi tikslą (target) ir progresą (progress). Progresas
 * skaičiuojamas dviem būdais:
 *   • counter:<name>  — per-user skaitiklis (user_meta „ss_ach_c_<name>"),
 *                       didinamas bump() įvykių vietose (login, nustatymai…);
 *   • derive          — apskaičiuojamas gyvai iš duomenų (Atspek žaidimo lentelės
 *                       wordle_lt_scores, balsavimo ss_euro_votes, draugų skaičiaus).
 *
 * Paslėpti pasiekimai (hidden) rodomi kaip „???" kol neatrakinti.
 * Atrakinimo laikas saugomas lentelėje {$prefix}ss_achievements.
 */
if (!defined('ABSPATH')) exit;

class SS_Achievements {

    /* ── Lentelė ────────────────────────────────────────────────────── */
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $t = $wpdb->prefix . 'ss_achievements';
        dbDelta("CREATE TABLE {$t} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            ach_key VARCHAR(40) NOT NULL,
            progress INT(11) NOT NULL DEFAULT 0,
            target INT(11) NOT NULL DEFAULT 1,
            unlocked_at DATETIME NULL DEFAULT NULL,
            updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (id),
            UNIQUE KEY user_key (user_id, ach_key),
            KEY user_id (user_id)
        ) {$charset};");
    }

    public static function init(): void {
        // Visi prisijungimai (plėtinio, wp-admin, QR, Google) iššaukia wp_login —
        // vienas centrinis kabliukas „login" ir „naktinei pelėdai".
        add_action('wp_login', [__CLASS__, 'on_login'], 10, 2);
    }

    public static function on_login($user_login, $user): void {
        $uid = ($user instanceof WP_User) ? (int) $user->ID : (int) get_current_user_id();
        if (!$uid) return;
        self::bump($uid, 'login');
        $h = (int) (class_exists('SS_Time') ? SS_Time::hour() : (int) current_time('G'));
        if ($h >= 22 || $h < 5) self::bump($uid, 'night_login');
    }

    /* ── Registras ──────────────────────────────────────────────────── */
    /**
     * key => [title, desc, icon, hidden, target, category, source]
     * source: 'counter:<name>' arba 'derive'
     */
    public static function defs(): array {
        return [
            // ── Atspek žaidimas ──
            'ats_first'   => ['Pirmas spėjimas',   'Sužaisk pirmą Atspėk partiją.',            '🎯', false, 1,   'atspek',   'derive'],
            'ats_10'      => ['Naujokas',          'Sužaisk 10 Atspėk partijų.',               '🎮', false, 10,  'atspek',   'derive'],
            'ats_50'      => ['Patyręs',           'Sužaisk 50 Atspėk partijų.',               '🥉', false, 50,  'atspek',   'derive'],
            'ats_100'      => ['Veteranas',        'Sužaisk 100 Atspėk partijų.',               '🥈', false, 100,  'atspek',   'derive'],
            'ats_250'      => ['Veteranų veteranas','Sužaisk 250 Atspėk partijų.',               '🏅', false, 250,  'atspek',   'derive'],
            'ats_points'  => ['Taškų krepšelis',      'Surink 5000 Atspėk taškų.',                 '🛍️', false, 5000, 'atspek',   'derive'],
            'ats_points1'  => ['Taškų vežimėlis',      'Surink 25000 Atspėk taškų.',                 '🛒', false, 25000, 'atspek',   'derive'],
            'ats_points2'  => ['Taškų kalnas',      'Surink 100000 Atspėk taškų.',                 '⛰️', false, 100000, 'atspek',   'derive'],
            'ats_streak'  => ['Apšilimas',   'Laimėk 5 partijas iš eilės.',              '🏃‍♀️‍➡️', false, 5,   'atspek',   'derive'],
            'ats_streak'  => ['Karštoji serija',   'Laimėk 25 partijas iš eilės.',              '🔥', false, 25,   'atspek',   'derive'],
            'ats_marathon'=> ['Maratonas',         'Sužaisk 5 partijas per vieną dieną.',      '🏃', true,  5,   'atspek',   'derive'],
            // ── Tvarkaraštis / prisijungimai ──
            'sch_first'   => ['Sveiki atvykę',     'Prisijunk prie sistemos.',                 '👋', false, 1,   'schedule', 'counter:login'],
            'sch_30'      => ['Nuolatinis',        'Atverk tvarkaraštį 30 kartų.',             '📅', false, 30,  'schedule', 'counter:sched_open'],
            'sch_night'   => ['Naktinė pelėda',    'Prisijunk tarp 23:00 ir 05:00.',           '🦉', true,  1,   'schedule', 'counter:night_login'],
            // ── Balsavimas ──
            'vote_first'  => ['Balsuotojas',       'Balsuok Ąžuolyno balsavime.',              '🗳️', false, 1,   'voting',   'derive'],
            // ── Nustatymai ──
            'set_change'  => ['Tvarkingas',        'Pakeisk bent vieną nustatymą.',            '⚙️', false, 1,   'settings', 'counter:settings'],
            'set_avatar'  => ['Individualumas',    'Įkelk savo nuotrauką (avatarą).',          '🖼️', false, 1,   'settings', 'counter:avatar'],
            'set_cover'   => ['Stilius',           'Pasirink profilio foną.',                  '🎨', false, 1,   'settings', 'counter:cover'],
            'set_2fa'     => ['Saugumas',          'Įjunk dviejų žingsnių autentifikaciją (2FA).', '🔒', true, 1,   'settings', 'counter:twofa'],
            // ── Socialiniai ──
            'soc_friend1' => ['Draugiškas',        'Susirask pirmą draugą.',                   '🤝', false, 1,   'social',   'derive'],
            'soc_friend10'=> ['Populiarus',        'Turėk 10 draugų.',                         '🌟', false, 10,  'social',   'derive'],
            'soc_msg'     => ['Pokalbis',          'Išsiųsk pirmą žinutę.',                    '💬', true, 1,   'social',   'counter:msg'],
        ];
    }

    /** Kategorijų etiketės (rikiavimui / grupavimui profilyje). */
    public static function categories(): array {
        return [
            'atspek'   => 'Atspėk',
            'schedule' => 'Tvarkaraštis',
            'voting'   => 'Balsavimas',
            'settings' => 'Nustatymai',
            'social'   => 'Draugai',
        ];
    }

    /* ── Skaitiklių didinimas ───────────────────────────────────────── */
    public static function bump(int $uid, string $counter, int $by = 1): void {
        if (!$uid || $counter === '') return;
        $mk  = 'ss_ach_c_' . $counter;
        $val = (int) get_user_meta($uid, $mk, true) + max(1, $by);
        update_user_meta($uid, $mk, $val);
        // Persyncinam TIK tuos pasiekimus, kurie priklauso nuo šio skaitiklio —
        // „karštuose" keliuose (pvz. dashboard) tai vos 1–3 upsertai.
        $src = 'counter:' . $counter;
        foreach (self::defs() as $key => $d) {
            if ($d[6] === $src) self::sync_one($uid, $key, $d);
        }
    }

    /** Vieno pasiekimo eilutės upsert'as pagal apskaičiuotą progresą. */
    private static function sync_one(int $uid, string $key, array $d): bool {
        global $wpdb;
        $t   = $wpdb->prefix . 'ss_achievements';
        $now = current_time('mysql');
        $target   = (int) $d[4];
        $progress = self::measure($key, $uid);
        $existing = $wpdb->get_row($wpdb->prepare("SELECT progress, unlocked_at FROM {$t} WHERE user_id=%d AND ach_key=%s", $uid, $key));
        $already  = $existing && $existing->unlocked_at;
        $unlock   = (!$already && $progress >= $target);
        if ($existing) {
            $wpdb->update($t, [
                'progress'    => $progress,
                'target'      => $target,
                'unlocked_at' => $unlock ? $now : $existing->unlocked_at,
                'updated_at'  => $now,
            ], ['user_id' => $uid, 'ach_key' => $key]);
        } else {
            $unlock = ($progress >= $target);
            $wpdb->insert($t, [
                'user_id' => $uid, 'ach_key' => $key, 'progress' => $progress, 'target' => $target,
                'unlocked_at' => $unlock ? $now : null, 'updated_at' => $now,
            ]);
        }
        return $unlock;
    }
    private static function counter(int $uid, string $name): int {
        return (int) get_user_meta($uid, 'ss_ach_c_' . $name, true);
    }

    /* ── Progreso apskaičiavimas ────────────────────────────────────── */
    public static function measure(string $key, int $uid): int {
        $defs = self::defs();
        if (!isset($defs[$key])) return 0;
        $src = $defs[$key][6];
        if (strncmp($src, 'counter:', 8) === 0) {
            return self::counter($uid, substr($src, 8));
        }
        // derive
        switch ($key) {
            case 'ats_first':
            case 'ats_10':
            case 'ats_50':    return self::atspek_stats($uid)['games'];
            case 'ats_points':return self::atspek_stats($uid)['points'];
            case 'ats_streak':return self::atspek_stats($uid)['streak'];
            case 'ats_marathon': return self::atspek_stats($uid)['today'];
            case 'vote_first':   return self::vote_count($uid);
            case 'soc_friend1':
            case 'soc_friend10': return class_exists('SS_Social') ? SS_Social::friends_count($uid) : 0;
        }
        return 0;
    }

    /* ── Sinchronizacija ────────────────────────────────────────────── */
    /**
     * Perskaičiuoja progresą ir atrakina pasiektus. Grąžina naujai atrakintų
     * pasiekimų sąrašą (toast'ams). $counters_only praleidžia „derive".
     */
    public static function sync(int $uid, bool $counters_only = false): array {
        if (!$uid) return [];
        global $wpdb;
        $t   = $wpdb->prefix . 'ss_achievements';
        $now = current_time('mysql');
        $newly = [];

        // Esamos eilutės vienu užklausimu
        $rows = $wpdb->get_results($wpdb->prepare("SELECT ach_key, progress, unlocked_at FROM {$t} WHERE user_id=%d", $uid), OBJECT_K);

        foreach (self::defs() as $key => $d) {
            $is_counter = strncmp($d[6], 'counter:', 8) === 0;
            if ($counters_only && !$is_counter) continue;

            $target   = (int) $d[4];
            $progress = self::measure($key, $uid);
            $existing = $rows[$key] ?? null;
            $already  = $existing && $existing->unlocked_at;
            $unlock   = (!$already && $progress >= $target);

            if ($existing) {
                $wpdb->update($t, [
                    'progress'    => $progress,
                    'target'      => $target,
                    'unlocked_at' => $unlock ? $now : $existing->unlocked_at,
                    'updated_at'  => $now,
                ], ['user_id' => $uid, 'ach_key' => $key]);
            } else {
                $wpdb->insert($t, [
                    'user_id'     => $uid,
                    'ach_key'     => $key,
                    'progress'    => $progress,
                    'target'      => $target,
                    'unlocked_at' => ($progress >= $target) ? $now : null,
                    'updated_at'  => $now,
                ]);
                $unlock = ($progress >= $target);
            }
            if ($unlock) $newly[] = ['key' => $key, 'title' => $d[0], 'icon' => $d[2]];
        }
        return $newly;
    }

    /**
     * Pasiekimų sąrašas profiliui. $owner — ar žiūri savininkas (nekeičia turinio,
     * bet gali būti naudinga ateityje). Paslėpti + neatrakinti maskuojami.
     * @return array[] eilutės su: key,title,desc,icon,category,target,progress,unlocked,unlocked_at,hidden
     */
    public static function for_user(int $uid, bool $full_sync = true): array {
        if ($full_sync) self::sync($uid, false);
        global $wpdb;
        $t = $wpdb->prefix . 'ss_achievements';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT ach_key, progress, target, unlocked_at FROM {$t} WHERE user_id=%d", $uid), OBJECT_K);

        $out = [];
        foreach (self::defs() as $key => $d) {
            $r        = $rows[$key] ?? null;
            $progress = $r ? (int) $r->progress : 0;
            $target   = (int) $d[4];
            $unlocked = $r && $r->unlocked_at;
            $hidden   = (bool) $d[3];
            $masked   = $hidden && !$unlocked;
            $out[] = [
                'key'         => $key,
                'title'       => $masked ? '???' : $d[0],
                'desc'        => $masked ? 'Paslėptas pasiekimas.' : $d[1],
                'icon'        => $masked ? '❓' : $d[2],
                'category'    => $d[5],
                'target'      => $target,
                'progress'    => min($progress, $target),
                'unlocked'    => (bool) $unlocked,
                'unlocked_at' => $unlocked ? $r->unlocked_at : null,
                'hidden'      => $hidden,
            ];
        }
        return $out;
    }

    /** Trumpa suvestinė (atrakinta / iš viso) profilio antraštei. */
    public static function summary(int $uid): array {
        $list = self::for_user($uid, false);
        $unlocked = 0;
        foreach ($list as $a) if ($a['unlocked']) $unlocked++;
        return ['unlocked' => $unlocked, 'total' => count($list)];
    }

    /* ── Atspek (Wordle-lt) — atskiro įskiepio lentelė, tikrinam gynybiškai ── */
    public static function atspek_stats(int $uid): array {
        static $cache = [];
        if (isset($cache[$uid])) return $cache[$uid];
        global $wpdb;
        $zero = ['games' => 0, 'wins' => 0, 'points' => 0, 'streak' => 0, 'today' => 0, 'winrate' => 0];
        $t = $wpdb->prefix . 'wordle_lt_scores';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) !== $t) return $cache[$uid] = $zero;

        $agg = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) games, COALESCE(SUM(won),0) wins, COALESCE(SUM(points),0) points
             FROM {$t} WHERE user_id=%d", $uid));
        $today = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$t} WHERE user_id=%d AND DATE(played_at)=CURDATE()", $uid));

        // Geriausia pergalių serija (iš eilės) — skaičiuojam PHP pusėje.
        $streak = 0; $cur = 0;
        foreach ((array) $wpdb->get_col($wpdb->prepare(
            "SELECT won FROM {$t} WHERE user_id=%d ORDER BY played_at ASC LIMIT 2000", $uid)) as $w) {
            if ((int) $w === 1) { $cur++; if ($cur > $streak) $streak = $cur; }
            else $cur = 0;
        }
        $games = $agg ? (int) $agg->games : 0;
        $wins  = $agg ? (int) $agg->wins  : 0;
        return $cache[$uid] = [
            'games'   => $games,
            'wins'    => $wins,
            'points'  => $agg ? (int) $agg->points : 0,
            'streak'  => $streak,
            'today'   => $today,
            'winrate' => $games ? (int) round($wins / $games * 100) : 0,
        ];
    }

    private static function vote_count(int $uid): int {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_euro_votes';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t)) !== $t) return 0;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE user_id=%d", $uid));
    }
}
