<?php
/**
 * SS_Turnstile — apsauga nuo botų su Cloudflare Turnstile.
 *
 * Trys apsaugos sluoksniai (visi valdomi iš „Saugumas → Apsauga nuo botų"):
 *
 *   1. PRISIJUNGIMO FORMA (plėtinio login.php)  — matomas „managed" valdiklis;
 *      žetonas tikrinamas serveryje prieš wp_signon (SS_Shortcodes::ajax_login).
 *
 *   2. VISO SAITO GYNYBA (ss_ts_sitewide_mode):
 *        • 'background'   — nematomas valdiklis įkeliamas fone VISUOSE puslapiuose;
 *                           turinys rodomas iškart, o iššūkis iššoka tik jei
 *                           Cloudflare/įtarimas to reikalauja (mažiausias trukdis);
 *        • 'interstitial' — pilno ekrano tarpinis puslapis PRIEŠ turinį; praėjus
 *                           nustatomas pasirašytas slapukas (ss_ts_ok), galiojantis
 *                           N min., tad kiti puslapiai nebeklausia (kietas blokas botams);
 *        • 'off'          — viso saito gynyba išjungta (lieka tik login/wp-admin).
 *
 *   3. WP-ADMIN LOGIN (wp-login.php, net ir pakeistu adresu) — valdiklis per
 *      `login_form` hook'ą + žetono tikrinimas per `authenticate` filtrą; hook'ai
 *      suveikia nepriklausomai nuo login URL, todėl „paslėptas" login vis tiek ginamas.
 *
 * ĮTARIMAS (kada iššūkis privalomas net fone / „interstitial" tik įtartiniems):
 *   • IP arti brute-force blokavimo slenksčio (SS_RateLimit);
 *   • IP pažymėtas IDS kaip galima ataka (transientas ss_ids_alerted_*);
 *   • „unknown device" — lankytojas be galiojančio ss_ts_ok slapuko (naujas įrenginys).
 *
 * STATISTIKA saugoma per audito žurnalą (SS_AuditLog) įvykiais:
 *   turnstile_pass / turnstile_fail / turnstile_block — todėl automatiškai matoma
 *   žurnale, filtruose, CSV eksporte ir 24 val. suvestinėje.
 */
if (!defined('ABSPATH')) exit;

class SS_Turnstile {

    /** Cloudflare Turnstile serverio patikros galinis taškas. */
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    /** „Žmogaus praėjimo" slapuko vardas (pasirašytas HMAC). */
    private const PASS_COOKIE = 'ss_ts_ok';

    /* ── Numatytieji nustatymai ─────────────────────────────────────── */
    public static function defaults(): array {
        return [
            'ss_ts_enabled'        => 0,          // pagrindinis jungiklis
            'ss_ts_site_key'       => '',
            'ss_ts_secret_key'     => '',
            'ss_ts_login_widget'   => 1,          // valdiklis plėtinio login formoje
            'ss_ts_login_mode'     => 'managed',  // managed | always | invisible
            'ss_ts_wpadmin'        => 1,          // ginti wp-login.php Turnstile valdikliu
            'ss_ts_block_wpadmin'  => 0,          // blokuoti wp-login.php → nukreipti į plėtinio login
            'ss_ts_sitewide_mode'  => 'off',      // off | background | interstitial
            'ss_ts_pass_ttl'       => 720,        // slapuko galiojimas (min.) — 12 val.
            'ss_ts_allow_search'   => 1,          // praleisti patikrintus paieškos robotus
            'ss_ts_challenge_new'  => 1,          // interstitial: mesti iššūkį visiems be slapuko
            'ss_ts_exempt_paths'   => '',         // eilutės su URL keliais (po vieną eilutėje)
            'ss_ts_cf_token'       => '',         // Cloudflare API žetonas (kešo valymui)
            'ss_ts_cf_zone'        => '',         // Cloudflare Zone ID (kešo valymui)
        ];
    }

    public static function install_defaults(): void {
        foreach (self::defaults() as $opt => $val) {
            if (get_option($opt, null) === null) add_option($opt, $val);
        }
    }

    public static function get(string $key) {
        $d = self::defaults();
        return get_option($key, $d[$key] ?? null);
    }

    /* ── Būsenos patikros ───────────────────────────────────────────── */
    public static function is_configured(): bool {
        return self::get('ss_ts_site_key') !== '' && self::secret_key() !== '';
    }
    public static function is_enabled(): bool {
        return (bool) self::get('ss_ts_enabled') && self::is_configured();
    }
    public static function site_key(): string   { return (string) self::get('ss_ts_site_key'); }
    private static function secret_key(): string { return self::decrypt((string) get_option('ss_ts_secret_key', '')); }
    private static function cf_token(): string   { return self::decrypt((string) get_option('ss_ts_cf_token', '')); }
    public static function has_secret(): bool    { return get_option('ss_ts_secret_key', '') !== ''; }
    public static function has_cf_token(): bool  { return get_option('ss_ts_cf_token', '') !== ''; }

    /* ── Slaptų reikšmių šifravimas ramybėje (secret_key, CF token) ──── */
    private static function crypt_key(): string {
        $salt = (defined('AUTH_KEY') ? AUTH_KEY : '')
              . (defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '')
              . (defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '');
        if ($salt === '') $salt = (defined('DB_NAME') ? DB_NAME : 'ss') . get_option('ss_db_version', 'x');
        return hash('sha256', 'ss-turnstile-secret|' . $salt, true);
    }
    private static function encrypt(string $plain): string {
        $plain = trim($plain);
        if ($plain === '' || !function_exists('openssl_encrypt')) return $plain;
        $iv = random_bytes(16);
        $ct = openssl_encrypt($plain, 'aes-256-cbc', self::crypt_key(), OPENSSL_RAW_DATA, $iv);
        return ($ct !== false) ? 'enc:' . base64_encode($iv . $ct) : $plain;
    }
    private static function decrypt(string $val): string {
        if ($val === '' || strncmp($val, 'enc:', 4) !== 0) return $val; // tuščia arba legacy plaintext
        if (!function_exists('openssl_decrypt')) return '';
        $raw = base64_decode(substr($val, 4), true);
        if ($raw === false || strlen($raw) < 17) return '';
        $pt = openssl_decrypt(substr($raw, 16), 'aes-256-cbc', self::crypt_key(), OPENSSL_RAW_DATA, substr($raw, 0, 16));
        return $pt === false ? '' : $pt;
    }
    /** Užšifruoja ir išsaugo slaptą reikšmę (autoload išjungtas). */
    public static function set_secret(string $opt, string $plain): void {
        $val = self::encrypt($plain);
        if (get_option($opt, null) === null) {
            add_option($opt, $val, '', 'no');
        } else {
            update_option($opt, $val, false);
        }
    }
    public static function login_mode(): string {
        $m = (string) self::get('ss_ts_login_mode');
        return in_array($m, ['managed','always','invisible'], true) ? $m : 'managed';
    }
    public static function sitewide_mode(): string {
        $m = (string) self::get('ss_ts_sitewide_mode');
        return in_array($m, ['off','background','interstitial'], true) ? $m : 'off';
    }

    /**
     * Avarinis išjungiklis: į wp-config.php įrašius `define('SS_TS_BYPASS', true);`
     * VISA botų apsauga (iššūkiai, wp-admin blokas, login valdiklis) laikinai
     * išjungiama. Skirta atsigauti, jei Turnstile/Cloudflare užrakintų prieigą.
     */
    public static function is_bypassed(): bool {
        return defined('SS_TS_BYPASS') && SS_TS_BYPASS;
    }

    /* ── Hook'ai ────────────────────────────────────────────────────── */
    public static function init(): void {
        if (self::is_bypassed()) return; // avarinis režimas — jokios apsaugos

        // wp-admin login blokas veikia NEPRIKLAUSOMAI nuo Turnstile (net be raktų).
        if ((bool) self::get('ss_ts_block_wpadmin')) {
            add_action('login_init', [__CLASS__, 'maybe_block_wpadmin'], 0);
        }

        if (!self::is_enabled()) return;

        // Žetono patikros galinis taškas (fonui ir interstitial'ui)
        add_action('wp_ajax_nopriv_ss_ts_verify', [__CLASS__, 'ajax_verify']);
        add_action('wp_ajax_ss_ts_verify',         [__CLASS__, 'ajax_verify']);

        // Iššūkio puslapis per admin-ajax (kad kešavimo įskiepiai jo nekešuotų)
        add_action('wp_ajax_nopriv_ss_ts_challenge', [__CLASS__, 'ajax_challenge']);
        add_action('wp_ajax_ss_ts_challenge',         [__CLASS__, 'ajax_challenge']);

        // Viso saito gynyba
        $mode = self::sitewide_mode();
        if ($mode === 'interstitial') {
            // Anksti — prieš temos atvaizdavimą
            add_action('template_redirect', [__CLASS__, 'maybe_interstitial'], 1);
        } elseif ($mode === 'background') {
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_background'], 5);
            add_action('wp_footer',           [__CLASS__, 'render_background'], 99);
        }

        // WP-ADMIN login (veikia ir pakeistu adresu)
        if ((bool) self::get('ss_ts_wpadmin')) {
            add_action('login_enqueue_scripts', [__CLASS__, 'enqueue_api']);
            add_action('login_form',            [__CLASS__, 'render_wpadmin_widget']);
            add_filter('authenticate',          [__CLASS__, 'verify_wpadmin'], 30, 3);
        }
    }

    /* ── Serverio patikra (siteverify) ──────────────────────────────── */

    /** Paskutinės nesėkmingos patikros priežastis (diagnostikai). */
    private static string $last_error = '';
    public static function last_error(): string { return self::$last_error; }

    /**
     * @return bool  true — žetonas galiojantis (arba „fail-open" per CF triktį).
     *
     * SVARBU (prieinamumas): jei Cloudflare NEPASIEKIAMAS (tinklo triktis, 5xx,
     * timeout), NEBLOKUOJAM — grąžinam true (fail-open). Priešingu atveju mokyklos
     * tinklui užblokavus challenges.cloudflare.com užstrigtų VISAS saitas / visi
     * prisijungimai. Užpuolikas šito išnaudoti negali — jis negali priversti
     * serverio→CF užklausos „nepasiekti". Klaidingas žetonas visada blokuojamas.
     */
    public static function verify(string $token, string $ip = '', string $action = ''): bool {
        self::$last_error = '';
        $token = trim($token);
        // Pigi formato patikra PRIEŠ tinklo užklausą (anti-DoS): tikras Turnstile
        // žetonas yra ilgas. Tuščias / akivaizdžiai netinkamas — atmetam iškart,
        // nedarydami brangaus serverio→CF kvietimo.
        $len = strlen($token);
        if ($len < 20 || $len > 4096) {
            self::$last_error = 'missing-token';
            self::log_fail($ip, $action, $token === '' ? 'missing' : 'bad-format');
            return false;
        }
        if ($ip === '') $ip = SS_RateLimit::get_client_ip();

        $resp = wp_remote_post(self::VERIFY_URL, [
            'timeout' => 5,
            'body'    => [
                'secret'   => self::secret_key(),
                'response' => $token,
                'remoteip' => $ip,
            ],
        ]);
        // Tinklo triktis ARBA ne-200 iš Cloudflare → fail-open (žr. paaiškinimą viršuje).
        $code = is_wp_error($resp) ? 0 : (int) wp_remote_retrieve_response_code($resp);
        if (is_wp_error($resp) || $code < 200 || $code >= 300) {
            self::$last_error = 'unreachable';
            if (class_exists('SS_AuditLog')) {
                SS_AuditLog::write('turnstile_fail', ['ip' => $ip, 'ctx' => $action, 'reason' => 'unreachable-open']);
            }
            return true; // NEBLOKUOJAM
        }
        $data = json_decode((string) wp_remote_retrieve_body($resp), true);
        $ok   = is_array($data) && !empty($data['success']);
        if ($ok) {
            self::log_pass($ip, $action);
        } else {
            $codes = (is_array($data) && !empty($data['error-codes'])) ? implode(',', (array) $data['error-codes']) : 'invalid';
            self::$last_error = $codes;
            self::log_fail($ip, $action, $codes);
        }
        return $ok;
    }

    /* ── „Žmogaus praėjimo" slapukas ────────────────────────────────── */
    private static function pass_ttl_seconds(): int {
        return max(5, (int) self::get('ss_ts_pass_ttl')) * MINUTE_IN_SECONDS;
    }

    private static function sign(string $payload): string {
        return hash_hmac('sha256', $payload, wp_salt('auth'));
    }

    public static function issue_pass(): void {
        $exp = time() + self::pass_ttl_seconds();
        // SVARBU: slapukas NEpririšamas prie IP. Už Cloudflare / reverse-proxy
        // REMOTE_ADDR gali skirtis tarp AJAX užklausos ir puslapio perkrovimo (kitas
        // CF edge), todėl IP susiejimas sukeltų begalinį iššūkio ciklą. Pasirašymas
        // HMAC + galiojimo laikas užtikrina, kad slapuko nepavyktų suklastoti.
        $val = $exp . '.' . self::sign((string) $exp);
        setcookie(self::PASS_COOKIE, $val, [
            'expires'  => $exp,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::PASS_COOKIE] = $val; // kad tas pats užklausos ciklas jau „praleistų"
    }

    public static function has_valid_pass(): bool {
        $raw = (string) ($_COOKIE[self::PASS_COOKIE] ?? '');
        if ($raw === '' || substr_count($raw, '.') !== 1) return false;
        [$exp, $sig] = explode('.', $raw, 2);
        if (!ctype_digit($exp) || (int) $exp < time()) return false;
        return hash_equals(self::sign($exp), $sig);
    }

    /**
     * Trumpalaikis (2 min.) vienkartinio perdavimo žetonas. Naudojamas perduoti
     * sėkmingą patikrą iš AJAX atsakymo atgal į fronto (naršyklės) GET užklausą,
     * kur slapukas nustatomas patikimai (teisingame host'e).
     */
    public static function make_pass_token(): string {
        $exp = time() + 120;
        return $exp . '.' . self::sign('pass|' . $exp);
    }
    private static function valid_pass_token(string $t): bool {
        if (substr_count($t, '.') !== 1) return false;
        [$exp, $sig] = explode('.', $t, 2);
        if (!ctype_digit($exp) || (int) $exp < time()) return false;
        return hash_equals(self::sign('pass|' . $exp), $sig);
    }

    /* ── Įtarimo logika ─────────────────────────────────────────────── */
    /**
     * Padidintos rizikos IP: IDS pažymėtas kaip galima ataka ARBA arti / jau
     * brute-force blokavimo. „Naujas įrenginys" (nėra slapuko) ČIA neįtraukiamas —
     * jis tvarkomas atskirai per praėjimo slapuką (has_valid_pass), kad fono
     * režimas nerodytų valdiklio visiems iškart.
     */
    public static function is_high_risk(string $ip = '', string $username = ''): bool {
        if ($ip === '') $ip = SS_RateLimit::get_client_ip();
        if (get_transient('ss_ids_alerted_' . md5($ip))) return true;   // IDS ataka
        if (SS_RateLimit::lock_remaining($ip, $username) > 0) return true; // blokuotas
        return false;
    }

    /* ── Geri robotai (paieškos sistemos) ───────────────────────────── */
    /**
     * Ar užklausa iš patikrinto paieškos roboto (Googlebot/Bingbot ir pan.)?
     * Tikrinam ne tik User-Agent, bet ir atvirkštinį DNS (kad UA nebūtų suklastotas).
     */
    public static function is_good_bot(): bool {
        if (!(bool) self::get('ss_ts_allow_search')) return false;
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '') return false;
        $known = '/(Googlebot|APIs-Google|Mediapartners-Google|AdsBot-Google|bingbot|BingPreview|Slurp|DuckDuckBot|Baiduspider|YandexBot|Applebot)/i';
        if (!preg_match($known, $ua)) return false;

        // Verdiktą kešuojam pagal IP — kitaip KIEKVIENA roboto užklausa darytų
        // blokuojantį atvirkštinį+tiesioginį DNS (lėta).
        $ip  = SS_RateLimit::get_client_ip();
        $key = 'ss_ts_bot_' . md5($ip);
        $cached = get_transient($key);
        if ($cached !== false) return $cached === '1';

        $verdict = false;
        $host = @gethostbyaddr($ip);
        if ($host) {
            // Atvirkštinis host'as turi baigtis patikimu domenu IR forward'as grįžti į tą IP.
            $trusted = '/\.(googlebot\.com|google\.com|search\.msn\.com|crawl\.yahoo\.net|duckduckgo\.com|yandex\.(com|net|ru)|apple\.com)$/i';
            if (preg_match($trusted, $host)) {
                $verdict = (@gethostbyname($host) === $ip);
            }
        }
        set_transient($key, $verdict ? '1' : '0', HOUR_IN_SECONDS);
        return $verdict;
    }

    /* ── Interstitial (pilno ekrano) gynyba ─────────────────────────── */
    public static function maybe_interstitial(): void {
        // Nefiltruojam admin/AJAX/REST/cron/feed/robots ir POST užklausų.
        if (is_admin() || is_user_logged_in()) return;
        if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) return;
        if (defined('REST_REQUEST') && REST_REQUEST) return;
        if (is_feed() || is_robots() || is_trackback()) return;
        if (($GLOBALS['pagenow'] ?? '') === 'wp-login.php') return;
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;

        // OAuth / išorinių paslaugų grįžimo (callback) užklausos — NEgundom iššūkiu,
        // kitaip vienkartinis „code" būtų prarastas (pvz. Google prisijungimas).
        if (isset($_GET['ss_google_cb']) || isset($_GET['ss_2fa_pending'])
            || (isset($_GET['code']) && isset($_GET['state']))) {
            return;
        }

        // 1) Grįžtame iš iššūkio su vienkartiniu žetonu — slapuką nustatome ČIA,
        //    fronto (naršyklės) host'e. Taip išvengiam problemos, kai admin-ajax
        //    atsakymo slapukas nepasiekia fronto (www/non-www, proxy Set-Cookie ir pan.).
        // Naudojam SANTYKINĮ kelią (ne absoliutų URL) — taip naršyklė lieka tame
        // pačiame host'e, kur nustatytas slapukas, o wp_safe_redirect nemeta į
        // fallback dėl www/non-www host neatitikimo.
        $clean_path = remove_query_arg('ss_ts_pass', self::current_path());

        $pass_tok = isset($_GET['ss_ts_pass']) ? (string) $_GET['ss_ts_pass'] : '';
        if ($pass_tok !== '') {
            if (self::valid_pass_token($pass_tok)) {
                self::send_no_cache();   // slapuko nustatymo redirect'as neturi būti kešuojamas
                self::issue_pass();
                wp_safe_redirect($clean_path);
                exit;
            }
            // Blogas žetonas — tiesiog krisim į įprastą iššūkio logiką žemiau.
        }

        // Plėtinio prisijungimo puslapis turi savo matomą valdiklį — negundom dukart.
        $login_pid = (int) get_option('ss_login_page_id');
        if ($login_pid && is_page($login_pid)) return;
        if (self::is_path_exempt()) return;
        // Jau patvirtintas žmogus — tikrą puslapį atiduodam, bet NEleidžiam jo
        // kešuoti (kad kešas neatiduotų jo be iššūkio kitiems / neužstrigtų).
        if (self::has_valid_pass()) { self::send_no_cache(); return; }
        if (self::is_good_bot()) return;

        // Jei „challenge_new" išjungtas — iššūkis tik padidintos rizikos IP.
        if (!(bool) self::get('ss_ts_challenge_new') && !self::is_high_risk()) return;

        // SVARBU dėl KEŠAVIMO: iššūkio NEatvaizduojam turinio URL'e (kešavimo
        // įskiepiai išsaugotų statinį iššūkio HTML ir po to atiduotų jį visiems).
        // Vietoje to nukreipiam į admin-ajax.php — šį adresą VISI kešavimo įskiepiai
        // pagal nutylėjimą praleidžia (nekešuoja), tad iššūkis niekada neužstringa.
        self::send_no_cache();
        $chl = add_query_arg(
            ['action' => 'ss_ts_challenge', 'r' => rawurlencode($clean_path)],
            admin_url('admin-ajax.php')
        );
        wp_safe_redirect($chl);
        exit;
    }

    /**
     * Iššūkio (pilno ekrano) puslapis, atvaizduojamas per admin-ajax.php, kad
     * kešavimo įskiepiai jo NEkešuotų. „r" — santykinis kelias, į kurį grįžtama.
     */
    public static function ajax_challenge(): void {
        if (!self::is_enabled()) { wp_safe_redirect(home_url('/')); exit; } // išjungta/nekonfigūruota
        if (is_user_logged_in()) { wp_safe_redirect(home_url('/')); exit; }
        // Grąžinimo kelias — tik santykinis (apsauga nuo open-redirect).
        $r = isset($_GET['r']) ? (string) wp_unslash($_GET['r']) : '/';
        $r = '/' . ltrim(preg_replace('/[\r\n]/', '', $r), '/');
        if (self::has_valid_pass()) { wp_safe_redirect($r); exit; } // jau praėjo — negundom
        self::render_interstitial($r);
        exit;
    }

    private static function is_path_exempt(): bool {
        $raw = trim((string) self::get('ss_ts_exempt_paths'));
        if ($raw === '') return false;
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (stripos($path, $line) === 0 || stripos($path, $line) !== false) return true;
        }
        return false;
    }

    /**
     * Griežtai uždraudžia kešuoti šį atsakymą — ir naršyklėje, ir CDN (Cloudflare).
     * Cloudflare NEKEŠUOJA atsakymo, kuris turi Set-Cookie antraštę, todėl visada
     * nustatom trumpą žymos slapuką + CDN-Cache-Control: no-store. Kitaip „Cache
     * Everything" atiduotų kešuotą iššūkio puslapį ir viskas ciklintų.
     */
    private static function send_no_cache(): void {
        if (headers_sent()) return;
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true);
        header('CDN-Cache-Control: no-store', true);
        header('Cloudflare-CDN-Cache-Control: no-store', true);
        header('Vary: Cookie', true);
        // Set-Cookie → Cloudflare priverstinai apeina kešą šiam atsakymui.
        // Fiksuota reikšmė (ne time()) — Set-Cookie antraštė vis tiek siunčiama
        // kiekvienam atsakymui, bet slapuko reikšmė nekinta (be bereikalingo triukšmo).
        setcookie('ss_ts_nc', '1', [
            'expires'  => 0, // sesijos slapukas
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** Santykinis šios užklausos kelias (pvz. „/tvarkarastis?x=1"). */
    private static function current_path(): string {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if ($uri === '' || $uri[0] !== '/') $uri = '/' . ltrim($uri, '/');
        return $uri;
    }


    /* ── Atvaizdavimas: interstitial puslapis ───────────────────────── */
    public static function render_interstitial(string $return_url): void {
        self::send_no_cache();
        status_header(200);
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex', true);
            header('Content-Type: text/html; charset=utf-8', true); // svarbu, kai atiduodama per admin-ajax
            header('X-Frame-Options: DENY', true);                  // apsauga nuo clickjacking'o
            header("Content-Security-Policy: frame-ancestors 'none'", true);
        }
        $site  = get_option('ss_school_name', SS_SCHOOL_NAME);
        $key   = esc_attr(self::site_key());
        // esc_url_raw (ne esc_url) — reikšmės eina į JS eilutes per wp_json_encode,
        // todėl NEturi būti HTML-entišuotos (& → &#038; sulaužytų URL).
        $ajax  = esc_url_raw(admin_url('admin-ajax.php'));
        $ret   = esc_url_raw($return_url);
        $nonce = wp_create_nonce('ss_ts_verify');
        // Mokyklos logotipas — favikonui (naršyklės skirtuke) ir puslapio viršuje.
        $logo_id  = (int) get_option('ss_logo_id', 0);
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : (string) get_option('ss_logo_url', '');
        if (!$logo_url) {
            $tid = get_theme_mod('custom_logo');
            $logo_url = $tid ? (string) wp_get_attachment_image_url($tid, 'thumbnail') : '';
        }
        if (!$logo_url && function_exists('get_site_icon_url')) {
            $logo_url = (string) get_site_icon_url(64);
        }
        ?><!doctype html>
<html lang="lt"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= esc_html($site) ?></title>
<?php if ($logo_url): ?><link rel="icon" href="<?= esc_url($logo_url) ?>"><?php endif; ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
       font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
       background:#0f2419;color:#e8f0ea;padding:24px}
  .box{max-width:440px;width:100%;text-align:center;background:#14301f;border:1px solid #23492f;
       border-radius:14px;padding:38px 30px}
  .spin{width:44px;height:44px;margin:0 auto 20px;border:4px solid #23492f;border-top-color:#4caf7d;
        border-radius:50%;animation:r 1s linear infinite}
  @keyframes r{to{transform:rotate(360deg)}}
  h1{font-size:19px;margin:0 0 8px;font-weight:700}
  p{font-size:14px;color:#a9c4b3;margin:0 0 22px;line-height:1.5}
  .cf{display:flex;justify-content:center;min-height:70px}
  .err{color:#ff9a9a;font-size:13px;margin-top:16px;display:none}
  .logo{width:56px;height:56px;object-fit:contain;margin:0 auto 16px;display:block;border-radius:10px}
  .school{font-size:12px;color:#7fa890;margin:0 0 18px;font-weight:600;letter-spacing:.2px}
</style>
</head><body>
  <div class="box">
    <div class="spin" id="ss-ts-spin"></div>
    <h1>Tikriname, ar esate žmogus</h1>
    <p>Prieš tęsdami, palaukite akimirką.</p>
    <div class="cf">
      <div class="cf-turnstile" data-sitekey="<?= $key ?>" data-callback="ssTsDone"
           data-action="sitewide" data-theme="dark"></div>
    </div>
    <div class="err" id="ss-ts-err">Nepavyko patvirtinti. Atnaujinkite puslapį ir bandykite dar kartą.</div>
  </div>
<script>
  function ssTsDone(token){
    var x=new XMLHttpRequest();
    x.open('POST',<?= wp_json_encode($ajax) ?>,true);
    x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    x.onload=function(){
      try{var r=JSON.parse(x.responseText);}catch(e){r=null;}
      if(r&&r.success){
        // Grįžtam su vienkartiniu žetonu — slapukas nustatomas fronto host'e.
        var u=<?= wp_json_encode($ret) ?>;
        if(r.data&&r.data.pass){ u+=(u.indexOf('?')>-1?'&':'?')+'ss_ts_pass='+encodeURIComponent(r.data.pass); }
        window.location.replace(u);
      }
      else{ document.getElementById('ss-ts-spin').style.display='none';
            var e=document.getElementById('ss-ts-err');
            if(r&&r.data&&r.data.message){ e.textContent=r.data.message; }
            e.style.display='block';
            if(window.turnstile){turnstile.reset();} }
    };
    x.onerror=function(){ document.getElementById('ss-ts-err').style.display='block'; };
    x.send('action=ss_ts_verify&context=sitewide'
      +'&token='+encodeURIComponent(token)
      +'&nonce='+encodeURIComponent(<?= wp_json_encode($nonce) ?>));
  }
</script>
</body></html><?php
    }

    /* ── Atvaizdavimas: fono valdiklis (visuose puslapiuose) ────────── */
    public static function enqueue_background(): void {
        if (is_admin() || is_user_logged_in()) return;
        if (self::has_valid_pass()) return;      // jau patvirtintas — nekartojam
        if (self::is_good_bot()) return;
        self::enqueue_api();
    }

    public static function render_background(): void {
        if (is_admin() || is_user_logged_in()) return;
        if (self::has_valid_pass()) return;
        if (self::is_good_bot()) return;
        // Padidintos rizikos IP — matomas („always"); kitiems — nematomas fono iššūkis.
        $appearance = self::is_high_risk() ? 'always' : 'interaction-only';
        $key   = esc_attr(self::site_key());
        $ajax  = esc_url(admin_url('admin-ajax.php'));
        $nonce = wp_create_nonce('ss_ts_verify');
        ?>
<div id="ss-ts-bg" style="position:fixed;right:14px;bottom:14px;z-index:2147483000"></div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer
        onload="ssTsBgRender()"></script>
<script>
  function ssTsBgSend(token){
    var x=new XMLHttpRequest();
    x.open('POST',<?= wp_json_encode($ajax) ?>,true);
    x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    x.send('action=ss_ts_verify&context=background'
      +'&token='+encodeURIComponent(token)
      +'&nonce='+encodeURIComponent(<?= wp_json_encode($nonce) ?>));
  }
  function ssTsBgRender(){
    if(!window.turnstile) return;
    turnstile.render('#ss-ts-bg',{
      sitekey:<?= wp_json_encode(self::site_key()) ?>,
      action:'background',
      appearance:<?= wp_json_encode($appearance) ?>,
      callback:ssTsBgSend
    });
  }
</script>
        <?php
    }

    /* ── WP-ADMIN login valdiklis ───────────────────────────────────── */
    public static function enqueue_api(): void {
        // Turnstile privalo krautis iš Cloudflare domeno — CDN blokas čia negalimas.
        wp_enqueue_script('ss-turnstile-api',
            'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
    }

    public static function render_wpadmin_widget(): void {
        $key  = esc_attr(self::site_key());
        $mode = self::login_mode();
        $appearance = ($mode === 'always') ? 'always' : 'interaction-only';
        echo '<div class="cf-turnstile" style="margin:0 0 16px" data-sitekey="' . $key . '"'
           . ' data-action="wpadmin" data-appearance="' . esc_attr($appearance) . '"></div>';
    }

    /**
     * `authenticate` filtras — reikalauja galiojančio žetono POST prisijungimams
     * per wp-login.php. Veikia ir pakeistu login adresu (hook'as suveikia visada).
     */
    public static function verify_wpadmin($user, $username, $password) {
        // Tik realiam formos POST'ui (ne XML-RPC / app-password / GET).
        if (($GLOBALS['pagenow'] ?? '') !== 'wp-login.php') return $user;
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return $user;
        if ($username === '' && $password === '') return $user;

        // Žetonas tikrinamas PRIEŠ slaptažodį — botas, neišsprendęs iššūkio,
        // blokuojamas anksčiau, nei atskleidžiama, ar kredencialai teisingi.
        $token = (string) ($_POST['cf-turnstile-response'] ?? '');
        if (!self::verify($token, '', 'wpadmin')) {
            self::log_block(SS_RateLimit::get_client_ip(), 'wpadmin');
            return new WP_Error('ss_ts_failed',
                '<strong>Klaida:</strong> nepavyko patvirtinti, kad esate žmogus. Bandykite dar kartą.');
        }
        return $user;
    }

    /**
     * Blokuoja tiesioginį wp-login.php prisijungimą ir nukreipia į plėtinio
     * login puslapį. Prisijungimą tvarko plėtinio forma (wp_signon per AJAX),
     * todėl administratoriai vis tiek gali patekti į wp-admin — tik per plėtinio
     * login. Atsijungimas, slaptažodžio atkūrimas (WP) ir el. pašto patvirtinimai
     * praleidžiami, kad nesugadintume tų srautų.
     */
    public static function maybe_block_wpadmin(): void {
        // Fail-safe: jei plėtinio login puslapis nesukonfigūruotas — NEblokuojam,
        // kad neužrakintume savęs.
        $login_url = get_permalink((int) get_option('ss_login_page_id'));
        if (!$login_url) return;

        $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : 'login';
        $allow  = ['logout','lostpassword','retrievepassword','resetpass','rp','postpass','confirmaction','confirm_admin_email'];
        if (in_array($action, $allow, true)) return;

        // Interactive login (GET forma arba POST bandymas) — nukreipiam.
        wp_safe_redirect($login_url);
        exit;
    }

    /* ── AJAX: žetono patikra (fonas + interstitial) ────────────────── */
    public static function ajax_verify(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ss_ts_verify')) {
            // Nonce pasibaigė (pvz. puslapis atidarytas seniai) — pasakom perkrauti.
            wp_send_json_error(['message' => 'Sesija pasibaigė. Atnaujinkite puslapį (F5) ir bandykite dar kartą.']);
        }
        $context = sanitize_key($_POST['context'] ?? 'sitewide');
        $token   = (string) ($_POST['token'] ?? '');
        $ip      = SS_RateLimit::get_client_ip();
        if (self::verify($token, $ip, $context)) {
            self::issue_pass(); // fonui — geriausiu atveju iškart nustatom slapuką
            // ...ir grąžinam vienkartinį žetoną, kad frontas nustatytų slapuką patikimai.
            wp_send_json_success(['ok' => 1, 'pass' => self::make_pass_token()]);
        }
        // Patikra NEPAVYKO — realus „blokas" (botas gavo žetoną, bet Cloudflare atmetė).
        self::log_block($ip, $context);
        wp_send_json_error(['message' => self::error_hint(self::$last_error)]);
    }

    /**
     * Paverčia Cloudflare klaidos kodą į suprantamą (ir administratoriui naudingą)
     * pranešimą. Rodoma iššūkio puslapyje bei žurnale (turnstile_fail → reason).
     */
    private static function error_hint(string $code): string {
        $map = [
            'invalid-input-secret'  => 'Neteisingas „Secret Key". Patikrinkite raktą nustatymuose (Saugumas → Apsauga nuo botų).',
            'missing-input-secret'  => 'Nenurodytas „Secret Key". Įrašykite jį nustatymuose.',
            'invalid-input-response'=> 'Netinkamas arba pasibaigęs iššūkio žetonas. Atnaujinkite puslapį.',
            'timeout-or-duplicate'  => 'Iššūkio žetonas jau panaudotas arba pasibaigė. Atnaujinkite puslapį.',
            'missing-input-response'=> 'Iššūkis neužbaigtas. Palaukite ir bandykite dar kartą.',
            'bad-request'           => 'Neteisinga užklausa į Cloudflare. Patikrinkite raktus.',
            'invalid-widget-id'     => 'Neteisingas valdiklio ID. Patikrinkite „Site Key".',
            'unreachable'           => 'Nepavyko pasiekti Cloudflare serverio. Patikrinkite interneto ryšį.',
            'missing-token'         => 'Iššūkis neužbaigtas. Palaukite akimirką ir bandykite dar kartą.',
        ];
        // Gali būti keli kodai per kablelį — imam pirmą pažįstamą.
        foreach (explode(',', $code) as $c) {
            $c = trim($c);
            if (isset($map[$c])) return $map[$c];
        }
        return 'Nepavyko patvirtinti. Atnaujinkite puslapį ir bandykite dar kartą.'
             . ($code ? ' (kodas: ' . esc_html($code) . ')' : '');
    }

    /* ── Kešo valymas („Atnaujinti") ────────────────────────────────── */
    /**
     * Išvalo kiek įmanoma daugiau kešų: WP objektų kešą, dažniausių kešavimo
     * įskiepių kešą ir (jei nurodyti raktai) Cloudflare edge kešą per API.
     * @return array ['local' => bool, 'cf' => 'ok'|'skip'|'error:...']
     */
    public static function purge_all_caches(): array {
        $out = ['local' => true, 'cf' => 'skip'];

        // 1) WP objektų kešas ir pasibaigę transientai
        wp_cache_flush();
        if (function_exists('delete_expired_transients')) delete_expired_transients();

        // 2) Populiarūs kešavimo įskiepiai (kviečiam tik jei yra)
        if (function_exists('wp_cache_clear_cache'))  wp_cache_clear_cache();      // WP Super Cache
        if (function_exists('w3tc_flush_all'))        w3tc_flush_all();            // W3 Total Cache
        if (function_exists('rocket_clean_domain'))   rocket_clean_domain();       // WP Rocket
        if (function_exists('sg_cachepress_purge_cache')) sg_cachepress_purge_cache(); // SiteGround
        if (has_action('litespeed_purge_all'))        do_action('litespeed_purge_all');   // LiteSpeed
        if (has_action('breeze_clear_all_cache'))     do_action('breeze_clear_all_cache'); // Breeze
        if (has_action('wpfc_clear_all_cache'))       do_action('wpfc_clear_all_cache', true); // WP Fastest Cache

        // 3) Cloudflare edge kešas (jei nurodytas API žetonas + Zone ID)
        $token = trim(self::cf_token());
        $zone  = trim((string) self::get('ss_ts_cf_zone'));
        if ($token !== '' && $zone !== '') {
            $resp = wp_remote_post("https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", [
                'timeout' => 12,
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode(['purge_everything' => true]),
            ]);
            if (is_wp_error($resp)) {
                $out['cf'] = 'error:' . $resp->get_error_message();
            } else {
                $body = json_decode((string) wp_remote_retrieve_body($resp), true);
                if (is_array($body) && !empty($body['success'])) {
                    $out['cf'] = 'ok';
                } else {
                    $msg = (is_array($body) && !empty($body['errors'][0]['message'])) ? $body['errors'][0]['message'] : 'atmesta';
                    $out['cf'] = 'error:' . $msg;
                }
            }
        }

        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('settings_saved', ['ip' => SS_RateLimit::get_client_ip(), 'action' => 'cache_purged', 'cf' => $out['cf']]);
        }
        return $out;
    }

    /* ── Statistika / žurnalas ──────────────────────────────────────── */
    private static function log_pass(string $ip, string $action): void {
        if (class_exists('SS_AuditLog')) SS_AuditLog::write('turnstile_pass', ['ip' => $ip, 'ctx' => $action]);
    }
    private static function log_fail(string $ip, string $action, string $reason): void {
        if (class_exists('SS_AuditLog')) SS_AuditLog::write('turnstile_fail', ['ip' => $ip, 'ctx' => $action, 'reason' => $reason]);
    }
    private static function log_block(string $ip, string $action): void {
        if (class_exists('SS_AuditLog')) SS_AuditLog::write('turnstile_block', ['ip' => $ip, 'ctx' => $action]);
    }

    /** 24 val. suvestinė administravimo kortelėms. */
    public static function stats_24h(): array {
        $s = class_exists('SS_AuditLog') ? SS_AuditLog::stats_24h() : [];
        $pass  = (int) ($s['turnstile_pass']  ?? 0);
        $fail  = (int) ($s['turnstile_fail']  ?? 0);
        $block = (int) ($s['turnstile_block'] ?? 0);
        $total = $pass + $fail;
        return [
            'pass'      => $pass,
            'fail'      => $fail,
            'block'     => $block,
            'total'     => $total,
            'pass_rate' => $total > 0 ? round($pass / $total * 100) : 0,
        ];
    }
}
