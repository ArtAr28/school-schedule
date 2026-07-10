<?php
/**
 * SS_Security — centrinė saugumo modulio logika:
 *   • numatytųjų nustatymų registravimas
 *   • slaptažodžių stiprumo validacija
 *   • cron užduotys (žurnalo valymas)
 *   • aktyvacijos hook'ai
 */
if (!defined('ABSPATH')) exit;
 
class SS_Security {

    /**
     * Visada įjungtos apsaugos — jų NEGALIMA išjungti per nustatymus.
     * get() šiems raktams visada grąžina 1, nepriklausomai nuo DB reikšmės.
     */
    public const ALWAYS_ON = [
        'ss_sec_bruteforce_enabled',   // brute-force apsauga
        'ss_sec_honeypot_enabled',     // honeypot laukas login formoje
        'ss_sec_audit_enabled',        // audito žurnalas
        'ss_sec_2fa_replay_protect',   // TOTP replay apsauga
        'ss_sec_2fa_backup_codes',     // atsarginiai 2FA kodai
        'ss_sec_invalidate_on_pw',     // sesijų panaikinimas po slaptažodžio keitimo
        'ss_sec_pw_block_common',      // dažnų slaptažodžių blokavimas
        'ss_sec_reg_role_from_email',  // rolė nustatoma serveryje (SS-07)
    ];

    /** Numatytieji nustatymai (option => default). */
    public static function defaults(): array {
        return [
            // Brute force — paskyros lygis (IP + vartotojas)
            'ss_sec_lockout_threshold_1'  => 5,
            'ss_sec_lockout_threshold_2'  => 10,
            'ss_sec_lockout_threshold_3'  => 20,
            'ss_sec_lockout_minutes_1'    => 1,
            'ss_sec_lockout_minutes_2'    => 5,
            'ss_sec_lockout_minutes_3'    => 30,
            // Pastaba: IP lygio blokas SĄMONINGAI nenaudojamas — visa mokykla už vieno
            // NAT IP, todėl bendras IP blokavimas užrakintų visus vienu metu. Apsauga
            // veikia per-paskyrą (IP + vartotojo vardas), o credential-stuffing aptinka
            // IDS (SS_AuditLog::maybe_alert_admin) ir įspėja administratorių el. paštu.
            // Kiti nustatymai
            'ss_sec_trust_proxy'          => 0,
            'ss_sec_login_alert_enabled'  => 1,
            'ss_sec_login_alert_threshold'=> 3,
            // Passwords
            'ss_sec_pw_require_upper'     => 1,
            'ss_sec_pw_require_digit'     => 1,
            'ss_sec_pw_min_length'        => 8,
            // Registration
            'ss_sec_reg_rate_limit'       => 5,
            // Audit / IDS
            'ss_sec_audit_retention_days' => 90,
            'ss_sec_ids_enabled'          => 1,
            'ss_sec_ids_window_min'       => 15,
            'ss_sec_ids_threshold'        => 10,
            'ss_sec_new_device_enabled'   => 1,
            // Sessions
            'ss_sec_max_sessions'         => 5,
        ];
    }
 
    /** Įdiegia numatytuosius nustatymus (tik jei dar nėra). */
    public static function install_defaults(): void {
        foreach (self::defaults() as $opt => $val) {
            if (get_option($opt, null) === null) add_option($opt, $val);
        }
    }
 
    public static function get(string $key) {
        // Kritinės apsaugos visada įjungtos — DB reikšmė ignoruojama
        if (in_array($key, self::ALWAYS_ON, true)) return 1;
        $defaults = self::defaults();
        return get_option($key, $defaults[$key] ?? null);
    }
 
    /* ── Slaptažodžio stiprumo validacija ──────────────────────────── */
    /**
     * @return string|null  Klaidos pranešimas arba null jei viskas gerai.
     */
    public static function validate_password(string $pw, string $email = ''): ?string {
        $min = max(8, (int) self::get('ss_sec_pw_min_length'));
        if (strlen($pw) < $min) {
            return sprintf('Slaptažodis turi būti bent %d simbolių.', $min);
        }
        if (self::get('ss_sec_pw_require_upper') && !preg_match('/[A-ZĄČĘĖĮŠŲŪŽ]/u', $pw)) {
            return 'Slaptažodyje turi būti bent viena didžioji raidė.';
        }
        if (self::get('ss_sec_pw_require_digit') && !preg_match('/[0-9]/', $pw)) {
            return 'Slaptažodyje turi būti bent vienas skaičius.';
        }
        if ($email) {
            $local = strtolower(strstr($email, '@', true) ?: $email);
            if (strlen($local) > 3 && stripos($pw, $local) !== false) {
                return 'Slaptažodis negali sutapti su el. pašto adresu.';
            }
        }
        if (self::get('ss_sec_pw_block_common')) {
            $blocklist = ['password','12345678','123456789','qwerty123','iloveyou',
                          'password1','slaptazodis','slaptazodis1','mokykla123',
                          'azuolynas','azuolynas1','11111111','00000000','qwertyui'];
            if (in_array(strtolower($pw), $blocklist, true)) {
                return 'Slaptažodis per dažnai naudojamas. Pasirinkite saugesnį.';
            }
        }
        return null;
    }
 
    /* ── Cron ───────────────────────────────────────────────────────── */
    public static function init(): void {
        add_action('ss_daily_maintenance', [__CLASS__, 'daily_maintenance']);
    }
 
    public static function schedule_cron(): void {
        if (!wp_next_scheduled('ss_daily_maintenance')) {
            wp_schedule_event(strtotime('tomorrow 03:00'), 'daily', 'ss_daily_maintenance');
        }
    }
 
    public static function unschedule_cron(): void {
        $ts = wp_next_scheduled('ss_daily_maintenance');
        if ($ts) wp_unschedule_event($ts, 'ss_daily_maintenance');
    }
 
    public static function daily_maintenance(): void {
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::prune((int) self::get('ss_sec_audit_retention_days'));
        }
        if (function_exists('delete_expired_transients')) {
            delete_expired_transients();
        }
    }
 
    /** Iškvieskite aktyvacijos metu. */
    public static function activate(): void {
        self::install_defaults();
        if (class_exists('SS_AuditLog')) SS_AuditLog::install();
        self::schedule_cron();
    }
}
 