<?php
if (!defined('ABSPATH')) exit;

class SS_Maintenance {

    /* ── Prieigos blokavimai (valdomi „Aptarnavimas" puslapyje) ────────
     * ss_maint_lock_site    — blokuoti VISĄ svetainę (išskyrus prisijungimą)
     * ss_maint_block_qr     — laikinai išjungti QR prisijungimą
     * ss_maint_block_google — laikinai išjungti Google prisijungimą
     *
     * SVARBU: visi blokavimai veikia TIK tada, kai įjungtas pats sistemos
     * uždarymas (neribotas arba laiko lange). Sistemai atsidarius (pvz.
     * pasibaigus laikotarpiui) blokavimai automatiškai nustoja galioti. */

    /**
     * Bazinis uždarymo aktyvumas (be prieigos blokavimų):
     * off / neribotas / laiko langas. @return array{active:bool,to_ts:int}
     */
    private static function base_active(): array {
        $now     = time();
        $enabled = (bool) get_option('ss_maint_enabled', 0);
        $type    = get_option('ss_maint_type', 'indefinite');
        $from    = (string) get_option('ss_maint_from', '');
        $to      = (string) get_option('ss_maint_to', '');

        // Tolerantiškas UTC parsavimas: 'Y-m-d H:i:s' arba 'Y-m-d H:i' (0 jei tuščia/bloga).
        $parse = static function (string $s): int {
            $s = trim($s);
            if ($s === '') return 0;
            try { return (new DateTime($s, new DateTimeZone('UTC')))->getTimestamp(); }
            catch (Exception $e) { return 0; }
        };

        $active = false;
        $to_ts  = 0;
        if ($enabled) {
            if ($type === 'indefinite') {
                $active = true;
            } elseif ($type === 'timed') {
                $from_ts = $parse($from);
                $to_ts   = $parse($to);
                if ($to_ts > 0) {
                    // „Nuo" tuščias → langas prasideda iškart (uždaryta IKI nurodyto laiko).
                    $active = ($now >= $from_ts) && ($now <= $to_ts);
                } elseif ($from_ts > 0) {
                    // „Iki" tuščias → uždaryta NUO nurodyto laiko, kol išjungs rankiniu būdu.
                    $active = ($now >= $from_ts);
                }
                if (!$active) $to_ts = 0; // laikmatis rodomas tik kai realiai uždaryta
            }
        }
        return array('active' => $active, 'to_ts' => $to_ts);
    }

    /** Ar įjungtas viso saito blokavimas (tik kai sistema uždaryta)? */
    public static function site_locked(): bool {
        return (bool) get_option('ss_maint_lock_site', 0) && self::base_active()['active'];
    }
    /** Ar QR prisijungimas laikinai užblokuotas (tik kai sistema uždaryta)? */
    public static function qr_blocked(): bool {
        return (bool) get_option('ss_maint_block_qr', 0) && self::base_active()['active'];
    }
    /** Ar Google prisijungimas laikinai užblokuotas (tik kai sistema uždaryta)? */
    public static function google_blocked(): bool {
        return (bool) get_option('ss_maint_block_google', 0) && self::base_active()['active'];
    }

    public static function init(): void {
        // Viso saito blokas turi suveikti ir NE plėtinio puslapiuose (temos,
        // įrašai ir t.t.) — plėtinio puslapiai užsidaro patys per banner partial.
        add_action('template_redirect', [__CLASS__, 'maybe_lock_site'], 2);
    }

    /**
     * Returns the full maintenance status array.
     */
    public static function get_status(): array {
        $base   = self::base_active();
        $active = $base['active'];
        $to_ts  = $base['to_ts'];
        $type   = get_option('ss_maint_type', 'indefinite'); // 'indefinite' | 'timed'
        $from   = get_option('ss_maint_from', '');
        $to     = get_option('ss_maint_to', '');

        $bypass_raw   = get_option('ss_maint_bypass_users', '');
        $bypass_users = $bypass_raw !== ''
            ? array_filter(array_map('intval', explode(',', $bypass_raw)))
            : [];

        return [
            'active'       => $active,
            'site_locked'  => $active && (bool) get_option('ss_maint_lock_site', 0),
            'warning'      => (bool) get_option('ss_maint_warning', 0),
            'warning_text' => (string) get_option('ss_maint_warning_text', ''),
            'reason'       => (string) get_option('ss_maint_reason', ''),
            'type'         => $type,
            'from'         => $from,
            'to'           => $to,
            'to_ts'        => $to_ts,
            'bypass_users' => array_values($bypass_users),
        ];
    }

    /**
     * Is the system currently in maintenance (closed)?
     */
    public static function is_active(): bool {
        return self::get_status()['active'];
    }

    /**
     * Can this user bypass maintenance (i.e. see pages normally)?
     * Admins always bypass. Explicitly listed users also bypass.
     */
    public static function user_can_bypass(int $user_id = 0): bool {
        if (!$user_id) $user_id = get_current_user_id();
        if (user_can($user_id, 'manage_options')) return true;
        $status = self::get_status();
        return in_array($user_id, $status['bypass_users'], true);
    }

    /**
     * Returns the visitor's IP address. Delegates to the single canonical
     * implementation (SS_RateLimit::get_client_ip) which honours the
     * „trust proxy" setting — avoids header-spoofing and code duplication.
     */
    public static function get_ip(): string {
        if (class_exists('SS_RateLimit')) return SS_RateLimit::get_client_ip();
        $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'nežinomas';
    }

    /* ── Viso saito blokavimas (išskyrus prisijungimą) ─────────────────
     * Plėtinio puslapiai uždarymo ekraną rodo patys (banner partial per
     * get_status()['active']). Šis hook'as uždengia VISUS kitus priekinius
     * puslapius (temos puslapiai, įrašai, archyvai…), paliekant atvirą tik
     * prisijungimo puslapį ir wp-login.php (kad administratoriai galėtų
     * prisijungti ir gauti bypass). */
    public static function maybe_lock_site(): void {
        if (!self::site_locked()) return;
        if (is_admin() || wp_doing_ajax()) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        if (defined('REST_REQUEST') && REST_REQUEST) return;
        if (($GLOBALS['pagenow'] ?? '') === 'wp-login.php') return;
        if (is_user_logged_in() && self::user_can_bypass()) return;

        // Plėtinio puslapiai — praleidžiam: prisijungimas turi veikti, o kiti
        // plėtinio puslapiai patys parodo uždarymo ekraną (banner partial).
        $plugin_pages = array_filter(array(
            (int) get_option('ss_login_page_id'),
            (int) get_option('ss_dashboard_page_id'),
            (int) get_option('ss_changes_page_id'),
            (int) get_option('ss_settings_page_id'),
            (int) get_option('ss_imp_page_id'),
            (int) get_option('ss_euro_page_id'),
        ));
        $qid = (int) get_queried_object_id();
        if ($qid && in_array($qid, $plugin_pages, true)) return;

        // Bet kuris kitas priekinis puslapis → savarankiškas uždarymo ekranas.
        if (!headers_sent()) {
            nocache_headers();
            status_header(503);
            header('Content-Type: text/html; charset=utf-8', true);
            header('Retry-After: 600', true);
        }
        echo '<!doctype html><html lang="lt"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width, initial-scale=1">'
           . '<meta name="robots" content="noindex">'
           . '<title>' . esc_html(get_option('ss_school_name', defined('SS_SCHOOL_NAME') ? SS_SCHOOL_NAME : '')) . '</title>';
        // Tas pats firminis šriftas kaip plėtinio puslapiuose (page-wrapper.php) —
        // kitaip „Svetainė uždaryta" ekranas atrodytų kitaip nei „Sistema uždaryta".
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">'
           . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
           . '<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">';
        // Plėtinio stiliai — uždarymo ekrano išvaizdai (overlay klasės iš style.css).
        echo '<link rel="stylesheet" href="' . esc_url(SS_PLUGIN_URL . 'public/css/style.css?ver=' . SS_VERSION) . '">';
        echo '<style>body{margin:0;font-family:\'Google Sans\',sans-serif;}</style>';
        echo '</head><body>';
        // Banner partial: active būsena → atvaizduoja uždarymo ekraną ir exit'ina.
        include SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php';
        // Jei partial'as dėl kokios nors priežasties neišėjo (pvz. bypass) — uždarom švariai.
        echo '</body></html>';
        exit;
    }
}
