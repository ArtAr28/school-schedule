<?php if (!defined('ABSPATH')) exit;
$ss_page_title    = 'Tvarkaraščių importas';
$ss_page_subtitle = 'Importuokite iki 500 tvarkaraščių iš karto iš HTML failų arba ZIP archyvo.';
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>

<div id="ssbulk-wrap">

  <!-- ═══ STEP 1: FILE SELECTION ═══ -->
  <div class="ss-card ssbulk-card" id="ssbulk-s1">
    <div class="ssbulk-card-head">
      <div class="ssbulk-head-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
      </div>
      <div>
        <h2 class="ssbulk-title">Failų pasirinkimas</h2>
        <p class="ssbulk-subtitle">Nutempkite HTML failus arba ZIP archyvą su html failais čia.</p>
      </div>
    </div>

    <div class="ssbulk-dropzone" id="ssbulk-drop" tabindex="0" role="button" aria-label="Pasirinkti failus">
      <div class="ssbulk-dz-glow"></div>
      <div class="ssbulk-dz-content" id="ssbulk-dz-idle">
        <div class="ssbulk-dz-upload-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/>
            <path d="M12 12v9"/><path d="m16 16-4-4-4 4"/>
          </svg>
        </div>
        <p class="ssbulk-dz-main"><strong>Nutempkite failus čia</strong></p>
        <p class="ssbulk-dz-sub">arba <label for="ssbulk-input" class="ssbulk-browse-lbl">naršykite failus</label></p>
        <div class="ssbulk-dz-tags">
          <span class="ssbulk-tag">.html</span>
          <span class="ssbulk-tag">.htm</span>
          <span class="ssbulk-tag">.zip</span>
          <span class="ssbulk-tag-sep">iki 500 failų</span>
        </div>
        <input type="file" id="ssbulk-input" multiple accept=".html,.htm,.zip" style="display:none">
      </div>
      <div class="ssbulk-dz-content ssbulk-drag-hint" id="ssbulk-drag-hint" style="display:none">
        <p class="ssbulk-dz-main">Paleiskite čia!</p>
      </div>
    </div>

    <!-- File list (shown after selection) -->
    <div id="ssbulk-list-wrap" style="display:none">
      <div class="ssbulk-list-header">
        <div class="ssbulk-list-badge">
          <span class="ssbulk-list-badge-count" id="ssbulk-fcnt">0</span>
          <span class="ssbulk-list-badge-lbl">failų paruošta importui</span>
        </div>
        <button class="ssbulk-ghost-btn" id="ssbulk-clear" type="button">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Išvalyti
        </button>
      </div>
      <div class="ssbulk-file-scroll" id="ssbulk-file-scroll">
        <div class="ssbulk-file-list" id="ssbulk-file-list"></div>
      </div>
    </div>

    <div class="ssbulk-step-actions">
      <button class="button button-primary ssbulk-primary-btn" id="ssbulk-start" disabled>
        Importuoti
      </button>
      <span class="ssbulk-step-hint" id="ssbulk-start-hint">Pasirinkite failus, kad pradėtumėte</span>
    </div>
  </div>

  <!-- ═══ STEP 2: PROGRESS ═══ -->
  <div class="ss-card ssbulk-card" id="ssbulk-s2" style="display:none">
    <div class="ssbulk-card-head">
      <div class="ssbulk-head-icon ssbulk-head-icon--spin" id="ssbulk-prog-hicon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
      </div>
      <div>
        <h2 class="ssbulk-title" id="ssbulk-prog-title">Importuojama...</h2>
        <p class="ssbulk-subtitle" id="ssbulk-prog-sub">Ruošiamasi — tai gali užtrukti kelias minutes</p>
      </div>
    </div>

    <!-- Progress bar -->
    <div class="ssbulk-bar-wrap">
      <div class="ssbulk-bar-track">
        <div class="ssbulk-bar-fill" id="ssbulk-bar" style="width:0%">
          <div class="ssbulk-bar-shimmer"></div>
        </div>
      </div>
      <div class="ssbulk-bar-meta">
        <span id="ssbulk-prog-nums">0 / 0</span>
        <span id="ssbulk-prog-pct">0%</span>
      </div>
    </div>

    <!-- Estimated time row -->
    <div class="ssbulk-time-row">
      <div class="ssbulk-time-item">
        <span class="ssbulk-time-ico dashicons dashicons-clock"></span>
        <span class="ssbulk-time-lbl">Pradėta</span>
        <span class="ssbulk-time-val" id="ssbulk-time-start">—</span>
      </div>
      <div class="ssbulk-time-item">
        <span class="ssbulk-time-ico dashicons dashicons-backup"></span>
        <span class="ssbulk-time-lbl">Praėjo</span>
        <span class="ssbulk-time-val" id="ssbulk-time-elapsed">0:00</span>
      </div>
      <div class="ssbulk-time-item">
        <span class="ssbulk-time-ico dashicons dashicons-flag"></span>
        <span class="ssbulk-time-lbl">Numatoma pabaiga</span>
        <span class="ssbulk-time-val" id="ssbulk-time-eta">—</span>
      </div>
    </div>

    <!-- Current file indicator -->
    <div class="ssbulk-cur-file-row">
      <div class="ssbulk-pulse-dot"></div>
      <span class="ssbulk-cur-lbl">Apdorojama:</span>
      <span class="ssbulk-cur-name" id="ssbulk-cur-name">—</span>
    </div>

    <!-- Running live log -->
    <div class="ssbulk-log-wrap">
      <div class="ssbulk-log-header">
        <span>Importo žurnalas</span>
      </div>
      <div class="ssbulk-log" id="ssbulk-log">
        <div class="ssbulk-log-inner" id="ssbulk-log-inner">
          <div class="ssbulk-log-empty">Laukiama pirmojo failo...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ STEP 3: SUMMARY ═══ -->
  <div class="ss-card ssbulk-card" id="ssbulk-s3" style="display:none">
    <div class="ssbulk-card-head">
      <div class="ssbulk-head-icon ssbulk-head-icon--done" id="ssbulk-done-icon">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div>
        <h2 class="ssbulk-title">Importas baigtas!</h2>
        <p class="ssbulk-subtitle" id="ssbulk-done-sub">Visi failai apdoroti.</p>
      </div>
    </div>

    <!-- Stat chips -->
    <div class="ssbulk-stat-row" id="ssbulk-stats"></div>

    <!-- Success list -->
    <div id="ssbulk-ok-section" style="display:none">
      <h3 class="ssbulk-sec-title ssbulk-sec-title--ok">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        Sėkmingai importuota <span id="ssbulk-ok-cnt"></span>
      </h3>
      <div class="ssbulk-class-grid" id="ssbulk-class-grid"></div>
    </div>

    <!-- Error list -->
    <div id="ssbulk-err-section" style="display:none">
      <h3 class="ssbulk-sec-title ssbulk-sec-title--err">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Klaidos <span id="ssbulk-err-cnt"></span>
      </h3>
      <div class="ssbulk-err-list" id="ssbulk-err-list"></div>
    </div>

    <div class="ssbulk-step-actions" style="margin-top:28px">
      <button class="button button-primary ssbulk-primary-btn" id="ssbulk-again">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
        Importuoti dar kartą
      </button>
      <a class="button ssbulk-sec-btn" href="<?php echo esc_url(admin_url('admin.php?page=ss-classes')); ?>">
        Peržiūrėti tvarkaraščius
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </a>
    </div>
  </div>

</div><!-- /ssbulk-wrap -->

<!-- JSZip for client-side ZIP extraction -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js" integrity="sha512-XMVd28F1oH/O71fzwBnV7HucLxVwtxf26XV8P4wPk26EDxuGZ91N8bsOttmnomcCD3CS5ZMRL50H0GgOHvegtg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
(function($) {
'use strict';

/* ── Constants ── */
var AJAX_URL = (typeof ss_admin !== 'undefined') ? ss_admin.ajax_url : (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
var NONCE    = (typeof ss_admin !== 'undefined') ? ss_admin.nonce   : '';
var MAX_FILES = 500;

/* ── State ── */
var gFiles = [];       // Array<File> — HTML files ready to import
var gProcessing = false;
var gStartMs = 0;      // import start timestamp (ms)
var gTimeTick = null;  // setInterval id for the live elapsed/ETA clock
var gEtaMs = 0;        // absolute estimated finish timestamp (ms)

/* ── Utility ── */
function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtNum(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
function pad2(n) { return (n < 10 ? '0' : '') + n; }
/* Laikrodis HH:MM:SS (su sekundėmis). */
function fmtClock(d) { return pad2(d.getHours()) + ':' + pad2(d.getMinutes()) + ':' + pad2(d.getSeconds()); }
/* Trukmė: H:MM:SS arba M:SS (su sekundėmis). */
function fmtDur(ms) {
  var t = Math.max(0, Math.round(ms / 1000));
  var h = Math.floor(t / 3600), m = Math.floor((t % 3600) / 60), s = t % 60;
  return h > 0 ? (h + ':' + pad2(m) + ':' + pad2(s)) : (m + ':' + pad2(s));
}

/* ── File handling ── */
function handleDrop(fileList) {
  var htmlArr = [], zipArr = [];
  for (var i = 0; i < fileList.length; i++) {
    var f = fileList[i];
    if (/\.zip$/i.test(f.name))        zipArr.push(f);
    else if (/\.html?$/i.test(f.name)) htmlArr.push(f);
  }
  if (zipArr.length) {
    showZipLoading(true);
    var promises = zipArr.map(extractZip);
    Promise.all(promises).then(function(groups) {
      showZipLoading(false);
      var all = htmlArr.slice();
      groups.forEach(function(g) { all = all.concat(g); });
      if (all.length > MAX_FILES) {
        alert('Rasta ' + all.length + ' failų — bus importuojami pirmieji ' + MAX_FILES + '.');
        all = all.slice(0, MAX_FILES);
      }
      setFiles(all);
    }).catch(function(e) {
      showZipLoading(false);
      alert('Klaida skaitant ZIP failą: ' + e.message);
    });
  } else {
    if (htmlArr.length > MAX_FILES) {
      alert('Pasirinkta ' + htmlArr.length + ' failų — bus importuojami pirmieji ' + MAX_FILES + '.');
      htmlArr = htmlArr.slice(0, MAX_FILES);
    }
    setFiles(htmlArr);
  }
}

function extractZip(zipFile) {
  return JSZip.loadAsync(zipFile).then(function(zip) {
    var items = [];
    zip.forEach(function(relPath, entry) {
      if (!entry.dir && /\.html?$/i.test(relPath)) items.push(entry);
    });
    return Promise.all(items.map(function(entry) {
      return entry.async('arraybuffer').then(function(buf) {
        var name = entry.name.replace(/.*[\\/]/, '');
        return new File([buf], name, { type: 'text/html' });
      });
    }));
  });
}

function showZipLoading(on) {
  if (on) {
    $('#ssbulk-dz-idle').hide();
    $('#ssbulk-drag-hint').show().find('.ssbulk-dz-main').text('Išskiriamas ZIP...');
  } else {
    $('#ssbulk-dz-idle').show();
    $('#ssbulk-drag-hint').hide().find('.ssbulk-dz-main').text('Paleiskite čia!');
  }
}

function setFiles(arr) {
  gFiles = arr;
  renderFileList();
}

function renderFileList() {
  var n = gFiles.length;
  if (!n) {
    $('#ssbulk-list-wrap').hide();
    $('#ssbulk-start').prop('disabled', true);
    $('#ssbulk-start-hint').text('Pasirinkite failus, kad pradėtumėte');
    return;
  }
  $('#ssbulk-fcnt').text(fmtNum(n));
  var $list = $('#ssbulk-file-list').empty();
  var show = Math.min(n, 30);
  for (var i = 0; i < show; i++) {
    var f = gFiles[i];
    $list.append(
      '<div class="ssbulk-fitem">' +
        '<span class="ssbulk-fitem-dot"></span>' +
        '<span class="ssbulk-fitem-name">' + esc(f.name) + '</span>' +
        '<span class="ssbulk-fitem-size">' + fmtKB(f.size) + '</span>' +
      '</div>'
    );
  }
  if (n > 30) {
    $list.append('<div class="ssbulk-fitem ssbulk-fitem--more">…ir dar ' + fmtNum(n - 30) + ' failų</div>');
  }
  $('#ssbulk-list-wrap').show();
  $('#ssbulk-start').prop('disabled', false);
  $('#ssbulk-start-hint').text(fmtNum(n) + ' failų paruošta. Spauskite pradėti.');
}

function fmtKB(bytes) {
  if (!bytes) return '';
  if (bytes < 1024)    return bytes + ' B';
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

/* ── Drop zone events ── */
var $drop = $('#ssbulk-drop');

$drop.on('dragenter dragover', function(e) {
  e.preventDefault();
  $(this).addClass('ssbulk-drag-active');
  $('#ssbulk-dz-idle').hide();
  $('#ssbulk-drag-hint').show();
});
$drop.on('dragleave', function(e) {
  if (!$(e.relatedTarget).closest('#ssbulk-drop').length) {
    $(this).removeClass('ssbulk-drag-active');
    $('#ssbulk-dz-idle').show();
    $('#ssbulk-drag-hint').hide();
  }
});
$drop.on('drop', function(e) {
  e.preventDefault();
  $(this).removeClass('ssbulk-drag-active');
  $('#ssbulk-dz-idle').show();
  $('#ssbulk-drag-hint').hide();
  handleDrop(e.originalEvent.dataTransfer.files);
});
$drop.on('keydown', function(e) {
  if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('#ssbulk-input').click(); }
});

$('#ssbulk-input').on('change', function() {
  if (this.files.length) handleDrop(this.files);
  this.value = '';
});

$('#ssbulk-clear').on('click', function() {
  gFiles = [];
  renderFileList();
});

/* ── Start import ── */
$('#ssbulk-start').on('click', function() {
  if (!gFiles.length || gProcessing) return;
  startImport();
});

$('#ssbulk-again').on('click', function() {
  gFiles = [];
  gProcessing = false;
  if (gTimeTick) { clearInterval(gTimeTick); gTimeTick = null; }
  $('#ssbulk-time-start').text('—');
  $('#ssbulk-time-elapsed').text('0:00');
  $('#ssbulk-time-eta').text('—');
  $('#ssbulk-s3, #ssbulk-s2').hide();
  $('#ssbulk-list-wrap').hide();
  $('#ssbulk-start').prop('disabled', true);
  $('#ssbulk-start-hint').text('Pasirinkite failus, kad pradėtumėte');
  // Reset progress icon
  $('#ssbulk-prog-hicon').removeClass('ssbulk-head-icon--done').addClass('ssbulk-head-icon--spin');
  $('#ssbulk-prog-hicon svg polyline').remove();
  $('#ssbulk-s1').show();
});

/* ── Core import logic ── */
function startImport() {
  gProcessing = true;

  var files  = gFiles.slice();
  var total  = files.length;
  var done   = 0, ok = 0, err = 0;
  var results = [];
  var totalNewTeachers = 0, totalNewLessons = 0;
  // teacherStatus accumulates teacher data across all imported files
  // key = teacher name; value = { wasExisting: bool, count: int, room: string }
  var teacherStatus = {};

  // Switch view
  $('#ssbulk-s1').fadeOut(200, function() {
    $('#ssbulk-s2').fadeIn(300);
  });

  // Reset progress UI
  $('#ssbulk-bar').css('width','0%');
  $('#ssbulk-prog-nums').text('0 / ' + fmtNum(total));
  $('#ssbulk-prog-pct').text('0%');
  $('#ssbulk-prog-sub').text('Ruošiamasi…');
  $('#ssbulk-cur-name').text('—');
  $('#ssbulk-log-inner').html('<div class="ssbulk-log-empty">Laukiama pirmojo failo…</div>');

  // ── Estimated time tracking ──
  gStartMs = Date.now();
  gEtaMs   = 0;
  $('#ssbulk-time-start').text(fmtClock(new Date(gStartMs)));
  $('#ssbulk-time-elapsed').text('0:00');
  $('#ssbulk-time-eta').text('skaičiuojama…');
  if (gTimeTick) clearInterval(gTimeTick);
  gTimeTick = setInterval(function() {
    $('#ssbulk-time-elapsed').text(fmtDur(Date.now() - gStartMs));
    if (gEtaMs) {
      var rem = Math.max(0, gEtaMs - Date.now());
      $('#ssbulk-time-eta').html('~' + fmtClock(new Date(gEtaMs)) +
        ' <span class="ssbulk-time-rem">(liko ~' + fmtDur(rem) + ')</span>');
    }
  }, 1000);

  // Recompute the absolute finish time from the average speed so far.
  function updateEta() {
    if (done > 0 && done < total) {
      var perFile = (Date.now() - gStartMs) / done;
      gEtaMs = Date.now() + perFile * (total - done);
      var rem = Math.max(0, gEtaMs - Date.now());
      $('#ssbulk-time-eta').html('~' + fmtClock(new Date(gEtaMs)) +
        ' <span class="ssbulk-time-rem">(liko ~' + fmtDur(rem) + ')</span>');
    }
  }

  function updateProgress(idx) {
    var pct = total > 0 ? Math.round(idx / total * 100) : 0;
    $('#ssbulk-bar').css('width', pct + '%');
    $('#ssbulk-prog-nums').text(fmtNum(idx) + ' / ' + fmtNum(total));
    $('#ssbulk-prog-pct').text(pct + '%');
    var sub = '';
    if (ok)  sub += '<span class="dashicons dashicons-yes-alt"></span> ' + fmtNum(ok) + ' importuota';
    if (err) sub += (sub ? ' · ' : '') + '<span class="dashicons dashicons-no-alt"></span> ' + fmtNum(err) + ' klaida(-ų)';
    $('#ssbulk-prog-sub').html(sub || 'Apdorojama…');
    updateEta();
  }

  function logItem(fname, isOk, detail) {
    var $empty = $('#ssbulk-log-inner .ssbulk-log-empty');
    if ($empty.length) $empty.remove();
    var $item = $('<div class="ssbulk-log-item ' + (isOk ? 'ssbulk-log-ok' : 'ssbulk-log-err') + '">' +
      '<span class="ssbulk-log-icon">' + (isOk
        ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'
        : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
      ) + '</span>' +
      '<span class="ssbulk-log-name">' + esc(fname) + '</span>' +
      '<span class="ssbulk-log-arrow">→</span>' +
      '<span class="ssbulk-log-detail">' + esc(detail) + '</span>' +
    '</div>');
    $('#ssbulk-log-inner').prepend($item);
    // Keep only last 200 in DOM
    var $items = $('#ssbulk-log-inner .ssbulk-log-item');
    if ($items.length > 200) $items.last().remove();
  }

  function processNext(idx) {
    if (idx >= total) {
      finishImport(results, ok, err, total, totalNewTeachers, totalNewLessons, teacherStatus);
      return;
    }

    var f = files[idx];
    $('#ssbulk-cur-name').text(f.name);
    updateProgress(done);

    // FileReader → base64 → plain POST — avoids multipart file-upload WAF/security blocks
    var reader = new FileReader();
    reader.onerror = function() {
      done++; err++;
      results.push({ ok: false, file: f.name, error: 'Nepavyko nuskaityti failo' });
      logItem(f.name, false, 'Klaida skaitant failą');
      updateProgress(done);
      processNext(idx + 1);
    };
    reader.onload = function(ev) {
      var bytes = new Uint8Array(ev.target.result), bin = '';
      for (var i = 0; i < bytes.byteLength; i++) bin += String.fromCharCode(bytes[i]);

    $.ajax({
      url: AJAX_URL,
      method: 'POST',
      data: { action: 'ss_quick_import', nonce: NONCE, html_base64: btoa(bin) },
      timeout: 30000,
      success: function(res) {
        done++;
        if (res.success) {
          ok++;
          totalNewTeachers += res.data.new_teachers || 0;
          totalNewLessons  += res.data.new_lessons  || 0;
          results.push({ ok: true, file: f.name, class_name: res.data.class_name, class_id: res.data.class_id });
          logItem(f.name, true, res.data.class_name);
          // Accumulate teacher stats
          var ts = res.data.teacher_stats || {};
          var existing = ts.existing || {};
          var newT     = ts.new     || {};
          for (var tn in existing) {
            if (!teacherStatus[tn]) teacherStatus[tn] = { wasExisting: true, count: 0, room: '' };
            teacherStatus[tn].count += (existing[tn].count || 0);
            if (existing[tn].room && !teacherStatus[tn].room) teacherStatus[tn].room = existing[tn].room;
          }
          for (var tn in newT) {
            if (!teacherStatus[tn]) teacherStatus[tn] = { wasExisting: false, count: 0, room: '' };
            teacherStatus[tn].count += (newT[tn].count || 0);
            if (newT[tn].room && !teacherStatus[tn].room) teacherStatus[tn].room = newT[tn].room;
          }
        } else {
          err++;
          var msg = (res.data && res.data.message) ? res.data.message : 'Nepavyko';
          results.push({ ok: false, file: f.name, error: msg });
          logItem(f.name, false, msg);
        }
        updateProgress(done);
        processNext(idx + 1);
      },
      error: function(xhr) {
        done++;
        err++;
        var errMsg = xhr.status === 0 ? 'Ryšio klaida / timeout' : 'Serverio klaida (' + xhr.status + ')';
        results.push({ ok: false, file: f.name, error: errMsg });
        logItem(f.name, false, errMsg);
        updateProgress(done);
        processNext(idx + 1);
      }
    }); // end $.ajax
    }; // end reader.onload
    reader.readAsArrayBuffer(f);
  }

  processNext(0);
}

/* ── Summary ── */
function finishImport(results, ok, err, total, newTeachers, newLessons, teacherStatus) {
  gProcessing = false;

  // Stop the live clock; show the real total duration + finish time (with seconds).
  if (gTimeTick) { clearInterval(gTimeTick); gTimeTick = null; }
  var endDate = new Date();
  $('#ssbulk-time-elapsed').text(fmtDur(endDate.getTime() - gStartMs));
  $('#ssbulk-time-eta').html(fmtClock(endDate) + ' <span class="ssbulk-time-rem">(baigta)</span>');

  // Animate bar to 100%
  $('#ssbulk-bar').css('width', '100%');
  $('#ssbulk-cur-name').text('Baigta!');
  $('#ssbulk-prog-title').text('Importas baigtas!');
  $('#ssbulk-prog-hicon').removeClass('ssbulk-head-icon--spin');
  $('#ssbulk-prog-hicon svg').attr({'stroke-width':'2.5'}).html('<polyline points="20 6 9 17 4 12"/>');
  $('#ssbulk-prog-hicon').addClass('ssbulk-head-icon--done');

  setTimeout(function() {
    $('#ssbulk-s2').fadeOut(300, function() {
      buildSummary(results, ok, err, total, newTeachers, newLessons, teacherStatus);
      $('#ssbulk-s3').fadeIn(400);
    });
  }, 900);
}

function buildSummary(results, ok, err, total, newTeachers, newLessons, teacherStatus) {
  // Sub-title
  var sub = '';
  if (!err) sub = '<span class="dashicons dashicons-yes-alt"></span> Visi ' + fmtNum(total) + ' failai importuoti sėkmingai!';
  else if (!ok) sub = 'Nepavyko importuoti nė vieno failo. Patikrinkite formatą.';
  else sub = fmtNum(ok) + ' importuota sėkmingai, ' + fmtNum(err) + ' nepavyko.';
  $('#ssbulk-done-sub').html(sub);

  // Stats
  var $stats = $('#ssbulk-stats').empty();
  $stats.append(mkStat('<span class="dashicons dashicons-chart-bar"></span>', fmtNum(total), 'Iš viso', ''));
  $stats.append(mkStat('<span class="dashicons dashicons-yes-alt"></span>', fmtNum(ok),    'Importuota', 'ss-stat--ok'));
  if (err)         $stats.append(mkStat('<span class="dashicons dashicons-warning"></span>', fmtNum(err), 'Klaidos', 'ss-stat--err'));
  if (newTeachers) $stats.append(mkStat('<span class="dashicons dashicons-admin-users"></span>', fmtNum(newTeachers), 'Naujų mokytojų', ''));
  if (newLessons)  $stats.append(mkStat('<span class="dashicons dashicons-book"></span>', fmtNum(newLessons),  'Naujų pamokų', ''));

  // Success section
  var okArr = results.filter(function(r) { return r.ok; });
  if (okArr.length) {
    $('#ssbulk-ok-cnt').text('(' + fmtNum(okArr.length) + ')');
    var $grid = $('#ssbulk-class-grid').empty();
    okArr.forEach(function(r) {
      $grid.append('<div class="ssbulk-class-chip"><span class="dashicons dashicons-calendar-alt"></span> ' + esc(r.class_name) + '</div>');
    });
    $('#ssbulk-ok-section').show();
  } else {
    $('#ssbulk-ok-section').hide();
  }

  // Error section
  var errArr = results.filter(function(r) { return !r.ok; });
  if (errArr.length) {
    $('#ssbulk-err-cnt').text('(' + fmtNum(errArr.length) + ')');
    var $errList = $('#ssbulk-err-list').empty();
    errArr.forEach(function(r) {
      $errList.append(
        '<div class="ssbulk-err-item">' +
          '<span class="ssbulk-err-file">' + esc(r.file) + '</span>' +
          '<span class="ssbulk-err-sep">—</span>' +
          '<span class="ssbulk-err-msg">' + esc(r.error) + '</span>' +
        '</div>'
      );
    });
    $('#ssbulk-err-section').show();
  } else {
    $('#ssbulk-err-section').hide();
  }

  // ── Teacher stats table ────────────────────────────────────────────────────
  var $teacherWrap = $('#ssbulk-teacher-section');
  if (!$teacherWrap.length) {
    // Create section on first call
    $teacherWrap = $('<div id="ssbulk-teacher-section" style="margin-top:24px"></div>');
    $('#ssbulk-s3 .ssbulk-step-actions').before($teacherWrap);
  }

  var existingArr = [], newArr = [];
  for (var tn in teacherStatus) {
    var d = teacherStatus[tn];
    if (d.wasExisting) existingArr.push({ name: tn, count: d.count, room: d.room || '' });
    else               newArr.push(    { name: tn, count: d.count, room: d.room || '' });
  }
  existingArr.sort(function(a,b){ return a.name.localeCompare(b.name, 'lt'); });
  newArr.sort(function(a,b){      return a.name.localeCompare(b.name, 'lt'); });

  var html = '';
  if (existingArr.length) {
    html += '<h3 class="ssbulk-sec-title" style="color:var(--sa-forest)!important;margin-bottom:10px!important">' +
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>' +
      ' Mokytojai bazėje (' + existingArr.length + ')</h3>';
    html += '<div class="ssbulk-teacher-table">';
    html += '<div class="ssbulk-trow ssbulk-trow--head"><span>Mokytojas</span><span>Pamokų</span><span>Kabinetas</span></div>';
    existingArr.forEach(function(t) {
      html += '<div class="ssbulk-trow ssbulk-trow--exist">' +
        '<span>' + esc(t.name) + '</span>' +
        '<span class="ssbulk-tnum">' + t.count + '</span>' +
        '<span class="ssbulk-troom">' + esc(t.room) + '</span></div>';
    });
    html += '</div>';
  }
  if (newArr.length) {
    html += '<h3 class="ssbulk-sec-title" style="color:var(--sa-red)!important;margin:18px 0 10px!important">' +
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' +
      ' Nauji mokytojai (nebuvo bazėje) (' + newArr.length + ')</h3>';
    html += '<div class="ssbulk-teacher-table">';
    html += '<div class="ssbulk-trow ssbulk-trow--head"><span>Mokytojas</span><span>Pamokų</span><span>Kabinetas</span></div>';
    newArr.forEach(function(t) {
      html += '<div class="ssbulk-trow ssbulk-trow--new">' +
        '<span>' + esc(t.name) + '</span>' +
        '<span class="ssbulk-tnum">' + t.count + '</span>' +
        '<span class="ssbulk-troom">' + esc(t.room) + '</span></div>';
    });
    html += '</div>';
  }
  $teacherWrap.html(html || '');
}

function mkStat(icon, val, lbl, cls) {
  return $('<div class="ssbulk-stat ' + cls + '">' +
    '<div class="ssbulk-stat-icon">' + icon + '</div>' +
    '<div class="ssbulk-stat-val">' + val + '</div>' +
    '<div class="ssbulk-stat-lbl">' + lbl + '</div>' +
  '</div>');
}

})(jQuery);
</script>

<!-- ════════════════════════════════════════════
     BULK IMPORT STYLES
════════════════════════════════════════════ -->
<style>
/* === Animations === */
@keyframes ssbulk-spin   { to { transform:rotate(360deg); } }
@keyframes ssbulk-shimmer{ 0%{transform:translateX(-100%)}100%{transform:translateX(300%)} }
@keyframes ssbulk-pulse  { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
@keyframes ssbulk-fadein { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
@keyframes ssbulk-glow   { 0%,100%{opacity:.3} 50%{opacity:.7} }
@keyframes ssbulk-pop    { 0%{transform:scale(.85);opacity:0} 70%{transform:scale(1.04)} 100%{transform:scale(1);opacity:1} }

/* === Wrap === */
#ssbulk-wrap { max-width:860px; }

/* === Card overrides === */
.ssbulk-card { padding:28px 30px!important;  margin-bottom:20px; }

/* === Card head === */
.ssbulk-card-head { display:flex; align-items:center; gap:18px; margin-bottom:26px; }
.ssbulk-head-icon {
  width:52px; height:52px;  flex-shrink:0;
  background: var(--sa-green);
  color:var(--sa-white); display:flex; align-items:center; justify-content:center;
  box-shadow:0 4px 14px rgba(79,137,82,.35);
}
.ssbulk-head-icon--spin svg { animation:ssbulk-spin 1s linear infinite; }
.ssbulk-head-icon--done {
  background: var(--sa-green);
  box-shadow:0 4px 14px rgba(34,197,94,.4);
}
.ssbulk-title { font-size:18px!important; font-weight:800!important; color:var(--sa-dark)!important; margin:0!important; padding:0!important; border:none!important; }
.ssbulk-subtitle { font-size:13px; color:var(--sa-green-lt); margin:5px 0 0; }

/* === Drop zone === */
.ssbulk-dropzone {
  position:relative; border:2px dashed var(--sa-green-pale); 
  padding:52px 24px; text-align:center; cursor:pointer; overflow:hidden;
  background:#fafff9; transition:border-color .22s,background .22s;
  min-height:180px; display:flex; align-items:center; justify-content:center;
}
.ssbulk-dropzone:hover, .ssbulk-dropzone:focus { border-color:var(--sa-green); background:#f2faf2; outline:none; }
.ssbulk-dropzone.ssbulk-drag-active { border-color:var(--sa-green); border-style:solid; background:#edf7ed; }
.ssbulk-dz-glow {
  position:absolute; inset:-40%; border-radius:50%;
  background:radial-gradient(circle,rgba(79,137,82,.08) 0%,transparent 70%);
  animation:ssbulk-glow 3s ease-in-out infinite; pointer-events:none;
}
.ssbulk-dz-content { width:100%; }
.ssbulk-drag-hint { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:#edf7ed; }
.ssbulk-dz-upload-icon { color:var(--sa-green); margin-bottom:16px; opacity:.75; }
.ssbulk-dz-drop-icon { font-size:52px; margin-bottom:12px; }
.ssbulk-dz-main { font-size:16px; color:var(--sa-dark); margin:0 0 6px; font-weight:500; }
.ssbulk-dz-sub  { font-size:13px; color:var(--sa-green-lt); margin:0 0 14px; }
.ssbulk-browse-lbl { color:var(--sa-green); text-decoration:underline; cursor:pointer; font-weight:600; }
.ssbulk-browse-lbl:hover { color:var(--sa-forest); }
.ssbulk-dz-tags { display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
.ssbulk-tag { background:rgba(79,137,82,.1); color:var(--sa-green); border:1px solid rgba(79,137,82,.25);  padding:3px 9px; font-size:12px; font-weight:600; }
.ssbulk-tag-sep { font-size:12px; color:var(--sa-green-lt); display:flex; align-items:center; }

/* === File list === */
.ssbulk-list-header { display:flex; align-items:center; justify-content:space-between; margin:20px 0 12px; }
.ssbulk-list-badge { display:flex; align-items:baseline; gap:6px; }
.ssbulk-list-badge-count { font-size:28px; font-weight:800; color:var(--sa-dark); line-height:1; }
.ssbulk-list-badge-lbl   { font-size:13px; color:var(--sa-green-lt); font-weight:500; }
.ssbulk-ghost-btn {
  background:none; border:1px solid var(--sa-border); 
  padding:6px 12px; cursor:pointer; color:var(--sa-red); font-size:12px; font-weight:600;
  display:flex; align-items:center; gap:5px; transition:all .15s;
}
.ssbulk-ghost-btn:hover { border-color:var(--sa-red); background:#fff5f5; }
.ssbulk-file-scroll { max-height:220px; overflow-y:auto; border:1px solid var(--sa-border);  background:var(--sa-bg); }
.ssbulk-file-list { display:flex; flex-direction:column; }
.ssbulk-fitem {
  display:flex; align-items:center; gap:10px; padding:8px 14px;
  border-bottom:1px solid var(--sa-border); font-size:12.5px;
  animation:ssbulk-fadein .15s ease;
}
.ssbulk-fitem:last-child { border-bottom:none; }
.ssbulk-fitem-dot { width:7px; height:7px;  background:var(--sa-green-pale); flex-shrink:0; }
.ssbulk-fitem-name { flex:1; color:var(--sa-dark); font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ssbulk-fitem-size { color:var(--sa-green-lt); font-size:11px; flex-shrink:0; }
.ssbulk-fitem--more { color:var(--sa-green-lt); font-style:italic; justify-content:center; border-bottom:none; padding:10px; }

/* === Actions === */
.ssbulk-step-actions { display:flex; align-items:center; gap:14px; margin-top:24px; flex-wrap:wrap; }
.ssbulk-primary-btn {
  display:inline-flex!important; align-items:center!important; gap:8px!important;
  background:linear-gradient(135deg,var(--sa-green),var(--sa-forest))!important;
  border:none!important; color:var(--sa-white)!important; 
  padding:10px 22px!important; font-size:14px!important; font-weight:700!important;
  cursor:pointer!important; box-shadow:0 4px 14px rgba(79,137,82,.4)!important;
  transition:all .2s!important; text-shadow:none!important;
  line-height: 1.5!important;
}
.ssbulk-primary-btn:hover:not(:disabled) { box-shadow:0 6px 20px rgba(79,137,82,.5)!important; /*transform:translateY(-1px)!important; */}
.ssbulk-primary-btn:disabled { opacity:.5!important; cursor:not-allowed!important; box-shadow:none!important; }
.ssbulk-sec-btn {
  display:inline-flex!important; align-items:center!important; gap:6px!important;
   border-color:var(--sa-border)!important;
  color:var(--sa-dark)!important; font-weight:600!important;
  padding:10px 20px!important; font-size:13px!important;
}
.ssbulk-sec-btn:hover { border-color:var(--sa-green)!important; color:var(--sa-green)!important; }
.ssbulk-step-hint { font-size:12px; color:var(--sa-green-lt); }

/* === Progress bar === */
.ssbulk-bar-wrap  { margin-bottom:14px; }
.ssbulk-bar-track { height:14px; background:var(--sa-bg);  overflow:hidden; border:1px solid var(--sa-border); position:relative; }
.ssbulk-bar-fill  {
  height:100%;  position:relative; overflow:hidden;
  background:linear-gradient(90deg,var(--sa-forest),var(--sa-green),var(--sa-green-lt));
  transition:width .35s cubic-bezier(.4,0,.2,1);
  min-width:0px;
}
.ssbulk-bar-shimmer {
  position:absolute; inset:0; width:60px;
  background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);
  animation:ssbulk-shimmer 1.6s infinite linear;
}
.ssbulk-bar-meta { display:flex; justify-content:space-between; margin-top:6px; font-size:12px; color:var(--sa-green-lt); font-weight:600; }

/* === Estimated time row === */
.ssbulk-time-row { display:flex; gap:10px; flex-wrap:wrap; margin:14px 0 4px; }
.ssbulk-time-item {
  flex:1; min-width:150px; display:flex; align-items:center; gap:8px;
  background:var(--sa-bg); border:1px solid var(--sa-border);  padding:9px 13px;
}
.ssbulk-time-ico { color:var(--sa-green); font-size:17px; width:17px; height:17px; flex-shrink:0; }
.ssbulk-time-lbl { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--sa-green-lt); }
.ssbulk-time-val { margin-left:auto; font-size:14px; font-weight:800; color:var(--sa-dark); font-variant-numeric:tabular-nums; white-space:nowrap; }
.ssbulk-time-rem { font-size:11px; font-weight:600; color:var(--sa-green-lt); }

/* === Current file indicator === */
.ssbulk-cur-file-row { display:flex; align-items:center; gap:10px; margin:14px 0 18px; font-size:13px; }
.ssbulk-pulse-dot { width:9px; height:9px; border-radius:50%; background:var(--sa-green); flex-shrink:0; animation:ssbulk-pulse 1.1s ease-in-out infinite; }
.ssbulk-cur-lbl  { color:var(--sa-green-lt); font-weight:600; }
.ssbulk-cur-name { color:var(--sa-dark); font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:400px; }

/* === Live log === */
.ssbulk-log-wrap  { border:1px solid var(--sa-border);  overflow:hidden; }
.ssbulk-log-header {
  background:var(--sa-dark); color:var(--sa-white); padding:8px 14px;
  display:flex; justify-content:space-between; align-items:center;
  font-size:11px; font-weight:700; letter-spacing:.5px; text-transform:uppercase;
}
.ssbulk-log-live-badge { color:#4ade80; font-size:10px; letter-spacing:.3px; }
.ssbulk-log { max-height:260px; overflow-y:auto; background:var(--sa-bg); }
.ssbulk-log-inner { display:flex; flex-direction:column; }
.ssbulk-log-empty { padding:16px; text-align:center; color:var(--sa-green-lt); font-size:12px; font-style:italic; }
.ssbulk-log-item {
  display:flex; align-items:center; gap:8px; padding:7px 14px;
  border-bottom:1px solid var(--sa-border); font-size:12px;
  animation:ssbulk-fadein .2s ease;
}
.ssbulk-log-item:last-child { border-bottom:none; }
.ssbulk-log-ok   { background:#f0fff4; }
.ssbulk-log-err  { background:#fff5f5; }
.ssbulk-log-icon { width:18px; height:18px;  display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ssbulk-log-ok  .ssbulk-log-icon { color:#16a34a; }
.ssbulk-log-err .ssbulk-log-icon { color:var(--sa-red); }
.ssbulk-log-name   { flex:1; font-weight:600; color:var(--sa-dark); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:240px; }
.ssbulk-log-arrow  { color:var(--sa-border); flex-shrink:0; }
.ssbulk-log-detail { color:var(--sa-green-lt); font-size:11.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:240px; }
.ssbulk-log-err .ssbulk-log-detail { color:var(--sa-red); }

/* === Summary stats === */
.ssbulk-stat-row { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:28px; }
.ssbulk-stat {
  flex:1; min-width:100px; background:var(--sa-bg); border:1px solid var(--sa-border);
   padding:18px 16px; text-align:center;
  animation:ssbulk-pop .35s cubic-bezier(.34,1.56,.64,1) both;
  border-top:3px solid var(--sa-green-pale);
}
.ssbulk-stat--ok  { border-top-color:#22c55e; background:#f0fff4; }
.ssbulk-stat--err { border-top-color:var(--sa-red); background:#fff5f5; }
.ssbulk-stat-icon { font-size:22px; margin-bottom:6px; }
.ssbulk-stat-icon .dashicons { font-size:24px; width:24px; height:24px; }
.ssbulk-prog-sub .dashicons, #ssbulk-prog-sub .dashicons, #ssbulk-done-sub .dashicons { font-size:15px; width:15px; height:15px; vertical-align:text-bottom; }
.ssbulk-class-chip .dashicons { font-size:14px; width:14px; height:14px; vertical-align:text-bottom; }
.ssbulk-stat-val  { font-size:26px; font-weight:800; color:var(--sa-dark); line-height:1; margin-bottom:4px; }
.ssbulk-stat--ok  .ssbulk-stat-val { color:#16a34a; }
.ssbulk-stat--err .ssbulk-stat-val { color:var(--sa-red); }
.ssbulk-stat-lbl  { font-size:11px; color:var(--sa-green-lt); font-weight:600; text-transform:uppercase; letter-spacing:.4px; }
.ssbulk-stat:nth-child(2) { animation-delay:.05s; }
.ssbulk-stat:nth-child(3) { animation-delay:.10s; }
.ssbulk-stat:nth-child(4) { animation-delay:.15s; }
.ssbulk-stat:nth-child(5) { animation-delay:.20s; }

/* === Section titles === */
.ssbulk-sec-title {
  display:flex; align-items:center; gap:8px;
  font-size:14px!important; font-weight:700!important; color:var(--sa-dark)!important;
  margin:0 0 14px!important; padding:0!important; border:none!important;
}
.ssbulk-sec-title--ok  { color:#16a34a!important; }
.ssbulk-sec-title--err { color:var(--sa-red)!important; }

/* === Class grid === */
.ssbulk-class-grid { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:24px; }
.ssbulk-class-chip {
  background:linear-gradient(135deg,rgba(79,137,82,.12),rgba(79,137,82,.06));
  border:1px solid rgba(79,137,82,.3); 
  padding:6px 14px; font-size:13px; font-weight:600; color:var(--sa-dark);
  animation:ssbulk-fadein .2s ease both;
}

/* === Error list === */
.ssbulk-err-list { display:flex; flex-direction:column; gap:6px; margin-bottom:18px; }
.ssbulk-err-item { display:flex; align-items:center; gap:10px; background:#fff5f5; border:1px solid rgba(208,81,85,.2);  padding:8px 14px; font-size:12.5px; }
.ssbulk-err-file { font-weight:700; color:var(--sa-dark); flex-shrink:0; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ssbulk-err-sep  { color:var(--sa-border); flex-shrink:0; }
.ssbulk-err-msg  { color:var(--sa-red); font-size:12px; }

/* === Teacher stats table === */
.ssbulk-teacher-table {
  border:1px solid var(--sa-border); 
  overflow:hidden; margin-bottom:6px; font-size:12.5px;
}
.ssbulk-trow {
  display:grid; grid-template-columns:1fr 60px 130px;
  padding:7px 14px; border-bottom:1px solid var(--sa-border);
  align-items:center; gap:8px;
}
.ssbulk-trow:last-child { border-bottom:none; }
.ssbulk-trow--head {
  background:var(--sa-dark); color:var(--sa-white);
  font-size:11px; font-weight:700; letter-spacing:.4px; text-transform:uppercase;
}
.ssbulk-trow--exist { background:#f0fff4; }
.ssbulk-trow--exist:hover { background:#e6fced; }
.ssbulk-trow--new   { background:#fff5f5; }
.ssbulk-trow--new:hover { background:#feeaea; }
.ssbulk-tnum  { font-weight:700; color:var(--sa-dark); text-align:center; }
.ssbulk-troom { color:var(--sa-green-lt); font-size:11.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

/* === Scrollbar style === */
.ssbulk-log::-webkit-scrollbar,
.ssbulk-file-scroll::-webkit-scrollbar { width:4px; }
.ssbulk-log::-webkit-scrollbar-track,
.ssbulk-file-scroll::-webkit-scrollbar-track { background:var(--sa-bg); }
.ssbulk-log::-webkit-scrollbar-thumb,
.ssbulk-file-scroll::-webkit-scrollbar-thumb { background:var(--sa-green-pale);  }
</style>
