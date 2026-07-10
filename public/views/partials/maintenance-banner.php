<?php
/**
 * Maintenance banner partial.
 * Include this right after </header> in every public view EXCEPT login.
 *
 * Requires SS_Maintenance class to be loaded.
 */
if (!defined('ABSPATH')) exit;

$_maint = SS_Maintenance::get_status();
$_maint_bypass = SS_Maintenance::user_can_bypass(get_current_user_id());

// ── Warning banner (shown even when system is NOT fully closed) ──────────────
if ($_maint['warning'] && !$_maint_bypass):
?>
<div class="ss-maint-warning" id="ss-maint-warning">
  <span class="ss-maint-warning-icon">
    <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
  </span>
  <span class="ss-maint-warning-text">
    <?php if ($_maint['warning_text']): ?>
      <?= esc_html($_maint['warning_text']) ?>
    <?php else: ?>
      Perspėjimas: sistema bus laikinai uždaryta techninių darbų metu.
    <?php endif; ?>
  </span>
</div>
<?php endif; ?>

<?php
// ── Maintenance closed screen (blocks all content) ───────────────────────────
// NOTE: When active, we output ONLY the maintenance page and exit immediately.
// This means no page content is ever sent to the browser — deleting the overlay
// via DevTools reveals nothing because nothing is rendered underneath.
if ($_maint['active'] && !$_maint_bypass):
  $current_user = wp_get_current_user();
  $_maint_ip    = SS_Maintenance::get_ip();
  $is_logged    = is_user_logged_in();
  $to_ts        = (int)$_maint['to_ts'];
  $login_pid    = (int)get_option('ss_login_page_id');
  $login_url    = $login_pid ? get_permalink($login_pid) : '';
  // Kai užblokuota VISA svetainė — sakome, kad uždaryta svetainė (ne „sistema").
  $_maint_lock  = !empty($_maint['site_locked']);
  $_maint_title = $_maint_lock ? 'Svetainė laikinai uždaryta' : 'Sistema laikinai uždaryta';
  $_maint_sub   = $_maint_lock
      ? 'Svetainė laikinai nepasiekiama. Ji bus prieinama netrukus.'
      : 'Techniniai darbai. Sistema bus prieinama netrukus.';
?>
<div class="ss-maint-closed-overlay" id="ss-maint-overlay">
  <div class="ss-maint-closed-box">

    <div class="ss-maint-closed-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
      </svg>
    </div>

    <h2 class="ss-maint-closed-title"><?= esc_html($_maint_title) ?></h2>
    <p class="ss-maint-closed-subtitle"><?= esc_html($_maint_sub) ?></p>

    <?php if ($_maint['reason']): ?>
      <div class="ss-maint-closed-reason">
        <strong>Priežastis:</strong> <?= esc_html($_maint['reason']) ?>
      </div>
    <?php endif; ?>

    <div class="ss-maint-closed-meta">
      <div class="ss-maint-meta-row">
        <span class="ss-maint-meta-label">IP adresas</span>
        <span class="ss-maint-meta-val"><?= esc_html($_maint_ip) ?></span>
      </div>
      <div class="ss-maint-meta-row">
        <span class="ss-maint-meta-label">Paskyra</span>
        <span class="ss-maint-meta-val">
          <?php if ($is_logged && $current_user->ID): ?>
            <?= esc_html($current_user->display_name) ?> (<?= esc_html($current_user->user_email) ?>)
          <?php else: ?>
            Neprisijungta
          <?php endif; ?>
        </span>
      </div>
      <?php if ($to_ts > 0): ?>
      <div class="ss-maint-meta-row">
        <span class="ss-maint-meta-label" id="ss-maint-timer-label">Iki atidarymo liko</span>
        <span class="ss-maint-meta-val" id="ss-maint-timer" data-to="<?= esc_attr($to_ts) ?>">
          Skaičiuojama...
        </span>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($is_logged): ?>
    <a href="<?= esc_url(wp_logout_url($login_url ?: home_url('/'))) ?>" class="ss-maint-logout-link">
      <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>
      Atsijungti
    </a>
    <?php elseif ($login_url): ?>
    <a href="<?= esc_url($login_url) ?>" class="ss-maint-login-link">Prisijungti</a>
    <?php endif; ?>

  </div>
</div>

<script>
(function(){
  // ── Countdown timer ──────────────────────────────────────────────────────
  <?php if ($to_ts > 0): ?>
  var timerEl  = document.getElementById('ss-maint-timer');
  var labelEl  = document.getElementById('ss-maint-timer-label');
  if (timerEl) {
    var toMs = <?= $to_ts ?> * 1000;
    (function tick(){
      var diff = toMs - Date.now();
      if (diff <= 0) {
        // Laikas pasibaigė — TIK dabar rodome „turi būti atidaryta" ir po kelių
        // sekundžių patys perkrauname puslapį (vieną kartą), kad atsidarytų.
        if (labelEl) labelEl.textContent = 'Turi būti jau atidaryta. Perkraukite puslapį.';
        timerEl.textContent = 'Atidaroma...';
        setTimeout(function(){ location.reload(); }, 4000);
        return;
      }
      var h = Math.floor(diff/3600000);
      var m = Math.floor((diff%3600000)/60000);
      var s = Math.floor((diff%60000)/1000);
      timerEl.textContent = (h > 0 ? h+'h ' : '') + (m > 0 || h > 0 ? m+'min ' : '') + s+'s';
      setTimeout(tick, 1000);
    })();
  }
  <?php endif; ?>

  // ── Overlay guardian — prevents removal via DevTools ─────────────────────
  // Strategy: since PHP exits after this script (no page content is sent),
  // if someone removes the overlay there is nothing beneath it.
  // This JS is an extra layer: if the overlay is removed or hidden,
  // we wipe the entire page and show only the maintenance message again.

  var MAINT_HTML = document.body.innerHTML;

  function restoreMaintPage() {
    // PASTABA: perrašytame dokumente NEBĖRA style.css, todėl jokių var(--x) —
    // tik konkrečios spalvos, kad langas atrodytų kaip tikrasis uždarymo ekranas.
    document.open();
    document.write('<!DOCTYPE html><html><head>'
      + '<meta charset="UTF-8">'
      + '<meta name="viewport" content="width=device-width,initial-scale=1">'
      + '<title><?= esc_js($_maint_title) ?></title>'
      + '<link rel="preconnect" href="https://fonts.googleapis.com">'
      + '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
      + '<link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&display=swap" rel="stylesheet">'
      + '<style>'
      + '*{box-sizing:border-box;margin:0;padding:0;font-family:\'Google Sans\',-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}'
      + 'body{min-height:100vh;background:#0e2b37;display:flex;align-items:center;justify-content:center;padding:20px}'
      + '.box{background:#fff;padding:40px 36px 32px;max-width:440px;width:100%;text-align:center;box-shadow:0 24px 64px rgba(1,42,54,.4)}'
      + '.ico{width:76px;height:76px;background:rgba(208,81,85,.1);border:2px solid rgba(208,81,85,.25);margin:0 auto 20px;display:flex;align-items:center;justify-content:center;color:#D05155}'
      + 'h2{font-family:\'Google Sans\',-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;font-size:22px;font-weight:800;color:#012A36;margin:0 0 8px}'
      + 'p{font-size:14px;color:#4a5568}'
      + '</style>'
      + '</head><body>'
      + '<div class="box">'
      + '<div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="48" height="48"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg></div>'
      + '<h2>Parašyta gi, kad uždaryta!</h2>'
      + '<p><?= esc_js($_maint_sub) ?></p>'
      + '</div>'
      + '<script>(function(){function g(){var e=document.querySelector(".box");if(!e||getComputedStyle(e).display==="none")location.reload();}setInterval(g,500);new MutationObserver(g).observe(document.documentElement,{childList:true,subtree:true,attributes:true});})()</\x73cript>'
      + '</body></html>');
    document.close();
  }

  function checkOverlay() {
    var el = document.getElementById('ss-maint-overlay');
    if (!el) { restoreMaintPage(); return false; }
    var st = window.getComputedStyle(el);
    if (st.display === 'none' || st.visibility === 'hidden' || parseFloat(st.opacity) < 0.05) {
      restoreMaintPage(); return false;
    }
    return true;
  }

  // MutationObserver watches for ANY DOM change
  var guardian = new MutationObserver(function() { checkOverlay(); });
  guardian.observe(document.documentElement, {
    childList: true, subtree: true,
    attributes: true,
    attributeFilter: ['style', 'class', 'hidden']
  });

  // Interval fallback (catches things MutationObserver might miss)
  setInterval(checkOverlay, 400);

})();
</script>
<?php
  // ── CRITICAL: Exit here — no page content is rendered below this point ────
  // Even if the overlay is deleted via DevTools, the browser received nothing
  // to reveal. The MutationObserver above is an additional client-side guard.
  echo '</body></html>';
  exit;
endif; // active && !bypass
