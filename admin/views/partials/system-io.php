<?php
/**
 * Sistemos importas / eksportas — sekcija nustatymų puslapyje
 */
if (!defined('ABSPATH')) exit;
?>
<div class="ss-card ss-io-card" id="ss-io-section">
  <h2 style="display:flex;align-items:center;gap:9px;">
    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M3 12v3c0 1.105 3.134 2 7 2s7-.895 7-2v-3c0 1.105-3.134 2-7 2s-7-.895-7-2z"/><path d="M3 7v3c0 1.105 3.134 2 7 2s7-.895 7-2V7c0 1.105-3.134 2-7 2s-7-.895-7-2z"/><path d="M17 5c0 1.105-3.134 2-7 2S3 6.105 3 5s3.134-2 7-2 7 .895 7 2z"/></svg>
    Sistemos importas / eksportas
  </h2>
  <p class="description" style="margin-bottom:20px;">Eksportuokite arba importuokite mokytojų, pamokų, tvarkaraščių ir mokinių duomenis <code>.azuolynotvs</code> formatu.</p>

  <div class="ss-io-grid">

    <!-- ═══════════════ EKSPORTAS ═══════════════ -->
    <div class="ss-io-panel">
      <div class="ss-io-panel-head">
        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        Eksportuoti
      </div>
      <p class="ss-io-desc">Pasirinkite, ką eksportuoti.</p>

      <div class="ss-io-checks">
        <label class="ss-io-check">
          <input type="checkbox" name="exp_section" value="teachers" checked>
          <span class="ss-io-check-box"></span>
          <span class="ss-io-check-label">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
            Mokytojai
          </span>
        </label>
        <label class="ss-io-check">
          <input type="checkbox" name="exp_section" value="lessons" checked>
          <span class="ss-io-check-box"></span>
          <span class="ss-io-check-label">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>
            Pamokos
          </span>
        </label>
        <label class="ss-io-check">
          <input type="checkbox" name="exp_section" value="schedules" checked>
          <span class="ss-io-check-box"></span>
          <span class="ss-io-check-label">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>
            Tvarkaraščiai
          </span>
        </label>
        <label class="ss-io-check">
          <input type="checkbox" name="exp_section" value="students" checked>
          <span class="ss-io-check-box"></span>
          <span class="ss-io-check-label">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/></svg>
            Mokiniai
          </span>
        </label>
      </div>

      <button type="button" class="button button-primary ss-io-export-btn" id="ss-io-do-export">
        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        Atsisiųsti .azuolynotvs
      </button>

      <!-- Hidden export form -->
      <form id="ss-io-export-form" method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" style="display:none;">
        <input type="hidden" name="action" value="ss_system_export">
        <input type="hidden" name="nonce"  id="ss-io-export-nonce" value="<?= wp_create_nonce('ss_system_export') ?>">
        <input type="hidden" name="sections" id="ss-io-export-sections" value="">
      </form>
    </div>

    <!-- ═══════════════ IMPORTAS ═══════════════ -->
    <div class="ss-io-panel">
      <div class="ss-io-panel-head">
        <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        Importuoti
      </div>
      <p class="ss-io-desc">Įkelkite <code>.azuolynotvs</code> failą, kad atkurtumėte arba papildytumėte duomenis.</p>

      <!-- Drop zone -->
      <div class="ss-io-drop" id="ss-io-drop">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
        <span id="ss-io-drop-label">Nuvilkite <code>.azuolynotvs</code> failą arba <u>pasirinkite</u></span>
        <input type="file" id="ss-io-file" accept=".azuolynotvs" style="position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;">
      </div>

      <!-- Preview (hidden until file loaded) -->
      <div id="ss-io-preview" style="display:none;">
        <div class="ss-io-preview-meta" id="ss-io-preview-meta"></div>

        <div class="ss-io-preview-sections" id="ss-io-preview-sections"></div>

        <div class="ss-io-mode-row">
          <span class="ss-io-mode-label">Importo režimas:</span>
          <label class="ss-io-radio">
            <input type="radio" name="imp_mode" value="merge" checked>
            <span>Papildyti</span>
            <span class="ss-io-radio-hint">Esami įrašai atnaujinami, nauji pridedami</span>
          </label>
          <label class="ss-io-radio">
            <input type="radio" name="imp_mode" value="replace">
            <span>Pakeisti</span>
            <span class="ss-io-radio-hint">Esami pasirinkti duomenys išvalomi prieš importą</span>
          </label>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button type="button" class="button button-primary" id="ss-io-do-import">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            Importuoti
          </button>
          <button type="button" class="button" id="ss-io-reset">Atšaukti</button>
        </div>
      </div>

      <!-- Result -->
      <div id="ss-io-result" style="display:none;"></div>
    </div>

  </div><!-- .ss-io-grid -->
</div><!-- .ss-io-card -->

<style>
.ss-io-card { margin-top:28px; }
.ss-io-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
@media(max-width:900px){ .ss-io-grid{ grid-template-columns:1fr; } }

.ss-io-panel {
  background:#f8faf8;
  border:1.5px solid #d4e6d5;
  
  padding:20px 20px 22px;
  display:flex; flex-direction:column; gap:14px;
}
.ss-io-panel-head {
  display:flex; align-items:center; gap:7px;
  font-size:14px; font-weight:700; color:var(--sa-dark);
}
.ss-io-desc { font-size:13px; color:#4a5568; margin:0; }

/* Checkboxes */
.ss-io-checks { display:flex; flex-direction:column; gap:8px; }
.ss-io-check { display:flex; align-items:center; gap:9px; cursor:pointer; user-select:none; }
.ss-io-check input { display:none; }
.ss-io-check-box {
  width:18px; height:18px;  border:2px solid #c5d9c6; background:var(--sa-white);
  display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all .15s;
}
.ss-io-check input:checked ~ .ss-io-check-box {
  background:var(--sa-green); border-color:var(--sa-green);
}
.ss-io-check input:checked ~ .ss-io-check-box::after {
  content:''; width:5px; height:9px; border:2px solid var(--sa-white);
  border-top:none; border-left:none; transform:rotate(45deg) translate(-1px,-1px); display:block;
}
.ss-io-check-label { display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color:var(--sa-dark); }
.ss-io-check-label svg { color:var(--sa-green); }

.ss-io-export-btn { display:inline-flex; align-items:center; gap:7px; margin-top:4px; }

/* Drop zone */
.ss-io-drop {
  border:2px dashed #b8d4ba; 
  padding:28px 16px; text-align:center;
  display:flex; flex-direction:column; align-items:center; gap:10px;
  cursor:pointer; color:var(--sa-green); background:var(--sa-white);
  transition:all .2s; font-size:13px;
  position:relative; overflow:hidden;
}
.ss-io-drop:hover, .ss-io-drop.dragover {
  border-color:var(--sa-green); background:#f0f7f0;
}
.ss-io-drop svg { opacity:.5; }

/* Preview */
.ss-io-preview-meta {
  background:var(--sa-white); border:1px solid #e5e7eb; 
  padding:10px 14px; font-size:12px; color:#6b7280; line-height:1.8;
}
.ss-io-preview-sections { display:flex; flex-direction:column; gap:7px; }
.ss-io-sec-row {
  display:flex; align-items:center; gap:9px;
  background:var(--sa-white); border:1.5px solid #d4e6d5;  padding:9px 12px;
}
.ss-io-sec-row input { width:16px; height:16px; accent-color:var(--sa-green); cursor:pointer; }
.ss-io-sec-row-info { flex:1; }
.ss-io-sec-row-label { font-size:13px; font-weight:700; color:var(--sa-dark); }
.ss-io-sec-row-count { font-size:12px; color:#6b7280; }

/* Mode radio */
.ss-io-mode-row { display:flex; flex-direction:column; gap:6px; }
.ss-io-mode-label { font-size:12px; font-weight:700; color:#4a5568; text-transform:uppercase; letter-spacing:.5px; }
.ss-io-radio { display:flex; align-items:center; gap:7px; cursor:pointer; font-size:13px; font-weight:600; color:var(--sa-dark); }
.ss-io-radio input { accent-color:var(--sa-green); }
.ss-io-radio-hint { font-size:11px; font-weight:400; color:#6b7280; margin-left:2px; }

/* Result */
.ss-io-result-ok  { background:#f0f9f0; border:1.5px solid var(--sa-green);  padding:14px 16px; }
.ss-io-result-err { background:#fef2f2; border:1.5px solid var(--sa-red);  padding:14px 16px; }
.ss-io-result-ok  p,
.ss-io-result-err p { margin:0 0 5px; font-size:13px; }
.ss-io-result-ok  p:last-child,
.ss-io-result-err p:last-child { margin:0; }
</style>

<script>
(function($){
  var importPkg = null;

  // ── Eksportas ─────────────────────────────────────────────────────────────
  $('#ss-io-do-export').on('click', function(){
    var secs = [];
    $('[name="exp_section"]:checked').each(function(){ secs.push(this.value); });
    if (!secs.length) { alert('Pasirinkite bent vieną skyrių eksportui.'); return; }
    $('#ss-io-export-sections').val(secs.join(','));
    $('#ss-io-export-form').submit();
  });

  // ── Drop zone ─────────────────────────────────────────────────────────────
  var $drop = $('#ss-io-drop');
  var $file = $('#ss-io-file');

  $drop.on('dragover dragenter', function(e){ e.preventDefault(); $drop.addClass('dragover'); });
  $drop.on('dragleave drop', function(){ $drop.removeClass('dragover'); });
  $drop.on('drop', function(e){
    e.preventDefault();
    var f = e.originalEvent.dataTransfer.files[0];
    if (f) loadFile(f);
  });
  $file.on('change', function(){
    if (this.files[0]) loadFile(this.files[0]);
  });

  function loadFile(file){
    var reader = new FileReader();
    reader.onload = function(e){
      var nonce = $('#ss-io-export-nonce').val();
      $.post(ajaxurl, {
        action: 'ss_system_import_preview',
        nonce:  ss_admin.nonce,
        json:   e.target.result
      }, function(resp){
        if (!resp.success){ showResult('err', resp.data.message || 'Klaida.'); return; }
        var d = resp.data;
        importPkg = d.raw;
        renderPreview(d, file.name);
      }).fail(function(){ showResult('err','Serverio klaida.'); });
    };
    reader.readAsText(file);
  }

  function renderPreview(d, filename){
    // Meta info
    var metaHtml = '<strong>' + escH(filename) + '</strong><br>'
      + 'Eksportuota: ' + escH(d.exported_at || '—') + '<br>'
      + 'Mokykla: '     + escH(d.school      || '—') + '<br>'
      + 'Versija: '     + escH(d.version     || '—');
    $('#ss-io-preview-meta').html(metaHtml);

    // Section rows
    var labels = {
      teachers:  { icon:'<span class="dashicons dashicons-groups" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span>', label:'Mokytojai' },
      lessons:   { icon:'<span class="dashicons dashicons-book" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span>', label:'Pamokos' },
      schedules: { icon:'<span class="dashicons dashicons-calendar-alt" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span>', label:'Tvarkaraščiai' },
      students:  { icon:'<span class="dashicons dashicons-admin-users" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span>', label:'Mokiniai' }
    };
    var secHtml = '';
    $.each(d.summary, function(key, info){
      var lbl = labels[key] || {icon:'•', label: info.label};
      var count = info.count || 0;
      var extra = key === 'schedules' ? ' (' + (info.entries||0) + ' pamokų įrašų)' : '';
      secHtml += '<label class="ss-io-sec-row">'
        + '<input type="checkbox" name="imp_section" value="'+key+'" checked>'
        + '<div class="ss-io-sec-row-info">'
        + '<div class="ss-io-sec-row-label">'+lbl.icon+' '+escH(lbl.label)+'</div>'
        + '<div class="ss-io-sec-row-count">'+count+' įrašai'+extra+'</div>'
        + '</div></label>';
    });
    $('#ss-io-preview-sections').html(secHtml);

    $('#ss-io-drop').hide();
    $('#ss-io-preview').show();
    $('#ss-io-result').hide();
  }

  // ── Importo patvirtinimas ─────────────────────────────────────────────────
  $('#ss-io-do-import').on('click', function(){
    if (!importPkg){ alert('Nėra duomenų importui.'); return; }
    var secs = [];
    $('[name="imp_section"]:checked').each(function(){ secs.push(this.value); });
    if (!secs.length){ alert('Pasirinkite bent vieną skyrių importui.'); return; }
    var mode = $('[name="imp_mode"]:checked').val() || 'merge';

    if (mode === 'replace' && !confirm('Pasirinkote „Pakeisti" režimą — pasirinkti duomenys bus ištrinti prieš importą. Tęsti?')) return;

    var $btn = $(this).prop('disabled', true).text('Importuojama…');

    $.post(ajaxurl, {
      action:   'ss_system_import_commit',
      nonce:    ss_admin.nonce,
      pkg:      JSON.stringify(importPkg),
      mode:     mode,
      sections: secs.join(',')
    }, function(resp){
      $btn.prop('disabled', false).html('<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> Importuoti');
      if (!resp.success){ showResult('err', resp.data.message || 'Klaida importuojant.'); return; }
      var lines = resp.data.results || {};
      var html  = '<p style="font-weight:700;margin-bottom:8px;"><span class="dashicons dashicons-yes-alt" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom"></span> Importas sėkmingas!</p>';
      $.each(lines, function(_, txt){ html += '<p>'+escH(txt)+'</p>'; });
      showResult('ok', html);
      resetImport();
    }).fail(function(){ $btn.prop('disabled',false); showResult('err','Serverio klaida.'); });
  });

  $('#ss-io-reset').on('click', function(){ resetImport(); });

  function resetImport(){
    importPkg = null;
    $file.val('');
    $('#ss-io-preview').hide();
    $('#ss-io-drop').show();
    $('#ss-io-drop-label').html('Nuvilkite <code>.azuolynotvs</code> failą arba <u>pasirinkite</u>');
  }

  function showResult(type, html){
    var cls = type === 'ok' ? 'ss-io-result-ok' : 'ss-io-result-err';
    $('#ss-io-result').html('<div class="'+cls+'">'+html+'</div>').show();
  }

  function escH(s){ return $('<div>').text(String(s)).html(); }

})(jQuery);
</script>
