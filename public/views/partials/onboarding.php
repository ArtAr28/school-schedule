<?php
/**
 * First-run onboarding (shown once after registration; flag: ss_needs_onboarding).
 * A small multi-step wizard: welcome → avatar → login methods → quizzes → security.
 */
if (!defined('ABSPATH')) exit;

$ob_user   = wp_get_current_user();
$ob_name   = trim(explode(' ', trim($ob_user->display_name))[0]) ?: $ob_user->display_name;
$ob_anon   = (bool) get_user_meta($ob_user->ID, 'ss_anon_quiz', true);                        // true = name hidden
$ob_nd     = (get_user_meta($ob_user->ID, 'ss_user_new_device_notify', true) !== '0');        // default: on
$ob_cn     = (get_user_meta($ob_user->ID, 'ss_changes_notify', true) !== '0');                // default: on (pakeitimų pranešimai)
$ob_av_id  = (int) get_user_meta($ob_user->ID, 'ss_avatar_id', true);
$ob_av_url = $ob_av_id ? wp_get_attachment_image_url($ob_av_id, 'thumbnail') : get_avatar_url($ob_user->ID, array('size' => 96));
$ob_set    = ($id = get_option('ss_settings_page_id')) ? get_permalink($id) : '';
$ob_google = class_exists('SS_Google_Auth') && SS_Google_Auth::is_enabled();
$ob_qr     = (bool) get_option('ss_qr_login_enabled', 0);

/* Inline SVG icon set (no emoji). */
$svg = array(
  'welcome' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2l3.357.935a1 1 0 010 1.93l-3.357.935-1.18 4.456a1 1 0 01-1.933 0l-1.18-4.455-3.357-.936a1 1 0 010-1.93l3.357-.935 1.18-4.455A1 1 0 0112 2z"/></svg>',
  'avatar'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>',
  'login'   => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
  'quiz'    => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>',
  'bell'    => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>',
  'google'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 011.523-1.943A5.977 5.977 0 0116 10c0 .34-.028.675-.083 1H15a2 2 0 00-2 2v2.197A5.973 5.973 0 0110 16v-2a2 2 0 00-2-2 2 2 0 01-2-2 2 2 0 00-1.668-1.973z" clip-rule="evenodd"/></svg>',
  'phone'   => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 2a2 2 0 00-2 2v12a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H7zm3 14a1 1 0 100-2 1 1 0 000 2z"/></svg>',
);
?>
<div class="ss-onb-overlay" id="ss-onboarding" data-step="1">
  <div class="ss-onb-card">
    <div class="ss-onb-progress"><span></span></div>

    <!-- 1 · Welcome -->
    <div class="ss-onb-step" data-ob="1">
      <span class="ss-onb-count">1  / 6</span>
      <div class="ss-onb-ico"><?= $svg['welcome'] ?></div>
      <h2 class="ss-onb-h">Sveiki, <?= esc_html($ob_name) ?>!</h2>
      <ul class="ss-onb-list">
        <li><b>Tvarkaraštis</b> — Jūsų savaitės pamokos, dienos ir savaitės rodiniai.</li>
        <li><b>Pakeitimai</b> — pavadavimai ir dienos komentarai. Jie bus rodomi suasmeninti Jūsų tvarkaraštyje.</li>
        <li><b>Asmeniniai įrašai</b> — pridėkite savo veiklas prie tvarkaraščio(pvz. konsultacijas ar modulius).</li>
      </ul>
    </div>

    <!-- 2 · Avatar -->
    <div class="ss-onb-step" data-ob="2" style="display:none">
      <span class="ss-onb-count">2  / 6</span>
      <div class="ss-onb-ico"><?= $svg['avatar'] ?></div>
      <h2 class="ss-onb-h">Profilio nuotrauka</h2>
      <p class="ss-onb-sub">Pridėkite nuotrauką. Galite praleisti.</p>
      <div class="ss-onb-avatar">
        <img id="ss-onb-av-img" src="<?= esc_url($ob_av_url) ?>" alt="">
        <label class="ss-onb-av-btn">
          <input type="file" id="ss-onb-avatar-input" accept="image/*" hidden>
          Įkelti nuotrauką
        </label>
      </div>
    </div>

    <!-- 3 · Login methods -->
    <div class="ss-onb-step" data-ob="3" style="display:none">
      <span class="ss-onb-count">3  / 6</span>
      <div class="ss-onb-ico"><?= $svg['login'] ?></div>
      <h2 class="ss-onb-h">Papildomi prisijungimo būdai</h2>
      <p class="ss-onb-sub">Saugesniam ir greitesniam prisijungimui. Galėsite įjungti bet kada nustatymuose.</p>
      <div class="ss-onb-methods">
        <div class="ss-onb-method"><span><?= $svg['login'] ?></span><div><b>Dviejų žingsnių patvirtinimas (2FA)</b><small>Apsauga su Authenticator programėle.</small></div></div>
        <?php if ($ob_google): ?><div class="ss-onb-method"><span><?= $svg['google'] ?></span><div><b>Google paskyra</b><small>Prisijunkite vienu paspaudimu.</small></div></div><?php endif; ?>
        <?php if ($ob_qr): ?><div class="ss-onb-method"><span><?= $svg['phone'] ?></span><div><b>QR kodas</b><small>Prisijunkite nuskenavę kodą telefonu.</small></div></div><?php endif; ?>
      </div>
      <?php if ($ob_set): ?><a class="ss-onb-link" href="<?= esc_url($ob_set) ?>">Atidaryti nustatymus →</a><?php endif; ?>
    </div>

    <!-- 4 · Quizzes -->
    <div class="ss-onb-step" data-ob="4" style="display:none">
      <span class="ss-onb-count">4  / 6</span>
      <div class="ss-onb-ico"><?= $svg['quiz'] ?></div>
      <h2 class="ss-onb-h">Apklausos ir viktorinos</h2>
      <p class="ss-onb-sub">Kartais sistemoje pasirodo mokyklos apklausos ir viktorinos. Rezultatų lentelėse gali būti rodomas Jūsų vardas.</p>
      <label class="ss-onb-toggle">
        <input type="checkbox" id="ss-onb-showname" <?= $ob_anon ? '' : 'checked' ?>>
        <span>Rodyti mano vardą viktorinų rezultatuose</span>
      </label>
      <p class="ss-onb-hint">Išjungus, kitiems bus rodomas atsitiktinis vardas „Anonimas-…". Tikrąjį vardą matys tik administratorius.</p>
    </div>

    <!-- 5 · Security -->
    <div class="ss-onb-step" data-ob="5" style="display:none">
      <span class="ss-onb-count">5  / 6</span>
      <div class="ss-onb-ico"><?= $svg['bell'] ?></div>
      <h2 class="ss-onb-h">Saugumo pranešimai</h2>
      <p class="ss-onb-sub">Galime el. paštu pranešti, kai prie Jūsų paskyros prisijungiama iš naujo įrenginio. Pranešimai apie įtartinas veiklas įjungti visada.</p>
      <label class="ss-onb-toggle">
        <input type="checkbox" id="ss-onb-newdevice" <?= $ob_nd ? 'checked' : '' ?>>
        <span>Pranešti apie prisijungimą iš naujo įrenginio</span>
      </label>
      <p class="ss-onb-hint">Rekomenduojama palikti įjungtą. Bet kada pakeisite nustatymuose.</p>
    </div>

    <!-- 6 · Pakeitimai -->
    <div class="ss-onb-step" data-ob="6" style="display:none">
      <span class="ss-onb-count">6 / 6</span>
      <div class="ss-onb-ico"><svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg></div>
      <h2 class="ss-onb-h">Pakeitimų pranešimai</h2>
      <p class="ss-onb-sub">Galime el. paštu atsiųsti kitos dienos pamokų pakeitimus — tik Jums aktualius (pamoka ir pakeitimas) bei bendrą komentarą.</p>
      <label class="ss-onb-toggle">
        <input type="checkbox" id="ss-onb-changes" <?= $ob_cn ? 'checked' : '' ?>>
        <span>Siųsti kitos dienos pakeitimus el. paštu</span>
      </label>
      <p class="ss-onb-hint">Įjungus, laiškas bus siunčiamas iškart po pakeitimų paskelbimo. Laiką galėsite keisti nustatymuose.</p>
    </div>

    <div class="ss-onb-foot">
      <button class="ss-onb-skip" type="button">Praleisti</button>
      <div class="ss-onb-nav">
        <button class="ss-onb-back" type="button" style="display:none">Atgal</button>
        <button class="ss-onb-next" type="button">Toliau</button>
      </div>
    </div>
  </div>
</div>

<!-- ════ ONBOARDING AVATAR CROP MODAL ════ -->
<div id="ss-onb-crop-modal" style="display:none;position:fixed;inset:0;z-index:100010;background:rgba(0,0,0,.72);align-items:center;justify-content:center;padding:20px;">
  <div style="background:var(--wh);max-width:480px;width:100%;padding:24px;box-shadow:0 24px 60px rgba(0,0,0,.35);">
    <h3 style="margin:0 0 16px;font-size:17px;font-weight:700;color:var(--dk);">Apkarpyti nuotrauką</h3>
    <div style="width:100%;max-height:340px;overflow:hidden;background:#f0f4f5;">
      <img id="ss-onb-crop-img" style="display:block;max-width:100%;" src="" alt="crop">
    </div>
    <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end;">
      <button type="button" id="ss-onb-crop-cancel" class="ss-btn ss-btn-sm" style="background:#eee;color:#333;">Atšaukti</button>
      <button type="button" id="ss-onb-crop-confirm" class="ss-btn ss-btn-pri ss-btn-sm">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:-2px"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        Apkarpyti ir įkelti
      </button>
    </div>
  </div>
</div>

<!-- Cropper.js (avatar crop in onboarding — same as settings) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
