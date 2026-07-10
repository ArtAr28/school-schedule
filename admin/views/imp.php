<?php if (!defined('ABSPATH')) exit;
$ss_page_title    = 'Individualūs ugdymo planai (IMP)';
$ss_page_subtitle = 'Katalogas, mokinių pateikti planai ir priėmimo nustatymai. Pateikti/patvirtinti planai įtraukiami į .azuolynotvs eksportą (Win programa).';
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<div class="ss-quiz-toolbar">
  <div class="ss-tabs" role="tablist">
    <button class="ss-tab-btn active" data-tab="cat">Katalogas</button>
    <button class="ss-tab-btn" data-tab="subs">Pateikimai</button>
    <button class="ss-tab-btn" data-tab="stats">Statistika</button>
    <button class="ss-tab-btn" data-tab="settings">Nustatymai</button>
  </div>
</div>

<!-- KATALOGAS -->
<div id="ss-imp-tab-cat">
  <div style="display:flex;gap:8px;margin-bottom:14px">
    <button class="button button-primary" id="ss-imp-add">+ Naujas dalykas / modulis</button>
    <button class="button" id="ss-imp-seed">Atstatyti gimnazijos pavyzdį</button>
  </div>
  <div id="ss-imp-catalog">Kraunama…</div>
</div>

<!-- PATEIKIMAI -->
<div id="ss-imp-tab-subs" style="display:none">
  <div class="ss-imp-card ss-imp-export">
    <div>
      <h3><span class="dashicons dashicons-desktop"></span> Eksportas į „Tvarkaraščių kūrėją" (Windows)</h3>
      <p>Atsisiųskite IMP planus kaip <code>.azuolynotvs</code>. Win programoje spauskite <strong>„Importuoti“</strong> ir pasirinkite <strong>„Tik planai (IMP)“</strong> — bus pridėti tik šie planai.</p>
    </div>
    <a class="button button-primary button-hero" href="<?php echo esc_url(SS_IMP::export_url()); ?>">
      <span class="dashicons dashicons-download"></span> Eksportuoti planus
    </a>
  </div>
  <div id="ss-imp-subs-toolbar" style="display:none;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <button class="button ss-btn-danger" id="imp-bulk-del">Šalinti pažymėtus</button>
    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#5B6B5C;margin:0">
      <span class="dashicons dashicons-filter" style="color:var(--sa-green)"></span>Dalykas:
      <select id="imp-subj-filter" class="regular-text" style="min-width:220px;max-width:340px;margin:0">
        <option value="">Visi dalykai</option>
      </select>
    </label>
    <span id="imp-subj-count" style="font-size:12px;color:var(--sa-green-lt)"></span>
    <span style="flex:1"></span>
    <a class="button" id="imp-dl-all" href="#" target="_blank"><span class="dashicons dashicons-media-archive" style="vertical-align:text-bottom"></span> Visi xlsx (ZIP)</a>
  </div>
  <div id="ss-imp-subs">Kraunama…</div>
</div>

<!-- STATISTIKA -->
<div id="ss-imp-tab-stats" style="display:none">
  <div id="ss-imp-stats-toolbar" style="display:none;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
    <span style="color:var(--sa-green-lt);font-size:13px">Pasirinkimų statistika pagal visus pateiktus planus.</span>
    <span style="flex:1"></span>
    <a class="button" id="imp-stats-csv" href="#" target="_blank"><span class="dashicons dashicons-media-spreadsheet" style="vertical-align:text-bottom"></span> Eksportuoti CSV</a>
  </div>
  <div id="ss-imp-stats">Kraunama…</div>
</div>

<!-- NUSTATYMAI -->
<div id="ss-imp-tab-settings" style="display:none">
  <div class="ss-imp-card" style="max-width:680px">
    <h3>Plano priėmimo langas</h3>
    <p style="margin:0 0 14px;color:var(--sa-green-lt);font-size:13px">
      Nurodykite, kada mokiniai gali įkelti / koreguoti planą ir kurios klasės dalyvauja.
      Tinkamiems mokiniams navigacijoje atsiranda mygtukas <strong>„Individualus planas"</strong>.
      Korekcija galima tik pakartotinai įkeliant failą, kol langas atviras.
    </p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <div class="ss-field"><label>Priėmimas atidaromas</label>
        <input type="datetime-local" id="imp-open" class="regular-text"></div>
      <div class="ss-field"><label>Priėmimas uždaromas</label>
        <input type="datetime-local" id="imp-close" class="regular-text"></div>
    </div>
    <p style="font-size:12px;color:var(--sa-green-lt);margin:0 0 6px">Palikus tuščią — be apribojimo (atidarymo/uždarymo).</p>
    <div class="ss-field" style="margin-top:6px">
      <label>Klasės, kurios gali pateikti planą</label>
      <div id="imp-levels" style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px"></div>
    </div>
    <div style="margin-top:16px">
      <button class="button button-primary" id="imp-set-save">Išsaugoti nustatymus</button>
      <span id="imp-set-status" style="margin-left:10px;font-size:13px"></span>
    </div>
  </div>
</div>

<style>
.ss-imp-card{background:var(--sa-white);border:1px solid #E3E8E3;padding:16px 18px;margin-bottom:14px}
.ss-imp-card h3{font-size:14px;color:var(--sa-dark);font-weight:800;margin-top:0}
.ss-imp-meta{display:flex;gap:22px;flex-wrap:wrap;font-size:13px;color:#5B6B5C}
.imp-lvl-lbl{display:inline-flex;align-items:center;gap:7px;font-size:14px;font-weight:600;border:1px solid #E3E8E3;padding:7px 13px;cursor:pointer}
.ss-imp-export{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:linear-gradient(135deg,#f3f9f3,#eef6ef);border-color:#cfe6d0}
.ss-imp-export h3{display:flex;align-items:center;gap:8px;margin:0 0 4px}
.ss-imp-export h3 .dashicons{color:var(--sa-green)}
.ss-imp-export p{margin:0;color:#5B6B5C;font-size:13px;max-width:580px}
.ss-imp-export .dashicons-download{vertical-align:text-bottom}
/* Statistika */
.ss-stat-cards{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.ss-stat-card{background:var(--sa-white);border:1px solid #E3E8E3;padding:12px 16px;min-width:150px}
.ss-stat-num{font-size:26px;font-weight:800;color:var(--sa-dark);line-height:1.1}
.ss-stat-lbl{font-size:12px;color:var(--sa-green-lt);font-weight:700;margin-bottom:5px}
.ss-stat-chips{display:flex;gap:6px;flex-wrap:wrap}
.ss-stat-chip{font-size:12px;background:#f2f7f2;border:1px solid #E3E8E3;padding:2px 8px;color:#5B6B5C}
.ss-stat-h{font-size:14px;color:var(--sa-dark);margin:18px 0 8px;font-weight:800}
.ss-stat-bar{position:relative;height:20px;background:#f0f4f0;border:1px solid #E3E8E3;min-width:130px;overflow:hidden}
.ss-stat-bar span{position:absolute;left:0;top:0;bottom:0;background:linear-gradient(90deg,var(--sa-green),var(--sa-green-lt));opacity:.5}
.ss-stat-bar b{position:relative;z-index:1;padding:0 8px;font-size:12px;line-height:20px;color:var(--sa-dark);font-weight:700}
</style>

<!-- Katalogo modalas -->
<div id="ss-imp-modal" class="ss-modal-overlay" style="display:none">
<div class="ss-modal" style="max-width:560px">
  <div class="ss-modal-head"><h2 id="ss-imp-modal-title">Dalykas</h2><button class="ss-modal-x" data-close><span class="dashicons dashicons-no-alt"></span></button></div>
  <div class="ss-modal-body">
    <input type="hidden" id="imp-id" value="0">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="ss-field">
        <label>Skiltis</label>
        <select id="imp-section" class="regular-text">
          <option value="privalomi">Privalomi dalykai</option>
          <option value="grupe">Privalomai pasirenkami (grupė)</option>
          <option value="pasirenkami">Pasirenkamieji dalykai</option>
          <option value="moduliai">Pasirenkamieji moduliai</option>
        </select>
      </div>
      <div class="ss-field">
        <label>Dalykų grupė (privalomai pasirenkamiems)</label>
        <input type="text" id="imp-grp" class="regular-text" placeholder="Pvz. Menai ir technologijos">
      </div>
    </div>
    <div class="ss-field"><label>Dalykas *</label><input type="text" id="imp-subject" class="regular-text"></div>
    <div class="ss-field"><label>Modulio pavadinimas (tik moduliams)</label><input type="text" id="imp-module" class="regular-text"></div>
    <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin:6px 0 12px;text-transform:none;letter-spacing:0;font-weight:500">
      <input type="checkbox" id="imp-haslevel"> Galimas kurso pasirinkimas (B – bendrasis / A – išplėstinis)
    </label>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px">
      <div class="ss-field"><label>Val. III kl. (B)</label><input type="number" id="imp-h3b" min="0" value="0"></div>
      <div class="ss-field"><label>Val. IV kl. (B)</label><input type="number" id="imp-h4b" min="0" value="0"></div>
      <div class="ss-field"><label>Val. III kl. (A)</label><input type="number" id="imp-h3a" min="0" value="0"></div>
      <div class="ss-field"><label>Val. IV kl. (A)</label><input type="number" id="imp-h4a" min="0" value="0"></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="ss-field"><label>Eiliškumas</label><input type="number" id="imp-sort" value="0"></div>
      <div class="ss-field"><label>Būsena</label>
        <select id="imp-active" class="regular-text"><option value="1">Aktyvus</option><option value="0">Paslėptas</option></select>
      </div>
    </div>
  </div>
  <div class="ss-modal-foot">
    <button class="button" data-close>Atšaukti</button>
    <button class="button button-primary" id="imp-save">Išsaugoti</button>
  </div>
</div>
</div>

<!-- Pateikimo peržiūros modalas -->
<div id="ss-imp-sub-modal" class="ss-modal-overlay" style="display:none">
<div class="ss-modal" style="max-width:620px">
  <div class="ss-modal-head"><h2 id="ss-imp-sub-title">Pateikimas</h2><button class="ss-modal-x" data-close><span class="dashicons dashicons-no-alt"></span></button></div>
  <div class="ss-modal-body" id="ss-imp-sub-body"></div>
  <div class="ss-modal-foot" id="ss-imp-sub-foot"></div>
</div>
</div>

<script>
jQuery(function($){
  var nonce = ss_admin.nonce, SEC = {};
  function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function notice(m,t){ var n=$('#ss-notice'); n.removeClass('notice-success notice-error').addClass('notice-'+(t||'success')).find('p').text(m); n.show(); setTimeout(function(){n.fadeOut();},2500); }

  $('.ss-tab-btn').on('click', function(){
    $('.ss-tab-btn').removeClass('active'); $(this).addClass('active');
    var t=$(this).data('tab');
    $('#ss-imp-tab-cat').toggle(t==='cat');
    $('#ss-imp-tab-subs').toggle(t==='subs');
    $('#ss-imp-tab-stats').toggle(t==='stats');
    $('#ss-imp-tab-settings').toggle(t==='settings');
    if (t==='subs') loadSubs();
    if (t==='stats') loadStats();
    if (t==='settings') loadSettings();
  });

  /* ── Katalogas ── */
  function loadCatalog(){
    $.post(ss_admin.ajax_url, {action:'ss_imp_cat_list', nonce:nonce}, function(res){
      if(!res.success){ $('#ss-imp-catalog').text('Klaida.'); return; }
      SEC = res.data.sections;
      var items = res.data.items, by = {};
      items.forEach(function(it){ (by[it.section]=by[it.section]||[]).push(it); });
      var html='';
      Object.keys(SEC).forEach(function(sec){
        html += '<h2 style="font-size:14px;color:var(--sa-dark);margin:18px 0 8px">'+esc(SEC[sec])+'</h2>';
        var list=by[sec]||[];
        if(!list.length){ html+='<p style="color:var(--sa-green-lt);margin:0 0 8px">—</p>'; return; }
        html += '<table class="ss-table" style="width:100%"><thead><tr>'
              + '<th>Grupė</th><th>Dalykas</th><th>Modulis</th><th>B/A</th><th>III (B/A)</th><th>IV (B/A)</th><th></th></tr></thead><tbody>';
        list.forEach(function(it){
          html += '<tr'+(it.active=='0'?' style="opacity:.5"':'')+'>'
            + '<td>'+esc(it.grp)+'</td><td><strong>'+esc(it.subject)+'</strong></td><td>'+esc(it.module_name)+'</td>'
            + '<td>'+(it.has_level=='1'?'taip':'—')+'</td>'
            + '<td>'+it.h3_b+(it.has_level=='1'?' / '+it.h3_a:'')+'</td>'
            + '<td>'+it.h4_b+(it.has_level=='1'?' / '+it.h4_a:'')+'</td>'
            + '<td style="white-space:nowrap"><button class="button imp-edit" data-id="'+it.id+'">Keisti</button> '
            + '<button class="button ss-btn-danger imp-del" data-id="'+it.id+'">×</button></td></tr>';
        });
        html += '</tbody></table>';
      });
      $('#ss-imp-catalog').html(html);
      window._impItems = items;
    });
  }

  function openModal(it){
    it = it || {id:0,section:'pasirenkami',grp:'',subject:'',module_name:'',has_level:0,h3_b:0,h4_b:0,h3_a:0,h4_a:0,sort_order:0,active:1};
    $('#imp-id').val(it.id); $('#imp-section').val(it.section); $('#imp-grp').val(it.grp);
    $('#imp-subject').val(it.subject); $('#imp-module').val(it.module_name);
    $('#imp-haslevel').prop('checked', it.has_level==1||it.has_level=='1');
    $('#imp-h3b').val(it.h3_b); $('#imp-h4b').val(it.h4_b); $('#imp-h3a').val(it.h3_a); $('#imp-h4a').val(it.h4_a);
    $('#imp-sort').val(it.sort_order); $('#imp-active').val((it.active==0||it.active=='0')?'0':'1');
    $('#ss-imp-modal-title').text(it.id?'Keisti įrašą':'Naujas įrašas');
    $('#ss-imp-modal').show();
  }
  $('#ss-imp-add').on('click', function(){ openModal(null); });
  $(document).on('click','.imp-edit', function(){
    var id=$(this).data('id'); var it=(window._impItems||[]).filter(function(x){return x.id==id;})[0]; openModal(it);
  });
  $(document).on('click','.imp-del', function(){
    if(!confirm('Šalinti įrašą?')) return; var id=$(this).data('id');
    $.post(ss_admin.ajax_url,{action:'ss_imp_cat_delete',nonce:nonce,id:id},function(r){ if(r.success){loadCatalog();notice('Pašalinta.');} });
  });
  $('#imp-save').on('click', function(){
    var d={action:'ss_imp_cat_save',nonce:nonce,
      id:$('#imp-id').val(), section:$('#imp-section').val(), grp:$('#imp-grp').val(),
      subject:$('#imp-subject').val(), module_name:$('#imp-module').val(),
      has_level:$('#imp-haslevel').is(':checked')?1:0,
      h3_b:$('#imp-h3b').val(), h4_b:$('#imp-h4b').val(), h3_a:$('#imp-h3a').val(), h4_a:$('#imp-h4a').val(),
      sort_order:$('#imp-sort').val(), active:$('#imp-active').val()};
    $.post(ss_admin.ajax_url,d,function(r){
      if(r.success){ $('#ss-imp-modal').hide(); loadCatalog(); notice('Išsaugota.'); }
      else notice((r.data&&r.data.message)||'Klaida','error');
    });
  });
  $('#ss-imp-seed').on('click', function(){
    if(!confirm('Įkelti gimnazijos pavyzdinį katalogą ir užpildyti valandas?')) return;
    $.post(ss_admin.ajax_url,{action:'ss_imp_seed',nonce:nonce},function(r){ if(r.success){loadCatalog();notice('Įkelta.');} });
  });

  /* ── Pateikimai ── */
  // Bazinis dalyko pavadinimas — be kurso priesagos „ (A)"/„ (B)", kad tas pats
  // dalykas (nepriklausomai nuo kurso) filtre būtų vienas įrašas.
  function impBase(name){ return String(name==null?'':name).replace(/\s*\((?:A|B)\)\s*$/,'').trim(); }

  function loadSubs(){
    $.post(ss_admin.ajax_url, {action:'ss_imp_sub_list', nonce:nonce}, function(res){
      if(!res.success){ $('#ss-imp-subs').text('Klaida.'); return; }
      var rows=res.data.items;
      window._impSubs = rows;
      $('#imp-dl-all').attr('href', res.data.dl_all_url||'#');
      if(!rows.length){ $('#ss-imp-subs-toolbar').hide(); $('#ss-imp-subs').html('<p style="color:var(--sa-green-lt)">Pateikimų dar nėra.</p>'); return; }
      $('#ss-imp-subs-toolbar').css('display','flex');

      // Filtro sąrašas: visi skirtingi dalykai iš visų pateikimų (abėcėlės tvarka).
      var set={};
      rows.forEach(function(r){ (r.resolved||[]).forEach(function(it){ var b=impBase(it.name); if(b) set[b]=1; }); });
      var subjects=Object.keys(set).sort(function(a,b){ return a.localeCompare(b,'lt'); });
      var cur=$('#imp-subj-filter').val()||'';
      var opt='<option value="">Visi dalykai</option>';
      subjects.forEach(function(s){ opt+='<option value="'+esc(s)+'"'+(s===cur?' selected':'')+'>'+esc(s)+'</option>'; });
      $('#imp-subj-filter').html(opt);

      renderSubs();
    });
  }

  // Atvaizduoja pateikimų lentelę, atfiltruotą pagal pasirinktą dalyką
  // (rodo tik tuos mokinius, kurie pasirinko tą dalyką).
  function renderSubs(){
    var rows=window._impSubs||[];
    var filt=$('#imp-subj-filter').val()||'';
    var list = !filt ? rows : rows.filter(function(r){
      return (r.resolved||[]).some(function(it){ return impBase(it.name)===filt; });
    });
    $('#imp-subj-count').text(filt ? ('Pasirinko: '+list.length) : '');

    if(!list.length){
      $('#ss-imp-subs').html('<p style="color:var(--sa-green-lt)">Nė vienas mokinys nepasirinko šio dalyko.</p>');
      return;
    }

    var html='<table class="ss-table" style="width:100%"><thead><tr>'
      +'<th style="width:28px"><input type="checkbox" id="imp-chk-all"></th>'
      +'<th>Mokinys</th><th>Klasė</th><th>Tel.</th><th>Failas</th><th>Dalykų</th><th>III</th><th>IV</th><th>Atnaujinta</th><th></th></tr></thead><tbody>';
    list.forEach(function(r){
      var file = r.has_file ? '<a href="'+esc(r.dl_url)+'" target="_blank">⬇ xlsx</a>' : '<span style="color:#aaa">—</span>';
      // Filtruojant — po vardu parodom konkretų pasirinkimą (su kursu A/B).
      var picked='';
      if(filt){
        var match=(r.resolved||[]).filter(function(it){ return impBase(it.name)===filt; })
                                  .map(function(it){ return it.name; });   // it.name jau su „ (A)"/„ (B)"
        if(match.length) picked='<br><span style="font-size:11px;color:var(--sa-green);font-weight:600">'+esc(match.join(', '))+'</span>';
      }
      html+='<tr>'
        +'<td><input type="checkbox" class="imp-chk" value="'+r.id+'"></td>'
        +'<td><strong>'+esc(r.display_name||r.user_email||('#'+r.user_id))+'</strong>'+picked+'<br><span style="font-size:11px;color:var(--sa-green-lt)">'+esc(r.user_email||'')+'</span></td>'
        +'<td>'+esc(r.class_label||'—')+'</td>'
        +'<td style="font-size:12px">'+esc(r.phone||'—')+'</td>'
        +'<td>'+file+'</td>'
        +'<td>'+r.total_subjects+'</td><td>'+r.total_h3+'</td><td>'+r.total_h4+'</td>'
        +'<td style="font-size:12px">'+esc((r.updated_at||'').slice(0,16))+'</td>'
        +'<td style="white-space:nowrap"><button class="button imp-view" data-id="'+r.id+'">Peržiūrėti</button> '
        +'<button class="button ss-btn-danger imp-sub-del" data-id="'+r.id+'">×</button></td></tr>';
    });
    html+='</tbody></table>';
    $('#ss-imp-subs').html(html);
  }
  $(document).on('change','#imp-subj-filter', renderSubs);
  $(document).on('change','#imp-chk-all', function(){ $('.imp-chk').prop('checked', $(this).is(':checked')); });

  $(document).on('click','.imp-sub-del', function(){
    if(!confirm('Pašalinti šį planą (kartu su įkeltu failu)?')) return;
    var id=$(this).data('id');
    $.post(ss_admin.ajax_url,{action:'ss_imp_sub_delete',nonce:nonce,id:id},function(r){ if(r.success){$('#ss-imp-sub-modal').hide();loadSubs();notice('Pašalinta.');} else notice((r.data&&r.data.message)||'Klaida','error'); });
  });

  $('#imp-bulk-del').on('click', function(){
    var ids=$('.imp-chk:checked').map(function(){return $(this).val();}).get();
    if(!ids.length){ notice('Pažymėkite bent vieną planą','error'); return; }
    if(!confirm('Pašalinti '+ids.length+' plan(ų)? Failai taip pat bus ištrinti.')) return;
    $.post(ss_admin.ajax_url,{action:'ss_imp_sub_bulk',nonce:nonce,bulk:'delete',ids:ids},function(r){
      if(r.success){ loadSubs(); notice('Pašalinta: '+r.data.count); }
      else notice((r.data&&r.data.message)||'Klaida','error');
    });
  });

  $(document).on('click','.imp-view', function(){
    var id=$(this).data('id');
    $.post(ss_admin.ajax_url,{action:'ss_imp_sub_get',nonce:nonce,id:id},function(res){
      if(!res.success){ notice('Klaida','error'); return; }
      var s=res.data;
      $('#ss-imp-sub-title').text((s.display_name||s.user_email||'Pateikimas'));
      var html='<p><span style="font-size:12px;color:var(--sa-green-lt)">'
        +'Klasė: '+esc(s.class_label||'—')+' · Tel.: '+esc(s.phone||'—')+'</span>'
        +(s.dl_url?' &nbsp;<a class="button button-small" href="'+esc(s.dl_url)+'" target="_blank">⬇ xlsx</a>':'')+'</p>';
      if(!s.resolved.length){ html+='<p style="color:var(--sa-green-lt)">Pasirinkimų nėra.</p>'; }
      else {
        html+='<table class="ss-table" style="width:100%"><thead><tr><th>Dalykas</th><th>Grupė</th><th>Kursas</th><th>III</th><th>IV</th></tr></thead><tbody>';
        s.resolved.forEach(function(it){
          html+='<tr><td>'+esc(it.name)+'</td><td>'+esc(it.group)+'</td><td>'+esc(it.level||'—')+'</td><td>'+it.h3+'</td><td>'+it.h4+'</td></tr>';
        });
        html+='</tbody></table>';
      }
      $('#ss-imp-sub-body').html(html);
      $('#ss-imp-sub-foot').html(
        '<button class="button" data-close>Uždaryti</button>'
        +'<button class="button ss-btn-danger imp-sub-del" data-id="'+s.id+'">Šalinti planą</button>');
      $('#ss-imp-sub-modal').show();
    });
  });

  /* ── Statistika ── */
  function statChips(obj, map){
    var s='', keys=Object.keys(obj||{});
    if(!keys.length) return '—';
    keys.forEach(function(k){ s+='<span class="ss-stat-chip">'+esc((map&&map[k])||k)+': <strong>'+obj[k]+'</strong></span>'; });
    return s;
  }
  function loadStats(){
    $.post(ss_admin.ajax_url, {action:'ss_imp_stats', nonce:nonce}, function(res){
      if(!res.success){ $('#ss-imp-stats').text('Klaida.'); return; }
      var d=res.data, SECN=d.sections||{}, items=d.items||[], tot=d.totals||{};
      $('#imp-stats-csv').attr('href', d.csv_url||'#');
      if(!tot.submissions){ $('#ss-imp-stats-toolbar').hide(); $('#ss-imp-stats').html('<p style="color:var(--sa-green-lt)">Pateikimų dar nėra.</p>'); return; }
      $('#ss-imp-stats-toolbar').css('display','flex');

      var statusMap={submitted:'Pateikta',approved:'Patvirtinta',rejected:'Atmesta'};
      var html='<div class="ss-stat-cards">'
        +'<div class="ss-stat-card"><div class="ss-stat-num">'+tot.submissions+'</div><div class="ss-stat-lbl">Pateiktų planų</div></div>'
        +'<div class="ss-stat-card"><div class="ss-stat-lbl">Pagal klasę</div><div class="ss-stat-chips">'+statChips(tot.by_class)+'</div></div>'
        +'<div class="ss-stat-card"><div class="ss-stat-lbl">Pagal būseną</div><div class="ss-stat-chips">'+statChips(tot.by_status, statusMap)+'</div></div>'
        +'</div>';

      var by={}, maxc=1;
      items.forEach(function(it){ (by[it.section]=by[it.section]||[]).push(it); if(it.count>maxc) maxc=it.count; });
      ['privalomi','grupe','pasirenkami','moduliai','kita'].forEach(function(sec){
        var list=by[sec]; if(!list||!list.length) return;
        var label = sec==='kita' ? 'Kita (nerasta kataloge)' : (SECN[sec]||sec);
        html+='<h2 class="ss-stat-h">'+esc(label)+'</h2>';
        html+='<table class="ss-table" style="width:100%"><thead><tr>'
          +'<th>Grupė</th><th>Dalykas / modulis</th><th style="width:170px">Pasirinko</th>'
          +'<th>B / A</th><th>III / IV</th><th>Viso val.</th></tr></thead><tbody>';
        list.forEach(function(it){
          var pct=Math.round(it.count/maxc*100);
          var ba=(it.count_b||it.count_a)?(it.count_b+' / '+it.count_a):'—';
          html+='<tr><td>'+esc(it.group||'')+'</td><td><strong>'+esc(it.name)+'</strong></td>'
            +'<td><div class="ss-stat-bar"><span style="width:'+pct+'%"></span><b>'+it.count+'</b></div></td>'
            +'<td>'+ba+'</td><td>'+it.count_iii+' / '+it.count_iv+'</td><td>'+it.hours+'</td></tr>';
        });
        html+='</tbody></table>';
      });
      $('#ss-imp-stats').html(html);
    });
  }

  /* ── Nustatymai ── */
  function toLocal(v){ return v ? v.replace(' ','T').slice(0,16) : ''; }
  function loadSettings(){
    $.post(ss_admin.ajax_url, {action:'ss_imp_settings_get', nonce:nonce}, function(res){
      if(!res.success){ notice('Klaida','error'); return; }
      var s=res.data.settings, levels=res.data.levels;
      $('#imp-open').val(toLocal(s.open_at));
      $('#imp-close').val(toLocal(s.close_at));
      var h='';
      levels.forEach(function(l){
        var on = s.levels.indexOf(l)>=0;
        h+='<label class="imp-lvl-lbl"><input type="checkbox" class="imp-lvl" value="'+l+'"'+(on?' checked':'')+'> '+l+' kl.</label>';
      });
      $('#imp-levels').html(h);
    });
  }
  $('#imp-set-save').on('click', function(){
    var levels=$('.imp-lvl:checked').map(function(){return $(this).val();}).get();
    var d={action:'ss_imp_settings_save',nonce:nonce,
      open_at:$('#imp-open').val(), close_at:$('#imp-close').val()};
    var btn=$(this); btn.prop('disabled',true);
    $.ajax({url:ss_admin.ajax_url,method:'POST',traditional:true,
      data:$.extend(d,{levels:levels})}).done(function(r){
      btn.prop('disabled',false);
      if(r.success){ $('#imp-set-status').css('color','var(--sa-forest)').text('✓ Išsaugota'); notice('Nustatymai išsaugoti.'); }
      else { $('#imp-set-status').css('color','#9F2226').text((r.data&&r.data.message)||'Klaida'); }
    }).fail(function(){ btn.prop('disabled',false); notice('Ryšio klaida','error'); });
  });

  /* modal close */
  $(document).on('click','[data-close]', function(){ $(this).closest('.ss-modal-overlay').hide(); });
  $('.ss-modal-overlay').on('click', function(e){ if(e.target===this) $(this).hide(); });

  loadCatalog();
});
</script>
</div>
