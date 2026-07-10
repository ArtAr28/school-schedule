<?php
/**
 * Standalone login page template.
 * Loaded via template_redirect — theme header/footer never rendered.
 */
if (!defined('ABSPATH')) exit;

// Tell WP we're rendering a full page so wp_head() outputs correctly
global $wp_query;

// Variables for the login view
$site_name       = get_option('ss_school_name', SS_SCHOOL_NAME);
$school_year     = get_option('ss_school_year', date('Y').'–'.(date('Y')+1));
$login_image_id  = (int)get_option('ss_login_image_id', 0);
$login_image_url = $login_image_id ? wp_get_attachment_image_url($login_image_id, 'full') : '';
$has_image       = !empty($login_image_url);

// Logo
$logo_id  = (int)get_option('ss_logo_id', 0);
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : get_option('ss_logo_url', '');

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($site_name); ?> &mdash; Prisijungimas</title>
<style>
  /* Reset any theme leftovers */
  *,*::before,*::after{box-sizing:border-box;}
  html,body{margin:0;padding:0;height:100%;}
  body{background:var(--bg);}
</style>
<?php wp_head(); ?>
</head>
<body>

<div class="ss-login-page<?= $has_image ? '' : ' ss-login-only' ?>">

  <div class="ss-login-left">
    <div class="ss-login-inner">

      <div class="ss-login-brand">
        <?php if ($logo_url): ?>
          <img src="<?= esc_url($logo_url) ?>" alt="<?= esc_attr($site_name) ?>" class="ss-login-logo">
        <?php else: ?>
          <div class="ss-login-logo-fb"><?= esc_html(mb_substr($site_name, 0, 1)) ?></div>
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
          <div class="ss-fld">
            <label for="ss-user" class="ss-lbl">Vartotojo vardas</label>
            <div class="ss-inp-wrap">
              <svg class="ss-inp-ico" viewBox="0 0 20 20" fill="currentColor"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg>
              <input type="text" id="ss-user" name="username" class="ss-inp ss-inp-ico-pad"
                     placeholder="vardas.pavarde" autocomplete="username" required>
            </div>
          </div>
          <div class="ss-fld">
            <label for="ss-pass" class="ss-lbl">Slaptažodis</label>
            <div class="ss-inp-wrap">
              <svg class="ss-inp-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
              <input type="password" id="ss-pass" name="password" class="ss-inp ss-inp-ico-pad ss-inp-eye-pad"
                     placeholder="••••••••" autocomplete="current-password" required>
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
          <button type="submit" class="ss-submit-btn" id="ss-login-submit">
            <span class="ss-btn-lbl">Prisijungti</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
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
        <p class="ss-card-sub" id="ss-reg-sub">Įveskite gimnazijos el. pašto adresą</p>
        <div class="ss-reg-steps" aria-hidden="true">
          <span class="ss-reg-step active" data-step="1">1</span>
          <span class="ss-reg-bar"></span>
          <span class="ss-reg-step" data-step="2">2</span>
          <span class="ss-reg-bar"></span>
          <span class="ss-reg-step" data-step="3">3</span>
        </div>
        <div id="ss-reg-err" class="ss-alert ss-alert-err" style="display:none"></div>
        <div id="ss-reg-ok"  class="ss-alert ss-alert-ok"  style="display:none"></div>
        <!-- STEP 1 -->
        <form id="ss-reg-form-email" class="ss-reg-step-form" data-step="1" novalidate>
          <div class="ss-fld">
            <label for="ss-reg-email" class="ss-lbl">El. paštas</label>
            <div class="ss-inp-wrap">
              <svg class="ss-inp-ico" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
              <input type="email" id="ss-reg-email" class="ss-inp ss-inp-ico-pad"
                     placeholder="vardas.pavarde@azuolynogimnazija.lt" autocomplete="email" required>
            </div>
            <p class="ss-fld-hint">Privalomas baigiasi @azuolynogimnazija.lt</p>
          </div>
          <button type="submit" class="ss-submit-btn" id="ss-reg-send-btn">
            <span class="ss-btn-lbl">Siųsti kodą</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
        </form>
        <!-- STEP 2 -->
        <form id="ss-reg-form-code" class="ss-reg-step-form" data-step="2" novalidate style="display:none">
          <p class="ss-reg-mailto">Kodas išsiųstas į <strong id="ss-reg-mailto"></strong></p>
          <div class="ss-fld">
            <label for="ss-reg-code" class="ss-lbl">6-ženklis kodas</label>
            <input type="text" id="ss-reg-code" class="ss-inp ss-inp-code"
                   inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   placeholder="000000" autocomplete="one-time-code" required>
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
        <!-- STEP 3 -->
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
          <p class="ss-fld-hint" id="ss-reg-class-preview">Pvz., IIf</p>
          <div class="ss-fld">
            <label for="ss-reg-pass" class="ss-lbl">Slaptažodis</label>
            <input type="password" id="ss-reg-pass" class="ss-inp" minlength="8" placeholder="Mažiausiai 8 simboliai" autocomplete="new-password" required>
          </div>
          <div class="ss-fld">
            <label for="ss-reg-pass2" class="ss-lbl">Pakartokite slaptažodį</label>
            <input type="password" id="ss-reg-pass2" class="ss-inp" minlength="8" placeholder="••••••••" autocomplete="new-password" required>
          </div>
          <button type="submit" class="ss-submit-btn" id="ss-reg-submit-btn">
            <span class="ss-btn-lbl">Toliau</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
        </form>
        <!-- STEP 4: success -->
        <div id="ss-reg-success" class="ss-reg-success" style="display:none">
          <div class="ss-reg-success-ico">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M8 12.5l3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <h3>Anketa išsiųsta</h3>
          <p>Jūsų anketą peržiūrės administratorius ir priskirs tvarkaraštį. Kai paskyra bus patvirtinta — gausite pranešimą el. paštu ir galėsite prisijungti.</p>
          <button type="button" class="ss-submit-btn" id="ss-reg-done-btn">Grįžti į prisijungimą</button>
        </div>
      </div>

      <!-- FORGOT PASSWORD CARD -->
      <div class="ss-form-card" id="ss-card-forgot" style="display:none">
        <button type="button" class="ss-back-btn" id="ss-back-to-login">
          <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>
          Grįžti į prisijungimą
        </button>
        <h2 class="ss-card-title">Slaptažodžio atkūrimas</h2>
        <p class="ss-card-sub">Įveskite el. paštą arba vartotojo vardą — išsiųsime nuorodą.</p>
        <form id="ss-forgot-form" novalidate>
          <div class="ss-fld">
            <label for="ss-forgot-inp" class="ss-lbl">El. paštas arba vartotojo vardas</label>
            <div class="ss-inp-wrap">
              <svg class="ss-inp-ico" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg>
              <input type="text" id="ss-forgot-inp" class="ss-inp ss-inp-ico-pad"
                     placeholder="ona.onaite arba ona@mokykla.lt" autocomplete="email">
            </div>
          </div>
          <div id="ss-forgot-err" class="ss-alert ss-alert-err" style="display:none"></div>
          <div id="ss-forgot-ok"  class="ss-alert ss-alert-ok"  style="display:none"></div>
          <button type="submit" class="ss-submit-btn" id="ss-forgot-submit">
            <span class="ss-btn-lbl">Siųsti nuorodą</span>
            <span class="ss-btn-spin" style="display:none"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity=".2"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/></svg></span>
          </button>
        </form>
      </div>

    </div>
  </div>

  <?php if ($has_image): ?>
  <div class="ss-login-right ss-login-right-image" aria-hidden="true">
    <img src="<?= esc_url($login_image_url) ?>" alt="" class="ss-login-bg-image">
  </div>
  <?php endif; ?>

</div>

<?php wp_footer(); ?>
</body>
</html>
