<?php if (!defined('ABSPATH')) exit;
$_ss_2fa_pending = sanitize_text_field($_GET['ss_2fa_pending'] ?? '');
$logo_id     = (int) get_option('ss_logo_id', 0);
$logo_url    = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : get_option('ss_logo_url', '');
if (!$logo_url) {
    $_theme_lid = get_theme_mod('custom_logo');
    $logo_url   = $_theme_lid ? wp_get_attachment_image_url($_theme_lid, 'medium') : '';
}
$site_name   = get_option('ss_school_name', SS_SCHOOL_NAME);
$school_year = get_option('ss_school_year', SS_Time::date('Y').'–'.((int)SS_Time::date('Y')+1));
$login_image_id  = (int)get_option('ss_login_image_id', 0);
$login_image_url = $login_image_id ? wp_get_attachment_image_url($login_image_id, 'full') : '';
$has_image       = !empty($login_image_url);

// Slaptažodžio reikalavimai (rodomi iš karto prie slaptažodžio lauko).
$pw_min   = class_exists('SS_Security') ? max(8, (int) SS_Security::get('ss_sec_pw_min_length')) : 8;
$pw_upper = class_exists('SS_Security') ? (bool) SS_Security::get('ss_sec_pw_require_upper') : true;
$pw_digit = class_exists('SS_Security') ? (bool) SS_Security::get('ss_sec_pw_require_digit') : true;
// Daugkartinio naudojimo reikalavimų sąrašas (HTML).
$ss_pw_reqs_html = function($for) use ($pw_min, $pw_upper, $pw_digit) {
    ob_start(); ?>
    <ul class="ss-pw-reqs" data-for="<?= esc_attr($for) ?>">
      <li data-rule="len"><span class="ss-pwr-ico"></span>Bent <?= (int)$pw_min ?> simbolių</li>
      <?php if ($pw_upper): ?><li data-rule="upper"><span class="ss-pwr-ico"></span>Bent viena didžioji raidė</li><?php endif; ?>
      <?php if ($pw_digit): ?><li data-rule="digit"><span class="ss-pwr-ico"></span>Bent vienas skaičius</li><?php endif; ?>
    </ul>
    <?php return ob_get_clean();
};
?>
<div class="ss-login-page<?= $has_image ? '' : ' ss-login-only' ?>">

  <div class="ss-login-left">
    <div class="ss-login-inner">

      <div class="ss-login-brand">
        <?php if ($logo_url): ?>
          <img src="<?= esc_url($logo_url) ?>" alt="<?= esc_attr($site_name) ?>" class="ss-login-logo">
        <?php else: ?>
          <div class="ss-login-logo-fb"><?= esc_html(mb_substr($site_name,0,1)) ?></div>
        <?php endif; ?>
        <div class="ss-login-brand-txt">
          <span class="ss-login-school"><?= esc_html($site_name) ?></span>
          
        </div>
      </div>

      <!-- LOGIN CARD -->
      <div class="ss-form-card" id="ss-card-login">
        <h2 class="ss-card-title">Prisijungimas</h2>
        <p class="ss-card-sub">Įveskite savo prisijungimo duomenis</p>
        <form id="ss-login-form" novalidate autocomplete="on">
          <?php /* 1.2: Honeypot laukas - botai jį užpildo, žmonės ne */ ?>
          <div style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden" aria-hidden="true">
            <input type="text" name="website" value="" tabindex="-1" autocomplete="off">
          </div>
          <div class="ss-fld">
            <label for="ss-user" class="ss-lbl">Vartotojo vardas arba el. paštas</label>
            <div class="ss-inp-wrap">
              <svg class="ss-inp-ico" viewBox="0 0 20 20" fill="currentColor"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg>
              <input type="text" id="ss-user" name="username" class="ss-inp ss-inp-ico-pad"
                     placeholder="xx.vardas.pavarde" autocomplete="username" required>
            </div>
          </div>
          <div class="ss-fld">
            <label for="ss-pass" class="ss-lbl">Slaptažodis</label>
            <div class="ss-inp-wrap">
              <svg class="ss-inp-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
              <input type="password" id="ss-pass" name="password" class="ss-inp ss-inp-ico-pad ss-inp-eye-pad"
                     placeholder="slaptažodis" autocomplete="current-password" required>
              <button type="button" class="ss-eye-btn" id="ss-toggle-pass" aria-label="Rodyti slaptažodį">
                <svg id="ss-eye-on" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                <svg id="ss-eye-off" viewBox="0 0 20 20" fill="currentColor" style="display:none"><path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/><path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.064 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/></svg>
              </button>
            </div>
          </div>
          <div class="ss-login-row">
            <label class="ss-remember"><input type="checkbox" id="ss-remember"> <span>Prisiminti mane</span></label>
            <button type="button" class="ss-link-btn" id="ss-show-forgot">Pamiršote slaptažodį?</button>
          </div>
          <div id="ss-login-err" class="ss-alert ss-alert-err" style="display:none"></div>
          <div id="ss-login-ok"  class="ss-alert ss-alert-ok"  style="display:none"></div>
          <?php if (class_exists('SS_Turnstile') && SS_Turnstile::is_enabled() && SS_Turnstile::get('ss_ts_login_widget')):
            $_ts_mode = SS_Turnstile::login_mode();
            $_ts_appearance = ($_ts_mode === 'always') ? 'always' : 'interaction-only';
          ?>
          <div id="ss-ts-login" class="cf-turnstile" style="margin:0 0 14px"
               data-sitekey="<?= esc_attr(SS_Turnstile::site_key()) ?>"
               data-action="login"
               data-appearance="<?= esc_attr($_ts_appearance) ?>"
               data-callback="ssTsLoginDone"></div>
          <?php endif; ?>
          <button type="submit" class="ss-submit-btn" id="ss-login-submit">
            <span class="ss-btn-lbl">Prisijungti</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
<?php
$_qr_on = (bool)get_option('ss_qr_login_enabled', 0) && !SS_Maintenance::qr_blocked();
$_gg_on = SS_Google_Auth::is_enabled();
$_both  = $_qr_on && $_gg_on;
$google_err = sanitize_text_field($_GET['ss_google_err'] ?? '');
?>
<?php if ($_qr_on || $_gg_on): ?>
          <div class="ss-qr-divider"><span>arba</span></div>

          <?php if ($_both): /* ── Abi opcijos: eilutė su tik SVG ── */ ?>
          <div class="ss-alt-methods">
            <button type="button" class="ss-alt-btn ss-alt-btn--qr" id="ss-qr-open-btn"
                    title="Prisijungti naudojant QR kodą" aria-label="Prisijungti naudojant QR kodą">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="5" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="16" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="5" y="16" width="3" height="3" fill="currentColor" stroke="none"/><path d="M14 14h3v3h-3zM17 17h3M17 20h3M20 17v3"/></svg>
            </button>
            <button type="button" class="ss-alt-btn ss-alt-btn--google" id="ss-google-login-btn"
                    title="Prisijungti naudojant Google" aria-label="Prisijungti naudojant Google">
              <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
            </button>
          </div>

          <?php else: /* ── Viena opcija: pilnas mygtukas su tekstu ── */ ?>

          <?php if ($_qr_on): ?>
          <button type="button" class="ss-qr-login-btn" id="ss-qr-open-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="5" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="16" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="5" y="16" width="3" height="3" fill="currentColor" stroke="none"/><path d="M14 14h3v3h-3zM17 17h3M17 20h3M20 17v3"/></svg>
            Prisijungti naudojant QR kodą
          </button>
          <?php endif; ?>

          <?php if ($_gg_on): ?>
          <button type="button" class="ss-google-login-btn" id="ss-google-login-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
            Prisijungti naudojant Google
          </button>
          <?php endif; ?>

          <?php endif; /* end single/both */ ?>

          <?php if ($google_err === 'not_linked'): ?>
          <div class="ss-alert ss-alert-err" style="margin-top:10px;">
            Ši Google paskyra mums nežinoma. Prisijunkite įprastu būdu ir susiekite Google paskyrą nustatymuose.
          </div>
          <?php endif; ?>
<?php endif; /* end qr||gg */ ?>
          <p class="ss-login-foot">
            Neturite paskyros?
            <button type="button" class="ss-link-btn" id="ss-show-register">Sukurti paskyrą</button>
          </p>
          <p class="ss-login-foot" style="margin-top:8px;">
            <a href="<?= esc_url(home_url('/')) ?>" class="ss-link-btn">← Grįžti į pagrindinį</a>
          </p>
        </form>
      </div>

      <!-- REGISTRATION CARD (multi-step) -->
      <div class="ss-form-card" id="ss-card-register" style="display:none">
        <button type="button" class="ss-back-btn" id="ss-reg-back-to-login">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
          Grįžti į prisijungimą
        </button>

        <h2 class="ss-card-title">Registracija</h2>

        <!-- Step indicator -->
        <div class="ss-reg-steps" aria-hidden="true">
          <span class="ss-reg-step active" data-step="1">1</span>
          <span class="ss-reg-bar"></span>
          <span class="ss-reg-step" data-step="2">2</span>
          <span class="ss-reg-bar"></span>
          <span class="ss-reg-step" data-step="3">3</span>
          <span class="ss-reg-bar"></span>
          <span class="ss-reg-step" data-step="4">4</span>
        </div>

        <div id="ss-reg-err" class="ss-alert ss-alert-err" style="display:none"></div>
        <div id="ss-reg-ok"  class="ss-alert ss-alert-ok"  style="display:none"></div>

        <!-- STEP 1: email -->
        <form id="ss-reg-form-email" class="ss-reg-step-form" data-step="1" novalidate>
          <div class="ss-fld">
            <label for="ss-reg-email" class="ss-lbl">El. paštas</label>
            <div class="ss-inp-wrap">
              <svg class="ss-inp-ico" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
              <input type="email" id="ss-reg-email" class="ss-inp ss-inp-ico-pad"
                     placeholder="vardas.pavarde@azuolynogimnazija.lt" autocomplete="email" required>
            </div>
            <p class="ss-fld-hint">Registruotis galima tik su mokykliniu paštu.</p>
          </div>
          <?php if (class_exists('SS_Turnstile') && SS_Turnstile::is_enabled() && SS_Turnstile::get('ss_ts_login_widget')): ?>
          <div id="ss-ts-register" class="cf-turnstile" style="margin:0 0 14px"
               data-sitekey="<?= esc_attr(SS_Turnstile::site_key()) ?>"
               data-action="register"
               data-appearance="<?= esc_attr(SS_Turnstile::login_mode() === 'always' ? 'always' : 'interaction-only') ?>"></div>
          <?php endif; ?>
          <button type="submit" class="ss-submit-btn" id="ss-reg-send-btn">
            <span class="ss-btn-lbl">Siųsti kodą</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
        </form>

        <!-- STEP 2: code -->
        <form id="ss-reg-form-code" class="ss-reg-step-form" data-step="2" novalidate style="display:none">
          <p class="ss-reg-mailto">Kodas išsiųstas į <strong id="ss-reg-mailto"></strong></p>
          <div class="ss-fld">
            <label for="ss-reg-code" class="ss-lbl">6-ženklis kodas</label>
            <input type="text" id="ss-reg-code" class="ss-inp ss-inp-code"
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   placeholder="" autocomplete="one-time-code" required>
          </div>
          <button type="submit" class="ss-submit-btn" id="ss-reg-verify-btn">
            <span class="ss-btn-lbl">Patvirtinti</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
          <p class="ss-login-foot">
            Negavote?
            <button type="button" class="ss-link-btn" id="ss-reg-resend">Siųsti naują kodą</button>
          </p>
        </form>

        <!-- STEP 2b: role choice (only for teacher-style emails) -->
        <div id="ss-reg-role-choice" class="ss-reg-step-form" data-step="role" style="display:none">
          <p class="ss-fld-hint" style="margin-bottom:14px">
            Jūsų el. paštas atrodo kaip darbuotojo. Pasirinkite, kas Jūs esate:
          </p>
          <div class="ss-role-grid">
            <button type="button" class="ss-role-btn" data-role="student" style="--rc:#1565C0;--rcbg:rgba(21,101,192,.1)">
              <span class="ss-role-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1 3 3 6 3s6-2 6-3v-5"/></svg>
              </span>
              <span class="ss-role-txt">
                <span class="ss-role-title">Mokinys</span>
              </span>
              <svg class="ss-role-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            </button>
            <button type="button" class="ss-role-btn" data-role="teacher" style="--rc:#6A1B9A;--rcbg:rgba(106,27,154,.1)">
              <span class="ss-role-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-7 8-7s8 3 8 7"/></svg>
              </span>
              <span class="ss-role-txt">
                <span class="ss-role-title">Mokytojas</span>
              </span>
              <svg class="ss-role-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            </button>
            <button type="button" class="ss-role-btn" data-role="employee" style="--rc:#E65100;--rcbg:rgba(230,81,0,.1)">
              <span class="ss-role-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l9 4v6c0 5-4 9-9 10-5-1-9-5-9-10V6l9-4z"/></svg>
              </span>
              <span class="ss-role-txt">
                <span class="ss-role-title">Darbuotojas</span>
              </span>
              <svg class="ss-role-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            </button>
            <button type="button" class="ss-role-btn" data-role="admin" style="--rc:#880E4F;--rcbg:rgba(136,14,79,.1)">
              <span class="ss-role-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l9 4v6c0 5-4 9-9 10-5-1-9-5-9-10V6l9-4z"/></svg>
              </span>
              <span class="ss-role-txt">
                <span class="ss-role-title">Administracija</span>
              </span>
              <svg class="ss-role-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
            </button>
          </div>
        </div>

        <!-- STEP 3 (staff): teacher / admin details -->
        <form id="ss-reg-form-staff" class="ss-reg-step-form" data-step="staff" novalidate style="display:none">
          <p class="ss-fld-hint" style="margin-bottom:12px" id="ss-staff-intro">Įveskite savo duomenis:</p>
          <div class="ss-mrow2">
            <div class="ss-fld">
              <label for="ss-reg-staff-first" class="ss-lbl">Vardas</label>
              <input type="text" id="ss-reg-staff-first" class="ss-inp" placeholder="Vardenis" autocomplete="given-name" required>
            </div>
            <div class="ss-fld">
              <label for="ss-reg-staff-last" class="ss-lbl">Pavardė</label>
              <input type="text" id="ss-reg-staff-last" class="ss-inp" placeholder="Pavardenis" autocomplete="family-name" required>
            </div>
          </div>
          <div class="ss-fld">
            <label for="ss-reg-staff-pass" class="ss-lbl">Slaptažodis</label>
            <input type="password" id="ss-reg-staff-pass" class="ss-inp" minlength="8" placeholder="Slaptažodis" autocomplete="new-password" required>
            <?= $ss_pw_reqs_html('ss-reg-staff-pass') ?>
          </div>
          <div class="ss-fld">
            <label for="ss-reg-staff-pass2" class="ss-lbl">Pakartokite slaptažodį</label>
            <input type="password" id="ss-reg-staff-pass2" class="ss-inp" minlength="8" autocomplete="new-password" required>
          </div>
          <button type="submit" class="ss-submit-btn" id="ss-reg-staff-submit">
            <span class="ss-btn-lbl">Baigti registraciją</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
          <p class="ss-login-foot" style="margin-top:8px">
            <button type="button" class="ss-link-btn" id="ss-reg-staff-back">← Pakeisti paskyros tipą</button>
          </p>
        </form>

        <!-- STEP 3: details -->
        <form id="ss-reg-form-details" class="ss-reg-step-form" data-step="3" novalidate style="display:none">
          <div class="ss-mrow2">
            <div class="ss-fld">
              <label for="ss-reg-first" class="ss-lbl">Vardas</label>
              <input type="text" id="ss-reg-first" class="ss-inp" placeholder="Vardenis" autocomplete="given-name" required>
            </div>
            <div class="ss-fld">
              <label for="ss-reg-last" class="ss-lbl">Pavardė</label>
              <input type="text" id="ss-reg-last" class="ss-inp" placeholder="Pavardenis" autocomplete="family-name" required>
            </div>
          </div>
          <div class="ss-mrow2">
            <div class="ss-fld">
              <label for="ss-reg-level" class="ss-lbl">Klasė</label>
              <select id="ss-reg-level" class="ss-inp" required>
                <option value="">— pasirinkti —</option>
                <option value="I">I</option>
                <option value="II">II</option>
                <option value="III">III</option>
                <option value="IV">IV</option>
              </select>
            </div>
            <div class="ss-fld">
              <label for="ss-reg-letter" class="ss-lbl">Raidė</label>
              <input type="text" id="ss-reg-letter" class="ss-inp" maxlength="3" placeholder="pvz. f" required>
            </div>
          </div>
          <p class="ss-fld-hint" id="ss-reg-class-preview">Pvz. IIf</p>
          <div class="ss-fld">
            <label for="ss-reg-pass" class="ss-lbl">Slaptažodis</label>
            <input type="password" id="ss-reg-pass" class="ss-inp" minlength="8" placeholder="Slaptažodis" autocomplete="new-password" required>
            <?= $ss_pw_reqs_html('ss-reg-pass') ?>
          </div>
          <div class="ss-fld">
            <label for="ss-reg-pass2" class="ss-lbl">Pakartokite slaptažodį</label>
            <input type="password" id="ss-reg-pass2" class="ss-inp" minlength="8" placeholder="Pakartokite slaptažodį" autocomplete="new-password" required>
          </div>
          <button type="submit" class="ss-submit-btn" id="ss-reg-submit-btn">
            <span class="ss-btn-lbl">Toliau</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
        </form>

        <!-- STEP 4: schedule code -->
        <form id="ss-reg-form-schedule" class="ss-reg-step-form" data-step="4" novalidate style="display:none">
          <div id="ss-schedule-prelinked" style="display:none;">
            <div class="ss-alert ss-alert-ok" style="display:block; margin-bottom:16px;">
              <strong>Jūsų el. paštas jau prisegtas prie tvarkaraščio!</strong><br>
              Tvarkaraštis: <strong id="ss-prelinked-class"></strong>
            </div>
            <p class="ss-fld-hint">Paspauskite „Baigti registraciją" ir jūsų paskyra bus sukurta automatiškai. Mes atsiųsime jums el.paštu jūsų registracijos patvirtinimo laišką, kuriame bus jūsų paskyros duomenys.</p>
            <button type="submit" class="ss-submit-btn" id="ss-reg-finish-prelinked" data-mode="prelinked">
              <span class="ss-btn-lbl">Baigti registraciją</span>
              <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
            </button>
          </div>
          <div id="ss-schedule-code-entry" style="display:none;">
            <p class="ss-fld-hint" style="margin-bottom:12px;">
              Įveskite tvarkaraščio kodą, kurį gavote iš kuratorės arba mokyklos administracijos.
            </p>
            <div class="ss-fld">
              <label for="ss-schedule-code" class="ss-lbl">Tvarkaraščio kodas</label>
              <input type="text" id="ss-schedule-code" class="ss-inp ss-inp-code" style="text-transform:uppercase; letter-spacing:3px; font-weight:700;"
                     maxlength="8" placeholder="" autocomplete="off">
            </div>
            <button type="submit" class="ss-submit-btn" id="ss-reg-finish-code" data-mode="code">
              <span class="ss-btn-lbl">Baigti registraciją</span>
              <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
            </button>
            <div style="margin-top:16px; padding-top:16px; border-top:1px solid #E5E7EB;">
              <button type="button" class="ss-link-btn" id="ss-reg-skip-code">Neturiu kodo — pateikti paraišką rankiniam patvirtinimui</button>
            </div>
          </div>
        </form>

        <!-- STEP 5: success -->
        <div id="ss-reg-success" class="ss-reg-success" style="display:none">
          <div class="ss-reg-success-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M8 12.5l3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3 id="ss-reg-success-title">Registracija sėkminga!</h3>
          <p id="ss-reg-success-message">
            Jūsų paskyra sukurta. Dabar galite prisijungti su savo el. paštu ir slaptažodžiu. Mes atsiųsime jums el.paštu jūsų registracijos patvirtinimo laišką, kuriame bus jūsų paskyros duomenys.
          </p>
          <button type="button" class="ss-submit-btn" id="ss-reg-done-btn">Prisijungti</button>
        </div>

        <!-- STEP 5b: pending (manual approval) -->
        <div id="ss-reg-pending" class="ss-reg-success" style="display:none">
          <div class="ss-reg-success-ico" style="color:#FFA500;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4" stroke-linecap="round"/><circle cx="12" cy="16" r="0.5" fill="currentColor"/></svg>
          </div>
          <h3>Anketa išsiųsta</h3>
          <p>
            Jūsų anketą peržiūrės administratorius ir priskirs tvarkaraštį.
            Kai paskyra bus patvirtinta — gausite pranešimą el. paštu ir galėsite prisijungti.
          </p>
          <button type="button" class="ss-submit-btn" id="ss-reg-pending-btn">Grįžti į prisijungimą</button>
        </div>
      </div>

      <!-- FORGOT PASSWORD CARD -->
      <div class="ss-form-card" id="ss-card-forgot" style="display:none">
        <button type="button" class="ss-back-btn" id="ss-back-to-login">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
          Grįžti į prisijungimą
        </button>
        <h2 class="ss-card-title">Slaptažodžio atkūrimas</h2>
        <p class="ss-card-sub">Įveskite el. paštą arba vartotojo vardą — išsiųsime nuorodą slaptažodžio keitimui.</p>
        <form id="ss-forgot-form" novalidate>
          <div class="ss-fld">
            <label for="ss-forgot-inp" class="ss-lbl">El. paštas arba vartotojo vardas</label>
            <div class="ss-inp-wrap">
              <svg class="ss-inp-ico" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
              <input type="text" id="ss-forgot-inp" class="ss-inp ss-inp-ico-pad"
                     placeholder="ona.onaite arba ona.onaite@azuolynogimnazija.lt" autocomplete="email">
            </div>
          </div>
          <div id="ss-forgot-err" class="ss-alert ss-alert-err" style="display:none"></div>
          <div id="ss-forgot-ok"  class="ss-alert ss-alert-ok"  style="display:none"></div>
          <?php if (class_exists('SS_Turnstile') && SS_Turnstile::is_enabled() && SS_Turnstile::get('ss_ts_login_widget')): ?>
          <div id="ss-ts-forgot" class="cf-turnstile" style="margin:0 0 14px"
               data-sitekey="<?= esc_attr(SS_Turnstile::site_key()) ?>"
               data-action="forgot"
               data-appearance="<?= esc_attr(SS_Turnstile::login_mode() === 'always' ? 'always' : 'interaction-only') ?>"></div>
          <?php endif; ?>
          <button type="submit" class="ss-submit-btn" id="ss-forgot-submit">
            <span class="ss-btn-lbl">Siųsti nuorodą</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
        </form>
      </div>

      <!-- ══ 2FA KORTELĖ ══ -->
      <div class="ss-form-card" id="ss-card-2fa" style="display:none">
        <h2 class="ss-card-title">Dviejų žingsnių autentifikacija</h2>
        <p class="ss-card-sub" id="ss-2fa-sub">Įveskite kodą</p>
        <div class="ss-fld">
          <label class="ss-lbl" for="ss-2fa-code" id="ss-2fa-lbl">2FA kodas</label>
          <input type="text" id="ss-2fa-code" class="ss-inp ss-inp-code"
                inputmode="numeric" maxlength="6" autocomplete="one-time-code"
                autofocus placeholder="">
        </div>
        <div id="ss-2fa-err" class="ss-alert ss-alert-err" style="display:none"></div>
        <button type="button" class="ss-submit-btn" id="ss-2fa-submit">
          <span class="ss-btn-lbl">
            Patvirtinti
          </span>
          <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
        </button>
        <p style="text-align:center;margin-top:14px;display:flex;gap:16px;justify-content:center;flex-wrap:wrap">
          <button type="button" class="ss-link-btn" id="ss-2fa-use-backup">Atsarginis kodas</button>
          <button type="button" class="ss-link-btn" id="ss-2fa-back">← Grįžti</button>
        </p>
      </div>

      <!-- ══ PRIVERSTINIS SLAPTAŽODŽIO KEITIMAS ══ -->
      <div class="ss-form-card" id="ss-card-newpw" style="display:none">
        <h2 class="ss-card-title">Nustatykite naują slaptažodį</h2>
        <p class="ss-card-sub">Prisijungėte su laikinu slaptažodžiu. Saugumui — pasirinkite naują.</p>
        <form id="ss-newpw-form" novalidate>
          <div class="ss-fld">
            <label for="ss-newpw-pass" class="ss-lbl">Naujas slaptažodis</label>
            <input type="password" id="ss-newpw-pass" class="ss-inp" minlength="8" placeholder="Naujas slaptažodis" autocomplete="new-password" required>
            <?= $ss_pw_reqs_html('ss-newpw-pass') ?>
          </div>
          <div class="ss-fld">
            <label for="ss-newpw-pass2" class="ss-lbl">Pakartokite slaptažodį</label>
            <input type="password" id="ss-newpw-pass2" class="ss-inp" minlength="8" autocomplete="new-password" required>
          </div>
          <div id="ss-newpw-err" class="ss-alert ss-alert-err" style="display:none"></div>
          <button type="submit" class="ss-submit-btn" id="ss-newpw-submit">
            <span class="ss-btn-lbl">Išsaugoti ir prisijungti</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
        </form>
      </div>

    </div>
  </div>

  <?php if (get_option('ss_qr_login_enabled', 0) && !SS_Maintenance::qr_blocked()): ?>
  <!-- ══ QR PRISIJUNGIMO MODALAS ══ -->
  <div class="ss-qr-modal" id="ss-qr-modal" role="dialog" aria-modal="true" aria-label="QR kodo prisijungimas" style="display:none">
    <div class="ss-qr-overlay" id="ss-qr-overlay"></div>
    <div class="ss-qr-dialog">
      <!-- Uždarymo mygtukas -->
      <button class="ss-qr-close" id="ss-qr-close" aria-label="Uždaryti">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>

      <!-- Loading -->
      <div class="ss-qr-state" id="ss-qr-state-loading">
        <div class="ss-qr-spinner">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        </div>
        <p>Generuojamas QR kodas...</p>
      </div>

      <!-- Aktyvus QR -->
      <div class="ss-qr-state" id="ss-qr-state-active" style="display:none">
        <div class="ss-qr-top">
          <h3 class="ss-qr-title">Prisijungimas naudojant QR kodu</h3>
          <p class="ss-qr-desc">Nuskenuokite kodą telefonu,<br>kuriame esate prisijungę prie paskyros</p>
        </div>
        <div class="ss-qr-code-frame" id="ss-qr-code-frame">
          <img id="ss-qr-img" src="" alt="QR kodas" class="ss-qr-img">
          <?php if ($logo_url): ?>
          <div class="ss-qr-logo-badge"><img src="<?= esc_url($logo_url) ?>" alt=""></div>
          <?php endif; ?>
        </div>
        <div class="ss-qr-timer-row">
          <div class="ss-qr-timer-wrap">
            <svg class="ss-qr-timer-svg" viewBox="0 0 88 88">
              <circle class="ss-qr-timer-bg"   cx="44" cy="44" r="38" fill="none" stroke-width="5"/>
              <circle class="ss-qr-timer-ring" cx="44" cy="44" r="38" fill="none" stroke-width="5"
                      id="ss-qr-timer-ring"
                      stroke-dasharray="238.76"
                      stroke-dashoffset="0"
                      transform="rotate(-90 44 44)"/>
            </svg>
            <span class="ss-qr-timer-txt" id="ss-qr-timer-txt">1:00</span>
          </div>
          <div class="ss-qr-timer-cap">
            <span class="ss-qr-wait-row"><!--<span class="ss-qr-pulse-dot"></span>-->Laukiama patvirtinimo</span>
            <span class="ss-qr-timer-sub">Kodas galioja ribotą laiką</span>
          </div>
        </div>
      </div>

      <!-- Patvirtinta -->
      <div class="ss-qr-state ss-qr-state--ok" id="ss-qr-state-confirmed" style="display:none">
        <div class="ss-qr-result-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
        </div>
        <h3>Patvirtinta!</h3>
        <p>Jungiamasi prie paskyros...</p>
        <div class="ss-qr-mini-spin">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.219-8.56" stroke-linecap="round"/></svg>
        </div>
      </div>

      <!-- Pasibaigė -->
      <div class="ss-qr-state" id="ss-qr-state-expired" style="display:none">
        <div class="ss-qr-result-ico ss-qr-result-ico--warn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16.5" r=".5" fill="currentColor"/></svg>
        </div>
        <h3>QR kodas baigė galioti</h3>
        <button class="ss-qr-refresh-btn" id="ss-qr-refresh">Bandyti dar kartą</button>
      </div>

      <!-- Atmesta -->
      <div class="ss-qr-state" id="ss-qr-state-rejected" style="display:none">
        <div class="ss-qr-result-ico ss-qr-result-ico--err">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <h3>Prisijungimas atmestas</h3>
        <button class="ss-qr-refresh-btn" id="ss-qr-refresh2">Bandyti dar kartą</button>
      </div>

    </div>
  </div>
  <?php endif; ?>

  <?php if ($has_image): ?>
  <div class="ss-login-right ss-login-right-image" aria-hidden="true">
    <img src="<?= esc_url($login_image_url) ?>" alt="" class="ss-login-bg-image">
  </div>
  <?php endif; ?>

</div>
<script>
  window.SS_PW_RULES = {
    min:   <?= (int)$pw_min ?>,
    upper: <?= $pw_upper ? 'true' : 'false' ?>,
    digit: <?= $pw_digit ? 'true' : 'false' ?>
  };
</script>
