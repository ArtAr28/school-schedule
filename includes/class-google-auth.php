<?php
/**
 * Google OAuth 2.0 prisijungimas ir paskyros susiejimas
 */
if (!defined('ABSPATH')) exit;

class SS_Google_Auth {

    /* ── Inicializacija ─────────────────────────────────── */

    public static function init() {
        // AJAX: gauti Google auth URL (prisijungimui arba susiejimui)
        add_action('wp_ajax_nopriv_ss_google_start',  [__CLASS__, 'ajax_start']);
        add_action('wp_ajax_ss_google_start',          [__CLASS__, 'ajax_start']);
        // AJAX: atsusieti Google paskyrą
        add_action('wp_ajax_ss_google_unlink',         [__CLASS__, 'ajax_unlink']);
        // Registruoti query kintamuosius, kad WordPress jų nenutrintų
        add_filter('query_vars', [__CLASS__, 'register_query_vars']);
        // OAuth callback – turi veikti prieš pagrindinį template_redirect
        add_action('template_redirect', [__CLASS__, 'handle_callback'], 1);
    }

    public static function register_query_vars(array $vars): array {
        $vars[] = 'ss_google_cb';
        $vars[] = 'code';
        $vars[] = 'state';
        return $vars;
    }

    /* ── Būsenos tikrinimas ─────────────────────────────── */

    public static function is_configured(): bool {
        return !empty(get_option('ss_google_client_id')) && self::get_secret() !== '';
    }

    public static function is_enabled(): bool {
        // Laikinas Google prisijungimo blokavimas (Aptarnavimas → Prieigos blokavimai)
        if (class_exists('SS_Maintenance') && SS_Maintenance::google_blocked()) return false;
        return (bool)get_option('ss_google_login_enabled', 0) && self::is_configured();
    }

    /* ── Client secret: stored encrypted at rest, autoload off ──────────── */

    /** 32-byte key derived from wp-config salts (never leaves the server). */
    private static function secret_key(): string {
        $salt = (defined('AUTH_KEY') ? AUTH_KEY : '')
              . (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '')
              . (defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '');
        if ($salt === '') $salt = (defined('DB_NAME') ? DB_NAME : 'ss') . get_option('ss_db_version', 'x');
        return hash('sha256', 'ss-google-secret|' . $salt, true);
    }

    /** Encrypt + store the secret with autoload disabled. */
    public static function set_secret(string $plain): void {
        $plain = trim($plain);
        if ($plain === '') return;
        if (function_exists('openssl_encrypt')) {
            $iv  = random_bytes(16);
            $ct  = openssl_encrypt($plain, 'aes-256-cbc', self::secret_key(), OPENSSL_RAW_DATA, $iv);
            $val = ($ct !== false) ? 'enc:' . base64_encode($iv . $ct) : $plain;
        } else {
            $val = $plain; // no OpenSSL — still kept out of the autoload cache
        }
        if (get_option('ss_google_client_secret') === false) {
            add_option('ss_google_client_secret', $val, '', 'no');
        } else {
            update_option('ss_google_client_secret', $val, false);
        }
    }

    /** Decrypt + return the secret ('' if unset). Transparently reads legacy plaintext. */
    public static function get_secret(): string {
        $val = (string) get_option('ss_google_client_secret', '');
        if ($val === '') return '';
        if (strncmp($val, 'enc:', 4) === 0) {
            if (!function_exists('openssl_decrypt')) return '';
            $raw = base64_decode(substr($val, 4), true);
            if ($raw === false || strlen($raw) < 17) return '';
            $pt = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', self::secret_key(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
            return $pt === false ? '' : $pt;
        }
        return $val; // legacy plaintext
    }

    /* ── Callback URL (turi sutapti su Google Console) ─── */

    public static function get_callback_url(): string {
        // Normalizuoti URL: visada HTTPS, be dvigubų slashų, su ?ss_google_cb=1
        $url = home_url('/');

        // Forsuoti HTTPS — reverse proxy ar SSL offload aplinkose
        // home_url() gali grąžinti http:// net jei puslapis veikia per HTTPS
        if (
            is_ssl()
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        ) {
            $url = preg_replace('#^http://#i', 'https://', $url);
        }

        return rtrim($url, '/') . '/?ss_google_cb=1';
    }

    /* ── Sukurti Google autorizacijos URL ───────────────── */

    public static function get_auth_url(string $mode = 'login', string $return_url = ''): string {
        $state = bin2hex(random_bytes(16)); // 32 hex simboliai
        set_transient('ss_google_state_' . $state, [
            'mode'       => $mode,
            'return_url' => $return_url ?: home_url('/'),
            'user_id'    => get_current_user_id(),
        ], 600); // 10 min galiojimas

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => get_option('ss_google_client_id'),
            'redirect_uri'  => self::get_callback_url(),
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'prompt'        => 'select_account',
        ]);
    }

    /* ── OAuth callback – apdorojamas anksčiau nei kiti template_redirect ─ */

    public static function handle_callback(): void {
        // Tikrinti $_GET tiesiogiai – WP query vars ne visada perduoda visus parametrus
        if (empty($_GET['ss_google_cb']) || empty($_GET['code'])) return;

        $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
        $code  = sanitize_text_field(wp_unslash($_GET['code']  ?? ''));

        if (!$state || !$code) {
            wp_die('Klaida: trūksta parametrų.', 'Google autentifikavimo klaida', ['response' => 400]);
        }

        $sd = get_transient('ss_google_state_' . $state);
        if ($sd === false) {
            wp_die('Klaida: neteisingas arba pasibaigęs state žetonas. Bandykite dar kartą.',
                   'Google autentifikavimo klaida', ['response' => 400]);
        }
        delete_transient('ss_google_state_' . $state);

        /* Pakeisti kodą į žetonus */
        $token_resp = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 15,
            'body'    => [
                'code'          => $code,
                'client_id'     => get_option('ss_google_client_id'),
                'client_secret' => self::get_secret(),
                'redirect_uri'  => self::get_callback_url(),
                'grant_type'    => 'authorization_code',
            ],
        ]);

        if (is_wp_error($token_resp)) {
            wp_die('Klaida: nepavyko susisiekti su Google: ' . esc_html($token_resp->get_error_message()),
                   'Google autentifikavimo klaida');
        }

        $tokens = json_decode(wp_remote_retrieve_body($token_resp), true);
        if (empty($tokens['id_token'])) {
            wp_die('Klaida: neteisingas Google atsakymas. Bandykite dar kartą.',
                   'Google autentifikavimo klaida');
        }

        // SAUGUMAS: id_token parašas IR teiginiai (iss/aud/exp) tikrinami Google pusėje.
        $info = self::verify_id_token($tokens['id_token']);
        if (!$info || empty($info['sub'])) {
            if (class_exists('SS_AuditLog')) {
                SS_AuditLog::write('google_token_invalid', ['method' => 'google']);
            }
            wp_die('Klaida: nepavyko patikrinti Google tapatybės žetono.',
                   'Google autentifikavimo klaida');
        }

        $google_id    = sanitize_text_field($info['sub']);
        $google_email = sanitize_email($info['email'] ?? '');

        /* ── SUSIEJIMO režimas ── */
        if ($sd['mode'] === 'link') {
            $uid = (int)$sd['user_id'];
            if (!$uid || !get_user_by('id', $uid)) {
                wp_die('Klaida: vartotojas nerastas.', 'Google autentifikavimo klaida');
            }
            $already = self::find_user_by_google_id($google_id);
            if ($already && (int)$already->ID !== $uid) {
                wp_die('Ši Google paskyra jau susieta su kita paskyra.',
                       'Google autentifikavimo klaida');
            }
            update_user_meta($uid, 'ss_google_id',        $google_id);
            update_user_meta($uid, 'ss_google_email',     $google_email);
            update_user_meta($uid, 'ss_google_linked_at', time());
            wp_redirect(add_query_arg('ss_google_linked', '1', $sd['return_url']));
            exit;
        }

        /* ── PRISIJUNGIMO režimas ── */
        if (!self::is_enabled()) {
            wp_die('Google prisijungimas šiuo metu išjungtas.', 'Google autentifikavimo klaida');
        }

        $wp_user = self::find_user_by_google_id($google_id);
        if (!$wp_user) {
            $login_url = get_permalink(get_option('ss_login_page_id')) ?: home_url('/');
            wp_redirect(add_query_arg('ss_google_err', 'not_linked', $login_url));
            exit;
        }

        $dash = get_permalink(get_option('ss_dashboard_page_id')) ?: home_url('/');
        // 2FA patikrinimas
        if (SS_2FA::is_enabled($wp_user->ID)) {
            $fa_token = SS_2FA::create_pending($wp_user->ID, true, 'google', $dash);
            $login_url = get_permalink(get_option('ss_login_page_id')) ?: home_url('/');
            wp_redirect(add_query_arg('ss_2fa_pending', $fa_token, $login_url));
            exit;
        }
        wp_set_current_user($wp_user->ID);
        wp_set_auth_cookie($wp_user->ID, true);
        do_action('wp_login', $wp_user->user_login, $wp_user);
        wp_redirect($dash);
        exit;
    }

    /* ── Rasti WP vartotoją pagal Google ID ─────────────── */

    public static function find_user_by_google_id(string $google_id): ?WP_User {
        $users = get_users([
            'meta_key'   => 'ss_google_id',
            'meta_value' => $google_id,
            'number'     => 1,
            'fields'     => 'all',
        ]);
        return $users ? $users[0] : null;
    }

    /* ── Patikrinti JWT parašą IR teiginius (RFC 7519 + Google certs) ─ */

    /**
     * Saugiai patikrina Google id_token:
     *   1. parašą prieš Google viešus RSA raktus (RS256),
     *   2. iss == accounts.google.com,
     *   3. aud == mūsų client_id,
     *   4. exp dar nepasibaigęs.
     * Jei vietinis parašo tikrinimas neįmanomas — atsarginis variantas
     * yra Google tokeninfo endpoint (patikrina parašą Google pusėje).
     *
     * @return array|null  Patvirtintas payload arba null.
     */
    public static function verify_id_token(string $jwt): ?array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;

        $header  = self::b64url_decode_json($parts[0]);
        $payload = self::b64url_decode_json($parts[1]);
        if (!is_array($header) || !is_array($payload)) return null;

        $client_id = (string) get_option('ss_google_client_id', '');

        // 1) Bandyti vietinį parašo tikrinimą (OpenSSL + Google certs).
        $local_ok = self::verify_signature_locally($parts, $header);

        // 2) Jei vietinis tikrinimas negalimas (nėra OpenSSL/raktų),
        //    pasitelkti Google tokeninfo (parašas tikrinamas Google pusėje).
        if ($local_ok === null) {
            $verified = self::verify_via_tokeninfo($jwt);
            if (!$verified) return null;
            $payload = $verified; // naudoti Google grąžintus (jau patikrintus) teiginius
        } elseif ($local_ok === false) {
            return null; // parašas tikrintas vietoje ir NEGALIOJA
        }

        // 3) Teiginių tikrinimas.
        $iss = $payload['iss'] ?? '';
        if (!in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return null;
        }
        if ($client_id && (($payload['aud'] ?? '') !== $client_id)) {
            return null;
        }
        if (!empty($payload['exp']) && (int) $payload['exp'] < (time() - 60)) {
            return null; // pasibaigęs (su 60s tolerancija laikrodžio nuokrypiui)
        }
        // El. paštas turi būti patvirtintas Google pusėje
        if (isset($payload['email_verified'])) {
            $ev = $payload['email_verified'];
            if ($ev !== true && $ev !== 'true' && $ev !== 1 && $ev !== '1') return null;
        }
        return $payload;
    }

    /**
     * Vietinis RS256 parašo tikrinimas prieš Google viešus raktus.
     * @return bool|null  true=galioja, false=negalioja, null=tikrinti neįmanoma.
     */
    private static function verify_signature_locally(array $parts, array $header): ?bool {
        if (!function_exists('openssl_verify')) return null;
        $alg = strtoupper($header['alg'] ?? '');
        $kid = $header['kid'] ?? '';
        if ($alg !== 'RS256' || !$kid) return null;

        $certs = self::get_google_certs();
        if (!$certs || empty($certs[$kid])) return null;

        $signature = self::b64url_decode($parts[2]);
        if ($signature === false || $signature === '') return null;

        $signed = $parts[0] . '.' . $parts[1];
        $result = openssl_verify($signed, $signature, $certs[$kid], OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /** Parsisiunčia ir kešuoja Google viešus sertifikatus (kid => PEM). */
    private static function get_google_certs(): ?array {
        $cached = get_transient('ss_google_certs');
        if (is_array($cached)) return $cached;

        $resp = wp_remote_get('https://www.googleapis.com/oauth2/v1/certs', ['timeout' => 15]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;

        $certs = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($certs) || !$certs) return null;

        // Kešuoti pagal Cache-Control max-age (numatytai 1 val.)
        $ttl     = HOUR_IN_SECONDS;
        $cc      = wp_remote_retrieve_header($resp, 'cache-control');
        if ($cc && preg_match('/max-age=(\d+)/', $cc, $mm)) {
            $ttl = max(MINUTE_IN_SECONDS, min((int) $mm[1], DAY_IN_SECONDS));
        }
        set_transient('ss_google_certs', $certs, $ttl);
        return $certs;
    }

    /** Atsarginis tikrinimas per Google tokeninfo endpoint. */
    private static function verify_via_tokeninfo(string $jwt): ?array {
        $resp = wp_remote_get(
            'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($jwt),
            ['timeout' => 15]
        );
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        return is_array($data) && !empty($data['sub']) ? $data : null;
    }

    private static function b64url_decode(string $data) {
        $pad    = strlen($data) % 4;
        $padded = $pad ? $data . str_repeat('=', 4 - $pad) : $data;
        return base64_decode(strtr($padded, '-_', '+/'), true);
    }

    private static function b64url_decode_json(string $data): ?array {
        $raw = self::b64url_decode($data);
        if ($raw === false) return null;
        $json = json_decode($raw, true);
        return is_array($json) ? $json : null;
    }

    /* ── AJAX: grąžinti Google auth URL ──────────────────── */

    public static function ajax_start(): void {
        // Rate limit: cap OAuth-start calls per IP (anti state-transient flooding).
        if (class_exists('SS_RateLimit')
            && !SS_RateLimit::hit('g_start_' . SS_RateLimit::get_client_ip(), 20, 10 * MINUTE_IN_SECONDS)) {
            wp_send_json_error(['message' => 'Per daug bandymų. Bandykite vėliau.']);
        }
        $mode = sanitize_text_field($_POST['mode'] ?? 'login');

        if ($mode === 'link') {
            if (!is_user_logged_in()) wp_send_json_error(['message' => 'Reikia prisijungti.']);
        } else {
            if (!self::is_enabled()) wp_send_json_error(['message' => 'Google prisijungimas išjungtas.']);
        }

        if (!self::is_configured()) {
            wp_send_json_error(['message' => 'Google prisijungimas nesukonfigūruotas.']);
        }

        $return_url = esc_url_raw(wp_unslash($_POST['return_url'] ?? home_url('/')));
        wp_send_json_success(['url' => self::get_auth_url($mode, $return_url)]);
    }

    /* ── AJAX: atsusieti Google paskyrą ──────────────────── */

    public static function ajax_unlink(): void {
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Reikia prisijungti.']);
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'ss_google_unlink_' . get_current_user_id())) {
            wp_send_json_error(['message' => 'Saugumo klaida.']);
        }
        $uid = get_current_user_id();
        delete_user_meta($uid, 'ss_google_id');
        delete_user_meta($uid, 'ss_google_email');
        delete_user_meta($uid, 'ss_google_linked_at');
        wp_send_json_success(['message' => 'Google paskyra sėkmingai atsieta.']);
    }
}
