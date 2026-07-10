<?php
/**
 * WP-Admin prisijungimo puslapis
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class SS_WP_Login {

    public static function init(): void {
        add_action( 'login_enqueue_scripts', [ __CLASS__, 'enqueue'            ] );
        add_action( 'login_head',            [ __CLASS__, 'inject_css'         ] );
        add_action( 'login_init',            [ __CLASS__, 'handle_2fa_post'    ] );
        add_filter( 'login_message',         [ __CLASS__, 'inject_brand'       ] );
        add_action( 'login_footer',          [ __CLASS__, 'inject_extras'      ] );
        add_filter( 'login_redirect',        [ __CLASS__, 'redirect_home'      ], 10, 3 );
        add_filter( 'login_headerurl',       fn() => home_url( '/' ) );
        add_filter( 'login_headertext',      fn() => get_option( 'ss_school_name', SS_SCHOOL_NAME ) );
        add_filter( 'login_body_class',      [ __CLASS__, 'body_classes'       ] );
        // 2FA perėmimas po WP prisijungimo
        add_action( 'wp_login',              [ __CLASS__, 'intercept_for_2fa'  ], 10, 2 );
    }

    /* ── Enqueue ───────────────────────────────────────────────────── */
    public static function enqueue(): void {
        wp_enqueue_style(  'ss-wplogin',    SS_PLUGIN_URL . 'public/css/style.css', [], SS_VERSION );
        wp_enqueue_script( 'ss-wplogin-js', SS_PLUGIN_URL . 'public/js/wp-login.js',
                           [ 'jquery' ], SS_VERSION, true );
        wp_localize_script( 'ss-wplogin-js', 'ss_wpl', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'qr_enabled' => get_option( 'ss_qr_login_enabled', 0 ) ? 1 : 0,
            'gg_enabled' => SS_Google_Auth::is_enabled() ? 1 : 0,
            'login_url'  => wp_login_url(),
        ] );
    }

    /* ── Body class'ai ─────────────────────────────────────────────── */
    public static function body_classes( array $c ): array {
        $action = sanitize_html_class( sanitize_text_field( $_GET['action'] ?? 'login' ) );
        $c[] = 'ss-wplogin';
        $c[] = 'ss-action-' . $action;
        return $c;
    }

    /* ═══════════════════════════════════════════════════════════════
       2FA — WP admin prisijungimo perėmimas
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Veikia po sėkmingo username+password prisijungimo.
     * Jei 2FA įjungtas – iškart naikina sesiją ir nukreipia į 2FA puslapį.
     * DOING_AJAX = true reiškia AJAX tvarkytuvė (QR/plugin) – ten 2FA
     * tvarkomasi atskirai, šio hooko nevykdyti.
     */
    public static function intercept_for_2fa( string $user_login, WP_User $user ): void {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) return;
        if ( ! SS_2FA::is_enabled( $user->ID ) ) return;

        wp_clear_auth_cookie();
        $remember  = ! empty( $_POST['rememberme'] );
        $redir     = admin_url();
        $fa_token  = SS_2FA::create_pending( $user->ID, $remember, 'wp_admin', $redir );
        wp_redirect( wp_login_url() . '?action=ss_2fa&fa=' . rawurlencode( $fa_token ) );
        exit;
    }

    /**
     * Apdoroja 2FA formos POST/GET (login_init vykdoma labai anksti, prieš HTML).
     */
    public static function handle_2fa_post(): void {
        $action = sanitize_text_field( $_GET['action'] ?? '' );
        if ( $action !== 'ss_2fa' ) return;

        $fa_token = sanitize_text_field( $_GET['fa'] ?? '' );

        // ── POST: verifikuoti kodą ──
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $posted_token = sanitize_text_field( $_POST['ss_fa_token'] ?? '' );
            $raw_code     = strtoupper( trim( sanitize_text_field( $_POST['ss_fa_code'] ?? '' ) ) );
            $code         = preg_replace( '/\D/', '', $raw_code );
            $data         = SS_2FA::consume_pending( $posted_token );

            if ( ! $data ) {
                wp_redirect( wp_login_url() . '?action=ss_2fa&fa=&ss_2fa_err=expired' );
                exit;
            }
            $ip     = class_exists( 'SS_RateLimit' ) ? SS_RateLimit::get_client_ip() : '';
            $secret = SS_2FA::get_secret( (int) $data['user_id'] );

            // Patikrinti, ar įrenginys užblokuotas
            if ( class_exists( 'SS_AuditLog' ) && SS_AuditLog::is_device_blocked( (int) $data['user_id'], $ip ) ) {
                SS_AuditLog::write( 'login_blocked', [
                    'user_id' => (int) $data['user_id'],
                    'ip'      => $ip,
                    'reason'  => 'device_blocked',
                    'source'  => 'wp_admin',
                ] );
                wp_redirect( wp_login_url() . '?action=ss_2fa&fa=&ss_2fa_err=device_blocked' );
                exit;
            }

            // 2FA bandymų ribojimas VARTOTOJUI (bendri helperiai su ajax_verify_pending).
            // Jei šiuo metu užblokuota — iškart blokuojam (be naujo žetono).
            $uid_2fa = (int) $data['user_id'];
            if ( SS_2FA::rl_locked_for( $uid_2fa ) > 0 ) {
                if ( class_exists( 'SS_AuditLog' ) ) {
                    SS_AuditLog::write( '2fa_failed', [
                        'user_id' => $uid_2fa,
                        'ip'      => $ip,
                        'source'  => 'wp_admin',
                        'reason'  => 'rate_limited',
                    ] );
                }
                wp_redirect( wp_login_url() . '?action=ss_2fa&fa=&ss_2fa_err=rate_limited' );
                exit;
            }

            // Backup kodas: 8 hex simboliai (A-F, 0-9)
            $verified        = false;
            $method          = '2fa';
            $backup_candidate = preg_replace( '/[^A-F0-9]/i', '', $raw_code );
            if ( strlen( $backup_candidate ) === 8 && ctype_xdigit( $backup_candidate ) ) {
                if ( SS_2FA::use_backup_code( (int) $data['user_id'], $backup_candidate ) ) {
                    $verified = true;
                    $method   = 'backup_code';
                }
            }
            if ( ! $verified ) {
                // Perduodam $uid_2fa — įjungia TOTP replay apsaugą (ss_2fa_last_counter).
                $verified = SS_2FA::verify( $secret, $code, $uid_2fa );
            }

            if ( ! $verified ) {
                $fail = SS_2FA::rl_register_failure( $uid_2fa, $ip );
                if ( class_exists( 'SS_AuditLog' ) ) {
                    SS_AuditLog::write( '2fa_failed', [
                        'user_id' => $uid_2fa,
                        'ip'      => $ip,
                        'source'  => 'wp_admin',
                        'reason'  => $fail['locked'] ? 'rate_limited' : 'invalid_code',
                    ] );
                }
                // Pasiekus ribą — 5 min. blokas (be naujo žetono).
                if ( $fail['locked'] ) {
                    wp_redirect( wp_login_url() . '?action=ss_2fa&fa=&ss_2fa_err=rate_limited' );
                    exit;
                }
                // Naujas žetonas, kad vartotojas galėtų bandyti dar kartą
                $new = SS_2FA::create_pending(
                    (int) $data['user_id'],
                    (bool) $data['remember'],
                    $data['source'],
                    $data['redirect']
                );
                wp_redirect( wp_login_url() . '?action=ss_2fa&fa=' . rawurlencode( $new ) . '&ss_2fa_err=invalid' );
                exit;
            }
            // Sėkmė
            if ( class_exists( 'SS_AuditLog' ) ) {
                SS_AuditLog::write( '2fa_ok', [
                    'user_id' => (int) $data['user_id'],
                    'ip'      => $ip,
                    'source'  => 'wp_admin',
                    'method'  => $method,
                ] );
                SS_AuditLog::write( 'login_ok', [
                    'user_id' => (int) $data['user_id'],
                    'ip'      => $ip,
                    'source'  => 'wp_admin',
                    'method'  => $method,
                ] );
                SS_AuditLog::check_new_device( (int) $data['user_id'], $ip, $method );
            }
            SS_2FA::rl_clear( $uid_2fa ); // sėkmė — išvalom skaitiklį ir bloką
            if ( $method === 'backup_code' ) SS_2FA::notify_backup_used( $uid_2fa, $ip );
            SS_2FA::do_login( (int) $data['user_id'], (bool) ( $data['remember'] ?? false ) );
            wp_redirect( $data['redirect'] ?: admin_url() );
            exit;
        }

        // ── GET: parodyti formą – inject brand + 2FA card via login_message ──
        add_filter( 'login_message', function( $msg ) use ( $fa_token ) {
            return self::render_2fa_message( $fa_token ) . $msg;
        } );
    }

    private static function render_2fa_message( string $token ): string {
        $err_code = sanitize_text_field( $_GET['ss_2fa_err'] ?? '' );
        $data     = $token ? SS_2FA::peek_pending( $token ) : null;

        $site_name = get_option( 'ss_school_name', SS_SCHOOL_NAME );
        $logo_id   = (int) get_option( 'ss_logo_id', 0 );
        $logo_url  = $logo_id
            ? wp_get_attachment_image_url( $logo_id, 'medium' )
            : get_option( 'ss_logo_url', '' );

        ob_start();
        ?>
        <div class="ss-login-brand">
            <?php if ( $logo_url ) : ?>
                <img src="<?= esc_url( $logo_url ) ?>" alt="<?= esc_attr( $site_name ) ?>" class="ss-login-logo">
            <?php else : ?>
                <div class="ss-login-logo-fb"><?= esc_html( mb_substr( $site_name, 0, 1 ) ) ?></div>
            <?php endif; ?>
            <div><span class="ss-login-school"><?= esc_html( $site_name ) ?></span></div>
        </div>
        <h2 class="ss-wp-card-title">Dviejų žingsnių autentifikacija</h2>
        <p  class="ss-wp-card-sub">Įveskite 6 skaitmenų kodą iš Authenticator programėlės</p>

        <?php if ( $err_code === 'expired' ) : ?>
            <div class="ss-wpl-2fa-err">Sesija pasibaigė. Prisijunkite iš naujo.</div>
        <?php elseif ( $err_code === 'rate_limited' ) : ?>
            <div class="ss-wpl-2fa-err">Per daug neteisingų kodų. Saugumo sumetimais palaukite ~5 min. ir prisijunkite iš naujo.</div>
        <?php elseif ( $err_code === 'invalid' ) : ?>
            <div class="ss-wpl-2fa-err">Neteisingas kodas. Bandykite dar kartą.</div>
        <?php elseif ( $err_code === 'device_blocked' ) : ?>
            <div class="ss-wpl-2fa-err ss-wpl-2fa-err--blocked">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="flex-shrink:0;margin-top:1px"><path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524L13.477 14.89zm1.414-1.414L6.524 5.11A6 6 0 0114.89 13.476zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/></svg>
                <span>Šis įrenginys yra <strong>užblokuotas</strong>. Atblokuokite jį prisijungę prie paskyros iš kito įrenginio.</span>
            </div>
        <?php endif; ?>

        <?php if ( $data ) : ?>
        <div class="ss-wpl-2fa-card">
        <form method="post" action="<?= esc_url( wp_login_url() . '?action=ss_2fa' ) ?>" id="ss-wpl-2fa-form">
            <input type="hidden" name="ss_fa_token" value="<?= esc_attr( $token ) ?>">
            <div class="ss-wpl-2fa-wrap">
                <label class="ss-wpl-2fa-lbl" for="ss-wpl-2fa-code">Autentifikatoriaus kodas</label>
                <input type="text"
                       id="ss-wpl-2fa-code"
                       name="ss_fa_code"
                       class="ss-wpl-2fa-input"
                       inputmode="text"
                       maxlength="12" autocomplete="one-time-code"
                       placeholder=""
                       autofocus required>
                <p class="ss-wpl-2fa-hint">TOTP kodas arba atsarginis kodas</p>
            </div>
            <button type="submit" class="ss-wpl-2fa-btn">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                Patvirtinti
            </button>
        </form>
        </div>
        <p style="text-align:center;margin-top:12px;">
            <a href="<?= esc_url( wp_login_url() ) ?>" style="color:#224768;font-size:13px;font-weight:600;text-decoration:none;">← Grįžti į prisijungimą</a>
        </p>
        <?php else : ?>
            <div class="ss-wpl-2fa-err">Žetonas nebegalioja. <a href="<?= esc_url( wp_login_url() ) ?>">Prisijunkite iš naujo.</a></div>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /* ═══════════════════════════════════════════════════════════════
       CSS
       ═══════════════════════════════════════════════════════════════ */
    public static function inject_css(): void {
        $icon_user = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23A7D8A9'%3E%3Cpath d='M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z'/%3E%3C/svg%3E";
        $icon_lock = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23A7D8A9'%3E%3Cpath fill-rule='evenodd' d='M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z' clip-rule='evenodd'/%3E%3C/svg%3E";
        $icon_mail = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23A7D8A9'%3E%3Cpath d='M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z'/%3E%3Cpath d='M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z'/%3E%3C/svg%3E";
        ?>
<style>
/* ═══════════════════ 1. LAYOUT ═══════════════════ */
html{height:100%;}
body.ss-wplogin{
  display:flex!important;align-items:stretch!important;
  min-height:100vh!important;margin:0!important;padding:0!important;
  background:#F3F7F3!important;font-family:var(--fn)!important;
}
body.ss-wplogin #login{
  flex:0 0 auto!important;width:100%!important;max-width:480px!important;
  min-height:100vh!important;background:#fff!important;margin:0!important;
  padding:52px 44px 44px!important;box-sizing:border-box!important;
  display:flex!important;flex-direction:column!important;justify-content:center!important;
  box-shadow:2px 0 24px rgba(1,42,54,.06)!important;
}
#ss-wplogin-right{
  flex:1;min-height:100vh;
  background:linear-gradient(145deg,#245326 0%,#224768 100%);
  overflow:hidden;position:relative;
}
#ss-wplogin-right img{width:100%;height:100%;object-fit:cover;display:block;}
/* Branded hero (kai nėra nuotraukos) */
#ss-wplogin-right.ss-wplogin-right--brand{display:flex;align-items:center;justify-content:center;padding:40px;}
#ss-wplogin-right.ss-wplogin-right--brand::before{
  content:"";position:absolute;top:-15%;right:-10%;width:60%;height:60%;border-radius:50%;
  background:radial-gradient(circle,rgba(255,255,255,.10),transparent 70%);pointer-events:none;
}
#ss-wplogin-right.ss-wplogin-right--brand::after{
  content:"";position:absolute;bottom:-20%;left:-12%;width:55%;height:55%;border-radius:50%;
  background:radial-gradient(circle,rgba(167,216,169,.14),transparent 70%);pointer-events:none;
}
.ss-wplogin-hero{position:relative;z-index:1;text-align:center;color:#fff;max-width:360px;}
.ss-wplogin-hero-logo{height:78px;width:auto;object-fit:contain;margin-bottom:22px;filter:drop-shadow(0 6px 18px rgba(0,0,0,.25));}
.ss-wplogin-hero-title{font-size:26px;font-weight:800;letter-spacing:-.3px;line-height:1.25;margin-bottom:8px;}
.ss-wplogin-hero-sub{font-size:14px;color:rgba(255,255,255,.78);margin-bottom:18px;}
.ss-wplogin-hero-year{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.5px;color:#fff;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);padding:5px 14px;border-radius:100px;}
body.ss-wplogin .privacy-policy-page-link{display:none!important;}

/* ═══════════════════ 2. HIDE WP DEFAULTS ═══════════════════ */
body.ss-wplogin #login h1{display:none!important;}
body.ss-wplogin #language-switcher,
body.ss-wplogin form[id*="language"],
body.ss-wplogin .wpml-login-ls-form,
body.ss-wplogin .pll-login-widget{display:none!important;}

/* ═══════════════════ 3. BRAND ═══════════════════ */
body.ss-wplogin .ss-login-brand{display:flex;align-items:center;gap:14px;margin-bottom:32px;}
body.ss-wplogin .ss-login-logo{height:52px;width:auto;object-fit:contain;}
body.ss-wplogin .ss-login-logo-fb{
  width:52px;height:52px;border-radius:14px;background:#4F8952;
  color:#fff;font-size:26px;font-weight:800;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
body.ss-wplogin .ss-login-school{font-size:15px;font-weight:700;color:#012A36;line-height:1.25;display:block;}
body.ss-wplogin .ss-wp-card-title{font-size:23px;font-weight:800;color:#012A36;margin:0 0 4px;letter-spacing:-.3px;}
body.ss-wplogin .ss-wp-card-sub{font-size:13px;color:#6FA672;margin:0 0 24px;}

/* ═══════════════════ 4. FORM CARD ═══════════════════ */
body.ss-wplogin #loginform,
body.ss-wplogin #lostpasswordform,
body.ss-wplogin #resetpassform{
  border-radius:16px!important;padding:28px 28px 24px!important;
  box-shadow:0 1px 3px rgba(1,42,54,.07),0 4px 14px rgba(1,42,54,.05)!important;
  border:none!important;margin:0 0 16px!important;background:#fff!important;
}
body.ss-wplogin #loginform p,
body.ss-wplogin #lostpasswordform p,
body.ss-wplogin #resetpassform p{margin-bottom:16px!important;padding:0!important;}

/* ═══════════════════ 5. LABELS ═══════════════════ */
body.ss-wplogin #loginform>p>label,
body.ss-wplogin #lostpasswordform>p>label,
body.ss-wplogin #resetpassform>p>label{
  display:flex!important;flex-direction:column!important;gap:6px!important;
  font-size:12px!important;font-weight:700!important;
  text-transform:uppercase!important;letter-spacing:.4px!important;
  color:#4F8952!important;margin-bottom:0!important;
}
body.ss-wplogin label br{display:none!important;}
body.ss-wplogin .user-pass-wrap>label,
body.ss-wplogin .resetpassword-wrap>label{
  display:block!important;font-size:12px!important;font-weight:700!important;
  text-transform:uppercase!important;letter-spacing:.4px!important;
  color:#4F8952!important;margin-bottom:6px!important;
}
body.ss-wplogin .user-pass-wrap,
body.ss-wplogin .resetpassword-wrap{margin-bottom:16px!important;}

/* ═══════════════════ 6. INPUTS ═══════════════════ */
body.ss-wplogin input[type="text"],
body.ss-wplogin input[type="password"],
body.ss-wplogin input[type="email"]{
  display:block!important;box-sizing:border-box!important;
  width:100%!important;height:48px!important;
  border:1.5px solid #EBEBEB!important;border-radius:12px!important;
  font-family:inherit!important;font-size:14px!important;
  color:#012A36!important;background-color:#fff!important;
  background-repeat:no-repeat!important;background-position:14px center!important;background-size:18px 18px!important;
  padding:0 14px 0 46px!important;outline:none!important;box-shadow:none!important;
  -webkit-appearance:none!important;
  transition:border-color .18s,box-shadow .18s!important;
}
body.ss-wplogin input[type="password"]{padding-right:48px!important;}
body.ss-wplogin input[type="text"]:focus,
body.ss-wplogin input[type="password"]:focus,
body.ss-wplogin input[type="email"]:focus{
  border-color:#4F8952!important;
  box-shadow:0 0 0 3px rgba(79,137,82,.13)!important;
}
body.ss-wplogin input::placeholder{color:#A7D8A9!important;opacity:1!important;}
body.ss-wplogin #user_login,body.ss-wplogin #user_email{background-image:url("<?= esc_attr($icon_user) ?>")!important;}
body.ss-wplogin #user_pass,body.ss-wplogin #pass1,body.ss-wplogin #pass2{background-image:url("<?= esc_attr($icon_lock) ?>")!important;}
body.ss-wplogin #lostpasswordform input[type="text"]{background-image:url("<?= esc_attr($icon_mail) ?>")!important;}

/* ═══════════════════ 7. EYE BUTTON ═══════════════════ */
body.ss-wplogin .wp-pwd{position:relative!important;}
body.ss-wplogin .wp-hide-pw{
  position:absolute!important;right:2px!important;top:0!important;bottom:0!important;
  width:46px!important;height:auto!important;
  background:none!important;border:none!important;box-shadow:none!important;
  color:#A7D8A9!important;cursor:pointer!important;
  display:flex!important;align-items:center!important;justify-content:center!important;
  padding:0!important;margin:0!important;min-height:unset!important;border-radius:0!important;
}
body.ss-wplogin .wp-hide-pw:hover,body.ss-wplogin .wp-hide-pw:focus{color:#4F8952!important;background:none!important;box-shadow:none!important;}
body.ss-wplogin .wp-hide-pw .dashicons{font-size:18px!important;width:18px!important;height:18px!important;}

/* ═══════════════════ 8. REMEMBER ME ═══════════════════ */
body.ss-wplogin .forgetmenot{margin-bottom:20px!important;padding:0!important;}
body.ss-wplogin .forgetmenot label{
  display:flex!important;align-items:center!important;gap:8px!important;
  text-transform:none!important;letter-spacing:0!important;
  font-size:13px!important;font-weight:400!important;color:#6FA672!important;cursor:pointer!important;
}
body.ss-wplogin input[type="checkbox"]{
  width:15px!important;height:15px!important;accent-color:#4F8952!important;
  margin:0!important;flex-shrink:0!important;
}

/* ═══════════════════ 9. SUBMIT ═══════════════════ */
body.ss-wplogin .submit{margin-bottom:0!important;padding:0!important;}
body.ss-wplogin #wp-submit,
body.ss-wplogin #lostpasswordform input[type="submit"],
body.ss-wplogin #resetpassform input[type="submit"]{
  width:100%!important;height:48px!important;
  background:linear-gradient(135deg,#4F8952,#3a6e3d)!important;border:none!important;border-radius:12px!important;
  color:#fff!important;font-family:inherit!important;font-size:15px!important;font-weight:700!important;
  letter-spacing:0!important;text-shadow:none!important;box-shadow:0 3px 10px rgba(79,137,82,.28)!important;
  cursor:pointer!important;display:block!important;padding:0!important;margin:0!important;
  -webkit-appearance:none!important;
  transition:filter .18s,transform .18s,box-shadow .18s!important;
  margin-bottom: 10px!important;
}
body.ss-wplogin #wp-submit:hover,
body.ss-wplogin #lostpasswordform input[type="submit"]:hover,
body.ss-wplogin #resetpassform input[type="submit"]:hover{
  filter:brightness(1.1)!important;/*transform:translateY(-1px)!important;*/
  box-shadow:0 6px 20px rgba(79,137,82,.35)!important;
}
body.ss-wplogin #wp-submit:focus{box-shadow:0 0 0 3px rgba(79,137,82,.3)!important;outline:none!important;}

/* ═══════════════════ 10. ERRORS ═══════════════════ */
body.ss-wplogin #login_error{
  border-radius:10px!important;border:1px solid #F5A0A2!important;background:#fff0f0!important;
  color:#D05155!important;padding:11px 15px!important;font-size:13px!important;
  font-weight:500!important;margin-bottom:16px!important;border-left:none!important;
}
body.ss-wplogin .message,body.ss-wplogin .updated{
  border-radius:10px!important;border:1px solid #A7D8A9!important;background:#f0fff4!important;
  color:#245326!important;padding:11px 15px!important;font-size:13px!important;
  font-weight:500!important;margin-bottom:16px!important;border-left:none!important;
}

/* ═══════════════════ 11. BOTTOM LINKS ═══════════════════ */
body.ss-wplogin #nav,body.ss-wplogin #backtoblog{text-align:center!important;margin:0 0 8px!important;}
body.ss-wplogin #nav a,body.ss-wplogin #backtoblog a{
  color:#224768!important;font-size:13px!important;font-weight:600!important;text-decoration:none!important;
}
body.ss-wplogin #nav a:hover,body.ss-wplogin #backtoblog a:hover{text-decoration:underline!important;}

/* ═══════════════════ 12. QR + GOOGLE MYGTUKAI ═══════════════════ */
.ss-wpl-divider{
  display:flex;align-items:center;gap:10px;
  margin:18px 0 14px;color:#A7D8A9;font-size:12px;font-weight:600;
  text-transform:uppercase;letter-spacing:.5px;
}
.ss-wpl-divider::before,.ss-wpl-divider::after{
  content:'';flex:1;height:1px;background:#EBEBEB;
}
.ss-wpl-alt-row{display:flex;gap:10px;margin-bottom:4px;}
/* Pilnas mygtukas (kai tik vienas būdas) */
.ss-wpl-full-btn{
  width:100%;height:48px;border-radius:12px;border:1.5px solid #EBEBEB;
  background:#fff;cursor:pointer;font-family:inherit;font-size:14px;font-weight:600;
  color:#012A36;display:flex;align-items:center;justify-content:center;gap:10px;
  transition:border-color .18s,box-shadow .18s;margin-bottom:8px;
}
.ss-wpl-full-btn:hover{border-color:#4F8952;box-shadow:0 0 0 3px rgba(79,137,82,.1);}
/* Ikoniniu mygtukas (kai abu) */
.ss-wpl-icon-btn{
  flex:1;height:48px;border-radius:12px;border:1.5px solid #EBEBEB;
  background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;
  transition:border-color .18s,box-shadow .18s;
}
.ss-wpl-icon-btn:hover{border-color:#4F8952;box-shadow:0 0 0 3px rgba(79,137,82,.1);}
/* QR Modalas */
.ss-wpl-qr-modal{
  position:fixed;inset:0;z-index:99999;display:none;
  align-items:center;justify-content:center;
}
.ss-wpl-qr-overlay{position:absolute;inset:0;background:rgba(1,42,54,.45);backdrop-filter:blur(4px);}
.ss-wpl-qr-dialog{
  position:relative;z-index:1;background:#fff;border-radius:20px;
  padding:32px 28px 28px;width:340px;max-width:calc(100vw - 32px);
  box-shadow:0 8px 40px rgba(1,42,54,.18);text-align:center;
}
.ss-wpl-qr-close{
  position:absolute;top:14px;right:14px;width:32px;height:32px;
  border-radius:8px;border:none;background:transparent;cursor:pointer;
  display:flex;align-items:center;justify-content:center;color:#6FA672;
}
.ss-wpl-qr-close:hover{background:#f3f7f3;color:#012A36;}
.ss-wpl-qr-title{font-size:17px;font-weight:800;color:#012A36;margin:0 0 6px;}
.ss-wpl-qr-desc{font-size:13px;color:#6FA672;margin:0 0 20px;}
.ss-wpl-qr-img-wrap{position:relative;background:#f8faf8;border-radius:14px;padding:16px;display:inline-block;margin-bottom:16px;}
.ss-wpl-qr-img-wrap>img{display:block;width:180px;height:180px;}
/* Logotipas QR centre (ecc=H — kodas lieka nuskaitomas) */
.ss-wpl-qr-logo{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:46px;height:46px;background:#fff;border-radius:9px;padding:5px;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 5px rgba(1,42,54,.18);}
.ss-wpl-qr-logo img{max-width:100%;max-height:100%;width:auto!important;height:auto!important;object-fit:contain;display:block;}
.ss-wpl-qr-timer{font-size:12px;color:#A7D8A9;}
.ss-wpl-qr-state{display:none;}
.ss-wpl-qr-state.active{display:block;}
.ss-wpl-qr-spinner svg{width:40px;height:40px;animation:ss-spin 1s linear infinite;color:#4F8952;margin:20px auto;}
@keyframes ss-spin{to{transform:rotate(360deg);}}
/* QR rezultatai */
.ss-wpl-qr-result-ico{
  width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:16px auto 12px;
}
.ss-wpl-qr-result-ico--ok{background:#e8f5e9;color:#4F8952;}
.ss-wpl-qr-result-ico--ok svg,.ss-wpl-qr-result-ico--err svg,.ss-wpl-qr-result-ico--warn svg{width:28px;height:28px;}
.ss-wpl-qr-result-ico--err{background:#fff0f0;color:#D05155;}
.ss-wpl-qr-result-ico--warn{background:#fff8e1;color:#e89d00;}
.ss-wpl-qr-refresh-btn{
  margin-top:14px;height:40px;padding:0 24px;border-radius:10px;
  background:#4F8952;color:#fff;border:none;font-size:13px;font-weight:700;cursor:pointer;
}

/* ═══════════════════ 13. 2FA FORMA (WP admin) ═══════════════════ */
body.ss-wplogin.ss-action-ss_2fa #loginform{display:none!important;}
body.ss-wplogin.ss-action-ss_2fa #nav{display:none!important;}
body.ss-wplogin.ss-action-ss_2fa #backtoblog{display:none!important;}
/* Kortelė — identiška #loginform stilistikai */
.ss-wpl-2fa-card{
  border-radius:16px;padding:28px 28px 24px;
  box-shadow:0 1px 3px rgba(1,42,54,.07),0 4px 14px rgba(1,42,54,.05);
  background:#fff;margin:0 0 16px;
}
.ss-wpl-2fa-err{
  background:#fff0f0;border:1px solid #F5A0A2;border-radius:10px;
  padding:10px 14px;font-size:13px;color:#D05155;margin-bottom:16px;
}
.ss-wpl-2fa-err--blocked{
  background:#fef8ec;border-color:#f5c76e;color:#92400e;
  display:flex;align-items:flex-start;gap:8px;line-height:1.5;
}
.ss-wpl-2fa-wrap{margin-bottom:20px;}
.ss-wpl-2fa-lbl{
  display:block;font-size:12px;font-weight:700;text-transform:uppercase;
  letter-spacing:.4px;color:#4F8952;margin-bottom:8px;
}
.ss-wpl-2fa-input{
  display:block!important;width:100%!important;height:60px!important;
  border:1.5px solid #EBEBEB!important;border-radius:12px!important;
  font-size:26px!important;font-weight:800!important;text-align:center!important;
  letter-spacing:6px!important;color:#012A36!important;
  background:#fff!important;box-sizing:border-box!important;
  padding:0 14px!important;outline:none!important;
  font-family:var(--fn)!important;
}
.ss-wpl-2fa-input:focus{border-color:#4F8952!important;box-shadow:0 0 0 3px rgba(79,137,82,.13)!important;}
.ss-wpl-2fa-hint{font-size:12px;color:#A7D8A9;margin:6px 0 0;text-align:center;}
.ss-wpl-2fa-btn{
  width:100%;height:48px;background:linear-gradient(135deg,#4F8952,#3a6e3d);border:none;border-radius:12px;
  color:#fff;font-family:var(--fn);font-size:15px;font-weight:700;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;
  box-shadow:0 3px 10px rgba(79,137,82,.28);
  transition:filter .18s,box-shadow .18s;
}
.ss-wpl-2fa-btn:hover{filter:brightness(1.1);box-shadow:0 6px 20px rgba(79,137,82,.35);}
/* Auto-submit: kad neparodytų submit mygtuko kai yra 6 skaitmenys */
#ss-wpl-2fa-form[data-submitting] .ss-wpl-2fa-btn::after{content:" ⋯";}

/* ═══════════════════ 14. MOBILUSIS ═══════════════════ */
@media(max-width:768px){
  /* Dešinysis panel — paslėptas */
  #ss-wplogin-right{display:none!important;}

  /* Body: paprastas blokas, ne flex — patikimesnis mobiliame */
  body.ss-wplogin{
    display:block!important;
    min-height:100vh!important;
  }

  /* Login konteineris: iš flex-column į block */
  body.ss-wplogin #login{
    display:block!important;
    width:100%!important;max-width:100%!important;
    min-height:100vh!important;
    padding:44px 20px 36px!important;
    box-sizing:border-box!important;
    box-shadow:none!important;
    flex:unset!important;
  }

  /* Brand tarpai */
  body.ss-wplogin .ss-login-brand{margin-bottom:24px!important;}
  body.ss-wplogin .ss-wp-card-sub{margin-bottom:20px!important;}

  /* Form kortelė — mažesni padding'ai */
  body.ss-wplogin #loginform,
  body.ss-wplogin #lostpasswordform,
  body.ss-wplogin #resetpassform{
    padding:22px 20px 20px!important;
  }
}

/* Labai maži ekranai — telefonai <400px */
@media(max-width:400px){
  body.ss-wplogin #login{
    padding:36px 14px 28px!important;
  }
  body.ss-wplogin #loginform,
  body.ss-wplogin #lostpasswordform,
  body.ss-wplogin #resetpassform{
    padding:18px 14px 16px!important;
  }
  body.ss-wplogin .ss-login-brand{
    gap:10px!important;margin-bottom:18px!important;
  }
  body.ss-wplogin .ss-login-logo{height:40px!important;}
  body.ss-wplogin .ss-login-logo-fb{width:40px!important;height:40px!important;font-size:20px!important;}
  body.ss-wplogin .ss-wp-card-title{font-size:20px!important;}
  /* Mygtukų pilnas plotis */
  .ss-wpl-full-btn,.ss-wpl-icon-btn{height:44px!important;}
}

/* ═══════════════════ 15. SLAPTAŽODŽIO KEITIMAS (action=rp) ═══════════════════ */

/* Confirm password field */
body.ss-wplogin #resetpassform .user-pass2-wrap{margin-bottom:16px!important;}
body.ss-wplogin #resetpassform .user-pass2-wrap>label{
  display:block!important;font-size:12px!important;font-weight:700!important;
  text-transform:uppercase!important;letter-spacing:.4px!important;
  color:#4F8952!important;margin-bottom:6px!important;
}

/* Strength meter bar */
body.ss-wplogin #pass-strength-result{
  display:block!important;border-radius:8px!important;border:none!important;
  font-size:12px!important;font-weight:700!important;text-align:center!important;
  padding:7px 12px!important;margin:6px 0 10px!important;
  background:#f3f7f3!important;color:#A7D8A9!important;
}
body.ss-wplogin #pass-strength-result.short,
body.ss-wplogin #pass-strength-result.bad{
  background:#fff0f0!important;color:#D05155!important;
}
body.ss-wplogin #pass-strength-result.good{
  background:#fff8e1!important;color:#b27800!important;
}
body.ss-wplogin #pass-strength-result.strong{
  background:#f0fff4!important;color:#245326!important;
}

/* Weak password warning row */
body.ss-wplogin .pw-weak{
  margin:0 0 14px!important;padding:10px 14px!important;
  background:#fff8e1!important;border:1px solid #f5d76a!important;
  border-radius:10px!important;
}
body.ss-wplogin .pw-weak label{
  display:flex!important;align-items:center!important;gap:8px!important;
  text-transform:none!important;letter-spacing:0!important;
  font-size:13px!important;font-weight:500!important;color:#5a4000!important;
  cursor:pointer!important;margin:0!important;
}

/* Password note / description */
body.ss-wplogin #resetpassform .description,
body.ss-wplogin #resetpassform p.description{
  font-size:12px!important;color:#8c8f94!important;
  margin:0 0 14px!important;line-height:1.55!important;padding:0!important;
}

/* "Generuoti slaptažodį" button */
body.ss-wplogin .wp-generate-pw{margin:0 0 16px!important;padding:0!important;}
body.ss-wplogin .wp-generate-pw .button,
body.ss-wplogin .button.wp-generate-pw{
  background:transparent!important;
  border:1.5px solid #EBEBEB!important;border-radius:10px!important;
  color:#6FA672!important;font-size:13px!important;font-weight:600!important;
  padding:0 18px!important;height:38px!important;line-height:36px!important;
  box-shadow:none!important;text-shadow:none!important;
  font-family:inherit!important;cursor:pointer!important;
  text-decoration:none!important;display:inline-block!important;
  transition:border-color .18s,color .18s,background .18s!important;
}
body.ss-wplogin .wp-generate-pw .button:hover,
body.ss-wplogin .button.wp-generate-pw:hover{
  border-color:#4F8952!important;color:#4F8952!important;
  background:#f3f7f3!important;box-shadow:none!important;
}

/* Hide WP's password toggle icon inside generate row (clutters UI) */
body.ss-wplogin .wp-cancel-pw{display:none!important;}

/* ── SUBMIT MYGTUKAS — visada matomas, disabled kai silpnas slaptažodis ── */
/* Aukšto specifiškumo selector nugali WP .hidden { display:none!important } */
body.ss-wplogin #login #resetpassform #wp-submit,
body.ss-wplogin #login #resetpassform #wp-submit.hidden{
  display:block!important;visibility:visible!important;
}
/* Disabled būsena (kai slaptažodis silpnas, laukia checkbox) */
body.ss-wplogin #resetpassform #wp-submit[disabled]{
  opacity:.4!important;cursor:not-allowed!important;
  filter:none!important;transform:none!important;box-shadow:none!important;
}
/* Kai vartotojas pažymėjo "naudoti silpną" — pilna spalva */
body.ss-wplogin #resetpassform #wp-submit:not([disabled]){
  opacity:1!important;
}
</style>
        <?php
    }

    /* ═══════════════════════════════════════════════════════════════
       Brand + papildomi elementai
       ═══════════════════════════════════════════════════════════════ */
    public static function inject_brand( string $message ): string {
        $action = sanitize_text_field( $_GET['action'] ?? 'login' );
        // 2FA puslapyje brand'ą injekuoja render_2fa_message
        if ( $action === 'ss_2fa' ) return $message;

        $site_name = get_option( 'ss_school_name', SS_SCHOOL_NAME );
        $logo_id   = (int) get_option( 'ss_logo_id', 0 );
        $logo_url  = $logo_id
            ? wp_get_attachment_image_url( $logo_id, 'medium' )
            : get_option( 'ss_logo_url', '' );
        if ( ! $logo_url ) {
            $tlid = get_theme_mod( 'custom_logo' );
            if ( $tlid ) $logo_url = wp_get_attachment_image_url( $tlid, 'medium' );
        }

        $titles = [
            'lostpassword' => [ 'Slaptažodžio atkūrimas', 'Įveskite el. paštą arba vartotojo vardą' ],
            'rp'           => [ 'Naujas slaptažodis',     'Įveskite naują slaptažodį'                ],
            'resetpass'    => [ 'Naujas slaptažodis',     'Įveskite naują slaptažodį'                ],
        ];
        [ $title, $sub ] = $titles[ $action ] ?? [ 'Prisijungimas', 'Įveskite savo prisijungimo duomenis' ];

        ob_start(); ?>
        <div class="ss-login-brand">
            <?php if ( $logo_url ) : ?>
                <img src="<?= esc_url( $logo_url ) ?>" alt="<?= esc_attr( $site_name ) ?>" class="ss-login-logo">
            <?php else : ?>
                <div class="ss-login-logo-fb"><?= esc_html( mb_substr( $site_name, 0, 1 ) ) ?></div>
            <?php endif; ?>
            <div><span class="ss-login-school"><?= esc_html( $site_name ) ?></span></div>
        </div>
        <h2 class="ss-wp-card-title"><?= esc_html( $title ) ?></h2>
        <p  class="ss-wp-card-sub"><?= esc_html( $sub ) ?></p>
        <?php
        return ob_get_clean() . $message;
    }

    /** Branded „hero" dešiniajame panelyje, kai nenustatyta prisijungimo nuotrauka. */
    private static function right_panel_hero(): string {
        $site    = get_option( 'ss_school_name', SS_SCHOOL_NAME );
        $year    = get_option( 'ss_school_year', SS_Time::date( 'Y' ) . '–' . ( (int) SS_Time::date( 'Y' ) + 1 ) );
        $logo_id = (int) get_option( 'ss_logo_id', 0 );
        $logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : get_option( 'ss_logo_url', '' );
        ob_start(); ?>
        <div class="ss-wplogin-hero">
            <?php if ( $logo ) : ?>
                <img src="<?= esc_url( $logo ) ?>" alt="" class="ss-wplogin-hero-logo">
            <?php endif; ?>
            <div class="ss-wplogin-hero-title"><?= esc_html( $site ) ?></div>
            <div class="ss-wplogin-hero-sub">Tvarkaraščių valdymo sistema</div>
            <div class="ss-wplogin-hero-year"><?= esc_html( $year ) ?> m. m.</div>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Injekuoja: dešinysis panel + QR/Google mygtukus + QR modalą */
    public static function inject_extras(): void {
        $action = sanitize_text_field( $_GET['action'] ?? 'login' );

        // Dešinysis panel visada — su nuotrauka arba branded „hero".
        $image_id  = (int) get_option( 'ss_login_image_id', 0 );
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '';
        if ( $image_url ) {
            echo '<div id="ss-wplogin-right"><img src="' . esc_url( $image_url ) . '" alt=""></div>';
        } else {
            echo '<div id="ss-wplogin-right" class="ss-wplogin-right--brand">' . self::right_panel_hero() . '</div>';
        }

        // QR ir Google tik ant pagrindinio login puslapio
        if ( $action !== 'login' ) return;

        $qr_on  = (bool) get_option( 'ss_qr_login_enabled', 0 );
        $gg_on  = SS_Google_Auth::is_enabled();
        if ( ! $qr_on && ! $gg_on ) return;

        $both = $qr_on && $gg_on;
        ?>
        <div id="ss-wpl-alt-wrap">
            <?php if ( $both ) : ?>
            <div class="ss-wpl-alt-row">
                <button type="button" class="ss-wpl-icon-btn" id="ss-wpl-qr-btn" title="Prisijungti per QR kodą">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="5" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="16" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="5" y="16" width="3" height="3" fill="currentColor" stroke="none"/><path d="M14 14h3v3h-3zM17 17h3M17 20h3M20 17v3"/></svg>
                </button>
                <button type="button" class="ss-wpl-icon-btn" id="ss-wpl-gg-btn" title="Prisijungti per Google">
                    <svg width="20" height="20" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                </button>
            </div>
            <?php else : ?>
                <?php if ( $qr_on ) : ?>
                <button type="button" class="ss-wpl-full-btn" id="ss-wpl-qr-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="5" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="16" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="5" y="16" width="3" height="3" fill="currentColor" stroke="none"/><path d="M14 14h3v3h-3zM17 17h3M17 20h3M20 17v3"/></svg>
                    Prisijungti naudojant QR kodą
                </button>
                <?php endif; ?>
                <?php if ( $gg_on ) : ?>
                <button type="button" class="ss-wpl-full-btn" id="ss-wpl-gg-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                    Prisijungti naudojant Google
                </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php
        if ( $qr_on ) :
            $qr_logo_id  = (int) get_option( 'ss_logo_id', 0 );
            $qr_logo_url = $qr_logo_id ? wp_get_attachment_image_url( $qr_logo_id, 'thumbnail' ) : get_option( 'ss_logo_url', '' );
        ?>
        <!-- QR MODALAS (identiškas plugin login modalui) -->
        <div class="ss-wpl-qr-modal" id="ss-wpl-qr-modal">
            <div class="ss-wpl-qr-overlay" id="ss-wpl-qr-overlay"></div>
            <div class="ss-wpl-qr-dialog">
                <button class="ss-wpl-qr-close" id="ss-wpl-qr-close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
                <!-- Loading -->
                <div class="ss-wpl-qr-state active" id="ss-wpl-state-loading">
                    <div class="ss-wpl-qr-spinner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg></div>
                    <p style="color:#6FA672;font-size:13px;margin-top:8px;">Generuojamas QR kodas...</p>
                </div>
                <!-- Aktyvus -->
                <div class="ss-wpl-qr-state" id="ss-wpl-state-active">
                    <p class="ss-wpl-qr-title">Prisijungimas naudojant QR</p>
                    <p class="ss-wpl-qr-desc">Nuskenuokite telefonu,<br>kuriame esate prisijungę prie paskyros</p>
                    <div class="ss-wpl-qr-img-wrap">
                        <img id="ss-wpl-qr-img" src="" alt="QR kodas">
                        <?php if ( $qr_logo_url ) : ?>
                        <div class="ss-wpl-qr-logo"><img src="<?= esc_url( $qr_logo_url ) ?>" alt=""></div>
                        <?php endif; ?>
                    </div>
                    <p class="ss-wpl-qr-timer" id="ss-wpl-qr-timer">3:00</p>
                </div>
                <!-- Patvirtinta -->
                <div class="ss-wpl-qr-state" id="ss-wpl-state-confirmed">
                    <div class="ss-wpl-qr-result-ico ss-wpl-qr-result-ico--ok">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    </div>
                    <h3 style="font-size:17px;font-weight:800;color:#012A36;margin:0 0 6px;">Patvirtinta!</h3>
                    <p style="font-size:13px;color:#6FA672;margin:0;">Jungiamasi...</p>
                </div>
                <!-- 2FA reikalingas -->
                <div class="ss-wpl-qr-state" id="ss-wpl-state-2fa">
                    <div class="ss-wpl-qr-result-ico" style="background:#e8f0fe;color:#224768;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <p style="font-size:15px;font-weight:700;color:#012A36;margin:0 0 6px;">2FA kodas</p>
                    <p style="font-size:13px;color:#6FA672;margin:0 0 14px;">Įveskite kodą iš Authenticator programėlės</p>
                    <input type="text" id="ss-wpl-qr-2fa-code"
                           inputmode="numeric" maxlength="6" pattern="[0-9]{6}"
                           placeholder="000 000" autocomplete="one-time-code"
                           style="display:block;width:100%;height:52px;border:1.5px solid #EBEBEB;border-radius:12px;font-size:22px;font-weight:700;text-align:center;letter-spacing:8px;color:#012A36;box-sizing:border-box;padding:0 14px;outline:none;">
                    <div id="ss-wpl-qr-2fa-err" style="display:none;color:#D05155;font-size:12px;margin-top:6px;"></div>
                    <button type="button" id="ss-wpl-qr-2fa-submit" style="margin-top:12px;width:100%;height:44px;background:#4F8952;color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;">Patvirtinti</button>
                </div>
                <!-- Pasibaigė -->
                <div class="ss-wpl-qr-state" id="ss-wpl-state-expired">
                    <div class="ss-wpl-qr-result-ico ss-wpl-qr-result-ico--warn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16.5" r=".5" fill="currentColor"/></svg>
                    </div>
                    <p style="font-size:15px;font-weight:700;color:#012A36;margin:8px 0 10px;">QR kodas baigė galioti</p>
                    <button class="ss-wpl-qr-refresh-btn" id="ss-wpl-qr-refresh">Bandyti dar kartą</button>
                </div>
                <!-- Atmesta -->
                <div class="ss-wpl-qr-state" id="ss-wpl-state-rejected">
                    <div class="ss-wpl-qr-result-ico ss-wpl-qr-result-ico--err">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <p style="font-size:15px;font-weight:700;color:#012A36;margin:8px 0 10px;">Prisijungimas atmestas</p>
                    <button class="ss-wpl-qr-refresh-btn" id="ss-wpl-qr-refresh2">Bandyti dar kartą</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php
    }

    /* ── Nukreipimas po prisijungimo ───────────────────────────────── */
    public static function redirect_home( $redirect_to, $requested, $user ): string {
        if ( is_wp_error( $user ) ) return $redirect_to;
        return home_url( '/' );
    }
}
