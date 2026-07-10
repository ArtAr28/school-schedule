<?php
if (!defined('ABSPATH')) exit;

$user         = wp_get_current_user();
$logo_id      = (int) get_option('ss_logo_id', 0);
$logo_url     = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : get_option('ss_logo_url', '');
if (!$logo_url) {
    $_theme_lid = get_theme_mod('custom_logo');
    $logo_url   = $_theme_lid ? wp_get_attachment_image_url($_theme_lid, 'medium') : '';
}
$site_name    = get_option('ss_school_name', SS_SCHOOL_NAME);
$is_admin     = current_user_can('manage_options');
$roles        = (array)$user->roles;
$role_lbl     = $is_admin ? 'Administratorius'
              : (in_array('ss_teacher', $roles)   ? 'Mokytojas'
              : (in_array('ss_employee', $roles)  ? 'Darbuotojas' : 'Mokinys'));
// „Mano tvarkaraštis" (kodo įvedimas) — tik mokiniams (ne mokytojams/darbuotojams/admin).
$show_my_schedule = !$is_admin && !in_array('ss_teacher', $roles, true) && !in_array('ss_employee', $roles, true);

$dash_id      = (int)get_option('ss_dashboard_page_id');
$dash_url     = $dash_id ? get_permalink($dash_id) : home_url('/');
$changes_id   = (int)get_option('ss_changes_page_id');
$changes_url  = $changes_id ? get_permalink($changes_id) : '';
$is_teacher   = in_array('ss_teacher', $roles);
$is_imp       = (!$is_admin && !$is_teacher) ? SS_Database::user_is_imp($user->ID) : false;

// Avatar
$avatar_att_id  = (int)get_user_meta($user->ID, 'ss_avatar_id', true);
$avatar_ts      = (int)get_user_meta($user->ID, 'ss_avatar_ts', true);
$avatar_src     = $avatar_att_id
    ? wp_get_attachment_image_url($avatar_att_id, 'thumbnail') . ($avatar_ts ? '?v=' . $avatar_ts : '')
    : get_avatar_url($user->ID, ['size' => 80]);
$has_custom_av  = (bool)$avatar_att_id;

// QR login per-user flag
$qr_allowed = get_option('ss_qr_login_enabled', 0)
    ? (bool)get_user_meta($user->ID, 'ss_qr_login_allowed', true)
    : false;
// QR-toggle + 2FA-setup nonces are now created in SS_Public::enqueue() and
// passed to public/js/settings.js via wp_localize_script (ss_settings).
$_2fa_enabled     = SS_2FA::is_enabled($user->ID);

// Hide name in quiz/survey results
$anon_quiz    = (bool)get_user_meta($user->ID, 'ss_anon_quiz', true);
// New-device login notifications (default: enabled)
$new_device_notify = (get_user_meta($user->ID, 'ss_user_new_device_notify', true) !== '0');

// Ątspėk ad preference
$atspek_mode  = get_user_meta($user->ID, 'ss_atspek_mode',  true) ?: 'day';
$atspek_hours = (int)(get_user_meta($user->ID, 'ss_atspek_hours', true) ?: 24);
if (!in_array($atspek_mode, ['day','hours','off'], true)) $atspek_mode = 'day';
$atspek_hours = min(168, max(1, $atspek_hours));

// User info
$user_email   = $user->user_email;
$user_login   = $user->user_login;
$user_since   = get_userdata($user->ID)->user_registered
    ? (function($s){ $dt = new DateTime($s, new DateTimeZone('UTC')); $dt->setTimezone(SS_Time::tz()); return $dt->format('Y-m-d'); })($user->user_registered)
    : '—';
?>
<div class="ss-app ss-settings-page" id="ss-app">

  <!-- ════ HEADER ════ -->
  <?php
    $ss_on_dashboard = false;
    $ss_active       = 'settings';
    include SS_PLUGIN_DIR . 'public/views/partials/app-nav.php';
    include SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php';
  ?>

  <main class="ss-main" style="padding-top:18px;">
    <div class="ss-settings-wrap">

      <!-- ── Page header + search ── -->
      <div class="ss-set-head">
        <div class="ss-set-head-txt">
          <h1 class="ss-set-title">Nustatymai</h1>
          <p class="ss-set-sub">Tvarkykite paskyrą, saugumą ir tvarkaraštį</p>
        </div>
        <div class="ss-set-search">
          <svg viewBox="0 0 20 20" fill="currentColor" width="17" height="17"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
          <input type="text" id="ss-set-search" placeholder="Ieškoti nustatymų…" autocomplete="off">
        </div>
      </div>
      <p id="ss-set-noresults" class="ss-scard-hint" style="display:none;text-align:center;padding:24px;">Nieko nerasta.</p>

      <?php if ($show_my_schedule): ?>
      <!-- ── Mano tvarkaraštis ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
          Mano tvarkaraštis
        </h2>
        <p class="ss-scard-desc">Įveskite tvarkaraščio kodą, kad gautumėte naują tvarkaraštį, arba patikrinkite, ar jis nėra priskirtas Jūsų el. paštui. Pakeitus, senasis tvarkaraštis pašalinamas iš paskyros.</p>
        <div class="ss-sfield" style="max-width:320px;">
          <label class="ss-slbl" for="ss-sched-code">Tvarkaraščio kodas</label>
          <input type="text" id="ss-sched-code" class="ss-sinp" maxlength="8" placeholder="pvz. AB12CD34" style="text-transform:uppercase;letter-spacing:2px;">
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;">
          <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-sched-apply">Gauti tvarkaraštį</button>
          <button type="button" class="ss-btn ss-btn-sm" id="ss-sched-check">Patikrinti pagal paštą</button>
        </div>
        <div id="ss-sched-msg" class="ss-smsg" style="display:none;margin-top:14px;"></div>
      </div>
      <?php endif; ?>

      <!-- ── Paskyra ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
          Paskyra
        </h2>
        <div class="ss-info-grid">
          <div class="ss-info-row">
            <span class="ss-info-lbl">Vardas</span>
            <span class="ss-info-val"><?= esc_html($user->display_name) ?></span>
          </div>
          <div class="ss-info-row">
            <span class="ss-info-lbl">El. paštas</span>
            <span class="ss-info-val"><?= esc_html($user_email) ?></span>
          </div>
          <div class="ss-info-row">
            <span class="ss-info-lbl">Vartotojo vardas</span>
            <span class="ss-info-val"><?= esc_html($user_login) ?></span>
          </div>
          <div class="ss-info-row">
            <span class="ss-info-lbl">Rolė</span>
            <span class="ss-info-val"><?= esc_html($role_lbl) ?></span>
          </div>
          <div class="ss-info-row">
            <span class="ss-info-lbl">Registracija</span>
            <span class="ss-info-val"><?= esc_html($user_since) ?></span>
          </div>
        </div>
      </div>

      <!-- ── Mano duomenys (PDF eksportas) ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm5 6a1 1 0 10-2 0v3.586L7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V8z" clip-rule="evenodd"/></svg>
          Mano duomenys
        </h2>
        <p class="ss-scard-desc">Atsisiųskite PDF su visa informacija, kurią sistema saugo apie Jūsų paskyrą.</p>
        <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-data-export">
          <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:-2px"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
          Atsisiųsti PDF
        </button>
        <span id="ss-data-export-msg" class="ss-smsg" style="display:none;margin-left:10px;"></span>
      </div>
      <script>
      jQuery(function($){
        function ensureAT(jsPDF){
          if (jsPDF && jsPDF.API && typeof jsPDF.API.autoTable === 'function') return true;
          var ap = (window.jspdf && typeof window.jspdf.applyPlugin === 'function') ? window.jspdf.applyPlugin : null;
          if (ap){ try{ ap(jsPDF); }catch(e){} }
          return jsPDF && jsPDF.API && typeof jsPDF.API.autoTable === 'function';
        }
        $('#ss-data-export').on('click', function(){
          var $b = $(this).prop('disabled', true);
          var $m = $('#ss-data-export-msg').css('color','var(--gl)').text('Ruošiama…').show();
          $.post(ss_pub.ajax_url, { action:'ss_settings_export_data', nonce: ss_pub.front_nonce }, function(res){
            if (!res || !res.success){ $b.prop('disabled', false); $m.css('color','var(--r)').text((res&&res.data&&res.data.message)||'Klaida.'); return; }
            try { buildDataPdf(res.data); $m.css('color','var(--g)').text('✓ Paruošta').delay(2500).fadeOut(); }
            catch(e){ console.error(e); $m.css('color','var(--r)').text('Nepavyko sukurti PDF.'); }
            $b.prop('disabled', false);
          }).fail(function(){ $b.prop('disabled', false); $m.css('color','var(--r)').text('Ryšio klaida.'); });
        });

        function buildDataPdf(data){
          if (!(window.jspdf && window.jspdf.jsPDF)) throw new Error('jsPDF nepasiekiamas');
          var jsPDF = window.jspdf.jsPDF;
          ensureAT(jsPDF);
          var doc = new jsPDF({ orientation:'p', unit:'mm', format:'a4' });
          var ff = 'helvetica';
          if (data.fonts && data.fonts.reg){
            try {
              doc.addFileToVFS('NotoSans-Regular.ttf', data.fonts.reg);
              doc.addFont('NotoSans-Regular.ttf','NotoSans','normal');
              if (data.fonts.bold){ doc.addFileToVFS('NotoSans-Bold.ttf', data.fonts.bold); doc.addFont('NotoSans-Bold.ttf','NotoSans','bold'); }
              ff = 'NotoSans';
            } catch(e){}
          }
          var M = data.meta || {}, margin = 14, pageW = doc.internal.pageSize.getWidth();
          var pageH = doc.internal.pageSize.getHeight(), y = 16;
          var hasLogo = data.logo && data.logo.b64;
          if (hasLogo){ try { doc.addImage('data:'+(data.logo.mime||'image/png')+';base64,'+data.logo.b64, 'PNG', margin, y, 13, 13); } catch(e){ hasLogo=false; } }
          var tx = margin + (hasLogo ? 18 : 0);
          doc.setFont(ff,'bold'); doc.setFontSize(15); doc.setTextColor(1,42,54);
          doc.text(M.title || 'Mano duomenys', tx, y+5);
          doc.setFont(ff,'normal'); doc.setFontSize(9); doc.setTextColor(80,110,120);
          doc.text(String(M.school||''), tx, y+10.5);
          y += 18;
          doc.setFontSize(8); doc.setTextColor(120,120,120);
          doc.text([String(M.name||''), String(M.username||''), String(M.generated||'')].filter(Boolean).join('  ·  '), margin, y);
          y += 5;
          doc.setDrawColor(225,228,225); doc.line(margin, y, pageW-margin, y); y += 7;

          function heading(t){
            if (y > pageH - 22){ doc.addPage(); y = 16; }
            doc.setFont(ff,'bold'); doc.setFontSize(11.5); doc.setTextColor(20,69,47);
            doc.text(String(t), margin, y); y += 2;
          }
          function afterTable(){ y = doc.lastAutoTable.finalY + 9; }
          var base = { styles:{ font:ff, fontSize:8.5, cellPadding:2.2, textColor:[40,40,40], overflow:'linebreak' },
                       headStyles:{ font:ff, fontStyle:'bold', fillColor:[20,69,47], textColor:[255,255,255], fontSize:8.5 },
                       margin:{ left:margin, right:margin } };

          (data.sections||[]).forEach(function(sec){
            heading(sec.title);
            if (sec.note){ doc.setFont(ff,'normal'); doc.setFontSize(8); doc.setTextColor(110,110,110); y+=4; doc.text(String(sec.note), margin, y); }
            if (sec.kind === 'kv'){
              doc.autoTable(Object.assign({}, base, { startY: y+3, body: sec.rows || [],
                theme:'plain',
                columnStyles:{ 0:{ fontStyle:'bold', cellWidth:60, textColor:[20,69,47] } } }));
              afterTable();
            } else if (sec.kind === 'table'){
              doc.autoTable(Object.assign({}, base, { startY: y+3, head:[sec.head||[]], body: sec.rows || [], theme:'striped' }));
              afterTable();
            } else if (sec.kind === 'list'){
              doc.autoTable(Object.assign({}, base, { startY: y+3, body: (sec.items||[]).map(function(i){ return [String(i)]; }), theme:'plain' }));
              afterTable();
            } else { // note-only
              doc.setFont(ff,'normal'); doc.setFontSize(9); doc.setTextColor(90,90,90);
              var lines = doc.splitTextToSize(String(sec.text||'—'), pageW - margin*2);
              y += 5; doc.text(lines, margin, y); y += lines.length*5 + 6;
            }
          });

          // Puslapių numeravimas
          var n = doc.internal.getNumberOfPages();
          for (var i=1;i<=n;i++){ doc.setPage(i); doc.setFont(ff,'normal'); doc.setFontSize(7.5); doc.setTextColor(150,150,150);
            doc.text((M.school||'') + ' · ' + i + ' / ' + n, pageW-margin, pageH-7, { align:'right' }); }

          var fn = 'mano-duomenys-' + (M.username||'paskyra') + '.pdf';
          doc.save(fn.replace(/[^a-z0-9_\-\.]+/gi,'_'));
        }
      });
      </script>

      <!-- ── Profilio nuotrauka ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
          Profilio nuotrauka
        </h2>
        <div class="ss-avatar-row">
          <div class="ss-avatar-preview-wrap">
            <img src="<?= esc_url($avatar_src) ?>" alt="Avatar" class="ss-avatar-preview" id="ss-avatar-preview">
            <?php if ($has_custom_av): ?>
            <button type="button" class="ss-avatar-del" id="ss-avatar-del" title="Pašalinti nuotrauką"><svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
            <?php endif; ?>
          </div>
          <div class="ss-avatar-actions">
            <label class="ss-btn ss-btn-pri ss-btn-sm" style="cursor:pointer;">
              <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
              Pasirinkti nuotrauką
              <input type="file" id="ss-avatar-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
            </label>
            <p class="ss-scard-hint">JPEG, PNG, GIF arba WEBP · maks. 3 MB · Po pasirinkimo galėsite apkarpyti</p>
            <div class="ss-avatar-prog" id="ss-avatar-prog" style="display:none;">
              <div class="ss-avatar-prog-bar"><div class="ss-avatar-prog-fill" id="ss-avatar-prog-fill"></div></div>
              <span>Įkeliama…</span>
            </div>
          </div>
        </div>
      </div>


      <!-- ── Privatumas: viktorinų rezultatai ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1.944A11.954 11.954 0 012.166 5C2.056 5.649 2 6.319 2 7c0 5.225 3.34 9.67 8 11.317C14.66 16.67 18 12.225 18 7c0-.682-.057-1.35-.166-2.001A11.954 11.954 0 0110 1.944zM11 14a1 1 0 11-2 0 1 1 0 012 0zm0-7a1 1 0 10-2 0v3a1 1 0 102 0V7z" clip-rule="evenodd"/></svg>
          Privatumas viktorinose
        </h2>
        <p class="ss-scard-desc">Jeigu įjungsite, jūsų vardas viktorinų ir apklausų rezultatų lentelėse kitiems vartotojams bus paslėptas (rodomas atsitiktinis vardas „Anonimas-…"). Tikrąjį vardą matys tik administratorius.</p>
        <label class="ss-toggle-row" style="display:flex;align-items:center;gap:12px;cursor:pointer;">
          <input type="checkbox" id="ss-anon-quiz" <?= $anon_quiz ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--g);cursor:pointer;">
          <span class="ss-slbl" style="margin:0;">Slėpti mano vardą viktorinų rezultatuose</span>
        </label>
        <div class="ss-srow" style="margin-top:14px;">
          <span id="ss-anon-msg" class="ss-smsg" style="display:none;"></span>
        </div>
      </div>

      <!-- ── Saugumo pranešimai: naujas įrenginys ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" clip-rule="evenodd"/></svg>
          Saugumo pranešimai
        </h2>
        <p class="ss-scard-desc">Gaukite el. laišką, kai prie Jūsų paskyros prisijungiama iš naujo, dar nematyto įrenginio ar naršyklės. Rekomenduojama palikti įjungtą.</p>
        <label class="ss-toggle-row" style="display:flex;align-items:center;gap:12px;cursor:pointer;">
          <input type="checkbox" id="ss-new-device" <?= $new_device_notify ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--g);cursor:pointer;">
          <span class="ss-slbl" style="margin:0;">Pranešti apie prisijungimą iš naujo įrenginio</span>
        </label>
        <div class="ss-srow" style="margin-top:14px;">
          <span id="ss-new-device-msg" class="ss-smsg" style="display:none;"></span>
        </div>
      </div>

      <!-- ── Pakeitimų pranešimai ── -->
      <?php $cn = class_exists('SS_ChangeNotify') ? SS_ChangeNotify::get_settings($user->ID) : ['on'=>false,'mode'=>'immediate','time'=>'17:00']; ?>
      <div class="ss-scard" id="ss-cn-card">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
          Pakeitimų pranešimai
        </h2>
        <p class="ss-scard-desc">Gaukite kitos dienos pamokų pakeitimus el. paštu — tik Jums aktualius (pamoka ir pakeitimas) bei bendrą dienos komentarą.</p>
        <label class="ss-toggle-row" style="display:flex;align-items:center;gap:12px;cursor:pointer;">
          <input type="checkbox" id="ss-cn-on" <?= $cn['on'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--g);cursor:pointer;">
          <span class="ss-slbl" style="margin:0;">Siųsti pakeitimus el. paštu</span>
        </label>

        <div id="ss-cn-opts" style="margin-top:16px;<?= $cn['on'] ? '' : 'display:none;' ?>">
          <label class="ss-slbl">Kada siųsti laišką</label>
          <div style="display:flex;flex-direction:column;gap:8px;margin:6px 0 12px;">
            <label class="ss-cn-mode" style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;color:var(--dk);">
              <input type="radio" name="ss-cn-mode" value="immediate" <?= $cn['mode']==='immediate' ? 'checked' : '' ?> style="accent-color:var(--g);">
              Iškart po paskelbimo
            </label>
            <label class="ss-cn-mode" style="display:flex;align-items:center;gap:10px;cursor:pointer;font-size:14px;color:var(--dk);">
              <input type="radio" name="ss-cn-mode" value="time" <?= $cn['mode']==='time' ? 'checked' : '' ?> style="accent-color:var(--g);">
              Nurodytu laiku
            </label>
          </div>
          <div id="ss-cn-time-wrap" style="<?= $cn['mode']==='time' ? '' : 'display:none;' ?>">
            <label class="ss-slbl" for="ss-cn-time">Laikas (16:00–21:00)</label>
            <select id="ss-cn-time" class="ss-sinp" style="width:150px;">
              <?php foreach (SS_ChangeNotify::time_options() as $t): ?>
              <option value="<?= esc_attr($t) ?>" <?= $cn['time']===$t ? 'selected' : '' ?>><?= esc_html($t) ?></option>
              <?php endforeach; ?>
            </select>
            <p class="ss-scard-hint" style="margin-top:6px;">Jei iki šio laiko pakeitimai dar nepaskelbti — laiškas atsiunčiamas iškart po paskelbimo. Jei iki 21:00 vis tiek nepaskelbta — gausite pranešimą, kad pakeitimų greičiausiai nėra.</p>
          </div>
        </div>

        <div class="ss-srow" style="margin-top:14px;">
          <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-cn-save">Išsaugoti</button>
          <span id="ss-cn-msg" class="ss-smsg" style="display:none;"></span>
        </div>
      </div>
      <script>
      jQuery(function($){
        $('#ss-cn-on').on('change', function(){ $('#ss-cn-opts').toggle($(this).is(':checked')); });
        $('input[name="ss-cn-mode"]').on('change', function(){ $('#ss-cn-time-wrap').toggle($(this).val()==='time'); });
        $('#ss-cn-save').on('click', function(){
          var on   = $('#ss-cn-on').is(':checked') ? 1 : 0;
          var mode = $('input[name="ss-cn-mode"]:checked').val() || 'immediate';
          var time = $('#ss-cn-time').val() || '17:00';
          var $b = $(this).prop('disabled', true).text('Saugoma…');
          var $m = $('#ss-cn-msg').hide();
          $.post(ss_pub.ajax_url, {action:'ss_settings_save_changes_notify', nonce:ss_pub.front_nonce, on:on, mode:mode, time:time}, function(r){
            $b.prop('disabled', false).text('Išsaugoti');
            if (r.success) { $m.css('color','var(--g)').text('✓ Išsaugota').show().delay(2500).fadeOut(); }
            else { $m.css('color','var(--r)').text((r.data&&r.data.message)||'Klaida.').show(); }
          }).fail(function(){ $b.prop('disabled', false).text('Išsaugoti'); $m.css('color','var(--r)').text('Ryšio klaida.').show(); });
        });
      });
      </script>

      <!-- ── Privatumas (žinutės, grupės, draugai) ── -->
      <?php if (class_exists('SS_Social') && get_option('ss_messages_page_id')):
        $priv = SS_Social::privacy_settings($user->ID); ?>
      <div class="ss-scard" id="ss-priv-card">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
          Privatumas
        </h2>
        <p class="ss-scard-desc">Nustatykite, kas gali Jums rašyti, pridėti į grupes ir matyti Jūsų buvimo statusą. Galima pažymėti kelias grupes.</p>
        <?php
          $aud_labels = SS_Social::audience_labels();
          $ss_aud_group = function ($id, $title, $hint, $selected) use ($aud_labels) {
            echo '<label class="ss-slbl" style="margin-top:14px">' . esc_html($title) . '</label>';
            if ($hint) echo '<p class="ss-scard-hint" style="margin:2px 0 6px">' . esc_html($hint) . '</p>';
            echo '<div class="ss-aud-grid" data-aud="' . esc_attr($id) . '">';
            foreach ($aud_labels as $tok => $lbl) {
              $ck = in_array($tok, (array) $selected, true) ? ' checked' : '';
              echo '<label class="ss-aud-chip"><input type="checkbox" value="' . esc_attr($tok) . '"' . $ck . '><span>' . esc_html($lbl) . '</span></label>';
            }
            echo '</div>';
          };
          $ss_aud_group('msg', 'Kas gali man rašyti', 'Mokytojai (savo mokiniams) ir administracija visada gali.', $priv['msg']);
          $ss_aud_group('group', 'Kas gali pridėti mane į grupes', '', $priv['group']);
          $ss_aud_group('seen', 'Kas mato tikslų mano buvimo laiką', 'Kiti matys tik „Prisijungęs" / „Buvo neseniai" / „Buvo seniai".', $priv['seen']);
        ?>

        <label class="ss-toggle-row" style="display:flex;align-items:center;gap:12px;cursor:pointer;margin-top:18px;">
          <input type="checkbox" id="ss-priv-femail" <?= $priv['friend_email'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--g);cursor:pointer;">
          <span class="ss-slbl" style="margin:0;">Siųsti man el. laišką, kai gaunu draugystės kvietimą</span>
        </label>
        <label class="ss-toggle-row" style="display:flex;align-items:center;gap:12px;cursor:pointer;margin-top:10px;">
          <input type="checkbox" id="ss-priv-memail" <?= $priv['msg_email'] ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--g);cursor:pointer;">
          <span class="ss-slbl" style="margin:0;">Priminti el. laišku apie neperskaitytas žinutes (po ~1 val.)</span>
        </label>

        <div class="ss-srow" style="margin-top:16px;">
          <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-priv-save">Išsaugoti</button>
          <span id="ss-priv-out" class="ss-smsg" style="display:none;"></span>
        </div>
      </div>
      <script>
      jQuery(function($){
        function audVals(id){ return $('.ss-aud-grid[data-aud="'+id+'"] input:checked').map(function(){return this.value;}).get(); }
        $('#ss-priv-save').on('click', function(){
          var $b = $(this).prop('disabled', true).text('Saugoma…');
          var $m = $('#ss-priv-out').hide();
          $.post(ss_pub.ajax_url, {action:'ss_save_privacy', nonce:ss_pub.front_nonce,
            priv_msg:audVals('msg'), priv_group:audVals('group'), priv_seen:audVals('seen'),
            friend_email:$('#ss-priv-femail').is(':checked')?1:0,
            msg_email:$('#ss-priv-memail').is(':checked')?1:0}, function(r){
            $b.prop('disabled', false).text('Išsaugoti');
            if (r.success) { $m.css('color','var(--g)').text('✓ Išsaugota').show().delay(2500).fadeOut(); }
            else { $m.css('color','var(--r)').text((r.data&&r.data.message)||'Klaida.').show(); }
          }).fail(function(){ $b.prop('disabled', false).text('Išsaugoti'); $m.css('color','var(--r)').text('Ryšio klaida.').show(); });
        });
      });
      </script>
      <?php endif; ?>

      <!-- ── Ątspėk nustatymai ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
          Ątspėk žaidimo reklama
        </h2>
        <p class="ss-scard-desc">Pasirinkite, kada reklama vėl turėtų atsirasti po uždarymo tvarkaraštyje ir pakeitimų puslapyje.</p>

        <div class="ss-atspek-opts">
          <label class="ss-atspek-opt<?= $atspek_mode==='day' ? ' ss-atspek-opt--active' : '' ?>">
            <input type="radio" name="ss_atspek_mode" value="day" <?= $atspek_mode==='day' ? 'checked' : '' ?>>
            <span class="ss-atspek-opt-icon"><span class="ss-atspek-opt-icon">
              <svg width="22" height="22" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true">
                <path d="M20.52 59.717l7.027-7.2a20.87 20.87 0 0 1-6.904-2.87l-.123 10.07" fill="#000000"></path>
                <path d="M43.48 4.284l-7.025 7.199a20.872 20.872 0 0 1 6.904 2.871l.121-10.07" fill="#000000"></path>
                <path d="M11.484 36.454l-7.201 7.025l10.07-.122a20.85 20.85 0 0 1-2.869-6.903" fill="#000000"></path>
                <path d="M52.516 27.547l7.201-7.027l-10.07.123a20.856 20.856 0 0 1 2.869 6.904" fill="#000000"></path>
                <path d="M14.354 20.642l-10.07-.123l7.201 7.027a20.805 20.805 0 0 1 2.869-6.904" fill="#000000"></path>
                <path d="M49.646 43.358l10.07.122l-7.201-7.026a20.814 20.814 0 0 1-2.869 6.904" fill="#000000"></path>
                <path d="M27.547 11.483l-7.027-7.2l.123 10.07a20.87 20.87 0 0 1 6.904-2.87" fill="#000000"></path>
                <path d="M36.453 52.517l7.027 7.199l-.123-10.069a20.891 20.891 0 0 1-6.904 2.87" fill="#000000"></path>
                <path d="M14.752 43.962l-3.967 9.251l9.252-3.965a21.104 21.104 0 0 1-5.285-5.286" fill="#000000"></path>
                <path d="M49.248 20.039l3.965-9.252l-9.25 3.965a21.108 21.108 0 0 1 5.285 5.287" fill="#000000"></path>
                <path d="M11 32c0-1.278.133-2.524.352-3.741L2 31.999l9.352 3.74A21.034 21.034 0 0 1 11 32z" fill="#000000"></path>
                <path d="M62 32l-9.352-3.741C52.867 29.476 53 30.722 53 32c0 1.279-.133 2.525-.352 3.741L62 32z" fill="#000000"></path>
                <path d="M20.039 14.751l-9.252-3.965l3.965 9.252a21.149 21.149 0 0 1 5.287-5.287" fill="#000000"></path>
                <path d="M43.961 49.248l9.252 3.965l-3.965-9.251a21.128 21.128 0 0 1-5.287 5.286" fill="#000000"></path>
                <path d="M35.74 11.352L32 2l-3.74 9.352C29.475 11.133 30.721 11 32 11s2.525.133 3.74.352" fill="#000000"></path>
                <path d="M28.26 52.648L32 62l3.74-9.352c-1.215.219-2.461.352-3.74.352s-2.525-.133-3.74-.352" fill="#000000"></path>
                <circle cx="32" cy="32" r="19" fill="#000000"></circle>
              </svg>
            </span></span>
            <span class="ss-atspek-opt-text">
              <strong>Kitą dieną</strong>
              <small>Reklama vėl atsiras rytoj</small>
            </span>
          </label>
          <label class="ss-atspek-opt<?= $atspek_mode==='hours' ? ' ss-atspek-opt--active' : '' ?>">
            <input type="radio" name="ss_atspek_mode" value="hours" <?= $atspek_mode==='hours' ? 'checked' : '' ?>>
            <span class="ss-atspek-opt-icon"><svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg></span>
            <span class="ss-atspek-opt-text">
              <strong>Po kelių valandų</strong>
              <small>Nurodyti laiką</small>
            </span>
          </label>
          <label class="ss-atspek-opt<?= $atspek_mode==='off' ? ' ss-atspek-opt--active' : '' ?>">
            <input type="radio" name="ss_atspek_mode" value="off" <?= $atspek_mode==='off' ? 'checked' : '' ?>>
            <span class="ss-atspek-opt-icon"><svg width="22" height="22" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.366zm1.414-1.413L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/></svg></span>
            <span class="ss-atspek-opt-text">
              <strong>Išjungti</strong>
              <small>Niekada nerodyti</small>
            </span>
          </label>
        </div>

        <div class="ss-atspek-hours-wrap" id="ss-atspek-hours-wrap" style="<?= $atspek_mode==='hours' ? '' : 'display:none;' ?>">
          <label class="ss-slbl">Po kiek valandų rodyti vėl? <small>(maks. 168 val. = 1 sav.)</small></label>
          <div class="ss-atspek-hours-row">
            <input type="number" id="ss-atspek-hours" class="ss-sinp" min="1" max="168" value="<?= esc_attr($atspek_hours) ?>" style="width:90px;">
            <span class="ss-atspek-hours-lbl">valandų</span>
          </div>
        </div>

        <div class="ss-srow" style="margin-top:14px;">
          <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-atspek-save">Išsaugoti</button>
          <span id="ss-atspek-msg" class="ss-smsg" style="display:none;"></span>
        </div>
      </div>

      <?php
      $_qr_enabled = (bool)get_option('ss_qr_login_enabled', 0);
      $_gg_enabled = SS_Google_Auth::is_enabled();
      if ($_qr_enabled || $_gg_enabled):
        if ($_gg_enabled) {
          $google_linked_email = get_user_meta($user->ID, 'ss_google_email', true);
          $google_linked_id    = get_user_meta($user->ID, 'ss_google_id',    true);
          $google_just_linked  = !empty($_GET['ss_google_linked']);
        }
      ?>
      <!-- ── Prisijungimas (QR + Google) ── -->
      <div class="ss-scard" id="ss-login-methods-card">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Prisijungimas
        </h2>
        <p class="ss-scard-desc">Papildomi prisijungimo prie jūsų paskyros būdai .</p>

        <?php if ($_qr_enabled): ?>
        <!-- QR kodas -->
        <div class="ss-lm-section<?= $_gg_enabled ? ' ss-lm-section--bordered' : '' ?>">
          <div class="ss-lm-header">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="5" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="16" y="5" width="3" height="3" fill="currentColor" stroke="none"/><rect x="5" y="16" width="3" height="3" fill="currentColor" stroke="none"/><path d="M14 14h3v3h-3zM17 17h3M17 20h3M20 17v3"/></svg>
            <strong>Prisijungimas naudojant QR kodą</strong>
          </div>
          <p class="ss-lm-desc">Kai įjungsite, nuskenavę QR kodą jau prisijungusiu įrenginiu galėsite patvirtinti prisijungimą kitame įrenginyje.</p>
          <label class="ss-toggle-row" style="display:flex;align-items:center;gap:12px;cursor:pointer;">
            <input type="checkbox" id="ss-qr-allowed" <?= $qr_allowed ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--g);cursor:pointer;">
            <span class="ss-slbl" style="margin:0;">Leisti man prisijungti naudojant QR kodą</span>
          </label>
          <span id="ss-qr-allowed-msg" class="ss-smsg" style="display:none;margin-top:10px;"></span>
        </div>
        <?php endif; ?>

        <?php if ($_gg_enabled): ?>
        <!-- Google -->
        <div class="ss-lm-section">
          <div class="ss-lm-header">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
            <strong>Google paskyra</strong>
          </div>
          <p class="ss-lm-desc">Susiekite savo Google paskyrą, kad galėtumėte prisijungti naudojant Google paskyrą.</p>

          <?php if (!empty($google_just_linked)): ?>
          <div class="ss-smsg ss-smsg-ok" style="display:block;margin-bottom:10px;"><svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:text-bottom"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> Google paskyra sėkmingai susisieta!</div>
          <?php endif; ?>

          <div id="ss-google-status">
            <?php if (!empty($google_linked_id)): ?>
            <div class="ss-google-link-row">
              <span class="ss-google-linked-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                <?= esc_html($google_linked_email) ?>
              </span>
              <button type="button" class="ss-btn ss-btn-sec ss-btn-sm" id="ss-google-unlink-btn"
                data-nonce="<?= wp_create_nonce('ss_google_unlink_' . $user->ID) ?>">Atsieti</button>
            </div>
            <?php else: ?>
            <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-google-link-btn">Susieti su Google paskyra</button>
            <?php endif; ?>
          </div>
          <div id="ss-google-msg" class="ss-smsg" style="display:none;margin-top:10px;"></div>
        </div>
        <?php endif; ?>

      </div>
      <?php endif; ?>

      <!-- ── 2FA TOTP ── -->
      <div class="ss-scard" id="ss-2fa-setup-card">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Dviejų žingsnių autentifikacija (2FA)
        </h2>
        <p class="ss-scard-desc">
          Apsaugokite savo paskyrą papildomu slaptažodžiu iš <strong>Google Authenticator</strong>, <strong>Authy</strong> ar kitos programėlės.
          Kai įjungsite, kiekvieno prisijungimo metu reikės įvesti 6 skaitmenų kodą.
        </p>

        <?php if ($_2fa_enabled): ?>
        <!-- ── Įjungta ── -->
        <div id="ss-2fa-enabled-view">
          <span class="ss-2fa-badge ss-2fa-badge--on">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            2FA įjungta
          </span>
          <?php
            $backup_remaining = class_exists('SS_2FA') ? SS_2FA::backup_codes_remaining(get_current_user_id()) : 0;
            if ($backup_remaining > 0): ?>
          <p style="margin:10px 0 0;font-size:13px;color:#646970">
            <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:text-bottom"><path fill-rule="evenodd" d="M18 8a6 6 0 01-7.743 5.743L10 14l-1 1-1 1H6v2H2v-4l4.257-4.257A6 6 0 1118 8zm-6-4a1 1 0 100 2 2 2 0 012 2 1 1 0 102 0 4 4 0 00-4-4z" clip-rule="evenodd"/></svg> Likusių atsarginių kodų: <strong><?= (int)$backup_remaining ?></strong>
            <?php if ($backup_remaining <= 3): ?>
            <span style="color:#c62828;font-weight:600"> — mažai! Išjunkite ir įjunkite 2FA iš naujo, kad generuoti naujus.</span>
            <?php endif; ?>
          </p>
          <?php endif; ?>
          <p class="ss-scard-desc" style="margin-top:12px;">Norėdami išjungti, įveskite dabartinį kodą iš programėlės:</p>
          <div class="ss-field-wrap" style="margin-bottom:14px;">
            <label class="ss-slbl" for="ss-2fa-dis-code">Kodas iš programėlės</label>
            <input type="text" id="ss-2fa-dis-code" class="ss-sinp ss-sinp-code"
                   inputmode="numeric" maxlength="6" autocomplete="off" placeholder="" style="width:160px;">
          </div>
          <div id="ss-2fa-dis-msg" class="ss-smsg" style="display:none;margin-bottom:10px;"></div>
          <button type="button" class="ss-btn ss-btn-danger" id="ss-2fa-disable-btn">Išjungti 2FA</button>
        </div>

        <?php else: ?>
        <!-- ── Išjungta ── -->
        <div id="ss-2fa-setup-view">
          <span class="ss-2fa-badge ss-2fa-badge--off">
            2FA išjungta
          </span>
          <div id="ss-2fa-step1" style="margin-top:16px;">
            <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-2fa-start-btn">
              Įjungti 2FA
            </button>
            <div id="ss-2fa-start-msg" class="ss-smsg ss-err" style="display:none;margin-top:10px;"></div>
          </div>
          <div id="ss-2fa-step2" style="display:none;margin-top:16px;">
            <p class="ss-scard-desc">1. Nuskenuokite QR kodą programėlėje arba įveskite raktą rankiniu būdu.</p>
            <div id="ss-2fa-qr-wrap" style="text-align:center;margin:14px 0;">
              <img id="ss-2fa-qr-img" src="" alt="2FA QR" style="width:180px;height:180px;border:1px solid var(--lt);">
            </div>
            <div style="background:#f5f7f5;padding:10px 14px;margin-bottom:14px;">
              <p style="font-size:11px;font-weight:700;color:var(--gl);text-transform:uppercase;letter-spacing:.4px;margin:0 0 4px;">Raktas</p>
              <code id="ss-2fa-secret-txt" style="font-size:13px;font-weight:700;color:var(--dk);letter-spacing:2px;word-break:break-all;display:block;"></code>
            </div>
            <p class="ss-scard-desc">2. Įveskite 6 skaitmenų kodą iš programėlės, kad patvirtintumėte:</p>
            <div class="ss-field-wrap" style="margin-bottom:14px;">
              <label class="ss-slbl" for="ss-2fa-verify-code">Patvirtinimo kodas</label>
              <input type="text" id="ss-2fa-verify-code" class="ss-sinp ss-sinp-code"
                     inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="" style="width:160px;">
            </div>
            <div id="ss-2fa-verify-msg" class="ss-smsg" style="display:none;margin-bottom:10px;"></div>
            <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-2fa-verify-btn">Patvirtinti ir įjungti</button>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <style>
        .ss-2fa-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;font-size:13px;font-weight:700;}
        .ss-2fa-badge--on{background:#e8f5e9;color:var(--fr);}
        .ss-2fa-badge--off{background:#fff0f0;color:var(--r);}
      </style>

      <!-- ── Slaptažodžio keitimas ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
          Slaptažodžio keitimas
        </h2>
        <p class="ss-scard-desc">Patvirtinimo kodas bus išsiųstas į jūsų el. paštą: <strong><?= esc_html(obfuscate_email($user_email)) ?></strong></p>

        <div id="ss-pw-step1">
          <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-pw-send-code">Gauti patvirtinimo kodą</button>
        </div>

        <div id="ss-pw-step2" style="display:none;">
          <div class="ss-sfield">
            <label class="ss-slbl">6 skaitmenų kodas</label>
            <input type="text" inputmode="numeric" maxlength="6" class="ss-sinp ss-sinp-code" id="ss-pw-code-inp" placeholder="XXXXXX" autocomplete="one-time-code">
          </div>
          <div class="ss-srow">
            <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-pw-verify-code">Patvirtinti</button>
            <button type="button" class="ss-btn-link" id="ss-pw-resend">Siųsti iš naujo</button>
          </div>
        </div>

        <div id="ss-pw-step3" style="display:none;">
          <div class="ss-sfield">
            <label class="ss-slbl">Naujas slaptažodis</label>
            <input type="password" class="ss-sinp" id="ss-pw-new" placeholder="Bent 8 simboliai" autocomplete="new-password">
          </div>
          <div class="ss-sfield">
            <label class="ss-slbl">Pakartokite slaptažodį</label>
            <input type="password" class="ss-sinp" id="ss-pw-new2" placeholder="Pakartokite slaptažodį" autocomplete="new-password">
          </div>
          <button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-pw-submit">Pakeisti slaptažodį</button>
        </div>

        <div id="ss-pw-msg" class="ss-smsg" style="display:none;"></div>
      </div>

      <!-- ── Aktyvios sesijos ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/></svg>
          Aktyvios sesijos
        </h2>
        <p class="ss-scard-desc">Šiuo metu prisijungę įrenginiai. Galite atjungti bet kurią sesiją, išskyrus dabartinę.</p>
        <div id="ss-sessions-list">
          <p class="ss-scard-hint">Kraunama…</p>
        </div>
      </div>

      <!-- ── Įrenginių istorija ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>
          Įrenginiai, kuriuose buvau prisijungęs
        </h2>
        <p class="ss-scard-desc">Paskutiniai 30 prisijungimų prie Jūsų paskyros.</p>
        <div id="ss-devices-list">
          <p class="ss-scard-hint">Kraunama…</p>
        </div>
      </div>

      <?php if ($is_admin): ?>
      <!-- ── Logotipas (tik adminui) — nuoroda į wp-admin ── -->
      <div class="ss-scard">
        <h2 class="ss-scard-title">
          <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/></svg>
          Mokyklos logotipas
        </h2>
        <?php if ($logo_url): ?>
        <div style="margin-bottom:14px;"><img src="<?= esc_url($logo_url) ?>" alt="Logotipas" style="max-height:56px;width:auto;border:1px solid #e2e8f0;background:#f8fafb;padding:6px;display:block;"></div>
        <?php else: ?>
        <p class="ss-scard-hint" style="margin-bottom:12px;">Logotipas nenustatytas.</p>
        <?php endif; ?>
        <a href="<?= esc_url(admin_url('admin.php?page=ss-settings')) ?>" target="_blank" class="ss-btn ss-btn-pri ss-btn-sm">
          <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:-2px"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>
          Keisti logotipą wp-admin'e
        </a>
        <p class="ss-scard-hint" style="margin-top:8px;">Logotipas rodomas tvarkaraštyje, pakeitimuose, prisijungime ir el. laiškuose.</p>
      </div>
      <?php endif; ?>

    </div><!-- /.ss-settings-wrap -->
  </main>

  <!-- ════ FOOTER ════ -->
  <?php $sys_year = (int) SS_Time::date('Y'); ?>
  <footer class="ss-settings-footer">
    <div class="ss-sf-inner">
      <div class="ss-sf-brand">
        <?php if ($logo_url): ?>
          <img src="<?= esc_url($logo_url) ?>" alt="<?= esc_attr($site_name) ?>" class="ss-sf-logo">
        <?php else: ?>
          <span class="ss-sf-logo-fb"><?= esc_html(mb_substr($site_name, 0, 1)) ?></span>
        <?php endif; ?>
        <div class="ss-sf-titles">
          <span class="ss-sf-name">Tvarkaraščių valdymo sistema ©</span>
          <span class="ss-sf-school"><?= esc_html($site_name) ?></span>
        </div>
      </div>
      <span class="ss-sf-version">v<?= esc_html(defined('SS_VERSION') ? SS_VERSION : '—') ?></span>
    </div>
    <div class="ss-sf-meta">
      2026
      <span class="ss-sf-dot">•</span>
      Kūrė Artiom Ariničev
    </div>
  </footer>
  <style>
    /* ── Settings page header + search ── */
    .ss-set-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:4px;}
    .ss-set-title{font-size:26px;font-weight:800;color:var(--dk);letter-spacing:-.5px;margin:0;}
    .ss-set-sub{font-size:13px;color:var(--gl);margin:3px 0 0;}
    .ss-set-search{display:flex;align-items:center;gap:8px;background:var(--wh);border:1.5px solid var(--lt);padding:9px 14px;min-width:230px;flex:1 1 230px;max-width:340px;transition:border-color .15s,box-shadow .15s;}
    .ss-set-search:focus-within{border-color:var(--g);box-shadow:0 0 0 3px rgba(79,137,82,.12);}
    .ss-set-search svg{color:var(--gl);flex-shrink:0;}
    .ss-set-search input{border:none;outline:none;background:none;font-family:var(--fn);font-size:14px;color:var(--dk);width:100%;}

    /* ── Footer — light, polished; brand + version badge + copyright only ── */
    .ss-settings-footer{position:relative;overflow:hidden;margin-top:8px;padding:30px 18px 22px;
      background:radial-gradient(130% 150% at 0% 0%, #f7faf7 0%, #e9f0e9 60%);color:#4a5568;border-top:1px solid #e2e8e2;}
    .ss-settings-footer::before{content:"";position:absolute;top:-45%;right:-8%;width:340px;height:340px;background:radial-gradient(circle, rgba(79,137,82,.18), transparent 70%);pointer-events:none;}
    .ss-settings-footer::after{content:"";position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--g),var(--gl),var(--gp),var(--nb));}
    .ss-sf-inner{position:relative;display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;max-width:var(--cw,1100px);margin:0 auto;}
    .ss-sf-brand{display:flex;align-items:center;gap:13px;}
    .ss-sf-logo{height:38px;width:auto;}
    .ss-sf-logo-fb{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;background:linear-gradient(135deg,var(--gl),var(--g));color:var(--wh);font-weight:800;font-size:19px;box-shadow:0 6px 16px rgba(1,42,54,.15);}
    .ss-sf-titles{display:flex;flex-direction:column;text-align:left;line-height:1.3;}
    .ss-sf-name{font-size:15px;font-weight:800;color:var(--dk);letter-spacing:-.2px;}
    .ss-sf-school{font-size:11.5px;color:var(--gl);}
    .ss-sf-version{font-size:11px;font-weight:700;color:var(--g);background:#E5F4E8;border:1px solid #BFE3C6;padding:5px 13px;letter-spacing:.4px;}
    .ss-sf-meta{position:relative;text-align:center;font-size:12px;color:#6b7280;margin:18px auto 0;padding-top:16px;border-top:1px solid #e2e8e2;max-width:var(--cw,1100px);}
    .ss-sf-meta strong{color:var(--fr);font-weight:700;}
    .ss-sf-dot{color:var(--gp);margin:0 6px;}
    @media(max-width:680px){
      .ss-sf-inner{flex-direction:column;text-align:center;gap:14px;}
      .ss-sf-brand{flex-direction:column;gap:8px;}
      .ss-sf-titles{text-align:center;align-items:center;}
      .ss-set-head{align-items:flex-start;}
      .ss-set-search{max-width:none;}
    }
  </style>

</div><!-- /#ss-app -->

<!-- ════ AVATAR CROP MODAL ════ -->
<div id="ss-crop-modal" style="display:none;position:fixed;inset:0;z-index:99990;background:rgba(0,0,0,.72);display:none;align-items:center;justify-content:center;padding:20px;">
  <div style="background:var(--wh);max-width:480px;width:100%;padding:24px;box-shadow:0 24px 60px rgba(0,0,0,.35);">
    <h3 style="margin:0 0 16px;font-size:17px;font-weight:700;color:var(--dk);">Apkarpyti nuotrauką</h3>
    <div style="width:100%;max-height:340px;overflow:hidden;background:#f0f4f5;">
      <img id="ss-crop-img" style="display:block;max-width:100%;" src="" alt="crop">
    </div>
    <div style="display:flex;gap:10px;margin-top:18px;justify-content:flex-end;">
      <button type="button" id="ss-crop-cancel" class="ss-btn ss-btn-sm" style="background:#eee;color:#333;">Atšaukti</button>
      <button type="button" id="ss-crop-confirm" class="ss-btn ss-btn-pri ss-btn-sm">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:-2px"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
        Apkarpyti ir įkelti
      </button>
    </div>
  </div>
</div>


<?php /* Cropper.js + QR toggle + 2FA setup scripts are now enqueued (self-hosted)
         via SS_Public::enqueue() — see public/js/settings.js and public/js/vendor/. */ ?>
