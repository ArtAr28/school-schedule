<?php
/**
 * Dviejų žingsnių autentifikacija (2FA) — TOTP RFC 6238
 * Veikia su Google Authenticator, Authy, Microsoft Authenticator ir t.t.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SS_2FA {

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /* ── AJAX registracija ─────────────────────────────────────────── */

    public static function init(): void {
        add_action( 'wp_ajax_ss_2fa_setup_start',   [ __CLASS__, 'ajax_setup_start'   ] );
        add_action( 'wp_ajax_ss_2fa_setup_verify',  [ __CLASS__, 'ajax_setup_verify'  ] );
        add_action( 'wp_ajax_ss_2fa_setup_disable', [ __CLASS__, 'ajax_setup_disable' ] );
        // Viešas verifikavimas (po prisijungimo)
        add_action( 'wp_ajax_nopriv_ss_verify_2fa', [ __CLASS__, 'ajax_verify_pending' ] );
        add_action( 'wp_ajax_ss_verify_2fa',        [ __CLASS__, 'ajax_verify_pending' ] );
    }

    /* ════ TOTP matematika ═════════════════════════════════════════════ */

    /** Sugeneruoja naują base32 kodą (20 atsitiktinių baitų) */
    public static function generate_secret(): string {
        return self::base32_encode( random_bytes( 20 ) );
    }

    /** Gauna dabartinį TOTP kodą */
    public static function get_current_code( string $secret ): string {
        return self::hotp( $secret, (int) floor( time() / 30 ) );
    }

    /**
     * Tikrina 6-skaitmenį kodą (leidžia ±1 langą = 90 s).
     * SS-02 pataisa: jei perduotas $user_id, naudojama replay apsauga —
     * panaudotas counter įsimenamas ir ankstesni/lygūs counters atmetami.
     */
    public static function verify( string $secret, string $code, int $user_id = 0, int $window = 1 ): bool {
        $code = trim( $code );
        if ( ! $secret || strlen( $code ) !== 6 || ! ctype_digit( $code ) ) {
            return false;
        }
        $current = (int) floor( time() / 30 );

        $replay_on = ! class_exists('SS_Security') || SS_Security::get('ss_sec_2fa_replay_protect');
        $last_used = ($user_id && $replay_on)
            ? (int) get_user_meta( $user_id, 'ss_2fa_last_counter', true )
            : 0;

        for ( $i = -$window; $i <= $window; $i++ ) {
            $c = $current + $i;
            // Replay apsauga: atmesti jau panaudotus (≤ paskutinio) counters
            if ( $user_id && $replay_on && $c <= $last_used ) continue;

            if ( hash_equals( self::hotp( $secret, $c ), $code ) ) {
                if ( $user_id && $replay_on ) {
                    update_user_meta( $user_id, 'ss_2fa_last_counter', $c );
                }
                return true;
            }
        }
        return false;
    }

    private static function hotp( string $secret, int $counter ): string {
        $key  = self::base32_decode( $secret );
        $msg  = pack( 'N*', 0 ) . pack( 'N*', $counter );
        $hash = hash_hmac( 'sha1', $msg, $key, true );
        $off  = ord( $hash[19] ) & 0x0F;
        $code = (
            ( ( ord( $hash[ $off ]     ) & 0x7F ) << 24 ) |
            ( ( ord( $hash[ $off + 1 ] ) & 0xFF ) << 16 ) |
            ( ( ord( $hash[ $off + 2 ] ) & 0xFF ) <<  8 ) |
            ( ( ord( $hash[ $off + 3 ] ) & 0xFF ) )
        ) % 1_000_000;
        return str_pad( (string) $code, 6, '0', STR_PAD_LEFT );
    }

    /* ── Base32 ──────────────────────────────────────────────────────── */

    private static function base32_encode( string $input ): string {
        $out = '';
        $v   = $c = 0;
        foreach ( str_split( $input ) as $byte ) {
            $v  = ( $v << 8 ) | ord( $byte );
            $c += 8;
            while ( $c >= 5 ) {
                $c  -= 5;
                $out .= self::BASE32[ ( $v >> $c ) & 31 ];
            }
        }
        if ( $c > 0 ) $out .= self::BASE32[ ( $v << ( 5 - $c ) ) & 31 ];
        return $out;
    }

    private static function base32_decode( string $input ): string {
        $input = strtoupper( preg_replace( '/\s+/', '', $input ) );
        $map   = array_flip( str_split( self::BASE32 ) );
        $out   = '';
        $v = $c = 0;
        foreach ( str_split( $input ) as $ch ) {
            if ( ! isset( $map[ $ch ] ) ) continue;
            $v  = ( $v << 5 ) | $map[ $ch ];
            $c += 5;
            if ( $c >= 8 ) {
                $c  -= 8;
                $out .= chr( ( $v >> $c ) & 0xFF );
            }
        }
        return $out;
    }

    /* ════ Vartotojo meta ══════════════════════════════════════════════ */

    public static function is_enabled( int $user_id ): bool {
        return (bool) get_user_meta( $user_id, 'ss_2fa_enabled', true );
    }

    public static function get_secret( int $user_id ): string {
        return (string) ( get_user_meta( $user_id, 'ss_2fa_secret', true ) ?? '' );
    }

    /** Suformuoja otpauth:// URL prisijungimui prie Authenticator programėlės */
    public static function get_provisioning_url( int $user_id ): string {
        $secret  = self::get_secret( $user_id );
        $user    = get_user_by( 'id', $user_id );
        $account = $user ? $user->user_email : "user-{$user_id}";
        $issuer  = get_option( 'ss_school_name', get_bloginfo( 'name' ) );
        return 'otpauth://totp/'
            . rawurlencode( $issuer . ':' . $account )
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode( $issuer )
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /* ════ Laukiantys prisijungimai ═══════════════════════════════════ */

    /**
     * Sukuria 5-minučių transient su nebaigtu prisijungimu.
     * Grąžina unikalų žetoną.
     */
    public static function create_pending(
        int    $user_id,
        bool   $remember  = false,
        string $source    = 'login',
        string $redirect  = ''
    ): string {
        $token = bin2hex( random_bytes( 16 ) );
        set_transient( 'ss_2fa_p_' . $token, [
            'user_id'  => $user_id,
            'remember' => $remember,
            'source'   => $source,
            'redirect' => $redirect ?: ( home_url( '/' ) ),
        ], 300 );
        return $token;
    }

    /** Gauna ir ištrina pending duomenis (vienkartinis naudojimas) */
    public static function consume_pending( string $token ): ?array {
        $data = get_transient( 'ss_2fa_p_' . $token );
        if ( $data === false ) return null;
        delete_transient( 'ss_2fa_p_' . $token );
        return $data;
    }

    /** Peek (be ištrinimo) — naudojama formai renderinti */
    public static function peek_pending( string $token ): ?array {
        $data = get_transient( 'ss_2fa_p_' . $token );
        return $data !== false ? $data : null;
    }

    /**
     * Prisijungti po sėkmingo 2FA verifikavimo.
     * Naudoja DOING_AJAX flagą, kad WP_Login hook neperimtų.
     */
    public static function do_login( int $user_id, bool $remember = false ): void {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) return;
        wp_clear_auth_cookie();
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, $remember );
        // Pastaba: wp_login action tyčia nekviečiama – tai neleis WP_Login hookui
        // dar kartą perimti ir sukurti begalinį apsukimą.
    }

    /* ════ AJAX tvarkyklės ════════════════════════════════════════════ */

    /** 1. Pradėti 2FA nustatymą — sugeneruoja laikiną slaptąjį raktą */
    public static function ajax_setup_start(): void {
        if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Reikia prisijungti.' ] );
        $nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, 'ss_2fa_setup_' . get_current_user_id() ) ) {
            wp_send_json_error( [ 'message' => 'Saugumo klaida.' ] );
        }
        $uid    = get_current_user_id();
        $secret = self::generate_secret();
        // Laikinai saugoti seanse (ne įjungtas dar)
        set_transient( 'ss_2fa_tmp_' . $uid, $secret, 600 );

        $prov_url = self::get_provisioning_url_with_secret( $uid, $secret );

        // SS-03 pataisa: QR generuojamas kliento naršyklėje (qrcode-generator),
        // todėl TOTP slaptasis raktas niekada nesiunčiamas į trečiųjų šalių servisą.
        wp_send_json_success( [
            'secret'  => $secret,
            'prov'    => $prov_url,
        ] );
    }

    private static function get_provisioning_url_with_secret( int $user_id, string $secret ): string {
        $user   = get_user_by( 'id', $user_id );
        $acct   = $user ? $user->user_email : "user-{$user_id}";
        $issuer = get_option( 'ss_school_name', get_bloginfo( 'name' ) );
        return 'otpauth://totp/'
            . rawurlencode( $issuer . ':' . $acct )
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode( $issuer )
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /** 2. Patvirtinti verifikavimo kodą ir įjungti 2FA */
    public static function ajax_setup_verify(): void {
        if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Reikia prisijungti.' ] );
        $nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, 'ss_2fa_setup_' . get_current_user_id() ) ) {
            wp_send_json_error( [ 'message' => 'Saugumo klaida.' ] );
        }
        $uid    = get_current_user_id();
        $code   = preg_replace( '/\D/', '', $_POST['code'] ?? '' );
        $secret = get_transient( 'ss_2fa_tmp_' . $uid );
        if ( ! $secret ) {
            wp_send_json_error( [ 'message' => 'Sesija pasibaigė. Pradėkite iš naujo.' ] );
        }
        if ( ! self::verify( $secret, $code, $uid ) ) {
            wp_send_json_error( [ 'message' => 'Neteisingas kodas. Patikrinkite laiką telefone.' ] );
        }
        delete_transient( 'ss_2fa_tmp_' . $uid );
        delete_user_meta( $uid, 'ss_2fa_last_counter' );
        update_user_meta( $uid, 'ss_2fa_secret',  $secret );
        update_user_meta( $uid, 'ss_2fa_enabled', 1 );

        // Sugeneruoti atsarginius kodus (jei įjungta)
        $backup = [];
        if ( ! class_exists('SS_Security') || SS_Security::get('ss_sec_2fa_backup_codes') ) {
            $backup = self::generate_backup_codes( $uid );
        }
        if ( class_exists('SS_AuditLog') ) {
            SS_AuditLog::write( '2fa_enabled', [ 'user_id' => $uid ] );
        }
        if ( class_exists('SS_Achievements') ) SS_Achievements::bump( $uid, 'twofa' );
        wp_send_json_success( [ 'message' => '2FA sėkmingai įjungta.', 'backup_codes' => $backup ] );
    }

    /** 3. Išjungti 2FA (reikia patvirtinti galiojančiu kodu) */
    public static function ajax_setup_disable(): void {
        if ( ! is_user_logged_in() ) wp_send_json_error( [ 'message' => 'Reikia prisijungti.' ] );
        $nonce = sanitize_text_field( $_POST['nonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, 'ss_2fa_setup_' . get_current_user_id() ) ) {
            wp_send_json_error( [ 'message' => 'Saugumo klaida.' ] );
        }
        $uid    = get_current_user_id();
        $code   = preg_replace( '/\D/', '', $_POST['code'] ?? '' );
        $secret = self::get_secret( $uid );
        if ( ! $secret ) {
            wp_send_json_error( [ 'message' => '2FA nėra įjungta.' ] );
        }
        // SS-02: Perduoti $uid replay apsaugai
        if ( ! self::verify( $secret, $code, $uid ) ) {
            wp_send_json_error( [ 'message' => 'Neteisingas kodas.' ] );
        }
        delete_user_meta( $uid, 'ss_2fa_enabled' );
        delete_user_meta( $uid, 'ss_2fa_secret' );
        delete_user_meta( $uid, 'ss_2fa_last_counter' );
        delete_user_meta( $uid, 'ss_2fa_backup_codes' );
        if ( class_exists('SS_AuditLog') ) {
            SS_AuditLog::write( '2fa_disabled', [ 'user_id' => $uid ] );
        }
        wp_send_json_success( [ 'message' => '2FA išjungta.' ] );
    }

    /** 4. Patvirtinti laukiantį prisijungimą (iš login.php) */
    public static function ajax_verify_pending(): void {
        $token    = sanitize_text_field( $_POST['token'] ?? '' );
        // Saugoma originali reikšmė atsarginiam kodui (gali turėti A-F raides).
        // \D regex pašalina hex raides, todėl backup tikrinimui naudojamas $raw_code.
        $raw_code = strtoupper( trim( sanitize_text_field( $_POST['code'] ?? '' ) ) );
        $code     = preg_replace( '/\D/', '', $raw_code ); // TOTP: tik skaitmenys

        if ( ! $token ) wp_send_json_error( [ 'message' => 'Trūksta žetono.' ] );

        $data = self::peek_pending( $token );
        if ( ! $data ) {
            wp_send_json_error( [ 'message' => 'Sesija pasibaigė. Prisijunkite iš naujo.' ] );
        }

        $user_id = (int) $data['user_id'];
        $ip      = class_exists('SS_RateLimit') ? SS_RateLimit::get_client_ip() : '';

        // Patikrinti, ar įrenginys užblokuotas — prieš bet kokį kodo tikrinimą
        if ( class_exists('SS_AuditLog') && SS_AuditLog::is_device_blocked($user_id, $ip) ) {
            self::consume_pending($token);
            SS_AuditLog::write('login_blocked', ['user_id' => $user_id, 'ip' => $ip, 'reason' => 'device_blocked', 'source' => '2fa']);
            wp_send_json_error( [ 'message' => 'Šis įrenginys yra užblokuotas.', 'code' => 'device_blocked' ] );
        }

        // 2FA bandymų ribojimas (žr. rl_* helperius). Jei šiuo metu užblokuota —
        // iškart blokuojam ir grąžinam likusį laiką (priekinė dalis rodo laikmatį).
        $username   = ($u = get_user_by('id', $user_id)) ? $u->user_login : '';
        $locked_for = self::rl_locked_for($user_id);
        if ($locked_for > 0) {
            self::consume_pending($token); // priverstinis pakartotinis prisijungimas
            if ( class_exists('SS_AuditLog') ) {
                SS_AuditLog::write('2fa_failed', [
                    'user_id'  => $user_id,
                    'username' => $username,
                    'ip'       => $ip,
                    'reason'   => 'rate_limited',
                    'source'   => $data['source'] ?? 'login',
                ]);
            }
            wp_send_json_error( [
                'message'     => 'Per daug neteisingų kodų. Saugumo sumetimais palaukite ~5 min. ir prisijunkite iš naujo.',
                'code'        => 'rate_limited',
                'retry_after' => $locked_for,
            ] );
        }

        $secret = self::get_secret( $user_id );
        if ( ! $secret ) {
            wp_send_json_error( [ 'message' => '2FA slaptasis raktas nerastas.' ] );
        }

        // SS-02: Perduoti $user_id replay apsaugai
        // 3.2: Pirmiausia patikrinti backup code.
        // SVARBU: $raw_code naudojamas, nes $code (preg_replace \D) pašalina A-F raides.
        // Backup kodas = 8 hex simboliai; išvalome viską, kas nėra hex, kad suderintume
        // ir su brūkšneliais ar tarpais atskirtu formatu (pvz. "A3F1-B2C4").
        $verified        = false;
        $method          = '2fa';
        $backup_candidate = preg_replace( '/[^A-F0-9]/i', '', $raw_code );
        if ( strlen( $backup_candidate ) === 8 && ctype_xdigit( $backup_candidate ) ) {
            if ( self::use_backup_code( $user_id, $backup_candidate ) ) {
                $verified = true;
                $method   = 'backup_code';
            }
        }
        if ( ! $verified ) {
            $verified = self::verify( $secret, $code, $user_id );
        }

        if ( ! $verified ) {
            $fail = self::rl_register_failure($user_id, $ip);
            if ( class_exists('SS_AuditLog') ) {
                SS_AuditLog::write('2fa_failed', [
                    'user_id' => $user_id,
                    'username'=> $username,
                    'ip'      => $ip,
                    'reason'  => $fail['locked'] ? 'rate_limited' : 'invalid_code',
                    'limit'   => self::RL_MAX,
                    'input'   => ( strlen($backup_candidate) === 8 ? 'backup_code' : 'totp' ),
                ]);
            }
            // Pasiekus ribą — 5 min. blokas (laikmatis priekinėje dalyje), be naujo žetono.
            if ( $fail['locked'] ) {
                self::consume_pending( $token );
                wp_send_json_error( [
                    'message'     => 'Per daug bandymų įvesti kodą. Saugumo tikslais palaukite 5 min. ir prisijunkite iš naujo.',
                    'code'        => 'rate_limited',
                    'retry_after' => $fail['retry_after'],
                ] );
            }
            // Kitu atveju — naujas žetonas, kad vartotojas galėtų bandyti dar kartą.
            $new_token = self::create_pending(
                $user_id,
                (bool) $data['remember'],
                $data['source']   ?? 'login',
                $data['redirect'] ?? ''
            );
            wp_send_json_error( [
                'message' => 'Neteisingas kodas. Bandykite dar kartą.',
                'token'   => $new_token,
            ] );
        }

        // Sėkmingas — sunaikinti pending ir prisijungti
        self::consume_pending( $token );
        self::rl_clear( $user_id );

        // Atsarginio kodo panaudojimas — pranešam vartotojui el. paštu.
        if ( $method === 'backup_code' ) {
            self::notify_backup_used( $user_id, $ip );
        }

        if ( class_exists('SS_AuditLog') ) {
            SS_AuditLog::write('2fa_ok',   ['user_id' => $user_id, 'ip' => $ip, 'method' => $method]);
            SS_AuditLog::write('login_ok', ['user_id' => $user_id, 'ip' => $ip, 'method' => $method]);
            // check_new_device viduje kviečia record_login_history — kaupia įrenginių istoriją
            SS_AuditLog::check_new_device($user_id, $ip, $method);
        }

        // Priverstinis slaptažodžio keitimas (po „Pamiršau slaptažodį") — po 2FA.
        if ( get_user_meta( $user_id, 'ss_must_change_pw', true ) && class_exists('SS_Shortcodes') ) {
            SS_Shortcodes::start_pw_change( $user_id, (bool) $data['remember'] ); // wp_send_json → exit
            return;
        }

        self::do_login( $user_id, (bool) $data['remember'] );
        wp_send_json_success( [
            'redirect' => $data['redirect'] ?: ( home_url( '/' ) ),
        ] );
    }

    /* ════ Atsarginiai kodai (Backup Codes) ══════════════════════════ */

    /**
     * Sugeneruoja 10 vienkartinių atsarginių kodų.
     * Saugo tik maišų reikšmes. Grąžina neapsaugotus kodus (parodyti vartotojui VIENĄ KARTĄ).
     */
    public static function generate_backup_codes( int $user_id ): array {
        $codes  = [];
        $hashes = [];
        for ( $i = 0; $i < 10; $i++ ) {
            $code     = strtoupper( bin2hex( random_bytes( 4 ) ) ); // 8 hex simboliai
            $codes[]  = $code;
            $hashes[] = wp_hash_password( $code );
        }
        update_user_meta( $user_id, 'ss_2fa_backup_codes', $hashes );
        return $codes; // Parodyti vartotojui VIENĄ KARTĄ
    }

    /**
     * Panaudoti atsarginį kodą (vienkartinis).
     * Pašalina iš sąrašo po sėkmingo naudojimo.
     */
    public static function use_backup_code( int $user_id, string $code ): bool {
        $hashes = (array) get_user_meta( $user_id, 'ss_2fa_backup_codes', true );
        $code   = strtoupper( preg_replace( '/[^A-F0-9]/i', '', $code ) );
        if ( ! $code ) return false;
        foreach ( $hashes as $i => $hash ) {
            if ( wp_check_password( $code, $hash ) ) {
                unset( $hashes[ $i ] );
                update_user_meta( $user_id, 'ss_2fa_backup_codes', array_values( $hashes ) );
                if ( class_exists('SS_AuditLog') ) {
                    SS_AuditLog::write( '2fa_backup_used', [ 'user_id' => $user_id ] );
                }
                return true;
            }
        }
        return false;
    }

    /** Grąžina likusių atsarginių kodų skaičių */
    public static function backup_codes_remaining( int $user_id ): int {
        return count( (array) get_user_meta( $user_id, 'ss_2fa_backup_codes', true ) );
    }

    /* ════ 2FA bandymų ribojimas (bendras AJAX ir wp-login keliams) ══════════
       Ribojama VARTOTOJUI (ne žetonui): po nesėkmės išduodamas naujas žetonas,
       todėl skaitiklis pagal žetoną būtų apeinamas. Pasiekus ribą — fiksuotas
       5 min. blokas (laikmatis priekinėje dalyje) + įspėjimas el. paštu. */
    private const RL_MAX = 5;
    private const RL_TTL = 300; // 5 min. (sutampa su pending TTL)

    /** Sekundės iki bloko pabaigos, jei vartotojas šiuo metu užblokuotas; kitaip 0. */
    public static function rl_locked_for( int $user_id ): int {
        $until = (int) get_transient( 'ss_2fa_lock_u' . $user_id );
        return $until > time() ? $until - time() : 0;
    }

    /**
     * Užregistruoja nesėkmingą 2FA bandymą. Pasiekus ribą — uždeda fiksuotą
     * 5 min. bloką, išvalo skaitiklį ir el. paštu įspėja vartotoją (slaptažodis
     * teisingas, bet 2FA nepavyko ⇒ galimas įsilaužimas).
     * @return array{locked:bool,retry_after:int}
     */
    public static function rl_register_failure( int $user_id, string $ip ): array {
        $cnt = (int) get_transient( 'ss_2fa_rl_u' . $user_id ) + 1;
        set_transient( 'ss_2fa_rl_u' . $user_id, $cnt, self::RL_TTL );
        if ( $cnt >= self::RL_MAX ) {
            set_transient( 'ss_2fa_lock_u' . $user_id, time() + self::RL_TTL, self::RL_TTL );
            delete_transient( 'ss_2fa_rl_u' . $user_id );
            self::notify_lockout( $user_id, $ip );
            return [ 'locked' => true, 'retry_after' => self::RL_TTL ];
        }
        return [ 'locked' => false, 'retry_after' => 0 ];
    }

    /** Po sėkmingo prisijungimo — išvalo skaitiklį ir bloką. */
    public static function rl_clear( int $user_id ): void {
        delete_transient( 'ss_2fa_rl_u' . $user_id );
        delete_transient( 'ss_2fa_lock_u' . $user_id );
    }

    /** El. laiškas: kažkas įvedė teisingą slaptažodį, bet kelis kartus nepavyko 2FA. */
    public static function notify_lockout( int $user_id, string $ip ): void {
        if ( ! class_exists( 'SS_Mail' ) ) return;
        $user = get_user_by( 'id', $user_id );
        if ( ! $user || ! $user->user_email ) return;
        $ctx = class_exists( 'SS_AuditLog' )
            ? SS_AuditLog::context_email_html( $ip )
            : '<p style="margin:0;">IP: <strong>' . esc_html( $ip ) . '</strong></p>';
        SS_Mail::send( $user->user_email, 'Nepavykęs dviejų žingsnių patvirtinimas', [
            'title'     => 'Įspėjimas apie saugumą',
            'accent'    => SS_Mail::RED,
            'icon'      => '!',
            'body'      => '<p style="margin:0 0 4px;">Jungiantis prie Jūsų paskyros buvo įvestas <strong>teisingas slaptažodis</strong>, tačiau kelis kartus iš eilės <strong>nepavyko įvesti teisingo dviejų žingsnių patvirtinimo (2FA) kodo</strong>. Prisijungimas laikinai užblokuotas 5 min. Bandymo informacija:</p>',
            'extra'     => $ctx,
            'note'      => 'Jei bandėte prisijungti ne Jūs — rekomenduojame nedelsiant pakeisti slaptažodį.',
            'note_type' => 'warn',
        ] );
    }

    /** El. laiškas: prisijungta panaudojant ATSARGINĮ (vienkartinį) 2FA kodą. */
    public static function notify_backup_used( int $user_id, string $ip ): void {
        if ( ! class_exists( 'SS_Mail' ) ) return;
        $user = get_user_by( 'id', $user_id );
        if ( ! $user || ! $user->user_email ) return;
        $remaining = self::backup_codes_remaining( $user_id );
        $ctx = class_exists( 'SS_AuditLog' )
            ? SS_AuditLog::context_email_html( $ip, '', 'backup_code' )
            : '<p style="margin:0;">IP: <strong>' . esc_html( $ip ) . '</strong></p>';
        SS_Mail::send( $user->user_email, 'Panaudotas atsarginis 2FA kodas', [
            'title'     => 'Panaudotas atsarginis kodas',
            'icon'      => '🔑',
            'body'      => '<p style="margin:0 0 4px;">Prisijungiant prie Jūsų paskyros buvo panaudotas <strong>atsarginis (vienkartinis) 2FA kodas</strong>. Liko nepanaudotų atsarginių kodų: <strong>' . (int) $remaining . '</strong>.</p>',
            'extra'     => $ctx,
            'note'      => 'Jei tai buvote ne Jūs — nedelsiant pakeiskite slaptažodį ir sugeneruokite naujus atsarginius kodus. Po slaptažodžio pakeitimo bus atjungti nuo paskyros visi įrenginiai.',
            'note_type' => 'warn',
        ] );
    }
}
