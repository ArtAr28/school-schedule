<?php
if (!defined('ABSPATH')) exit;

class SS_Shortcodes {
    public static function init() {
        add_shortcode('ss_login',     array(__CLASS__, 'sc_login'));
        add_shortcode('ss_dashboard', array(__CLASS__, 'sc_dashboard'));
        add_shortcode('ss_changes',   array(__CLASS__, 'sc_changes'));

        add_action('wp_ajax_nopriv_ss_do_login',        array(__CLASS__, 'ajax_login'));
        add_action('wp_ajax_ss_do_login',               array(__CLASS__, 'ajax_login'));
        add_action('wp_ajax_nopriv_ss_login_lock_status', array(__CLASS__, 'ajax_login_lock_status'));
        add_action('wp_ajax_ss_login_lock_status',         array(__CLASS__, 'ajax_login_lock_status'));
        add_action('wp_ajax_ss_do_logout',              array(__CLASS__, 'ajax_logout'));
        add_action('wp_ajax_nopriv_ss_forgot_password', array(__CLASS__, 'ajax_forgot_password'));
        add_action('wp_ajax_ss_forgot_password',        array(__CLASS__, 'ajax_forgot_password'));
        add_action('wp_ajax_nopriv_ss_set_new_password', array(__CLASS__, 'ajax_set_new_password'));
        add_action('wp_ajax_ss_set_new_password',        array(__CLASS__, 'ajax_set_new_password'));
        add_action('wp_ajax_ss_save_personal',          array(__CLASS__, 'ajax_save_personal'));
        add_action('wp_ajax_ss_delete_personal',        array(__CLASS__, 'ajax_delete_personal'));
        add_action('wp_ajax_ss_save_subgroup_pref',     array(__CLASS__, 'ajax_save_subgroup_pref'));
        add_action('wp_ajax_ss_get_teacher_schedule',   array(__CLASS__, 'ajax_get_teacher_schedule'));
        add_action('wp_ajax_ss_get_room_schedule',      array(__CLASS__, 'ajax_get_room_schedule'));
        add_action('wp_ajax_ss_get_class_schedule',     array(__CLASS__, 'ajax_get_class_schedule'));
        add_action('wp_ajax_ss_settings_send_pw_code',   array(__CLASS__, 'ajax_settings_send_pw_code'));
        add_action('wp_ajax_ss_settings_verify_pw_code', array(__CLASS__, 'ajax_settings_verify_pw_code'));
        add_action('wp_ajax_ss_settings_change_pw',      array(__CLASS__, 'ajax_settings_change_pw'));
        add_action('wp_ajax_ss_settings_upload_avatar',  array(__CLASS__, 'ajax_settings_upload_avatar'));
        add_action('wp_ajax_ss_settings_delete_avatar',  array(__CLASS__, 'ajax_settings_delete_avatar'));
        add_action('wp_ajax_ss_settings_save_atspek',    array(__CLASS__, 'ajax_settings_save_atspek'));
        add_action('wp_ajax_ss_settings_save_anon_quiz', array(__CLASS__, 'ajax_settings_save_anon_quiz'));
        add_action('wp_ajax_ss_settings_save_new_device',array(__CLASS__, 'ajax_settings_save_new_device'));
        add_action('wp_ajax_ss_onboarding_finish',       array(__CLASS__, 'ajax_onboarding_finish'));
        add_action('wp_ajax_ss_settings_apply_code',     array(__CLASS__, 'ajax_settings_apply_code'));
        add_action('wp_ajax_ss_settings_check_email',    array(__CLASS__, 'ajax_settings_check_email'));
        add_action('wp_ajax_ss_settings_upload_logo',    array(__CLASS__, 'ajax_settings_upload_logo'));
        add_action('wp_ajax_ss_settings_delete_logo',    array(__CLASS__, 'ajax_settings_delete_logo'));
        add_action('wp_ajax_ss_settings_get_sessions',   array(__CLASS__, 'ajax_settings_get_sessions'));
        add_action('wp_ajax_ss_settings_revoke_session', array(__CLASS__, 'ajax_settings_revoke_session'));
        add_action('wp_ajax_ss_settings_get_devices',    array(__CLASS__, 'ajax_settings_get_devices'));
        add_action('wp_ajax_ss_settings_block_device',   array(__CLASS__, 'ajax_settings_block_device'));
        add_action('wp_ajax_ss_settings_unblock_device', array(__CLASS__, 'ajax_settings_unblock_device'));
        add_action('wp_ajax_nopriv_ss_get_changes_public', array(__CLASS__, 'ajax_get_changes_public'));
        add_action('wp_ajax_ss_get_changes_public',        array(__CLASS__, 'ajax_get_changes_public'));
        add_action('wp_ajax_ss_get_teacher_slot_groups',   array(__CLASS__, 'ajax_get_teacher_slot_groups'));
        add_action('wp_ajax_ss_get_pdf_data',              array(__CLASS__, 'ajax_get_pdf_data'));
        add_action('wp_ajax_ss_get_view_pdf_data',         array(__CLASS__, 'ajax_get_view_pdf_data'));

        // Registracija (visi šie endpoint'ai prieinami ir nepasijungus)
        add_action('wp_ajax_nopriv_ss_register_send_code',   array(__CLASS__, 'ajax_register_send_code'));
        add_action('wp_ajax_ss_register_send_code',          array(__CLASS__, 'ajax_register_send_code'));
        add_action('wp_ajax_nopriv_ss_register_verify_code', array(__CLASS__, 'ajax_register_verify_code'));
        add_action('wp_ajax_ss_register_verify_code',        array(__CLASS__, 'ajax_register_verify_code'));
        add_action('wp_ajax_nopriv_ss_register_submit',      array(__CLASS__, 'ajax_register_submit'));
        add_action('wp_ajax_ss_register_submit',             array(__CLASS__, 'ajax_register_submit'));
        add_action('wp_ajax_nopriv_ss_check_email_link',     array(__CLASS__, 'ajax_check_email_link'));
        add_action('wp_ajax_ss_check_email_link',            array(__CLASS__, 'ajax_check_email_link'));

        // QR kodo prisijungimas
        add_action('wp_ajax_nopriv_ss_qr_create', array(__CLASS__, 'ajax_qr_create'));
        add_action('wp_ajax_ss_qr_create',         array(__CLASS__, 'ajax_qr_create'));
        add_action('wp_ajax_nopriv_ss_qr_poll',   array(__CLASS__, 'ajax_qr_poll'));
        add_action('wp_ajax_ss_qr_poll',           array(__CLASS__, 'ajax_qr_poll'));
        add_action('wp_ajax_nopriv_ss_qr_info',   array(__CLASS__, 'ajax_qr_info'));
        add_action('wp_ajax_ss_qr_info',           array(__CLASS__, 'ajax_qr_info'));
        add_action('wp_ajax_ss_qr_confirm',        array(__CLASS__, 'ajax_qr_confirm'));
        add_action('wp_ajax_ss_qr_reject',         array(__CLASS__, 'ajax_qr_reject'));
        add_action('wp_ajax_ss_settings_toggle_qr_login', array(__CLASS__, 'ajax_settings_toggle_qr_login'));

        // Nonce refresh — used by JS when a cached nonce expires
        add_action('wp_ajax_nopriv_ss_refresh_nonce', array(__CLASS__, 'ajax_refresh_nonce'));
        add_action('wp_ajax_ss_refresh_nonce',         array(__CLASS__, 'ajax_refresh_nonce'));
    }

    public static function sc_login() {
        if (is_user_logged_in()) {
            $ss_qr_tok = sanitize_text_field($_GET['ss_qr'] ?? '');
            if ($ss_qr_tok && get_option('ss_qr_login_enabled', 0) && !SS_Maintenance::qr_blocked()) {
                ob_start();
                include SS_PLUGIN_DIR . 'public/views/qr-confirm.php';
                return ob_get_clean();
            }
            $url = get_permalink(get_option('ss_dashboard_page_id')) ?: home_url();
            return '<p>Jau prisijungėte. <a href="'.esc_url($url).'">Eiti į tvarkaraštį →</a></p>';
        }
        ob_start();
        include SS_PLUGIN_DIR . 'public/views/login.php';
        return ob_get_clean();
    }

    // ── Variable setup (shared between shortcodes & template_redirect) ────────

    public static function setup_changes_vars(): array {
        return [
            'periods'       => SS_Database::get_periods(),
            'active_monday' => SS_Database::get_active_monday(),
        ];
    }

    public static function setup_dashboard_vars(): array {
        $user_id       = get_current_user_id();
        $user          = wp_get_current_user();
        if ($user_id && class_exists('SS_Achievements')) SS_Achievements::bump($user_id, 'sched_open');
        $active_monday = SS_Database::get_active_monday();
        // ── Prieiga pagal TEISES (ne tik pagal rolę). Taip „Rolės ir teisės"
        //    skiltyje individualiai suteiktos plugin teisės iš tikrųjų veikia. ──
        $is_admin_user = current_user_can('manage_options') || current_user_can('ss_manage_all');
        $is_employee   = in_array('ss_employee', (array)$user->roles, true) || current_user_can('ss_employee_access');
        $is_imp        = SS_Database::user_is_imp($user_id) || current_user_can('ss_imp_access');
        // „Matyti visų tvarkaraščius" (Visi tvarkaraščiai): admin, darbuotojas (per rolę)
        // ir bet kuris vartotojas, kuriam ši teisė suteikta individualiai.
        $can_view_all  = $is_admin_user || current_user_can('ss_view_all_schedules');
        // Kabinetų tvarkaraščius mato tie, kas mato visus, arba IMP.
        $can_rooms     = $can_view_all || $is_imp;
        return [
            'user_id'       => $user_id,
            'user'          => $user,
            'sched'         => SS_Database::get_user_full_schedule($user_id),
            'personal'      => SS_Database::get_personal_items($user_id),
            'periods'       => SS_Database::get_periods(),
            'is_imp'        => $is_imp,
            'is_employee'   => $is_employee,
            'can_rooms'     => $can_rooms,
            'can_view_all'  => $can_view_all,
            'all_teachers'  => ($can_view_all || $is_imp) ? SS_Database::get_all_teachers_with_schedule() : array(),
            'all_rooms'     => $can_rooms ? SS_Database::get_all_rooms_with_schedule() : array(),
            'all_classes'   => $can_view_all ? SS_Database::get_all_classes() : array(),
            'active_monday' => $active_monday,
            // Mokiniai/mokytojai mato tik PASKELBTAS pakeitimų dienas; admin — viską (peržiūrai).
            'absent_map'    => SS_Database::get_week_absent_map($active_monday, !$is_admin_user),
            'changes_map'   => SS_Database::get_week_changes_map($active_monday, !$is_admin_user),
            'weekdays'      => SS_Database::get_active_weekdays($active_monday),
        ];
    }

    public static function setup_teacher_dashboard_vars($user): array {
        $user_id     = $user->ID;
        if ($user_id && class_exists('SS_Achievements')) SS_Achievements::bump($user_id, 'sched_open');
        $teacher_rec = self::find_teacher_record($user);
        $sched       = $teacher_rec
            ? SS_Database::get_teacher_full_schedule($teacher_rec->id)
            : array('grid' => array(), 'periods' => SS_Database::get_periods());
        return [
            'user_id'      => $user_id,
            'user'         => $user,
            'teacher_rec'  => $teacher_rec,
            'sched'        => $sched,
            'personal'     => SS_Database::get_personal_items($user_id),
            'periods'      => SS_Database::get_periods(),
            'all_teachers' => SS_Database::get_all_teachers_with_schedule(),
            'all_rooms'    => SS_Database::get_all_rooms_with_schedule(),
            'all_classes'  => SS_Database::get_all_classes(),
        ];
    }

    // ── Shortcodes ───────────────────────────────────────────────────────────

    public static function sc_changes() {
        extract(self::setup_changes_vars(), EXTR_SKIP);
        ob_start();
        include SS_PLUGIN_DIR . 'public/views/changes.php';
        return ob_get_clean();
    }

    public static function sc_dashboard() {
        if (!is_user_logged_in()) {
            $url = get_permalink(get_option('ss_login_page_id')) ?: wp_login_url(get_permalink());
            return '<p>Prašome <a href="'.esc_url($url).'">prisijungti</a>.</p>';
        }
        $user = wp_get_current_user();
        if (in_array('ss_teacher', (array)$user->roles, true)) {
            return self::teacher_dashboard($user->ID, $user);
        }
        extract(self::setup_dashboard_vars(), EXTR_SKIP);
        ob_start();
        include SS_PLUGIN_DIR . 'public/views/dashboard.php';
        return ob_get_clean();
    }

    private static function teacher_dashboard($user_id, $user) {
        extract(self::setup_teacher_dashboard_vars($user), EXTR_SKIP);
        ob_start();
        include SS_PLUGIN_DIR . 'public/views/teacher_dashboard.php';
        return ob_get_clean();
    }

    private static function find_teacher_record($user) {
        global $wpdb;
        // 1) Persistent link by WP user id (set via „Mokytojų paskyros")
        $rec = SS_Database::get_teacher_by_user_id($user->ID);
        if ($rec) return $rec;
        // 2) Fallback: exact display_name match
        $name = $user->display_name;
        $rec  = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_teachers WHERE name=%s", $name
        ));
        if ($rec) return $rec;
        // 3) Fallback: email match
        if ($user->user_email) {
            $rec = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ss_teachers WHERE email=%s", $user->user_email
            ));
        }
        return $rec;
    }

    /* ─── Nonce refresh (used when page is cached and nonce has expired) ─── */
    public static function ajax_refresh_nonce() {
        wp_send_json_success(array(
            'login_nonce'  => wp_create_nonce('ss_login_nonce'),
            'front_nonce'  => wp_create_nonce('ss_front_nonce'),
            'logout_nonce' => wp_create_nonce('ss_logout_nonce'),
        ));
    }

    /* ─── Login ─── */
    /**
     * Grąžina, ar šis įrenginys šiuo metu užblokuotas, ir kiek liko laiko.
     * Tik skaitymas (be šalutinių poveikių) — leidžia prisijungimo formai
     * parodyti laikmatį iškart įkėlus puslapį (net be localStorage).
     */
    public static function ajax_login_lock_status() {
        $ip        = SS_RateLimit::get_client_ip();
        $username  = sanitize_user($_REQUEST['username'] ?? '');
        $remaining = SS_RateLimit::lock_remaining($ip, $username);
        wp_send_json_success(array(
            'locked'      => $remaining > 0,
            'retry_after' => $remaining,
        ));
    }

    public static function ajax_login() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ss_login_nonce')) {
            wp_send_json_error(array('message' => 'Sesija pasibaigė. Perkraukite puslapį ir bandykite dar kartą.', 'code' => 'expired_nonce'));
            return;
        }

        // SS-04 + 1.2: Honeypot — jei suveikė, grąžinti apsimetimą sėkme
        if (!empty($_POST['website'])) {
            wp_send_json_success(array('redirect' => home_url('/')));
            return;
        }

        $ip       = SS_RateLimit::get_client_ip();
        $username = sanitize_user($_POST['username'] ?? '');

        // Apsauga nuo botų (Cloudflare Turnstile) — jei valdiklis įjungtas login
        // formoje, žetonas privalomas PRIEŠ bet kokį brangų darbą / wp_signon.
        if (class_exists('SS_Turnstile') && SS_Turnstile::is_enabled()
            && SS_Turnstile::get('ss_ts_login_widget')) {
            if (!SS_Turnstile::verify((string)($_POST['ts_token'] ?? ''), $ip, 'login')) {
                wp_send_json_error(array(
                    'message' => 'Nepavyko patvirtinti, kad esate žmogus. Bandykite dar kartą.',
                    'code'    => 'turnstile_failed',
                ));
                return;
            }
        }

        // Apsauga nuo kenksmingų / per ilgų įvesčių per „prisijungimo eilutę"
        // (anti-DoS ir anti-injekcija) PRIEŠ bet kokį brangų darbą:
        //  • tuščias vardas ⇒ prisijungti neįmanoma;
        //  • per ilgas slaptažodis ⇒ brangus maišos skaičiavimas (DoS rizika);
        //  • valdymo simboliai / NUL varde ⇒ tipiškas injekcijos / anomalijos požymis.
        // sanitize_user jau nuėmė <>"'% ir pan., todėl SQL/HTML injekcija neperduodama.
        $u_raw = (string) wp_unslash($_POST['username'] ?? '');
        $p_raw = (string) ($_POST['password'] ?? '');
        if ($username === '' || strlen($u_raw) > 100 || strlen($p_raw) > 200
            || preg_match('/[\x00-\x1F\x7F]/', $u_raw)) {
            SS_RateLimit::record_failure($ip, $username);
            SS_AuditLog::write('login_failed', array('username' => mb_substr($username, 0, 60), 'ip' => $ip, 'reason' => 'invalid_input'));
            wp_send_json_error(array('message' => 'Neteisingas vartotojo vardas arba slaptažodis.'));
            return;
        }

        // SS-04 + 1.1: Tikrinti rate limit prieš bandant prisijungti
        $block = SS_RateLimit::check_login($ip, $username);
        if ($block) {
            SS_AuditLog::write('login_blocked', array('username' => $username, 'ip' => $ip, 'reason' => $block['reason']));
            wp_send_json_error(array(
                'message'     => 'Per daug nesėkmingų bandymų. Prisijungimas prie šios paskyros iš šio įrenginio laikinai užblokuotas.',
                'code'        => 'rate_limited',
                'retry_after' => max(0, (int) $block['retry_after']),
            ));
            return;
        }

        $user = wp_signon(array(
            'user_login'    => $username,
            'user_password' => $_POST['password'] ?? '',
            'remember'      => !empty($_POST['remember']),
        ), is_ssl());

        // Jei įprastas slaptažodis neteisingas — pabandome LAIKINĄ slaptažodį
        // (išsiųstą per „Pamiršau slaptažodį"). Galioja ir senasis, ir laikinas.
        if (is_wp_error($user)) {
            $target = get_user_by('login', $username) ?: get_user_by('email', $username);
            if ($target && self::check_temp_password($target->ID, (string)($_POST['password'] ?? ''))) {
                $user = $target;
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, !empty($_POST['remember']), is_ssl());
            }
        }

        if (is_wp_error($user)) {
            $fail_data = SS_RateLimit::record_failure($ip, $username);
            SS_AuditLog::write('login_failed', array('username' => $username, 'ip' => $ip));

            // IDS: perspėjimas apie credential stuffing
            SS_AuditLog::maybe_alert_admin($ip);

            // 1.3: Perspėjimas vartotojui po N nesėkmingų bandymų (konfigūruojama)
            $alert_after = max(1, (int) SS_Security::get('ss_sec_login_alert_threshold'));
            if (($fail_data['fails'] ?? 0) === $alert_after && SS_Security::get('ss_sec_login_alert_enabled')) {
                $target = get_user_by('login', $username) ?: get_user_by('email', $username);
                if ($target && class_exists('SS_Mail')) {
                    $ctx_html = class_exists('SS_AuditLog')
                        ? SS_AuditLog::context_email_html($ip)
                        : '<p style="margin:0;">IP: <strong>' . esc_html($ip) . '</strong></p>';
                    SS_Mail::send($target->user_email, 'Pastebėta įtartina veikla', array(
                        'title'     => 'Įspėjimas apie saugumą',
                        'icon'      => '!',
                        'body'      => '<p style="margin:0 0 4px;">Fiksuojami pakartotiniai nesėkmingi bandymai prisijungti prie Jūsų paskyros. Bandymo informacija:</p>',
                        'extra'     => $ctx_html,
                        'note'      => 'Jei bandėte prisijungti ne Jūs — rekomenduojame pakeisti slaptažodį ir įjungti 2FA.',
                        'note_type' => 'warn',
                    ));
                }
            }

            // Jei ŠIS bandymas ką tik įjungė įrenginio bloką — aiškiai pranešam ir žurnaluojam.
            if (!empty($fail_data['locked']) && (int)($fail_data['retry_after'] ?? 0) > 0) {
                $wait = (int) ceil((int)$fail_data['retry_after'] / 60);
                SS_AuditLog::write('login_blocked', array('username' => $username, 'ip' => $ip, 'reason' => 'device', 'minutes' => $wait));
                wp_send_json_error(array(
                    'message'     => "Per daug nesėkmingų bandymų. Prisijungimas prie šios paskyros iš šio įrenginio užblokuotas {$wait} min.",
                    'code'        => 'rate_limited',
                    'retry_after' => max(0, (int)$fail_data['retry_after']),
                ));
            }

            // Kitu atveju — įspėjam apie likusius bandymus prieš bloką (kai jau arti slenksčio).
            $remaining = (int)($fail_data['remaining_attempts'] ?? 0);
            $msg = 'Neteisingas vartotojo vardas arba slaptažodis.';
            if ($remaining >= 1 && $remaining <= 2) {
                $msg .= ' Liko bandymų iki laikino blokavimo: ' . $remaining . '.';
            }
            wp_send_json_error(array('message' => $msg));
        }

        // Prisijungimas sėkmingas
        SS_RateLimit::clear_on_success($ip, $username);

        // Patikrinti, ar įrenginys užblokuotas šio vartotojo
        if (SS_AuditLog::is_device_blocked($user->ID, $ip)) {
            SS_AuditLog::write('login_blocked', ['username' => $username, 'ip' => $ip, 'user_id' => $user->ID, 'reason' => 'device_blocked']);
            wp_logout(); // Panaikinti sesiją, kurią sukūrė wp_signon
            wp_send_json_error(array(
                'message' => 'Šis įrenginys yra užblokuotas. Atblokuokite jį prisijungę iš kito įrenginio.',
                'code'    => 'device_blocked',
            ));
            return;
        }

        // 2FA patikrinimas
        if (SS_2FA::is_enabled($user->ID)) {
            wp_clear_auth_cookie(); // Nutraukti sesiją, kurią nustatė wp_signon
            $redirect = get_permalink(get_option('ss_dashboard_page_id')) ?: home_url('/');
            $fa_token = SS_2FA::create_pending($user->ID, !empty($_POST['remember']), 'login', $redirect);
            wp_send_json_success(array('status' => '2fa_required', 'token' => $fa_token));
            return;
        }

        // Priverstinis slaptažodžio keitimas (po „Pamiršau slaptažodį").
        // 2FA vartotojams tai tikrinama po 2FA (SS_2FA::ajax_verify_pending).
        if (get_user_meta($user->ID, 'ss_must_change_pw', true)) {
            self::start_pw_change($user->ID, !empty($_POST['remember'])); // wp_send_json → exit
            return;
        }

        // 7.1: Sesijų limitas
        // KLAIDA: WP_Session_Tokens::get_all() naudoja array_values(), todėl praranda
        // sha256 hash raktus. usort() dar kartą pernumeruoja, todėl array_key_first()
        // grąžina 0 (skaičių), o ne sesijos hash'ą — destroy(0) nieko nepanaikina.
        // Taisymas: skaitome user meta tiesiogiai (kaip ajax_settings_get_sessions),
        // naudojame uasort() raktams išsaugoti ir triname tiesiai iš meta.
        $max_sessions = (int) SS_Security::get('ss_sec_max_sessions');
        if ($max_sessions > 0) {
            $raw_sessions = (array) get_user_meta($user->ID, 'session_tokens', true);
            $now          = time();
            $valid        = array_filter($raw_sessions, static fn($s) =>
                is_array($s) && (empty($s['expiration']) || $s['expiration'] > $now)
            );
            if (count($valid) >= $max_sessions) {
                uasort($valid, static fn($a, $b) => ($a['login'] ?? 0) - ($b['login'] ?? 0));
                $oldest_hash = array_key_first($valid);
                if ($oldest_hash !== null) {
                    unset($raw_sessions[$oldest_hash]);
                    update_user_meta($user->ID, 'session_tokens', $raw_sessions);
                    SS_AuditLog::write('session_kicked', [
                        'user_id' => $user->ID,
                        'ip'      => $ip,
                        'reason'  => 'max_sessions',
                    ]);
                }
            }
        }

        SS_AuditLog::write('login_ok', array('user_id' => $user->ID, 'ip' => $ip, 'method' => 'password'));
        SS_AuditLog::check_new_device($user->ID, $ip, 'password');
        wp_send_json_success(array('redirect' => get_permalink(get_option('ss_dashboard_page_id')) ?: home_url()));
    }

    /* ─── Logout ─── */
    public static function ajax_logout() {
        check_ajax_referer('ss_logout_nonce', 'nonce');
        $uid = get_current_user_id();
        wp_logout();
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('logout', ['user_id' => $uid]);
        }
        wp_send_json_success(array('redirect' => get_permalink(get_option('ss_login_page_id')) ?: home_url()));
    }

    /* ─── Forgot password → laikinas slaptažodis ─── */
    public static function ajax_forgot_password() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ss_login_nonce')) {
            wp_send_json_error(array('message' => 'Sesija pasibaigė. Perkraukite puslapį ir bandykite dar kartą.', 'code' => 'expired_nonce'));
            return;
        }
        $ip = SS_RateLimit::get_client_ip();

        // Apsauga nuo botų — Turnstile prieš siunčiant el. laišką.
        if (class_exists('SS_Turnstile') && SS_Turnstile::is_enabled()
            && SS_Turnstile::get('ss_ts_login_widget')) {
            if (!SS_Turnstile::verify((string)($_POST['ts_token'] ?? ''), $ip, 'forgot')) {
                wp_send_json_error(array('message' => 'Nepavyko patvirtinti, kad esate žmogus. Bandykite dar kartą.'));
            }
        }

        // Rate limit: max 5 reset requests per IP per hour (anti-spam / enumeration).
        if (!SS_RateLimit::hit('forgot_' . $ip, 5, HOUR_IN_SECONDS)) {
            wp_send_json_error(array('message' => 'Per daug bandymų. Bandykite vėliau.'));
        }
        $input = sanitize_text_field($_POST['login'] ?? '');
        if (!$input) wp_send_json_error(array('message' => 'Įveskite el. paštą arba vartotojo vardą.'));
        $user = strpos($input,'@') !== false ? get_user_by('email',$input) : get_user_by('login',$input);
        // Neatskleidžiam, ar vartotojas egzistuoja (anti-enumeration).
        if (!$user) {
            wp_send_json_success(array('message' => 'Jei tokia paskyra egzistuoja, laikinas slaptažodis išsiųstas el. paštu.'));
        }

        self::issue_temp_password($user);

        $obf = mb_substr($user->user_email,0,2) . str_repeat('*',max(2,mb_strlen(explode('@',$user->user_email)[0])-2)) . '@' . (explode('@',$user->user_email)[1]??'');
        wp_send_json_success(array('message' => "Laikinas slaptažodis išsiųstas: {$obf}"));
    }

    /* ══ Laikinas slaptažodis + priverstinis keitimas ══════════════════
       Srautas: „Pamiršau slaptažodį" → sugeneruojamas laikinas slaptažodis,
       išsiunčiamas el. paštu. Prisijungti galima SU LAIKINU ARBA SENU
       slaptažodžiu; po prisijungimo priverstinai prašoma nustatyti naują.
    */

    /** Sugeneruoja laikiną slaptažodį, išsaugo maišą + galiojimą ir išsiunčia. */
    private static function issue_temp_password(WP_User $user): void {
        // Lengvai perrašomas laikinas slaptažodis (be painių simbolių).
        $temp = wp_generate_password(10, false, false);
        update_user_meta($user->ID, 'ss_temp_pw_hash', wp_hash_password($temp));
        update_user_meta($user->ID, 'ss_temp_pw_exp',  time() + DAY_IN_SECONDS);
        update_user_meta($user->ID, 'ss_must_change_pw', 1);

        $site = SS_Mail::school();
        SS_Mail::send($user->user_email, "{$site} — Laikinas slaptažodis", array(
            'title'    => 'Laikinas slaptažodis',
            'subtitle' => 'Prisijunkite ir nustatykite naują slaptažodį',
            'icon'     => '🔑',
            'body'     => '<p style="margin:0 0 14px;">Sveiki, <strong>' . esc_html($user->display_name) . '</strong>,</p>'
                        . '<p style="margin:0 0 14px;">Gavome prašymą atkurti slaptažodį. Prisijunkite naudodami šį <strong>laikiną slaptažodį</strong> (galioja ir senasis). Prisijungę turėsite nustatyti naują slaptažodį.</p>',
            'extra'    => SS_Mail::code_box($temp, SS_Time::date('Y-m-d H:i', time() + DAY_IN_SECONDS)),
            'note'     => 'Laikinas slaptažodis galioja <strong>24 valandas</strong>. Jei prašėte ne Jūs — tiesiog ignoruokite laišką, senasis slaptažodis liks galioti.',
            'note_type'=> 'warn',
        ));
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('password_reset', array('user_id' => $user->ID, 'ip' => SS_RateLimit::get_client_ip(), 'method' => 'temp_pw'));
        }
    }

    /** Ar pateiktas slaptažodis atitinka galiojantį laikiną slaptažodį? */
    public static function check_temp_password(int $user_id, string $pw): bool {
        if ($pw === '') return false;
        $hash = (string) get_user_meta($user_id, 'ss_temp_pw_hash', true);
        $exp  = (int) get_user_meta($user_id, 'ss_temp_pw_exp', true);
        if ($hash === '' || $exp < time()) return false;
        return wp_check_password($pw, $hash, $user_id);
    }

    private static function clear_temp_password(int $user_id): void {
        delete_user_meta($user_id, 'ss_temp_pw_hash');
        delete_user_meta($user_id, 'ss_temp_pw_exp');
    }

    /**
     * Pradeda priverstinį slaptažodžio keitimą: nutraukia sesiją, sukuria
     * trumpalaikį žetoną ir grąžina statusą „must_change_pw". Iškviečia
     * wp_send_json_success (nebegrįžta).
     */
    public static function start_pw_change(int $user_id, bool $remember): void {
        wp_clear_auth_cookie(); // slaptažodis dar nepakeistas — jokios veikiančios sesijos
        $token = wp_generate_password(32, false);
        set_transient('ss_pwchg_' . $token, array('user_id' => $user_id, 'remember' => $remember ? 1 : 0), 15 * MINUTE_IN_SECONDS);
        wp_send_json_success(array('status' => 'must_change_pw', 'token' => $token));
    }

    /** Nustato naują slaptažodį pagal „must_change_pw" žetoną ir prijungia. */
    public static function ajax_set_new_password() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $data  = $token ? get_transient('ss_pwchg_' . $token) : false;
        if (!is_array($data) || empty($data['user_id'])) {
            wp_send_json_error(array('message' => 'Sesija pasibaigė. Prisijunkite iš naujo.', 'code' => 'expired'));
        }
        $user_id = (int) $data['user_id'];
        $user    = get_user_by('id', $user_id);
        if (!$user) wp_send_json_error(array('message' => 'Paskyra nerasta.'));

        $pw1 = (string) ($_POST['password']  ?? '');
        $pw2 = (string) ($_POST['password2'] ?? '');
        if ($pw1 !== $pw2) wp_send_json_error(array('message' => 'Slaptažodžiai nesutampa.'));
        $err = SS_Security::validate_password($pw1, $user->user_email);
        if ($err) wp_send_json_error(array('message' => $err));

        // Naujas slaptažodis negali sutapti su laikinu (kad tikrai pasikeistų).
        if (self::check_temp_password($user_id, $pw1)) {
            wp_send_json_error(array('message' => 'Naujas slaptažodis negali sutapti su laikinu.'));
        }

        wp_set_password($pw1, $user_id);            // panaikina visas sesijas
        self::clear_temp_password($user_id);
        delete_user_meta($user_id, 'ss_must_change_pw');
        delete_transient('ss_pwchg_' . $token);

        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('password_changed', array('user_id' => $user_id, 'ip' => SS_RateLimit::get_client_ip(), 'method' => 'forced'));
        }

        // Prijungiam su nauju slaptažodžiu.
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, !empty($data['remember']), is_ssl());
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('login_ok', array('user_id' => $user_id, 'ip' => SS_RateLimit::get_client_ip(), 'method' => 'pw_change'));
        }
        wp_send_json_success(array('redirect' => get_permalink(get_option('ss_dashboard_page_id')) ?: home_url()));
    }

    /* ─── Personal items ─── */
    public static function ajax_save_personal() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Neprisijungta.'));
        $user_id = get_current_user_id();
        $id = intval($_POST['id'] ?? 0);
        if ($id) {
            global $wpdb;
            $owner = (int)$wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}ss_personal_items WHERE id=%d",$id));
            if ($owner !== $user_id) wp_send_json_error(array('message'=>'Neturite teisių.'));
        }
        $new_id = SS_Database::save_personal_item(array(
            'id'=>$id,'user_id'=>$user_id,'day_of_week'=>intval($_POST['day_of_week']??1),
            'start_time'=>sanitize_text_field($_POST['start_time']??''),
            'end_time'=>sanitize_text_field($_POST['end_time']??''),
            'subject'=>sanitize_text_field($_POST['subject']??''),
            'notes'=>sanitize_textarea_field($_POST['notes']??''),
            'color'=>sanitize_hex_color($_POST['color']??'')?:'#4F8952',
        ));
        wp_send_json_success(array('id'=>$new_id));
    }

    public static function ajax_delete_personal() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error();
        $id = intval($_POST['id']??0);
        global $wpdb;
        $owner = (int)$wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}ss_personal_items WHERE id=%d",$id));
        if ($owner !== get_current_user_id()) wp_send_json_error(array('message'=>'Neturite teisių.'));
        SS_Database::delete_personal_item($id);
        wp_send_json_success();
    }

    /* ─── Subgroup pref ─── */
    public static function ajax_save_subgroup_pref() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error();
        SS_Database::save_user_subgroup_pref(
            get_current_user_id(),
            intval($_POST['class_id']   ?? 0),
            intval($_POST['day']        ?? 1),
            intval($_POST['period_id']  ?? 0),
            intval($_POST['schedule_id']?? 0)
        );
        wp_send_json_success();
    }

    /* ─── Get teacher schedule ─── */
    public static function ajax_get_teacher_schedule() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Neprisijungta.'));

        $user_id    = get_current_user_id();
        $user       = wp_get_current_user();
        $is_teacher = in_array('ss_teacher', (array)$user->roles);
        $is_admin   = current_user_can('manage_options') || current_user_can('ss_manage_all');
        $is_employee= in_array('ss_employee', (array)$user->roles) || current_user_can('ss_employee_access');

        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        if (!$teacher_id) wp_send_json_error(array('message' => 'Neteisingas mokytojas.'));

        $teacher = SS_Database::get_teacher($teacher_id);
        if (!$teacher) wp_send_json_error(array('message' => 'Mokytojas nerastas.'));

        $viewer = 'student';

        if ($is_admin || $is_employee || current_user_can('ss_view_imp_students')) {
            // Administratorius / darbuotojas / turintis teisę „matyti IMP mokinių
            // sąrašus" mato IMP sąrašų mygtuką prie bet kurio mokytojo.
            $viewer = 'teacher';
        } elseif ($is_teacher) {
            // Mokytojas gali matyti BET KOKIO mokytojo tvarkaraštį,
            // bet mokinių sąrašus mato tik savo pamokose.
            $own = self::find_teacher_record($user);
            $viewer = ($own && (int)$own->id === $teacher_id) ? 'teacher' : 'student';
        } else {
            // Mokinys: leidžiama, jei yra IMP klasėje ARBA turi teisę „matyti visų
            // tvarkaraščius" / IMP prieigą (suteiktą „Rolės ir teisės" skiltyje).
            $can = SS_Database::user_is_imp($user_id)
                || current_user_can('ss_view_all_schedules')
                || current_user_can('ss_imp_access');
            if (!$can) {
                wp_send_json_error(array('message' => 'Neturite teisės.'));
            }
        }

        $sched = SS_Database::get_teacher_full_schedule($teacher_id);
        wp_send_json_success(array(
            'teacher' => $teacher,
            'grid'    => $sched['grid'],
            'periods' => $sched['periods'],
            'viewer'  => $viewer,
        ));
    }

    /* ─── Get room schedule (kabineto tvarkaraštis) ─── */
    public static function ajax_get_room_schedule() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Neprisijungta.'));

        $user_id = get_current_user_id();
        $user    = wp_get_current_user();
        $roles   = (array)$user->roles;

        // Visible to: teachers, employees (darbuotojai), admin, and IMP students only.
        $allowed = current_user_can('manage_options')
                || current_user_can('ss_view_all_schedules')
                || in_array('ss_teacher', $roles, true)
                || in_array('ss_employee', $roles, true)
                || SS_Database::user_is_imp($user_id)
                || current_user_can('ss_imp_access');
        if (!$allowed) {
            wp_send_json_error(array('message' => 'Neturite teisės.'));
        }

        $room_id = (int)($_POST['room_id'] ?? 0);
        if (!$room_id) wp_send_json_error(array('message' => 'Neteisingas kabinetas.'));

        $room = SS_Database::get_room($room_id);
        if (!$room) wp_send_json_error(array('message' => 'Kabinetas nerastas.'));

        $sched = SS_Database::get_room_full_schedule($room_id);
        wp_send_json_success(array(
            'room'    => $room,
            'grid'    => $sched['grid'],
            'periods' => $sched['periods'],
        ));
    }

    /* ─── Get teacher slot groups (mokytojo IMP mokinių sąrašas) ─── */
    public static function ajax_get_teacher_slot_groups() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Neprisijungta.'));

        $user      = wp_get_current_user();
        $is_admin  = current_user_can('manage_options') || current_user_can('ss_manage_all');
        $is_teacher= in_array('ss_teacher', (array)$user->roles);
        $is_employee = in_array('ss_employee', (array)$user->roles) || current_user_can('ss_employee_access');
        // Teisė „matyti IMP mokinių sąrašus" leidžia žiūrėti bet kurio mokytojo sąrašą.
        $can_imp_list = current_user_can('ss_view_imp_students');
        $is_staff  = $is_admin || $is_employee || $can_imp_list;
        if (!$is_staff && !$is_teacher) {
            wp_send_json_error(array('message' => 'Neturite teisės.'));
        }

        $day        = (int)($_POST['day']        ?? 0);
        $period     = (int)($_POST['period']     ?? 0);
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        if (!$day || !$period) wp_send_json_error(array('message' => 'Neteisingi duomenys.'));

        // Choose which teacher's slot we're inspecting:
        //  - Admin / darbuotojas / „matyti IMP sąrašus": any teacher (must be passed)
        //  - Teacher: only themselves (mokinių sąrašas — tik savose pamokose)
        $target_id = 0;
        if ($is_staff && $teacher_id > 0) {
            $target_id = $teacher_id;
        } else {
            $rec = self::find_teacher_record($user);
            if (!$rec) wp_send_json_error(array('message' => 'Jūsų mokytojo įrašas nerastas.'));
            $target_id = (int)$rec->id;
        }

        $groups = SS_Database::get_teacher_slot_groups($target_id, $day, $period);
        wp_send_json_success(array('groups' => $groups));
    }

    /* ─── PDF DATA (current user's schedule) ─── */
    public static function ajax_get_pdf_data() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Neprisijungta.'));

        $user       = wp_get_current_user();
        $user_id    = $user->ID;
        $is_teacher = in_array('ss_teacher', (array)$user->roles);
        $is_admin   = current_user_can('manage_options');
        $school     = stripslashes(get_option('ss_school_name', SS_SCHOOL_NAME));

        // Schedule title and fonts for PDF
        $schedule_title = stripslashes(get_option('ss_schedule_title', 'Savaitės tvarkaraštis'));
        $fonts_dir      = SS_PLUGIN_DIR . 'public/fonts/';
        $font_reg_b64   = null;
        $font_bold_b64  = null;
        if (file_exists($fonts_dir . 'NotoSans-Regular.ttf')) {
            $font_reg_b64  = base64_encode(file_get_contents($fonts_dir . 'NotoSans-Regular.ttf'));
        }
        if (file_exists($fonts_dir . 'NotoSans-Bold.ttf')) {
            $font_bold_b64 = base64_encode(file_get_contents($fonts_dir . 'NotoSans-Bold.ttf'));
        }

        // Logo for PDF header
        $logo       = SS_Admin::get_logo_for_pdf();
        $logo_b64   = $logo ? $logo['b64']  : null;
        $logo_mime  = $logo ? $logo['mime'] : null;
        $username   = $user->display_name;
        // Personal items — woven into the PDF schedule too.
        $personal   = SS_Database::get_personal_items($user_id);

        if ($is_teacher || $is_admin) {
            $teacher_rec = self::find_teacher_record($user);
            if (!$teacher_rec) {
                // Adminas be teacher įrašo — pereina į student režimą jei jam priskirtos klasės.
                $sched = SS_Database::get_user_full_schedule($user_id);
                wp_send_json_success(array(
                    'mode'           => 'student',
                    'school'         => $school,
                    'schedule_title' => $schedule_title,
                    'name'           => $user->display_name,
                    'username'       => $username,
                    'logo_b64'       => $logo_b64,
                    'logo_mime'      => $logo_mime,
                    'font_reg_b64'   => $font_reg_b64,
                    'font_bold_b64'  => $font_bold_b64,
                    'periods'        => $sched['periods'],
                    'grid'           => $sched['grid'],
                    'subgroup_prefs' => SS_Database::get_user_subgroup_prefs($user_id),
                    'personal'       => $personal,
                ));
            }
            $sched = SS_Database::get_teacher_pdf_grid((int)$teacher_rec->id);
            wp_send_json_success(array(
                'mode'           => 'teacher',
                'school'         => $school,
                'schedule_title' => $schedule_title,
                'name'           => $teacher_rec->name ?: $user->display_name,
                'username'       => $username,
                'logo_b64'       => $logo_b64,
                'logo_mime'      => $logo_mime,
                'font_reg_b64'   => $font_reg_b64,
                'font_bold_b64'  => $font_bold_b64,
                'periods'        => $sched['periods'],
                'grid'           => $sched['grid'],
                'personal'       => $personal,
                'can_imp_lists'  => true, // savas tvarkaraštis — mato savo mokinių sąrašus
            ));
        }

        // Mokinys: paprastas tvarkaraštis.
        $sched = SS_Database::get_user_full_schedule($user_id);
        wp_send_json_success(array(
            'mode'           => 'student',
            'school'         => $school,
            'schedule_title' => $schedule_title,
            'name'           => $user->display_name,
            'username'       => $username,
            'logo_b64'       => $logo_b64,
            'logo_mime'      => $logo_mime,
            'font_reg_b64'   => $font_reg_b64,
            'font_bold_b64'  => $font_bold_b64,
            'periods'        => $sched['periods'],
            'grid'           => $sched['grid'],
            'subgroup_prefs' => SS_Database::get_user_subgroup_prefs($user_id),
            'personal'       => $personal,
        ));
    }

    /* ─── PDF DATA: atverto tvarkaraščio (mokytojo/klasės/kabineto) ───
       Tas pats formatas kaip ajax_get_pdf_data, todėl frontas naudoja tą patį
       parinkčių modalą ir spausdinimo srautą. Teisės — kaip peržiūros endpoint'ų.
       IMP sąrašai: pilni vardai grąžinami TIK jei žiūrintysis turi mokytojo lygio
       prieigą prie šio tvarkaraščio (kitaip — tik skaičius). */
    public static function ajax_get_view_pdf_data() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Neprisijungta.'));

        $kind = sanitize_key($_POST['kind'] ?? '');
        $id   = (int)($_POST['id'] ?? 0);
        if (!$id || !in_array($kind, array('teacher','class','room'), true)) {
            wp_send_json_error(array('message' => 'Neteisingi duomenys.'));
        }

        $user_id    = get_current_user_id();
        $user       = wp_get_current_user();
        $roles      = (array)$user->roles;
        $is_teacher = in_array('ss_teacher', $roles, true);
        $is_admin   = current_user_can('manage_options') || current_user_can('ss_manage_all');
        $is_employee= in_array('ss_employee', $roles, true) || current_user_can('ss_employee_access');

        $school         = stripslashes(get_option('ss_school_name', SS_SCHOOL_NAME));
        $schedule_title = stripslashes(get_option('ss_schedule_title', 'Savaitės tvarkaraštis'));
        $logo           = SS_Admin::get_logo_for_pdf();

        $out = array(
            'school'         => $school,
            'schedule_title' => $schedule_title,
            'logo_b64'       => $logo ? $logo['b64']  : null,
            'logo_mime'      => $logo ? $logo['mime'] : null,
            'personal'       => array(),   // svetimame tvarkaraštyje asmeninių įrašų nerodome
            'subgroup_prefs' => array(),
        );

        if ($kind === 'teacher') {
            $teacher = SS_Database::get_teacher($id);
            if (!$teacher) wp_send_json_error(array('message' => 'Mokytojas nerastas.'));

            // Teisės — veidrodis ajax_get_teacher_schedule logikos.
            $viewer = 'student';
            if ($is_admin || $is_employee || current_user_can('ss_view_imp_students')) {
                $viewer = 'teacher';
            } elseif ($is_teacher) {
                $own    = self::find_teacher_record($user);
                $viewer = ($own && (int)$own->id === $id) ? 'teacher' : 'student';
            } else {
                $can = SS_Database::user_is_imp($user_id)
                    || current_user_can('ss_view_all_schedules')
                    || current_user_can('ss_imp_access');
                if (!$can) wp_send_json_error(array('message' => 'Neturite teisės.'));
            }

            $sched = SS_Database::get_teacher_pdf_grid($id);
            $grid  = $sched['grid'];
            // Be mokytojo lygio prieigos — vardų NEatiduodame (tik skaičių).
            if ($viewer !== 'teacher') {
                foreach ($grid as &$days_col) {
                    foreach ($days_col as &$entries) {
                        foreach ($entries as &$e) { $e['student_names'] = ''; $e['class_label'] = ''; }
                    }
                }
                unset($days_col, $entries, $e);
            }
            $out['mode']          = 'teacher';
            $out['name']          = $teacher->name;
            $out['periods']       = $sched['periods'];
            $out['grid']          = $grid;
            $out['can_imp_lists'] = ($viewer === 'teacher');
            wp_send_json_success($out);
        }

        if ($kind === 'class') {
            // Kaip ajax_get_class_schedule — pakanka būti prisijungusiam.
            $class_info = SS_Database::get_class($id);
            if (!$class_info) wp_send_json_error(array('message' => 'Klasė nerasta.'));
            $sched = SS_Database::get_class_schedule($id);
            // Klasės tinklelis yra grid[diena][pamoka] — PDF srautas naudoja
            // grid[pamoka][diena], todėl apverčiam.
            $grid = array();
            foreach ((array)$sched['grid'] as $day => $by_period) {
                foreach ((array)$by_period as $pid => $entries) {
                    $grid[$pid][$day] = $entries;
                }
            }
            $out['mode']    = 'class';
            $out['name']    = $class_info->name;
            $out['periods'] = $sched['periods'];
            $out['grid']    = $grid;
            wp_send_json_success($out);
        }

        // kind === 'room' — teisės kaip ajax_get_room_schedule.
        $allowed = current_user_can('manage_options')
                || current_user_can('ss_view_all_schedules')
                || $is_teacher || $is_employee
                || SS_Database::user_is_imp($user_id)
                || current_user_can('ss_imp_access');
        if (!$allowed) wp_send_json_error(array('message' => 'Neturite teisės.'));
        $room = SS_Database::get_room($id);
        if (!$room) wp_send_json_error(array('message' => 'Kabinetas nerastas.'));
        $sched = SS_Database::get_room_full_schedule($id);
        $out['mode']    = 'room';
        $out['name']    = $room->name;
        $out['periods'] = $sched['periods'];
        $out['grid']    = $sched['grid'];
        wp_send_json_success($out);
    }

    /* ─── Public changes feed ─── */
    public static function ajax_get_changes_public() {
        // Default: same day the dashboard banner is showing (matches
        // 16:00 / Friday / weekend rollover so admins and students see
        // the same default day).
        $default = SS_Time::active_date();
        $date    = sanitize_text_field($_POST['date'] ?? $default);
        // Pakeitimai matomi tik PASKELBus. Administratorius mato ir nepaskelbtus (peržiūrai).
        $can_drafts = current_user_can('manage_options');
        $published  = SS_Database::is_changes_published($date);
        if (!$published && !$can_drafts) {
            wp_send_json_success(array(
                'changes'   => array(),
                'absent'    => array(),
                'comment'   => '',
                'published' => false,
            ));
            return;
        }
        wp_send_json_success(array(
            'changes'   => SS_Database::get_changes_for_date($date),
            'absent'    => SS_Database::get_absent_for_date($date),
            'comment'   => get_option('ss_changes_comment_' . $date, ''),
            'published' => $published,
        ));
    }
    
    /* ─── Registration: send 6-digit verification code ─── */
    public static function ajax_register_send_code() {
        if (is_user_logged_in()) wp_send_json_error(array('message' => 'Jūs jau prisijungę.'));
        if (SS_Maintenance::is_active()) wp_send_json_error(array('message' => 'Registracija šiuo metu negalima – sistema laikinai uždaryta techninių darbų metu.'));

        $ip = SS_RateLimit::get_client_ip();

        // Apsauga nuo botų — Turnstile žetonas prieš siunčiant el. laišką (anti-spam).
        if (class_exists('SS_Turnstile') && SS_Turnstile::is_enabled()
            && SS_Turnstile::get('ss_ts_login_widget')) {
            if (!SS_Turnstile::verify((string)($_POST['ts_token'] ?? ''), $ip, 'register')) {
                wp_send_json_error(array('message' => 'Nepavyko patvirtinti, kad esate žmogus. Bandykite dar kartą.'));
            }
        }

        // 4.2: Rate limit — ne daugiau 5 užklausų iš vieno IP per valandą
        if (!SS_RateLimit::hit('reg_code_' . $ip, (int) SS_Security::get('ss_sec_reg_rate_limit'), HOUR_IN_SECONDS)) {
            wp_send_json_error(array('message' => 'Per daug bandymų. Bandykite vėliau.'));
        }

        $email = strtolower(sanitize_email($_POST['email'] ?? ''));
        if (!$email || !is_email($email)) {
            wp_send_json_error(array('message' => 'Įveskite teisingą el. pašto adresą.'));
        }
        if (substr($email, -strlen('@azuolynogimnazija.lt')) !== '@azuolynogimnazija.lt') {
            wp_send_json_error(array('message' => 'Leidžiamas tik @azuolynogimnazija.lt el. paštas.'));
        }
        if (email_exists($email) || get_user_by('email', $email)) {
            wp_send_json_error(array('message' => 'Šis el. paštas jau užregistruotas. Pamiršote slaptažodį?'));
        }

        $existing = SS_Database::get_registration_by_email($email);
        if ($existing && $existing->status === 'approved') {
            wp_send_json_error(array('message' => 'Šis el. paštas jau patvirtintas. Bandykite prisijungti.'));
        }
        // Soft rate limit — 60s between code sends
        if ($existing && !empty($existing->code_expires_at) && $existing->code_expires_at !== '0000-00-00 00:00:00') {
            $remain = strtotime($existing->code_expires_at) - time();
            // code_expires_at is +15min; if more than 14m left -> just sent
            if ($remain > 14 * MINUTE_IN_SECONDS) {
                wp_send_json_error(array('message' => 'Kodas jau buvo išsiųstas. Patikrinkite el. paštą.'));
            }
        }

        // SS-12: Naudoti kriptografiškai saugų random_int()
        $code        = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exp_ts      = time() + 15 * MINUTE_IN_SECONDS;
        $expires_at  = date('Y-m-d H:i:s', $exp_ts); // stored UTC for strtotime() comparisons
        $exp_human   = SS_Time::date('Y-m-d H:i', $exp_ts); // displayed in WP (Vilnius) time
        $reg_id      = SS_Database::upsert_registration_code($email, $code, $expires_at);
        if (!$reg_id) wp_send_json_error(array('message' => 'Nepavyko prisiregistruotis.'));

        $school = SS_Mail::school();
        SS_Mail::send($email, "{$school} — Registracijos patvirtinimo kodas", array(
            'title'    => 'El. pašto patvirtinimas',
            'subtitle' => 'Jūsų registracijos patvirtinimo kodas',
            'icon'     => '✉',
            'body'     => '<p style="margin:0 0 14px;">Sveiki,</p>'
                        . '<p style="margin:0;">Norėdami užbaigti registraciją į <strong>' . esc_html($school) . '</strong> tvarkaraščių sistemą, įveskite žemiau pateiktą patvirtinimo kodą.</p>',
            'extra'    => SS_Mail::code_box($code, $exp_human),
            /*'note'     => 'Jeigu registruojatės <strong>ne Jūs</strong> — tiesiog ignoruokite šį laišką.',
            'note_type'=> 'warn',*/
        ));

        wp_send_json_success(array('message' => 'Kodas išsiųstas į ' . $email));
    }

    /* ─── Registration: verify 6-digit code ─── */
    public static function ajax_register_verify_code() {
        if (is_user_logged_in()) wp_send_json_error(array('message' => 'Jūs jau prisijungę.'));

        $email = strtolower(sanitize_email($_POST['email'] ?? ''));
        $code  = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
        if (!$email || !$code) wp_send_json_error(array('message' => 'Trūksta duomenų.'));

        $reg = SS_Database::get_registration_by_email($email);
        if (!$reg) wp_send_json_error(array('message' => 'Registracija nerasta. Pradėkite iš naujo.'));
        if ((int)$reg->verified === 1) {
            wp_send_json_success(array('message' => 'El. paštas jau patvirtintas.'));
        }
        if ((int)$reg->code_attempts >= 3) {
            wp_send_json_error(array('message' => 'Per daug bandymų. Užsakykite naują kodą.'));
        }
        if (empty($reg->code_expires_at) || strtotime($reg->code_expires_at) < time()) {
            wp_send_json_error(array('message' => 'Kodas nebegalioja. Užsakykite naują kodą.'));
        }
        if (!hash_equals((string)$reg->code, (string)$code)) {
            SS_Database::increment_registration_attempts($email);
            wp_send_json_error(array('message' => 'Neteisingas kodas.'));
        }
        SS_Database::mark_registration_verified($email);
        wp_send_json_success(array('message' => 'El. paštas patvirtintas.'));
    }

    /* ─── Registration: check if email is pre-linked to a class ─── */
    public static function ajax_check_email_link() {
        // SS-06: Nonce patikrinimas (prieš tai nebuvo jokios apsaugos)
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ss_login_nonce')) {
            wp_send_json_error(array('message' => 'Saugumo klaida.'));
        }
        // SS-06: Rate limit
        $ip = SS_RateLimit::get_client_ip();
        if (!SS_RateLimit::hit('chk_email_' . $ip, 30, MINUTE_IN_SECONDS)) {
            wp_send_json_error(array('message' => 'Per daug bandymų.'));
        }

        $email = strtolower(sanitize_email($_POST['email'] ?? ''));
        if (!$email) wp_send_json_error(array('message' => 'Trūksta el. pašto.'));

        $link = SS_Database::get_class_link_by_email($email);
        if ($link) {
            // SS-06: Grąžinti tik boolean — nekverti klasės pavadinimo neautentifikuotiems
            wp_send_json_success(array(
                'linked'   => true,
                'class_id' => (int)$link->class_id,
            ));
        }
        wp_send_json_success(array('linked' => false));
    }

    /* ─── Registration: submit final details ─── */
    public static function ajax_register_submit() {
        if (is_user_logged_in()) wp_send_json_error(array('message' => 'Jūs jau prisijungėte.'));
        if (SS_Maintenance::is_active()) wp_send_json_error(array('message' => 'Registracija šiuo metu negalima - sistema laikinai uždaryta.  Prašome bandyti vėliau.'));

        $email      = strtolower(sanitize_email($_POST['email'] ?? ''));
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name  = sanitize_text_field($_POST['last_name']  ?? '');
        $password   = (string)($_POST['password'] ?? '');

        // SS-07: Rolė nustatoma serveryje pagal el. pašto formatą, NE iš POST
        $reg_role_requested = sanitize_text_field($_POST['reg_role'] ?? 'student');
        // Visi gali registruotis kaip student, teacher, employee — admin patvirtina
        if (in_array($reg_role_requested, array('teacher', 'employee', 'admin'), true)) {
            $reg_role = $reg_role_requested === 'admin' ? 'teacher' : $reg_role_requested;
            $mode = 'staff';
        } else {
            $reg_role = 'student';
            $mode     = 'student';
        }
        $is_staff = ($mode === 'staff');

        // SS-07: rolės pasirinkimą turi tik ne skaitmenimis prasidedantys el. paštai.
        // Skaitmenimis prasidedantys (pvz. 27.vardenis.pavardenis@…) — visada mokiniai.
        $local_part = strstr($email, '@', true);
        if ($local_part === false) $local_part = $email;
        if ($is_staff && preg_match('/^\d/', (string)$local_part)) {
            wp_send_json_error(array('message' => 'Šis el. paštas gali registruotis tik kaip mokinys.'));
        }

        if (!$email || !$first_name || !$last_name || !$password) {
            wp_send_json_error(array('message' => 'Užpildykite visus laukus.'));
        }

        // 2.1: Pilna slaptažodžio stiprumo patikra
        $pw_err = SS_Security::validate_password($password, $email);
        if ($pw_err) {
            wp_send_json_error(array('message' => $pw_err));
        }

        $reg = SS_Database::get_registration_by_email($email);
        if (!$reg) wp_send_json_error(array('message' => 'Registracija neegzistuoja.'));
        if ((int)$reg->verified !== 1) {
            wp_send_json_error(array('message' => 'Pirmiausia patvirtinkite el. paštą.'));
        }
        if ($reg->status === 'approved') {
            wp_send_json_error(array('message' => 'Jūs jau patvirtintas.'));
        }
        if (email_exists($email)) {
            wp_send_json_error(array('message' => 'Šis el. paštas jau registruotas.'));
        }

        /* ───── STAFF: teacher / admin ───── */
        if ($mode === 'staff' && in_array($reg_role, array('teacher','admin','employee'), true)) {
            // Save base details
            $matched_teacher = ($reg_role === 'teacher')
                ? SS_Database::match_teacher_by_name($first_name, $last_name)
                : null;

            SS_Database::update_registration_details($email, array(
                'first_name'         => $first_name,
                'last_name'          => $last_name,
                'password_hash'      => wp_hash_password($password),
                'reg_role'           => $reg_role,
                'matched_teacher_id' => $matched_teacher ? (int)$matched_teacher->id : 0,
                'class_label'        => ($reg_role === 'teacher' ? 'Mokytojas' : ($reg_role === 'employee' ? 'Darbuotojas' : 'Administracija')),
            ));

            // Auto-approve teacher if name matches a teacher record
            if ($reg_role === 'teacher' && $matched_teacher) {
                // Generate username
                $local = strstr($email, '@', true) ?: 'mokytojas';
                $base  = sanitize_user($local, true);
                $login = $base; $i = 1;
                while (username_exists($login)) {
                    $login = $base . $i++;
                    if ($i > 50) wp_send_json_error(array('message' => 'Nepavyko sugeneruoti vartotojo vardo.'));
                }
                $user_id = wp_insert_user(array(
                    'user_login'   => $login,
                    'user_email'   => $email,
                    'user_pass'    => '',
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'display_name' => trim($first_name . ' ' . $last_name),
                    'role'         => 'ss_teacher',
                ));
                if (is_wp_error($user_id)) wp_send_json_error(array('message' => $user_id->get_error_message()));
                global $wpdb;
                $wpdb->update($wpdb->users, array('user_pass' => wp_hash_password($password)), array('ID' => $user_id), array('%s'), array('%d'));
                clean_user_cache($user_id);
                // Update teacher record with email if missing
                if (empty($matched_teacher->email)) {
                    $wpdb->update("{$wpdb->prefix}ss_teachers", array('email' => $email), array('id' => (int)$matched_teacher->id));
                }
                SS_Database::set_registration_status($reg->id, 'approved', array(
                    'user_id'     => $user_id,
                    'approved_at' => current_time('mysql'),
                ));
                $redirect = self::auto_login_after_register($user_id);
                wp_send_json_success(array(
                    'status'   => 'approved',
                    'message'  => 'Mokytojo paskyra sukurta.',
                    'redirect' => $redirect,
                ));
            }

            // Otherwise – pending admin review (notify admin)
            self::notify_admin_registration($email, $first_name, $last_name,
                $reg_role === 'teacher' ? 'Mokytojas (vardas pavardė nesutapo)' : 'Administracija');

            wp_send_json_success(array(
                'status'  => 'pending',
                'message' => 'Anketa išsiųsta administratoriui peržiūrai.',
            ));
        }

        /* ───── STUDENT (existing flow) ───── */
        $level         = sanitize_text_field($_POST['class_level'] ?? '');
        $letter        = sanitize_text_field($_POST['class_letter'] ?? '');
        $schedule_code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['schedule_code'] ?? '')));

        if (!in_array($level, array('I','II','III','IV'), true)) {
            wp_send_json_error(array('message' => 'Klasė turi būti I, II, III ar IV.'));
        }
        if (!preg_match('/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]{1,3}$/u', $letter)) {
            wp_send_json_error(array('message' => 'Klasės raidė netinka.'));
        }

        $class_label = $level . mb_strtolower($letter);

        // Check for pre-linked email OR schedule code
        $auto_class_id = 0;
        $prelink = SS_Database::get_class_link_by_email($email);

        // Auto-approve (no manual review) when the email is already linked to a class
        // OR a valid schedule code was entered. NB: we intentionally do NOT use
        // $_POST['mode'] here — it was overwritten to 'student' above, which made the
        // old `$mode==='prelinked'` / `$mode==='code'` checks always fail and forced
        // every student into manual approval even with a correct code.
        if ($prelink) {
            $auto_class_id = (int)$prelink->class_id;
        } elseif (!empty($schedule_code)) {
            $class_by_code = SS_Database::get_class_by_code($schedule_code);
            if (!$class_by_code) {
                wp_send_json_error(array('message' => 'Neteisingas tvarkaraščio kodas.'));
            }
            $auto_class_id = (int)$class_by_code->id;
        }

        SS_Database::update_registration_details($email, array(
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'class_level'   => $level,
            'class_letter'  => $letter,
            'class_label'   => $class_label,
            'password_hash' => wp_hash_password($password),
            'reg_role'      => 'student',
        ));

        // If auto-approved, create user immediately
        if ($auto_class_id > 0) {
            $class = SS_Database::get_class($auto_class_id);
            if (!$class) wp_send_json_error(array('message' => 'Klasė nerasta.'));

            // Generate unique username from email local-part
            $local = strstr($email, '@', true) ?: 'mokinys';
            $base  = sanitize_user($local, true);
            $login = $base; $i = 1;
            while (username_exists($login)) {
                $login = $base . $i++;
                if ($i > 50) wp_send_json_error(array('message' => 'Nepavyko sugeneruoti vartotojo vardo.'));
            }

            // Create user
            $user_id = wp_insert_user(array(
                'user_login'    => $login,
                'user_email'    => $email,
                'user_pass'     => '',
                'first_name'    => $first_name,
                'last_name'     => $last_name,
                'display_name'  => trim($first_name . ' ' . $last_name),
                'role'          => 'ss_student',
            ));
            if (is_wp_error($user_id)) wp_send_json_error(array('message' => $user_id->get_error_message()));

            // Replace password hash directly
            global $wpdb;
            $pass_hash = wp_hash_password($password);
            $wpdb->update($wpdb->users,
                array('user_pass' => $pass_hash),
                array('ID' => $user_id),
                array('%s'), array('%d')
            );
            clean_user_cache($user_id);

            // Mokinio klasė (I–IV) — kad veiktų mokslo metų pabaigos kėlimas.
            if (class_exists('SS_Roles')) {
                SS_Roles::set_student_level($user_id, $level, $letter);
            }

            SS_Database::set_user_classes($user_id, array($auto_class_id));
            SS_Database::set_registration_status($reg->id, 'approved', array(
                'assigned_class_id' => $auto_class_id,
                'user_id'           => $user_id,
                'approved_at'       => current_time('mysql'),
            ));

            // Delete the email link if it was used (one-time use)
            if ($prelink) {
                SS_Database::delete_class_link((int)$prelink->id);
            }

            // Send approval email (compact)
            self::send_approval_email($email, $first_name, $last_name, $login, $class->name);

            $redirect = self::auto_login_after_register($user_id);
            wp_send_json_success(array(
                'status'     => 'approved',
                'class_name' => $class->name,
                'message'    => 'Paskyra sukurta!',
                'redirect'   => $redirect,
            ));
        }

        // Manual approval flow
        self::notify_admin_registration($email, $first_name, $last_name, $class_label);

        wp_send_json_success(array(
            'status'  => 'pending',
            'message' => 'Anketa išsiųsta administratoriui. Lauk patvirtinimo savo el. pašte.'
        ));
    }

    /* Internal: log the freshly-registered user in immediately and flag that the
       first-run onboarding (tour + initial preferences) should be shown. Returns
       the dashboard URL to redirect to. */
    private static function auto_login_after_register(int $user_id): string {
        update_user_meta($user_id, 'ss_needs_onboarding', 1);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);
        if (class_exists('SS_AuditLog')) {
            $ip = class_exists('SS_RateLimit') ? SS_RateLimit::get_client_ip() : '';
            SS_AuditLog::write('login_ok', array('user_id' => $user_id, 'ip' => $ip, 'method' => 'password'));
            SS_AuditLog::check_new_device($user_id, $ip, 'password');
        }
        return get_permalink(get_option('ss_dashboard_page_id')) ?: home_url('/');
    }

    /* Internal: send minimal admin notification email */
    private static function notify_admin_registration($email, $first_name, $last_name, $detail_label) {
        $admin_email = get_option('ss_admin_email', get_option('admin_email'));
        if (!$admin_email) return;
        $school = SS_Mail::school();
        $admin_url = admin_url('admin.php?page=ss-registrations');
        SS_Mail::send($admin_email, "{$school} — Nauja registracija: {$first_name} {$last_name}", array(
            'title'    => 'Nauja registracija',
            'subtitle' => 'Laukia jūsų patvirtinimo',
            'accent'   => SS_Mail::NAVY,
            'icon'     => '👤',
            'body'     => '<p style="margin:0;">Sistemoje gauta nauja registracijos paraiška. Peržiūrėkite ją administravimo skydelyje.</p>',
            'extra'    => SS_Mail::info_list(array(
                array('label' => 'Vardas, pavardė', 'value' => esc_html($first_name . ' ' . $last_name)),
                array('label' => 'El. paštas',      'value' => esc_html($email)),
                array('label' => 'Tipas',           'value' => esc_html($detail_label)),
            )),
            'button'   => array('text' => 'Peržiūrėti administracijoje', 'url' => $admin_url),
        ));
    }

    /* Internal: brief approval email */
    private static function send_approval_email($email, $first_name, $last_name, $login, $class_name) {
        $school    = SS_Mail::school();
        $login_url = ($lp = get_option('ss_login_page_id')) ? get_permalink($lp) : wp_login_url();
        SS_Mail::send($email, "$school | Registracija patvirtinta", array(
            'title'    => 'Registracija patvirtinta',
            'subtitle' => 'Jūsų paskyra paruošta',
            'icon'     => '✓',
            'body'     => '<p style="margin:0 0 14px;">Sveiki, <strong>' . esc_html($first_name) . '</strong>,</p>'
                        . '<p style="margin:0;">Jūsų registracija į <strong>' . esc_html($school) . '</strong> tvarkaraščių sistemą sėkmingai patvirtinta. Galite prisijungti naudodami žemiau nurodytus duomenis.</p>',
            'extra'    => SS_Mail::info_list(array(
                array('label' => 'Vartotojas',  'value' => esc_html($login)),
                array('label' => 'Tvarkaraštis', 'value' => esc_html($class_name)),
            )),
            'button'   => array('text' => 'Prisijungti prie sistemos', 'url' => $login_url),
        ));
    }

    /* ════ CLASS SCHEDULE (for teacher view) ════ */
    public static function ajax_get_class_schedule() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        // SS-05: Reikia autentifikacijos — nonce nepakanka
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Neprisijungta.'));
        $class_id = intval($_POST['class_id'] ?? 0);
        if (!$class_id) wp_send_json_error(array('message' => 'Neteisinga klase.'));
        $class_info = SS_Database::get_class($class_id);
        if (!$class_info) wp_send_json_error(array('message' => 'Klase nerasta.'));
        $sched = SS_Database::get_class_schedule($class_id);
        wp_send_json_success(array(
            'class_info' => $class_info,
            'grid'       => $sched['grid'],
            'periods'    => $sched['periods']
        ));
    }

    /* ════ SETTINGS: send password change code ════ */
    public static function ajax_settings_send_pw_code() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user = wp_get_current_user();
        $email = $user->user_email;

        // Rate limit: 60s
        $last = (int)get_user_meta($user->ID, 'ss_pw_code_sent_at', true);
        if ($last && (time() - $last) < 60) {
            wp_send_json_error(array('message' => 'Kodas jau išsiųstas. Palaukite minutę.'));
        }

        // SS-12: Naudoti kriptografiškai saugų random_int()
        $code       = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires_at = time() + 15 * MINUTE_IN_SECONDS;
        // Saugom TIK maišą (ne patį kodą) — kad DB nutekėjimas neatskleistų kodo.
        update_user_meta($user->ID, 'ss_pw_code',          hash_hmac('sha256', $code, wp_salt('auth')));
        update_user_meta($user->ID, 'ss_pw_code_expires',  $expires_at);
        update_user_meta($user->ID, 'ss_pw_code_attempts', 0);
        update_user_meta($user->ID, 'ss_pw_code_sent_at',  time());
        update_user_meta($user->ID, 'ss_pw_code_verified', 0);

        $school   = SS_Mail::school();
        $exp_fmt  = SS_Time::date('H:i', $expires_at);

        SS_Mail::send($email, "$school | Slaptažodžio keitimo kodas", array(
            'title'    => 'Slaptažodžio keitimas',
            'subtitle' => 'Slaptažodžio keitimo patvirtinimo kodas',
            'icon'     => '🔐',
            'body'     => '<p style="margin:0;">Gautas prašymas pakeisti slaptažodį paskyroje <strong>' . esc_html($email) . '</strong>. Įveskite žemiau pateiktą kodą, kad tęstumėte.</p>',
            'extra'    => SS_Mail::code_box($code, $exp_fmt),
            /*'note'     => 'Jeigu slaptažodžio keisti <strong>neprašėte</strong> — tiesiog ignoruokite šį laišką, jūsų slaptažodis nepasikeis.',*/
            /*'note_type'=> 'warn',*/
        ));

        wp_send_json_success(array('message' => 'Kodas išsiųstas į ' . obfuscate_email($email)));
    }

    /* ════ SETTINGS: verify password change code ════ */
    public static function ajax_settings_verify_pw_code() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user = wp_get_current_user();
        $code = preg_replace('/\D/', '', (string)($_POST['code'] ?? ''));
        if (!$code) wp_send_json_error(array('message' => 'Įveskite kodą.'));

        $stored   = get_user_meta($user->ID, 'ss_pw_code', true);
        $expires  = (int)get_user_meta($user->ID, 'ss_pw_code_expires', true);
        $attempts = (int)get_user_meta($user->ID, 'ss_pw_code_attempts', true);

        if ($attempts >= 3) wp_send_json_error(array('message' => 'Per daug bandymų. Užsakykite naują kodą.'));
        if (!$stored || time() > $expires) wp_send_json_error(array('message' => 'Kodas nebegalioja. Užsakykite naują.'));
        if (!hash_equals((string)$stored, hash_hmac('sha256', (string)$code, wp_salt('auth')))) {
            update_user_meta($user->ID, 'ss_pw_code_attempts', $attempts + 1);
            wp_send_json_error(array('message' => 'Neteisingas kodas. Liko ' . (2 - $attempts) . ' bandymai.'));
        }
        update_user_meta($user->ID, 'ss_pw_code_verified', 1);
        wp_send_json_success(array('message' => 'Kodas patvirtintas.'));
    }

    /* ════ SETTINGS: change password (after code verified) ════ */
    public static function ajax_settings_change_pw() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user    = wp_get_current_user();
        $new_pw  = (string)($_POST['password'] ?? '');
        $new_pw2 = (string)($_POST['password2'] ?? '');

        if ((int)get_user_meta($user->ID, 'ss_pw_code_verified', true) !== 1) {
            wp_send_json_error(array('message' => 'Pirmiausia patvirtinkite kodą.'));
        }
        if ($new_pw !== $new_pw2) wp_send_json_error(array('message' => 'Slaptažodžiai nesutampa.'));

        // 2.1: Pilna slaptažodžio stiprumo patikra
        $pw_err = SS_Security::validate_password($new_pw, $user->user_email);
        if ($pw_err) wp_send_json_error(array('message' => $pw_err));

        wp_set_password($new_pw, $user->ID);
        // Clear temp code
        delete_user_meta($user->ID, 'ss_pw_code');
        delete_user_meta($user->ID, 'ss_pw_code_expires');
        delete_user_meta($user->ID, 'ss_pw_code_attempts');
        delete_user_meta($user->ID, 'ss_pw_code_sent_at');
        delete_user_meta($user->ID, 'ss_pw_code_verified');

        // 2.2: Panaikinti visas sesijas išskyrus dabartinę (apsauga nuo pavogtų slapukų)
        if (SS_Security::get('ss_sec_invalidate_on_pw')) {
            $sessions = WP_Session_Tokens::get_instance($user->ID);
            $sessions->destroy_others(wp_get_session_token());
        }
        // Re-auth so user isn't logged out
        wp_set_auth_cookie($user->ID, true);
        SS_AuditLog::write('password_changed', array('user_id' => $user->ID, 'ip' => SS_RateLimit::get_client_ip()));
        if (class_exists('SS_Achievements')) SS_Achievements::bump($user->ID, 'settings');
        wp_send_json_success(array('message' => 'Slaptažodis sėkmingai pakeistas.'));
    }

    /* ════ SETTINGS: upload avatar ════ */
    public static function ajax_settings_upload_avatar() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id = get_current_user_id();

        if (empty($_FILES['avatar'])) wp_send_json_error(array('message' => 'Failas nepasirinktas.'));

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file = $_FILES['avatar'];
        // SS-09: Visada naudoti mime_content_type() iš tikro failo turinio —
        // $file['type'] nustatomas naršyklės ir gali būti suklastotas.
        $tmp  = $file['tmp_name'] ?? '';
        if (!$tmp || !is_uploaded_file($tmp)) {
            wp_send_json_error(array('message' => 'Failo įkėlimo klaida.'));
        }
        $type    = mime_content_type($tmp);
        $allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($type, $allowed, true)) {
            wp_send_json_error(array('message' => 'Leidžiami tik JPEG, PNG, GIF, WEBP formatai.'));
        }
        // Papildoma apsauga: patikrinti magikinius baitus per getimagesize()
        if (!@getimagesize($tmp)) {
            wp_send_json_error(array('message' => 'Failas neatitinka vaizdo formato.'));
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'Failas per didelis (maks. 3 MB).'));
        }

        // Delete old avatar attachment
        $old_id = (int)get_user_meta($user_id, 'ss_avatar_id', true);
        if ($old_id) wp_delete_attachment($old_id, true);

        // Use the native file input key 'avatar' directly via media_handle_upload
        $att_id = media_handle_upload('avatar', 0);

        if (is_wp_error($att_id)) {
            wp_send_json_error(array('message' => 'Nepavyko įkelti nuotraukos: ' . $att_id->get_error_message()));
        }
        update_user_meta($user_id, 'ss_avatar_id', $att_id);
        update_user_meta($user_id, 'ss_avatar_ts', time());
        if (class_exists('SS_Achievements')) SS_Achievements::bump($user_id, 'avatar');
        $url = wp_get_attachment_image_url($att_id, 'thumbnail');
        wp_send_json_success(array('url' => $url));
    }

    /* ════ SETTINGS: delete avatar ════ */
    public static function ajax_settings_delete_avatar() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id = get_current_user_id();
        $old_id  = (int)get_user_meta($user_id, 'ss_avatar_id', true);
        if ($old_id) wp_delete_attachment($old_id, true);
        delete_user_meta($user_id, 'ss_avatar_id');
        wp_send_json_success();
    }

    /* ════ SETTINGS: save atspek preference ════ */
    public static function ajax_settings_save_atspek() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id = get_current_user_id();
        $mode    = sanitize_text_field($_POST['mode'] ?? 'day'); // 'day','hours','off'
        $hours   = min(168, max(1, (int)($_POST['hours'] ?? 24)));
        if (!in_array($mode, array('day','hours','off'), true)) $mode = 'day';
        update_user_meta($user_id, 'ss_atspek_mode',  $mode);
        update_user_meta($user_id, 'ss_atspek_hours', $hours);
        if (class_exists('SS_Achievements')) SS_Achievements::bump($user_id, 'settings');
        wp_send_json_success(array('mode' => $mode, 'hours' => $hours));
    }

    /* ════ SETTINGS: hide own name in quiz results ════ */
    public static function ajax_settings_save_anon_quiz() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id = get_current_user_id();
        $hide    = !empty($_POST['hide']) && $_POST['hide'] !== 'false' && $_POST['hide'] !== '0';
        if ($hide) {
            update_user_meta($user_id, 'ss_anon_quiz', 1);
        } else {
            delete_user_meta($user_id, 'ss_anon_quiz');
        }
        wp_send_json_success(array('hide' => $hide ? 1 : 0));
    }

    /* ════ SETTINGS: new-device login notifications (per-user) ════ */
    public static function ajax_settings_save_new_device() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id = get_current_user_id();
        $on = !empty($_POST['on']) && $_POST['on'] !== 'false' && $_POST['on'] !== '0';
        update_user_meta($user_id, 'ss_user_new_device_notify', $on ? 1 : 0);
        wp_send_json_success(array('on' => $on ? 1 : 0));
    }

    /* ════ ONBOARDING: mark the first-run tour as completed ════ */
    public static function ajax_onboarding_finish() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error();
        $uid = get_current_user_id();
        update_user_meta($uid, 'ss_onboarding_done', 1);   // shown once per account, regardless of how it was created
        delete_user_meta($uid, 'ss_needs_onboarding');
        wp_send_json_success();
    }

    /* Internal: switch the user to a class — replaces the old schedule (set_user_classes
       deletes the previous one) and clears now-stale subgroup picks. */
    private static function ss_switch_user_class(int $user_id, int $class_id): void {
        SS_Database::set_user_classes($user_id, array($class_id));
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}ss_user_subgroup_prefs", array('user_id' => $user_id));
    }

    /* ════ SETTINGS: apply a schedule code (get a new schedule) ════ */
    public static function ajax_settings_apply_code() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user = wp_get_current_user();
        if (in_array('ss_teacher', (array)$user->roles, true)) {
            wp_send_json_error(array('message' => 'Mokytojo paskyrai tvarkaraščio kodas netaikomas.'));
        }
        $code  = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['code'] ?? '')));
        if (strlen($code) < 6) wp_send_json_error(array('message' => 'Įveskite teisingą tvarkaraščio kodą.'));
        $class = SS_Database::get_class_by_code($code);
        if (!$class) wp_send_json_error(array('message' => 'Toks tvarkaraščio kodas nerastas.'));
        self::ss_switch_user_class($user->ID, (int)$class->id);
        SS_AuditLog::write('schedule_changed', array('user_id' => $user->ID, 'method' => 'code', 'class' => $class->name));
        wp_send_json_success(array('class_name' => $class->name));
    }

    /* ════ SETTINGS: check if a schedule is linked to the user's email ════ */
    public static function ajax_settings_check_email() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user = wp_get_current_user();
        if (in_array('ss_teacher', (array)$user->roles, true)) {
            wp_send_json_error(array('message' => 'Mokytojo paskyrai netaikoma.'));
        }
        $link = SS_Database::get_class_link_by_email($user->user_email);
        if (!$link || empty($link->class_id)) {
            wp_send_json_success(array('found' => false));
        }
        self::ss_switch_user_class($user->ID, (int)$link->class_id);
        SS_AuditLog::write('schedule_changed', array('user_id' => $user->ID, 'method' => 'email_link', 'class' => $link->class_name));
        wp_send_json_success(array('found' => true, 'class_name' => $link->class_name));
    }

    /* ════ SETTINGS: upload site logo (admin only) ════ */
    public static function ajax_settings_upload_logo() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Nėra teisių.'));
        if (empty($_FILES['logo'])) wp_send_json_error(array('message' => 'Failas neprisegtas.'));

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $file = $_FILES['logo'];
        // (kaip avataro įkėlime): tikriname tikrą failo turinį per
        // mime_content_type(), o ne naršyklės nurodytą $file['type'], kurį galima
        // suklastoti. SVG NELEIDŽIAMAS — gali turėti <script> (saugomas XSS).
        $tmp = $file['tmp_name'] ?? '';
        if (!$tmp || !is_uploaded_file($tmp)) {
            wp_send_json_error(array('message' => 'Failo įkėlimo klaida.'));
        }
        $type    = mime_content_type($tmp);
        $allowed = array('image/jpeg','image/png','image/gif','image/webp');
        if (!in_array($type, $allowed, true)) {
            wp_send_json_error(array('message' => 'Leidžiami tik JPEG, PNG, GIF, WEBP formatai.'));
        }
        if (!@getimagesize($tmp)) {
            wp_send_json_error(array('message' => 'Failas neatitinka vaizdo formato.'));
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'Failas per didelis (maks. 3 MB).'));
        }

        // Override attachment data so it lands in media library nicely.
        // Naudojam ĮVARDYTĄ closure ir pašalinam TIK jį (ne remove_all_filters,
        // kuris būtų nunešęs ir kitų įskiepių upload_dir filtrus).
        $ss_logo_dir = function($dirs) {
            $dirs['subdir'] = '/ss-logos';
            $dirs['path']   = $dirs['basedir'] . '/ss-logos';
            $dirs['url']    = $dirs['baseurl'] . '/ss-logos';
            return $dirs;
        };
        add_filter('upload_dir', $ss_logo_dir);

        $_FILES['logo']['name'] = 'ss-logo-' . sanitize_file_name($file['name']);
        $attachment_id = media_handle_upload('logo', 0);

        remove_filter('upload_dir', $ss_logo_dir);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()));
        }

        // Delete old logo attachment if one existed and was uploaded via this tool
        $old_id = (int) get_option('ss_logo_id', 0);
        if ($old_id && $old_id !== $attachment_id) {
            wp_delete_attachment($old_id, true);
        }

        $url = wp_get_attachment_image_url($attachment_id, 'medium') ?: wp_get_attachment_url($attachment_id);
        update_option('ss_logo_id',  $attachment_id);
        update_option('ss_logo_url', $url);

        wp_send_json_success(array('url' => $url, 'id' => $attachment_id));
    }

    /* ════ SETTINGS: delete site logo (admin only) ════ */
    public static function ajax_settings_delete_logo() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(array('message' => 'Nėra teisių.'));
        $old_id = (int) get_option('ss_logo_id', 0);
        if ($old_id) wp_delete_attachment($old_id, true);
        delete_option('ss_logo_id');
        delete_option('ss_logo_url');
        wp_send_json_success(array('deleted' => true));
    }

    /* ════ SETTINGS: get login sessions ════ */
    public static function ajax_settings_get_sessions() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id = get_current_user_id();

        // IMPORTANT: WP_Session_Tokens::get_all() calls array_values() internally,
        // which strips the sha256 hash keys. We must read user meta directly
        // to preserve the keys needed for targeted revocation.
        $sessions_raw = get_user_meta($user_id, 'session_tokens', true);
        if (!is_array($sessions_raw)) $sessions_raw = array();

        $current_raw  = wp_get_session_token();
        $current_hash = $current_raw ? hash('sha256', $current_raw) : '';
        $now          = time();
        $result       = array();

        foreach ($sessions_raw as $token_hash => $data) {
            if (!is_array($data)) continue;
            // Skip expired sessions (same logic WP uses internally)
            if (!empty($data['expiration']) && $data['expiration'] < $now) continue;
            $sess_ip = $data['ip'] ?? '';
            $result[] = array(
                'token'      => (string)$token_hash,
                'is_current' => ($token_hash === $current_hash),
                'ua'         => $data['ua'] ?? '',
                'ip'         => $sess_ip,
                'geo'        => class_exists('SS_AuditLog') ? SS_AuditLog::geo_label($sess_ip) : '',
                'expiration' => !empty($data['expiration']) ? SS_Time::date('Y-m-d H:i', $data['expiration']) : '',
                'login'      => !empty($data['login'])      ? SS_Time::date('Y-m-d H:i', $data['login'])      : '',
            );
        }
        usort($result, function($a, $b) { return (int)$b['is_current'] - (int)$a['is_current']; });
        wp_send_json_success(array('sessions' => $result));
    }

    /* ════ SETTINGS: revoke a session ════ */
    public static function ajax_settings_revoke_session() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id     = get_current_user_id();
        $token_hash  = sanitize_text_field($_POST['token'] ?? '');
        if (!$token_hash) wp_send_json_error(array('message' => 'Neteisingas užklausa.'));

        // Protect current session
        $current_raw  = wp_get_session_token();
        $current_hash = $current_raw ? hash('sha256', $current_raw) : '';
        if ($token_hash === $current_hash) {
            wp_send_json_error(array('message' => 'Negalima panaikinti dabartinės sesijos.'));
        }

        // WP stores session_tokens as [ sha256_hash => data ].
        // WP_Session_Tokens::destroy() expects the RAW token (hashes internally),
        // so we manipulate the meta directly using the hash key we already have.
        $raw_sessions = get_user_meta($user_id, 'session_tokens', true);
        if (!is_array($raw_sessions) || !array_key_exists($token_hash, $raw_sessions)) {
            wp_send_json_error(array('message' => 'Sesija nerasta.'));
        }
        unset($raw_sessions[$token_hash]);
        update_user_meta($user_id, 'session_tokens', $raw_sessions);
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('session_revoked', [
                'user_id' => $user_id,
                'ip'      => class_exists('SS_RateLimit') ? SS_RateLimit::get_client_ip() : '',
            ]);
        }
        wp_send_json_success();
    }

    /* ════ SETTINGS: get login device history ════ */
    public static function ajax_settings_get_devices() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id    = get_current_user_id();
        $ip         = class_exists('SS_RateLimit') ? SS_RateLimit::get_client_ip() : '';
        $current_fp = class_exists('SS_AuditLog')  ? SS_AuditLog::current_device_fp($ip) : '';
        $blocked    = (array) get_user_meta($user_id, 'ss_blocked_devices', true);

        $history = (array) get_user_meta($user_id, 'ss_login_history', true);
        $result  = [];
        foreach ($history as $entry) {
            if (!is_array($entry) || empty($entry['ts'])) continue;

            // Senesni įrašai gali neturėti fp — apskaičiuojame iš išsaugotų laukų
            $stored_ua = sanitize_text_field($entry['ua'] ?? '');
            $stored_ip = sanitize_text_field($entry['ip'] ?? '');
            if (!empty($entry['fp'])) {
                $fp = sanitize_text_field($entry['fp']);
            } else {
                $pfx = implode('.', array_slice(explode('.', $stored_ip), 0, 2));
                $fp  = hash('sha256', $stored_ua . '|' . $pfx);
            }

            // Geo: naudoti išsaugotą reikšmę, kitaip apskaičiuoti (su talpykla)
            $geo = sanitize_text_field($entry['geo'] ?? '');
            if ($geo === '' && $stored_ip !== '' && class_exists('SS_AuditLog')) {
                $geo = SS_AuditLog::geo_label($stored_ip);
            }
            $result[] = [
                'ts'         => (int) $entry['ts'],
                'date'       => SS_Time::date('Y-m-d H:i', (int) $entry['ts']),
                'ip'         => $stored_ip,
                'ua'         => $stored_ua,
                'geo'        => $geo,
                'method'     => sanitize_text_field($entry['method'] ?? 'password'),
                'fp'         => $fp,
                'is_current' => ($fp === $current_fp),
                'is_blocked' => in_array($fp, $blocked, true),
            ];
        }
        wp_send_json_success(array('devices' => $result, 'current_fp' => $current_fp));
    }

    /* ════ SETTINGS: block a device ════ */
    public static function ajax_settings_block_device() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id = get_current_user_id();
        $fp      = sanitize_text_field($_POST['fp'] ?? '');

        // Patikrinti, kad fp yra validus hex
        if (strlen($fp) !== 64 || !ctype_xdigit($fp)) {
            wp_send_json_error(array('message' => 'Neteisingas įrenginio identifikatorius.'));
        }

        // Draudžiame blokuoti dabartinį įrenginį
        $ip         = class_exists('SS_RateLimit') ? SS_RateLimit::get_client_ip() : '';
        $current_fp = class_exists('SS_AuditLog')  ? SS_AuditLog::current_device_fp($ip) : '';
        if ($fp === $current_fp) {
            wp_send_json_error(array('message' => 'Negalima užblokuoti dabartinio įrenginio.'));
        }

        // fp turi būti matomas šio vartotojo istorijoje (apsauga nuo atsitiktinių blokavimų)
        $history     = (array) get_user_meta($user_id, 'ss_login_history', true);
        $known_fps   = [];
        foreach ($history as $entry) {
            if (!is_array($entry)) continue;
            if (!empty($entry['fp'])) {
                $known_fps[] = $entry['fp'];
            } else {
                $pfx         = implode('.', array_slice(explode('.', $entry['ip'] ?? ''), 0, 2));
                $known_fps[] = hash('sha256', ($entry['ua'] ?? '') . '|' . $pfx);
            }
        }
        if (!in_array($fp, $known_fps, true)) {
            wp_send_json_error(array('message' => 'Įrenginys nerastas istorijoje.'));
        }

        $blocked = (array) get_user_meta($user_id, 'ss_blocked_devices', true);
        if (!in_array($fp, $blocked, true)) {
            $blocked[] = $fp;
            update_user_meta($user_id, 'ss_blocked_devices', $blocked);
        }
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('device_blocked', ['user_id' => $user_id, 'ip' => $ip, 'fp' => substr($fp, 0, 8) . '…']);
        }
        wp_send_json_success();
    }

    /* ════ SETTINGS: unblock a device ════ */
    public static function ajax_settings_unblock_device() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Nesate prisijungęs.'));
        $user_id = get_current_user_id();
        $fp      = sanitize_text_field($_POST['fp'] ?? '');

        if (strlen($fp) !== 64 || !ctype_xdigit($fp)) {
            wp_send_json_error(array('message' => 'Neteisingas įrenginio identifikatorius.'));
        }

        $blocked = (array) get_user_meta($user_id, 'ss_blocked_devices', true);
        $blocked = array_values(array_filter($blocked, fn($b) => $b !== $fp));
        update_user_meta($user_id, 'ss_blocked_devices', $blocked);

        $ip = class_exists('SS_RateLimit') ? SS_RateLimit::get_client_ip() : '';
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('device_unblocked', ['user_id' => $user_id, 'ip' => $ip, 'fp' => substr($fp, 0, 8) . '…']);
        }
        wp_send_json_success();
    }


    /* ═══════════════════════════════════════════════════════════════
       QR KODO PRISIJUNGIMAS
       ═══════════════════════════════════════════════════════════════ */

    /** Sukuria naują QR žetoną (3 min. galiojimas) */
    public static function ajax_qr_create() {
        if (!get_option('ss_qr_login_enabled', 0) || SS_Maintenance::qr_blocked()) {
            wp_send_json_error(array('message' => 'QR prisijungimas laikinai išjungtas.'));
        }
        // Rate limit: max 15 QR tokens per IP per 10 min (anti transient-flooding).
        if (!SS_RateLimit::hit('qr_create_' . SS_RateLimit::get_client_ip(), 15, 10 * MINUTE_IN_SECONDS)) {
            wp_send_json_error(array('message' => 'Per daug bandymų. Bandykite vėliau.'));
        }
        $token  = bin2hex(random_bytes(24));
        $raw_ip = SS_RateLimit::get_client_ip();
        // Geo lookup intentionally deferred to qr-confirm.php (avoids blocking this AJAX call)
        $data  = array(
            'status'     => 'pending',
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'ip'         => $raw_ip,
            'created'    => time(),
            'user_id'    => null,
        );
        // Galiojimas: 60 s rodoma, +10 s atsarga serveryje (paskutinės sekundės patvirtinimui)
        set_transient('ss_qr_' . $token, $data, 70);
        $login_url   = get_permalink(get_option('ss_login_page_id')) ?: home_url('/');
        $confirm_url = add_query_arg(array('ss_qr' => $token), $login_url);
        wp_send_json_success(array(
            'token'       => $token,
            'confirm_url' => $confirm_url,
            'expires_in'  => 60,
        ));
    }

    /** Desktop polling – tikrina ar mobilusis patvirtino */
    public static function ajax_qr_poll() {
        if (!get_option('ss_qr_login_enabled', 0) || SS_Maintenance::qr_blocked()) {
            wp_send_json_error(array('message' => 'QR prisijungimas laikinai išjungtas.'));
        }
        $token = sanitize_text_field($_POST['token'] ?? '');
        if (!$token || strlen($token) !== 48) {
            wp_send_json_error(array('message' => 'Neteisingas žetonas.'));
        }
        $data = get_transient('ss_qr_' . $token);
        if ($data === false) {
            wp_send_json_success(array('status' => 'expired'));
            return;
        }
        if ($data['status'] === 'rejected') {
            delete_transient('ss_qr_' . $token);
            wp_send_json_success(array('status' => 'rejected'));
            return;
        }
        // Idempotentiškumas: jei žetonas jau „suvartotas" (prisijungimas atliktas),
        // grąžinam tą patį rezultatą NEatlikdami prisijungimo iš naujo. Taip pavėlavusi
        // lygiagreti apklausa po sėkmės nebegauna „expired" (ištrynus transientą).
        if ($data['status'] === 'used') {
            wp_send_json_success(array(
                'status'   => $data['result'] ?? 'confirmed',
                'redirect' => $data['redirect'] ?? (get_permalink(get_option('ss_dashboard_page_id')) ?: home_url('/')),
                'token'    => $data['fa_token'] ?? null,
            ));
            return;
        }
        if ($data['status'] === 'confirmed' && $data['user_id']) {
            $user = get_user_by('id', $data['user_id']);
            if (!$user) { wp_send_json_error(array('message' => 'Vartotojas nerastas.')); }
            $redirect = get_permalink(get_option('ss_dashboard_page_id')) ?: home_url('/');
            // 2FA patikrinimas
            if (SS_2FA::is_enabled($data['user_id'])) {
                $fa_token = SS_2FA::create_pending($data['user_id'], false, 'qr', $redirect);
                // Pažymim „used" (idempotentiškai), kad pakartotinė apklausa gautų tą patį.
                $data['status']   = 'used';
                $data['result']   = '2fa_required';
                $data['fa_token'] = $fa_token;
                $data['redirect'] = $redirect;
                set_transient('ss_qr_' . $token, $data, 30);
                wp_send_json_success(array('status' => '2fa_required', 'token' => $fa_token));
                return;
            }
            wp_clear_auth_cookie();
            wp_set_current_user($data['user_id']);
            wp_set_auth_cookie($data['user_id'], false);
            do_action('wp_login', $user->user_login, $user);
            if (class_exists('SS_AuditLog')) {
                SS_AuditLog::write('login_ok', [
                    'user_id' => (int) $data['user_id'],
                    'ip'      => class_exists('SS_RateLimit') ? SS_RateLimit::get_client_ip() : '',
                    'method'  => 'qr',
                ]);
                SS_AuditLog::check_new_device((int) $data['user_id'], class_exists('SS_RateLimit') ? SS_RateLimit::get_client_ip() : '', 'qr');
            }
            // Prisijungimas atliktas vieną kartą — pažymim „used", o ne triname,
            // kad pavėlavusi lygiagreti apklausa negautų „expired".
            $data['status']   = 'used';
            $data['result']   = 'confirmed';
            $data['redirect'] = $redirect;
            set_transient('ss_qr_' . $token, $data, 30);
            wp_send_json_success(array('status' => 'confirmed', 'redirect' => $redirect));
            return;
        }
        wp_send_json_success(array('status' => 'pending'));
    }

    /** Informacija apie prašomą sesiją – mobiliam patvirtinimo puslapiui */
    public static function ajax_qr_info() {
        if (SS_Maintenance::qr_blocked()) {
            wp_send_json_error(array('message' => 'QR prisijungimas laikinai išjungtas.'));
        }
        $token = sanitize_text_field($_POST['token'] ?? '');
        if (!$token || strlen($token) !== 48) {
            wp_send_json_error(array('message' => 'Neteisingas žetonas.'));
        }
        $data = get_transient('ss_qr_' . $token);
        if ($data === false) { wp_send_json_error(array('message' => 'QR kodas nebegalioja.')); }
        if ($data['status'] !== 'pending') { wp_send_json_error(array('message' => 'QR kodas jau panaudotas.')); }
        $ip_clean = trim(explode(',', $data['ip'])[0]);
        wp_send_json_success(array(
            'browser'     => self::qr_browser($data['user_agent']),
            'os'          => self::qr_os($data['user_agent']),
            'ip'          => filter_var($ip_clean, FILTER_VALIDATE_IP) ? $ip_clean : self::qr_mask_ip($data['ip']),
            'geo_city'    => $data['geo_city']    ?? '',
            'geo_country' => $data['geo_country'] ?? '',
            'created'     => $data['created'],
            'expires'     => $data['created'] + 60,
        )); // geo fields filled lazily by qr-confirm.php
    }

    /** Patvirtina prisijungimą – kviečiamas iš mobilaus įrenginio */
    public static function ajax_qr_confirm() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Reikia prisijungti.'));
        }
        $token = sanitize_text_field($_POST['token'] ?? '');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!$token || strlen($token) !== 48) {
            wp_send_json_error(array('message' => 'Neteisingas žetonas.'));
        }
        if (!wp_verify_nonce($nonce, 'ss_qr_' . $token)) {
            wp_send_json_error(array('message' => 'Saugumo klaida. Atnaujinkite puslapį.'));
        }
        // Per-user check: mobile user must have allowed QR login in their settings
        $confirming_user_id = get_current_user_id();
        if (!get_user_meta($confirming_user_id, 'ss_qr_login_allowed', true)) {
            wp_send_json_error(array('message' => 'Jūsų paskyroje QR prisijungimas neįjungtas. Įjunkite jį nustatymuose → Saugumas.'));
        }
        $data = get_transient('ss_qr_' . $token);
        if ($data === false) { wp_send_json_error(array('message' => 'QR kodas nebegalioja.')); }
        if ($data['status'] !== 'pending') { wp_send_json_error(array('message' => 'QR kodas jau panaudotas.')); }
        $data['status']  = 'confirmed';
        $data['user_id'] = get_current_user_id();
        set_transient('ss_qr_' . $token, $data, 90);
        wp_send_json_success(array('message' => 'Prisijungimas patvirtintas!'));
    }

    /** Atmeta prisijungimą */
    public static function ajax_qr_reject() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Reikia prisijungti.'));
        }
        $token = sanitize_text_field($_POST['token'] ?? '');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!$token || strlen($token) !== 48) {
            wp_send_json_error(array('message' => 'Neteisingas žetonas.'));
        }
        if (!wp_verify_nonce($nonce, 'ss_qr_' . $token)) {
            wp_send_json_error(array('message' => 'Saugumo klaida.'));
        }
        $data = get_transient('ss_qr_' . $token);
        if ($data !== false && $data['status'] === 'pending') {
            $data['status'] = 'rejected';
            set_transient('ss_qr_' . $token, $data, 60);
        }
        wp_send_json_success(array('message' => 'Prisijungimas atmestas.'));
    }

    /** Vartotojas įjungia/išjungia QR prisijungimą savo paskyroje */
    public static function ajax_settings_toggle_qr_login() {
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Reikia prisijungti.'));
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'ss_qr_toggle_' . get_current_user_id())) {
            wp_send_json_error(array('message' => 'Saugumo klaida.'));
        }
        $enabled = (int)(bool)($_POST['enabled'] ?? 0);
        update_user_meta(get_current_user_id(), 'ss_qr_login_allowed', $enabled);
        wp_send_json_success(array('enabled' => $enabled));
    }

    /* ── Pagalbinės QR funkcijos (public – kviečiamos iš view) ── */

    public static function qr_browser($ua) {
        if (preg_match('/Edg\/([\d]+)/', $ua, $m))               return 'Edge '    . $m[1];
        if (preg_match('/OPR\/([\d]+)/', $ua, $m))               return 'Opera '   . $m[1];
        if (preg_match('/Chrome\/([\d]+)/', $ua, $m))            return 'Chrome '  . $m[1];
        if (preg_match('/Firefox\/([\d]+)/', $ua, $m))           return 'Firefox ' . $m[1];
        if (preg_match('/Version\/([\d]+).*Safari/', $ua, $m))   return 'Safari '  . $m[1];
        return 'Naršyklė';
    }

    public static function qr_os($ua) {
        $nt = array('10.0'=>'Windows 10/11','6.3'=>'Windows 8.1','6.1'=>'Windows 7');
        if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) return $nt[$m[1]] ?? 'Windows';
        if (preg_match('/Mac OS X ([\d_]+)/', $ua, $m))   return 'macOS '   . str_replace('_', '.', $m[1]);
        if (preg_match('/Android ([\d.]+)/', $ua, $m))    return 'Android ' . $m[1];
        if (preg_match('/iPhone OS ([\d_]+)/', $ua, $m))  return 'iOS '     . str_replace('_', '.', $m[1]);
        if (preg_match('/Linux/', $ua))                    return 'Linux';
        return 'OS';
    }

    public static function qr_mask_ip($ip) {
        $ip = trim(explode(',', $ip)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $p = explode('.', $ip);
            return $p[0] . '.' . $p[1] . '.*.*';
        }
        return mb_substr($ip, 0, 8) . '…';
    }

}

/* ════ HELPERS ════ */
if (!function_exists('obfuscate_email')) {
    function obfuscate_email($email) {
        $parts = explode('@', $email);
        $name  = $parts[0] ?? '';
        return mb_substr($name, 0, 2) . str_repeat('*', max(2, mb_strlen($name) - 2)) . '@' . ($parts[1] ?? '');
    }
}
