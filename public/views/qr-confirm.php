<?php
/**
 * QR kodo prisijungimo patvirtinimo puslapis
 * Rodomas prisijungusiam vartotojui telefone, kai nuskenavo QR kodą.
 */
if (!defined('ABSPATH')) exit;

$token   = sanitize_text_field($_GET['ss_qr'] ?? '');
$user    = wp_get_current_user();
$data    = $token ? get_transient('ss_qr_' . $token) : false;
$valid   = ($data !== false && $data['status'] === 'pending');

// Saugos patikrinimas - žetono ilgis
if ($valid && strlen($token) !== 48) $valid = false;

$browser = $os = $ip_display = $time_str = '';
$nonce   = '';

if ($valid) {
    // Parsinti naršyklę / OS / IP
    $ua          = $data['user_agent'];
    $browser     = SS_Shortcodes::qr_browser($ua);
    $os          = SS_Shortcodes::qr_os($ua);
    $ip_raw     = trim(explode(',', $data['ip'])[0]);
    $ip_display = filter_var($ip_raw, FILTER_VALIDATE_IP) ? $ip_raw : mb_substr($ip_raw, 0, 20) . '…';
    // Geo lookup: cached per-token in a separate transient so it's done only once
    $geo_city = $geo_country = '';
    $geo_key  = 'ss_qrgeo_' . $token;
    $geo_cache = get_transient($geo_key);
    if ($geo_cache !== false) {
        $geo_city    = $geo_cache['city']    ?? '';
        $geo_country = $geo_cache['country'] ?? '';
    } elseif (filter_var($ip_raw, FILTER_VALIDATE_IP)
              && !in_array($ip_raw, ['127.0.0.1', '127.0.1.1', '::1'], true)) {
        $gr = wp_remote_get("https://ipapi.co/{$ip_raw}/json/", ['timeout' => 3]);
        if (!is_wp_error($gr)) {
            $gb = json_decode(wp_remote_retrieve_body($gr), true);
            $geo_city    = sanitize_text_field($gb['city']         ?? '');
            $geo_country = sanitize_text_field($gb['country_name'] ?? '');
        }
        set_transient($geo_key, ['city' => $geo_city, 'country' => $geo_country], 70);
    }
    $geo_str    = trim(implode(', ', array_filter([$geo_city, $geo_country])));
    // wp_date() korektiškai konvertuoja UTC laiko žymą į vietos laiko juostą
    // (date_i18n su laiko žyma rodydavo UTC).
    $time_str   = wp_date('H:i', (int) $data['created']);
    $nonce      = wp_create_nonce('ss_qr_' . $token);
}

// Vartotojo info
$logo_id  = (int) get_option('ss_logo_id', 0);
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : get_option('ss_logo_url', '');
$site_name = get_option('ss_school_name', SS_SCHOOL_NAME);

// WordPress avataras: pirmiausia plugin'o įkeltas, tada Gravatar/WP numatytasis
$avatar_url = '';
$_att_id = (int) get_user_meta($user->ID, 'ss_avatar_id', true);
if ($_att_id) {
    $_att_url = wp_get_attachment_image_url($_att_id, [96, 96]);
    if ($_att_url) {
        $_att_ts  = (int) get_user_meta($user->ID, 'ss_avatar_ts', true);
        $avatar_url = $_att_ts ? $_att_url . '?v=' . $_att_ts : $_att_url;
    }
}
if (!$avatar_url) {
    $avatar_url = get_avatar_url($user->ID, ['size' => 96, 'default' => 'mystery']);
}
$display_name = $user->display_name ?: ($user->first_name . ' ' . $user->last_name);
$dash_url = get_permalink(get_option('ss_dashboard_page_id')) ?: home_url('/');
$ajax_url = admin_url('admin-ajax.php');
?>

<div class="ss-qrc-wrap">

  <?php if (!$valid): ?>
  <!-- ═══ NEGALIOJANTIS ═══ -->
  <div class="ss-qrc-card ss-qrc-card--invalid">
    <div class="ss-qrc-invalid-ico">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16.5" r=".5" fill="currentColor"/></svg>
    </div>
    <h2 class="ss-qrc-title">QR kodas nebegalioja</h2>
    <p class="ss-qrc-sub">Šis QR kodas jau nebegalioja, buvo panaudotas arba yra neteisingas. Grįžkite į prisijungimo puslapį ir nuskenuokite naują QR kodą.</p>
  </div>

  <?php else: ?>
  <!-- ═══ PATVIRTINIMO FORMA ═══ -->
  <div class="ss-qrc-card" id="ss-qrc-card-main">

    <!-- Viršutinė dalis: logotipas ir pavadinimas -->
    <div class="ss-qrc-header">
      <?php if ($logo_url): ?>
        <img src="<?= esc_url($logo_url) ?>" alt="<?= esc_attr($site_name) ?>" class="ss-qrc-logo-img">
      <?php else: ?>
        <div class="ss-qrc-logo-fb"><?= esc_html(mb_substr($site_name, 0, 1)) ?></div>
      <?php endif; ?>
      <div>
        <div class="ss-qrc-school"><?= esc_html($site_name) ?></div>
        <div class="ss-qrc-header-title">Prisijungimo patvirtinimas</div>
      </div>
    </div>

    <!-- Prisijungęs vartotojas -->
    <div class="ss-qrc-user-row">
      <img src="<?= esc_url($avatar_url) ?>" alt="" class="ss-qrc-avatar" onerror="this.style.display='none'">
      <div class="ss-qrc-user-info">
        <div class="ss-qrc-user-name"><?= esc_html($display_name) ?></div>
        <div class="ss-qrc-user-email"><?= esc_html($user->user_email) ?></div>
      </div>
    </div>

    <!-- Įrenginio informacija -->
    <div class="ss-qrc-device-box">
      <div class="ss-qrc-device-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      </div>
      <div class="ss-qrc-device-info">
        <div class="ss-qrc-device-title">Naujas įrenginys bando prisijungti</div>
        <div class="ss-qrc-device-detail">
          <span class="ss-qrc-device-chip"><?= esc_html($browser) ?></span>
          <span class="ss-qrc-device-chip"><?= esc_html($os) ?></span>
        </div>
        <div class="ss-qrc-device-meta">
          <span class="ss-qrc-meta-item">
            <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
            <span><?= esc_html($ip_display) ?></span>
          </span>
          <?php if ($geo_str): ?>
          <span class="ss-qrc-meta-item">
            <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z" clip-rule="evenodd"/></svg>
            <span><?= esc_html($geo_str) ?></span>
          </span>
          <?php endif; ?>
          <span class="ss-qrc-meta-item">
            <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
            <span><?= esc_html($time_str) ?></span>
          </span>
        </div>
      </div>
    </div>

    <!-- Slinkimo mygtuko patvirtinimo zona -->
    <div class="ss-qrc-action-area" id="ss-qrc-action-area">
      <p class="ss-qrc-action-hint">Patempkite rodyklę į dešinę, norėdami patvirtinti prisijungimą</p>

      <div class="ss-qrc-slide-track" id="ss-qrc-track">
        <div class="ss-qrc-slide-fill"  id="ss-qrc-fill"></div>
        <div class="ss-qrc-slide-label" id="ss-qrc-label">Tempkite dešinėn →</div>
        <div class="ss-qrc-thumb"       id="ss-qrc-thumb">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </div>
      </div>

      <button class="ss-qrc-reject-btn" id="ss-qrc-reject-btn" type="button">
        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        Atmesti prisijungimą
      </button>
    </div>

    <!-- Sėkmės pranešimas (paslėptas) -->
    <div class="ss-qrc-result ss-qrc-result--ok" id="ss-qrc-result-ok" style="display:none">
      <div class="ss-qrc-result-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
      </div>
      <h3>Prisijungimas patvirtintas!</h3>
      <p>Įrenginys sėkmingai prisijungė prie paskyros.</p>
    </div>

    <!-- Atmetimo pranešimas (paslėptas) -->
    <div class="ss-qrc-result ss-qrc-result--err" id="ss-qrc-result-err" style="display:none">
      <div class="ss-qrc-result-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      </div>
      <h3>Prisijungimas atmestas</h3>
    </div>

  </div>
  <?php endif; ?>

</div>

<?php if ($valid): ?>
<script>
(function($){
'use strict';
var TOKEN    = <?= json_encode($token) ?>;
var NONCE    = <?= json_encode($nonce) ?>;
var AJAX_URL = <?= json_encode($ajax_url) ?>;

/* ── Slinkimo logika (Pointer Events API – patikima telefone) ── */
var $track  = $('#ss-qrc-track');
var $thumb  = $('#ss-qrc-thumb');
var $fill   = $('#ss-qrc-fill');
var $label  = $('#ss-qrc-label');
var thumb   = $thumb[0];
var dragging = false, startX = 0, curX = 0, maxX = 0, confirmed = false;

function calcMax() {
  return Math.max(0, $track.width() - $thumb.outerWidth() - 8);
}
function snapBack() {
  $thumb.css('transition','transform .3s cubic-bezier(.4,0,.2,1)');
  $fill.css('transition','width .3s ease');
  $thumb.css('transform','translateX(0)');
  $fill.css('width','0');
  $label.css('opacity','1');
  curX = 0;
}

thumb.addEventListener('pointerdown', function(e) {
  if (confirmed) return;
  e.preventDefault();
  dragging = true;
  maxX  = calcMax();
  startX = e.clientX;
  $thumb.css('transition','none');
  $fill.css('transition','none');
  thumb.setPointerCapture(e.pointerId);
});

thumb.addEventListener('pointermove', function(e) {
  if (!dragging) return;
  curX = Math.max(0, Math.min(e.clientX - startX, maxX));
  var pct = maxX > 0 ? curX / maxX : 0;
  $thumb.css('transform','translateX('+curX+'px)');
  $fill.css('width', (curX + $thumb.outerWidth()/2) + 'px');
  $label.css('opacity', Math.max(0, 1 - pct * 2.2));
  if (pct >= 0.92) { dragging = false; doConfirm(); }
});

thumb.addEventListener('pointerup',     function() { if (dragging) { dragging = false; if (curX < maxX * 0.92) snapBack(); } });
thumb.addEventListener('pointercancel', function() { if (dragging) { dragging = false; snapBack(); } });

function doConfirm() {
  if (confirmed) return;
  confirmed = true;
  // Animacija iki galo
  $thumb.css('transition','transform .25s ease');
  $fill.css({'transition':'width .25s ease','width':'100%'});
  $thumb.css('transform','translateX(' + maxX + 'px)');
  $track.addClass('ss-qrc-track--done');
  $label.text('Patvirtinama...').css('opacity','1');

  $.post(AJAX_URL, { action:'ss_qr_confirm', token:TOKEN, nonce:NONCE }, function(res) {
    if (res.success) {
      setTimeout(function() {
        $('#ss-qrc-action-area').fadeOut(300, function() {
          $('#ss-qrc-result-ok').fadeIn(400);
        });
      }, 400);
    } else {
      confirmed = false;
      $track.removeClass('ss-qrc-track--done');
      $thumb.css({'transition':'transform .3s ease','transform':'translateX(0)'});
      $fill.css({'transition':'width .3s ease','width':'0'});
      $label.text('Tempkite dešinėn →').css('opacity','1');
      alert(res.data.message || 'Klaida. Bandykite dar kartą.');
    }
  }).fail(function() {
    confirmed = false;
    alert('Ryšio klaida. Patikrinkite internetą.');
  });
}

/* ── Atmetimas ── */
$('#ss-qrc-reject-btn').on('click', function() {
  if (!confirm('Ar tikrai norite atmesti šį prisijungimą?')) return;
  $.post(AJAX_URL, { action:'ss_qr_reject', token:TOKEN, nonce:NONCE }, function(res) {
    $('#ss-qrc-action-area').fadeOut(300, function() {
      $('#ss-qrc-result-err').fadeIn(400);
    });
  });
});

})(jQuery);
</script>
<?php endif; ?>

<style>
/* ═══════════════════════════════════════
   QR PATVIRTINIMO PUSLAPIS
   ═══════════════════════════════════════ */
.ss-qrc-wrap {
  min-height: 70vh;
  display: flex; align-items: center; justify-content: center;
  padding: 24px 16px;
}

/* Kortelė */
.ss-qrc-card {
  background: var(--wh);
  border: 1px solid var(--bd);
  border-top: 3px solid var(--g);
  box-shadow: 0 8px 36px rgba(1,42,54,.12), 0 1px 4px rgba(1,42,54,.06);
  width: 100%; max-width: 420px;
  overflow: hidden;
}

/* Viršus (header) */
.ss-qrc-header {
  display: flex; align-items: center; gap: 12px;
  padding: 18px 22px;
  background: linear-gradient(135deg, var(--fr), var(--nb));
  color: var(--wh);
}
.ss-qrc-logo-img { height: 34px; width: auto; }
.ss-qrc-logo-fb  { width:36px;height:36px;background:rgba(255,255,255,.18);color:var(--wh);font-size:18px;font-weight:800;display:flex;align-items:center;justify-content:center; }
.ss-qrc-school       { font-size: 10.5px; font-weight: 600; opacity: .8; text-transform: uppercase; letter-spacing: .5px; }
.ss-qrc-header-title { font-size: 15px; font-weight: 800; }

/* Vartotojas */
.ss-qrc-user-row {
  display: flex; align-items: center; gap: 14px;
  padding: 18px 22px;
  border-bottom: 1px solid var(--bd);
}
.ss-qrc-avatar { width: 50px; height: 50px; object-fit: cover; flex-shrink: 0; border: 1px solid var(--bd); }
.ss-qrc-user-info { flex: 1; min-width: 0; }
.ss-qrc-user-name  { font-size: 15px; font-weight: 700; color: var(--dk); }
.ss-qrc-user-email { font-size: 12px; color: var(--gl); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; }

/* Įrenginys */
.ss-qrc-device-box {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 16px 22px;
  background: var(--bg);
  border-bottom: 1px solid var(--bd);
}
.ss-qrc-device-icon {
  width: 40px; height: 40px;
  background: var(--wh);
  border: 1px solid var(--bd);
  display: flex; align-items: center; justify-content: center;
  color: var(--nb);
  flex-shrink: 0;
}
.ss-qrc-device-info { min-width: 0; }
.ss-qrc-device-title  { font-size: 13px; font-weight: 700; color: var(--dk); margin-bottom: 7px; }
.ss-qrc-device-detail { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
.ss-qrc-device-chip {
  background: var(--wh); color: var(--nb);
  border: 1px solid var(--bd);
  padding: 2px 9px; font-size: 11.5px; font-weight: 600;
}
.ss-qrc-device-meta {
  display: flex; align-items: center; flex-wrap: wrap;
  gap: 5px 14px; font-size: 11px; color: var(--gl);
}
.ss-qrc-meta-item { display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; min-width: 0; }
.ss-qrc-meta-item svg { flex-shrink: 0; opacity: .7; }
.ss-qrc-meta-item span { overflow: hidden; text-overflow: ellipsis; }

/* Slinkimo zona */
.ss-qrc-action-area { padding: 20px 22px 22px; }
.ss-qrc-action-hint { font-size: 12px; color: var(--gl); margin: 0 0 14px; text-align: center; }

/* Slinkimo takelis */
.ss-qrc-slide-track {
  position: relative; height: 54px;
  background: #f0fbf1;
  border: 1.5px solid var(--gp);
  overflow: hidden; cursor: pointer; user-select: none;
  margin-bottom: 12px;
  transition: border-color .2s;
}
.ss-qrc-slide-track:hover { border-color: var(--g); }
.ss-qrc-slide-fill {
  position: absolute; left: 0; top: 0; bottom: 0; width: 0;
  background: linear-gradient(90deg, rgba(79,137,82,.32), rgba(79,137,82,.16));
  pointer-events: none; transition: width .05s linear;
}
.ss-qrc-slide-label {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700; color: var(--g);
  pointer-events: none;
}
.ss-qrc-thumb {
  /* Vertikaliai centruojama per top:50% + margin-top (atsparu track rėmeliui ir
     box-sizing; JS keičia tik transform:translateX, todėl centras nenukrypsta). */
  position: absolute; left: 4px; top: 50%; margin-top: -23px;
  width: 46px; height: 46px;
  background: linear-gradient(135deg, var(--g), var(--fr));
  color: var(--wh);
  display: flex; align-items: center; justify-content: center;
  cursor: grab;
  box-shadow: 0 2px 10px rgba(79,137,82,.4);
  z-index: 2; touch-action: none;
  transition: box-shadow .15s;
}
.ss-qrc-thumb svg { width: 22px; height: 22px; }
.ss-qrc-thumb:active { cursor: grabbing; box-shadow: 0 4px 18px rgba(79,137,82,.5); }
.ss-qrc-track--done .ss-qrc-thumb { cursor: default; background: linear-gradient(135deg,#22c55e,#16a34a); }

/* Atmesti mygtukas */
.ss-qrc-reject-btn {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  width: 100%; height: 42px;
  background: none;
  border: 1.5px solid #f0e0e0;
  color: var(--r);
  font-size: 13px; font-weight: 600; cursor: pointer;
  transition: background .15s, border-color .15s;
}
.ss-qrc-reject-btn:hover { background: #fff0f0; border-color: var(--r); }

/* Rezultatai */
.ss-qrc-result { text-align: center; padding: 34px 24px; }
.ss-qrc-result-ico {
  width: 64px; height: 64px;
  margin: 0 auto 16px;
  display: flex; align-items: center; justify-content: center;
}
.ss-qrc-result-ico svg { width: 32px; height: 32px; }
.ss-qrc-result--ok  .ss-qrc-result-ico { background: #edf7ed; color: #16a34a; }
.ss-qrc-result--err .ss-qrc-result-ico { background: #fff0f0; color: var(--r); }
.ss-qrc-result h3 { font-size: 18px; font-weight: 800; color: var(--dk); margin: 0 0 8px; }
.ss-qrc-result p  { font-size: 13px; color: var(--gl); margin: 0; line-height: 1.6; }

/* Negaliojantis */
.ss-qrc-card--invalid { text-align: center; padding: 40px 28px; border-top-color: var(--y); }
.ss-qrc-invalid-ico { width:64px;height:64px;background:#fff8e6;color:#b45309;margin:0 auto 16px;display:flex;align-items:center;justify-content:center; }
.ss-qrc-invalid-ico svg { width: 32px; height: 32px; }
.ss-qrc-title { font-size: 20px; font-weight: 800; color: var(--dk); margin: 0 0 10px; }
.ss-qrc-sub   { font-size: 13px; color: var(--gl); margin: 0; line-height: 1.6; }

@media (max-width: 480px) {
  .ss-qrc-wrap { padding: 12px; align-items: flex-start; padding-top: 18px; }
}
</style>
