<?php
if (!defined('ABSPATH')) exit;

$nonce        = wp_create_nonce('ss_admin_nonce');
$cur_year     = get_option('ss_school_year', SS_Time::date('Y') . '–' . ((int)SS_Time::date('Y') + 1));
// Suskaičiuojam pasiūlymą kitiems mokslo metams (YYYY–YYYY → +1 / +1).
$suggest_year = $cur_year;
if (preg_match('/(\d{4})\s*[–\-]\s*(\d{4})/u', $cur_year, $m)) {
    $suggest_year = ((int)$m[1] + 1) . '–' . ((int)$m[2] + 1);
}

$ss_page_title    = 'Mokslo metų pabaiga';
$ss_page_subtitle = 'Mokslo metų / pusmečio užbaigimas: tvarkaraščių išvalymas ir mokinių kėlimas į aukštesnę klasę.';
?>
<div class="wrap ss-wrap ss-ye-page">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>

<div class="ss-ye-warning">
  <span class="dashicons dashicons-warning"></span>
  <div>
    <strong>Dėmesio — negrįžtamas veiksmas.</strong>
    Bus <u>ištrinti visi tvarkaraščiai</u> (klasių tvarkaraščiai, pamokų langeliai, mokinių priskyrimai,
    tvarkaraščių kodai, pakeitimai ir pavadavimai). Mokytojai, kabinetai, pamokų sąrašas ir paskyros išliks.
    Prieš tvirtindami būtinai peržiūrėkite žemiau pateiktą sąrašą.
  </div>
</div>

<div class="ss-ye-card">
  <h2 class="ss-ye-h2">1. Pasirinkite užbaigimo tipą</h2>
  <div class="ss-ye-types">
    <label class="ss-ye-type">
      <input type="radio" name="ss-ye-type" value="year" checked>
      <span class="ss-ye-type-body">
        <strong>Mokslo metų pabaiga</strong>
        <small>Ištrinami tvarkaraščiai · mokiniai keliami +1 klasę · IV klasė (laida) ištrinama · atnaujinami mokslo metai.</small>
      </span>
    </label>
    <label class="ss-ye-type">
      <input type="radio" name="ss-ye-type" value="semester">
      <span class="ss-ye-type-body">
        <strong>Pusmečio pabaiga</strong>
        <small>Tik ištrinami tvarkaraščiai (kad galėtumėte importuoti naujus). Klasės <u>nekeliamos</u>, mokiniai netrinami.</small>
      </span>
    </label>
  </div>

  <div class="ss-ye-yearrow" id="ss-ye-yearrow">
    <label for="ss-ye-year">Nauji mokslo metai</label>
    <input type="text" id="ss-ye-year" class="regular-text" value="<?= esc_attr($suggest_year) ?>" placeholder="pvz. 2026–2027">
    <span class="ss-ye-dim">Dabartiniai: <strong><?= esc_html($cur_year) ?></strong></span>
  </div>

  <p style="margin:16px 0 0">
    <button id="ss-ye-preview-btn" class="button button-primary"><span class="dashicons dashicons-visibility"></span> Peržiūrėti, kas bus pakeista</button>
  </p>
</div>

<div class="ss-ye-card" id="ss-ye-preview-card" style="display:none">
  <h2 class="ss-ye-h2">2. Peržiūra — kas bus atlikta</h2>
  <div id="ss-ye-preview"></div>

  <div class="ss-ye-confirm">
    <label class="ss-ye-confirm-lbl">
      <input type="checkbox" id="ss-ye-confirm-cb">
      Supratau, kad šis veiksmas negrįžtamas, ir noriu tęsti.
    </label>
    <button id="ss-ye-commit-btn" class="button ss-ye-danger" disabled><span class="dashicons dashicons-yes"></span> Patvirtinti ir užbaigti</button>
  </div>
</div>

<div class="ss-ye-card" id="ss-ye-result-card" style="display:none">
  <h2 class="ss-ye-h2">3. Atlikta</h2>
  <div id="ss-ye-result"></div>
  <p style="margin:18px 0 0">
    <a id="ss-ye-import-link" href="<?= esc_url(admin_url('admin.php?page=ss-import')) ?>" class="button button-primary"><span class="dashicons dashicons-upload"></span> Importuoti naujus tvarkaraščius</a>
  </p>
</div>

<style>
.ss-ye-warning{display:flex;gap:12px;align-items:flex-start;background:#FFF6F6;border:1px solid #F5C2C2;border-left:4px solid var(--sa-red);padding:14px 16px;margin:16px 0;color:#7a2b2e;font-size:13.5px;line-height:1.55;}
.ss-ye-warning .dashicons{color:var(--sa-red);font-size:22px;width:22px;height:22px;flex-shrink:0;margin-top:1px;}
.ss-ye-card{background:var(--sa-surface);border:1px solid var(--sa-border);box-shadow:var(--sa-shadow);padding:18px 20px;margin:0 0 16px;}
.ss-ye-h2{font-size:15px;font-weight:800;color:var(--sa-dark);margin:0 0 14px;}
.ss-ye-types{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.ss-ye-type{display:flex;gap:10px;align-items:flex-start;padding:14px 16px;border:2px solid var(--sa-border);cursor:pointer;transition:.15s;background:var(--sa-white);}
.ss-ye-type:hover{border-color:var(--sa-green-pale);}
.ss-ye-type input{margin-top:3px;accent-color:var(--sa-green);}
.ss-ye-type:has(input:checked){border-color:var(--sa-green);background:#f1f8f1;}
.ss-ye-type-body strong{display:block;font-size:14px;color:var(--sa-dark);margin-bottom:3px;}
.ss-ye-type-body small{display:block;font-size:12px;color:var(--sa-green-lt);line-height:1.45;}
.ss-ye-yearrow{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:16px;}
.ss-ye-yearrow label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--sa-green-lt);}
.ss-ye-dim{font-size:12px;color:var(--sa-green-lt);}
.ss-ye-stats{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px;}
.ss-ye-stat{background:#FFF6F6;border:1px solid #F5C2C2;padding:8px 12px;font-size:13px;color:#7a2b2e;}
.ss-ye-stat b{font-size:16px;color:var(--sa-red);}
.ss-ye-loglist{margin:0 0 16px;}
.ss-ye-loglist h4{font-size:13px;font-weight:700;color:var(--sa-dark);margin:0 0 6px;display:flex;align-items:center;gap:6px;}
.ss-ye-loglist .ss-ye-count{font-size:11px;font-weight:700;background:var(--sa-bg);color:var(--sa-green-lt);padding:1px 9px;}
.ss-ye-ol{max-height:240px;overflow-y:auto;border:1px solid var(--sa-border);margin:0;padding:8px 8px 8px 36px;background:var(--sa-white);}
.ss-ye-ol li{font-size:13px;color:var(--sa-dark);line-height:1.7;}
.ss-ye-empty{font-size:13px;color:var(--sa-green-lt);font-style:italic;margin:0 0 14px;}
.ss-ye-grad li{color:#7a2b2e;}
.ss-ye-confirm{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-top:8px;padding-top:16px;border-top:1px solid var(--sa-border);}
.ss-ye-confirm-lbl{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--sa-dark);cursor:pointer;}
.ss-ye-confirm-lbl input{accent-color:var(--sa-red);width:16px;height:16px;}
.ss-ye-danger{background:var(--sa-red)!important;border-color:var(--sa-red)!important;color:var(--sa-white)!important;font-weight:700!important;}
.ss-ye-danger:hover:not(:disabled){background:#b8393d!important;border-color:#b8393d!important;color:var(--sa-white)!important;}
.ss-ye-danger:disabled{opacity:.5;cursor:not-allowed;}
.ss-ye-page .button .dashicons{vertical-align:text-bottom;font-size:16px;width:16px;height:16px;}
.ss-ye-ok{display:flex;gap:10px;align-items:flex-start;background:#F1F8F1;border:1px solid #BFE3C6;border-left:4px solid var(--sa-green);padding:12px 14px;margin:0 0 14px;font-size:13.5px;color:var(--sa-forest);}
@media(max-width:782px){.ss-ye-types{grid-template-columns:1fr;}}
</style>

<script>
(function($){
  var nonce = '<?= esc_js($nonce) ?>';
  var lastType = 'year';

  function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function toggleYearRow(){
    // Mokslo metai privalomi tik metų pabaigai; pusmečiui paliekame (neprivalomi).
    var t = $('input[name="ss-ye-type"]:checked').val();
    $('#ss-ye-yearrow').css('opacity', t === 'year' ? '1' : '.6');
  }
  $('input[name="ss-ye-type"]').on('change', function(){
    toggleYearRow();
    // pakeitus tipą — peržiūra nebegalioja
    $('#ss-ye-preview-card,#ss-ye-result-card').hide();
    $('#ss-ye-confirm-cb').prop('checked', false);
    $('#ss-ye-commit-btn').prop('disabled', true);
  });
  toggleYearRow();

  function logBlock(title, cls, items, render){
    var html = '<div class="ss-ye-loglist"><h4>'+esc(title)+' <span class="ss-ye-count">'+items.length+'</span></h4>';
    if(!items.length){ html += '<p class="ss-ye-empty">Nėra.</p></div>'; return html; }
    html += '<ol class="ss-ye-ol '+cls+'">';
    items.forEach(function(it){ html += '<li>'+render(it)+'</li>'; });
    html += '</ol></div>';
    return html;
  }

  $('#ss-ye-preview-btn').on('click', function(){
    var type = $('input[name="ss-ye-type"]:checked').val();
    lastType = type;
    var $btn = $(this).prop('disabled', true);
    $btn.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-update');
    $.post(ajaxurl, {action:'ss_rollover_preview', nonce:nonce, type:type}, function(res){
      $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-visibility');
      if(!res || !res.success){ alert((res&&res.data&&res.data.message)||'Klaida.'); return; }
      var d = res.data, c = d.counts || {};
      var html = '';

      html += '<div class="ss-ye-stats">'
        + '<span class="ss-ye-stat"><b>'+(c.classes||0)+'</b> tvarkaraščių bus ištrinta</span>'
        + '<span class="ss-ye-stat"><b>'+(c.cells||0)+'</b> pamokų langelių</span>'
        + '<span class="ss-ye-stat"><b>'+(c.links||0)+'</b> mokinių priskyrimų</span>'
        + '<span class="ss-ye-stat"><b>'+(c.email_links||0)+'</b> prisegtų el. paštų</span>'
        + '<span class="ss-ye-stat"><b>'+(c.changes||0)+'</b> pakeitimų</span>'
        + '<span class="ss-ye-stat"><b>'+(c.absences||0)+'</b> pavadavimų</span>'
        + '</div>';

      html += logBlock('Bus ištrinti tvarkaraščiai', 'ss-ye-del', (d.classes||[]), function(n){ return esc(n); });

      if(type === 'year'){
        html += logBlock('Mokiniai bus pakelti į aukštesnę klasę', 'ss-ye-prom', (d.promote||[]), function(it){
          return esc(it.name)+' — <strong>'+esc(it.from)+' → '+esc(it.to)+'</strong>';
        });
        html += logBlock('IV klasė (laida) — paskyros bus IŠTRINTOS', 'ss-ye-grad', (d.graduate||[]), function(it){
          return esc(it.name)+' <small>('+esc(it.email)+')</small> — '+esc(it.level);
        });
        if((d.skipped||[]).length){
          html += logBlock('Praleisti (nenurodyta klasė I–IV)', 'ss-ye-skip', (d.skipped||[]), function(it){
            return esc(it.name)+' <small>('+esc(it.email)+')</small>';
          });
        }
        html += '<p class="ss-ye-empty">Nauji mokslo metai bus nustatyti į: <strong>'+esc($('#ss-ye-year').val())+'</strong></p>';
      } else {
        html += '<p class="ss-ye-empty">Pusmečio pabaiga: klasės nekeliamos, mokinių paskyros netrinamos.</p>';
      }

      $('#ss-ye-preview').html(html);
      $('#ss-ye-preview-card').show();
      $('#ss-ye-result-card').hide();
      $('#ss-ye-confirm-cb').prop('checked', false);
      $('#ss-ye-commit-btn').prop('disabled', true);
      $('html,body').animate({scrollTop: $('#ss-ye-preview-card').offset().top - 40}, 300);
    }).fail(function(){
      $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-visibility');
      alert('Ryšio klaida.');
    });
  });

  $('#ss-ye-confirm-cb').on('change', function(){ $('#ss-ye-commit-btn').prop('disabled', !$(this).is(':checked')); });

  $('#ss-ye-commit-btn').on('click', function(){
    var type = lastType;
    var year = $('#ss-ye-year').val().trim();
    if(type === 'year' && !year){ alert('Įveskite naujus mokslo metus.'); return; }
    if(!confirm('Tikrai užbaigti '+(type==='year'?'mokslo metus':'pusmetį')+'? Šio veiksmo atšaukti nebus galima.')) return;
    var $btn = $(this).prop('disabled', true);
    $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-update');
    $.post(ajaxurl, {action:'ss_rollover_commit', nonce:nonce, type:type, school_year:year, confirm:1}, function(res){
      if(!res || !res.success){
        $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-yes');
        alert((res&&res.data&&res.data.message)||'Klaida.'); return;
      }
      var d = res.data, del = d.deleted || {}, log = d.log || {};
      var html = '<div class="ss-ye-ok"><span class="dashicons dashicons-yes-alt"></span><div>'
        + (d.type==='year'
            ? 'Mokslo metai užbaigti. Nauji mokslo metai: <strong>'+esc(d.school_year)+'</strong>.'
            : 'Pusmetis užbaigtas. Tvarkaraščiai išvalyti.')
        + '</div></div>';

      html += '<div class="ss-ye-stats">'
        + '<span class="ss-ye-stat"><b>'+(del.classes||0)+'</b> tvarkaraščių ištrinta</span>'
        + '<span class="ss-ye-stat"><b>'+(del.cells||0)+'</b> pamokų langelių</span>'
        + '<span class="ss-ye-stat"><b>'+(del.links||0)+'</b> priskyrimų</span>'
        + '</div>';

      if(d.type === 'year'){
        html += logBlock('Pakelti mokiniai', 'ss-ye-prom', (log.promoted||[]), function(it){
          return esc(it.name)+' — <strong>'+esc(it.from)+' → '+esc(it.to)+'</strong>';
        });
        html += logBlock('Ištrintos IV klasės (laidos) paskyros', 'ss-ye-grad', (log.graduated||[]), function(it){
          return esc(it.name)+' <small>('+esc(it.email)+')</small> — '+esc(it.level);
        });
        if((log.skipped||[]).length){
          html += logBlock('Praleista (nenurodyta klasė)', 'ss-ye-skip', (log.skipped||[]), function(it){
            return esc(it.name)+' <small>('+esc(it.email)+')</small>';
          });
        }
      }

      $('#ss-ye-result').html(html);
      $('#ss-ye-import-link').attr('href', d.import_url || $('#ss-ye-import-link').attr('href'));
      $('#ss-ye-result-card').show();
      $('#ss-ye-preview-card').hide();
      $('html,body').animate({scrollTop: $('#ss-ye-result-card').offset().top - 40}, 300);
    }).fail(function(){
      $btn.prop('disabled', false).find('.dashicons').removeClass('dashicons-update').addClass('dashicons-yes');
      alert('Ryšio klaida.');
    });
  });
})(jQuery);
</script>
</div>
