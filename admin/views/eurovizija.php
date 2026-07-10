<?php
/**
 * Admin: „Ąžuolyno Eurovizija" — nustatymai, dalyviai, rezultatai.
 * Kintamieji: $classes (objektai), $candidates (payload masyvas), $total_voters (int).
 */
if (!defined('ABSPATH')) exit;

$ss_page_title    = 'Ąžuolyno Eurovizija';
$ss_page_subtitle = 'Balsavimo nustatymai, dalyviai ir rezultatai. Rezultatus mato tik administratorius.';

$eligible = SS_Eurovizija::eligible();
$is_elig  = function ($key) use ($eligible) { return in_array($key, $eligible, true); };

$mode    = SS_Eurovizija::mode();
$enabled = SS_Eurovizija::is_enabled();
$open    = (bool) get_option(SS_Eurovizija::OPT_OPEN, 0);
$title   = SS_Eurovizija::title();
$desc    = SS_Eurovizija::description();

$dt_local = function ($v) {
    $v = trim((string) $v);
    if ($v === '') return '';
    return substr(str_replace(' ', 'T', $v), 0, 16);
};
$from = $dt_local(get_option(SS_Eurovizija::OPT_OPEN_FROM, ''));
$to   = $dt_local(get_option(SS_Eurovizija::OPT_CLOSE_AT, ''));

$export_url = wp_nonce_url(admin_url('admin-post.php?action=ss_euro_export_csv'), 'ss_euro_export');
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<!-- ════ NUSTATYMAI ════ -->
<div class="ss-card">
  <h2>Balsavimo nustatymai</h2>
  <table class="form-table" role="presentation"><tbody>
    <tr>
      <th scope="row">Būsena</th>
      <td>
        <label style="display:block;margin-bottom:6px;">
          <input type="checkbox" id="euro-enabled" <?= checked($enabled, true, false) ?>>
          Įjungta (mygtukas matomas balsuojantiems meniu)
        </label>
        <label style="display:block;">
          <input type="checkbox" id="euro-open" <?= checked($open, true, false) ?>>
          Atidaryta (priimami balsai)
        </label>
        <p class="description">Mygtukas atsiranda meniu, kai įjungta. Balsuoti galima tik kai atidaryta.</p>
      </td>
    </tr>
    <tr>
      <th scope="row">Balsavimo režimas</th>
      <td>
        <label style="margin-right:18px;"><input type="radio" name="euro-mode" value="single" <?= checked($mode, 'single', false) ?>> 1 balsas iš viso</label>
        <label><input type="radio" name="euro-mode" value="parallel" <?= checked($mode, 'parallel', false) ?>> Po 1 iš kiekvienos grupės (paralelės)</label>
      </td>
    </tr>
    <tr>
      <th scope="row">Pavadinimas</th>
      <td><input type="text" id="euro-title" class="regular-text" value="<?= esc_attr($title) ?>"></td>
    </tr>
    <tr>
      <th scope="row">Aprašymas</th>
      <td><textarea id="euro-desc" class="large-text" rows="2"><?= esc_textarea($desc) ?></textarea></td>
    </tr>
    <tr>
      <th scope="row">Kas gali balsuoti</th>
      <td>
        <label style="display:block;margin-bottom:4px;"><input type="checkbox" class="euro-elig" value="all" <?= $is_elig('all') ? 'checked' : '' ?>> Visi vartotojai</label>
        <label style="display:block;margin-bottom:4px;"><input type="checkbox" class="euro-elig" value="students" <?= $is_elig('students') ? 'checked' : '' ?>> Visi mokiniai</label>
        <label style="display:block;margin-bottom:8px;"><input type="checkbox" class="euro-elig" value="teachers" <?= $is_elig('teachers') ? 'checked' : '' ?>> Mokytojai ir darbuotojai</label>
        <details>
          <summary style="cursor:pointer;color:#2271b1;">Pasirinkti konkrečias klases…</summary>
          <div style="display:flex;flex-wrap:wrap;gap:4px 16px;margin-top:8px;max-height:200px;overflow:auto;">
            <?php foreach ($classes as $cl): ?>
              <label style="display:inline-flex;align-items:center;gap:5px;min-width:90px;">
                <input type="checkbox" class="euro-elig" value="class:<?= (int)$cl->id ?>" <?= $is_elig('class:' . (int)$cl->id) ? 'checked' : '' ?>>
                <?= esc_html($cl->name) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </details>
      </td>
    </tr>
    <tr>
      <th scope="row">Laiko langas (nebūtina)</th>
      <td>
        Nuo: <input type="datetime-local" id="euro-from" value="<?= esc_attr($from) ?>">
        &nbsp; Iki: <input type="datetime-local" id="euro-to" value="<?= esc_attr($to) ?>">
        <p class="description">Jei nustatyta — balsai priimami tik šiame intervale (balsuoti bus galima šiame laiko intervale kai balsavimas įjungtas, jeigu balsavimas išjungtas balsuoti nebus galima).</p>
      </td>
    </tr>
  </tbody></table>
  <p><button class="button button-primary" id="euro-save-settings"><span class="dashicons dashicons-saved" style="vertical-align:middle"></span> Išsaugoti nustatymus</button></p>
</div>

<!-- ════ PRIDĖTI DALYVĮ (rankinis sąrašas) ════ -->
<div class="ss-card">
  <h2>Pridėti dalyvį</h2>
  <p class="description">Dalyvių sąrašas sudaromas rankiniu būdu. Įveskite pavadinimą, grupę/paralelę (laisvas tekstas — pvz. „5 klasės", „Gimnazistai") ir nuotrauką. Toje pačioje grupėje esantys dalyviai bus rodomi kartu, o režime „po 1 iš grupės" iš kiekvienos grupės renkamasi vienas.</p>
  <table class="form-table" role="presentation"><tbody>
    <tr>
      <th scope="row">Pavadinimas</th>
      <td><input type="text" id="euro-new-name" class="regular-text" placeholder="pvz. 5A klasės šokis"></td>
    </tr>
    <tr>
      <th scope="row">Grupė / paralelė</th>
      <td>
        <input type="text" id="euro-new-group" class="regular-text" list="euro-groups" placeholder="pvz. 5 klasės">
        <datalist id="euro-groups"></datalist>
        <p class="description">Palikite tuščią, jei grupių nereikia (režimas „1 balsas iš viso").</p>
      </td>
    </tr>
    <tr>
      <th scope="row">Nuotrauka</th>
      <td>
        <input type="hidden" id="euro-new-photo">
        <span id="euro-new-photo-prev" style="display:none;margin-right:10px;vertical-align:middle"></span>
        <button class="button" id="euro-new-photo-btn">Pasirinkti nuotrauką</button>
        <button class="button-link" id="euro-new-photo-clear" style="display:none;color:#b32d2e;margin-left:8px">Pašalinti</button>
      </td>
    </tr>
  </tbody></table>
  <p><button class="button button-primary" id="euro-add-candidate"><span class="dashicons dashicons-plus-alt2" style="vertical-align:middle"></span> Pridėti dalyvį</button></p>
</div>

<!-- ════ REZULTATAI / DALYVIŲ SĄRAŠAS ════ -->
<div class="ss-card">
  <h2>Dalyviai ir rezultatai</h2>
  <p>
    Iš viso balsavo: <strong id="euro-total-voters"><?= (int)$total_voters ?></strong> žmonių.
    <a class="button" href="<?= esc_url($export_url) ?>" style="margin-left:10px;"><span class="dashicons dashicons-media-spreadsheet" style="vertical-align:middle"></span> Eksportuoti CSV</a>
  </p>
  <div id="euro-cand-wrap"></div>
</div>

</div><!-- /.wrap -->

<script>
var SS_EURO_DATA = <?= wp_json_encode($candidates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
(function($){
  'use strict';
  var A = window.ss_admin || {};
  var EURO_NONCE = '<?= esc_js(wp_create_nonce('ss_euro_manage')) ?>';
  var $notice = $('#ss-notice');

  function notify(msg, ok){
    $notice.removeClass('notice-success notice-error')
           .addClass(ok ? 'notice-success' : 'notice-error')
           .show().find('p').text(msg);
    if (ok) setTimeout(function(){ $notice.fadeOut(); }, 2500);
  }
  function post(action, data, cb){
    data = data || {}; data.action = action; data.nonce = EURO_NONCE;
    $.post(A.ajax_url, data, function(res){
      if (cb) cb(res);
      if (res && !res.success) notify((res.data && res.data.message) || 'Klaida.', false);
    }).fail(function(){ notify('Ryšio klaida.', false); });
  }
  function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  /* ── Grupių datalist + dalyvių sąrašo perpiešimas ── */
  function refreshGroupList(){
    var seen = {}, $dl = $('#euro-groups').empty();
    $.each(SS_EURO_DATA, function(i,c){
      if (c.group_label && !seen[c.group_label]){ seen[c.group_label]=1; $dl.append('<option value="'+esc(c.group_label)+'">'); }
    });
  }
  function renderCandidates(list){
    SS_EURO_DATA = list || [];
    refreshGroupList();
    var $w = $('#euro-cand-wrap').empty();
    if (!SS_EURO_DATA.length){ $w.html('<p>Dalyvių dar nėra. Pridėkite juos viršuje.</p>'); return; }
    var $t = $('<table class="widefat striped"><thead><tr>'+
      '<th style="width:48px"></th><th>Pavadinimas</th><th>Grupė/paralelė</th><th style="width:70px">Balsai</th><th style="width:200px"></th>'+
      '</tr></thead><tbody></tbody></table>');
    var $b = $t.find('tbody');
    $.each(SS_EURO_DATA, function(i, c){
      var media = c.photo_url
        ? '<img src="'+esc(c.photo_url)+'" style="width:40px;height:40px;object-fit:cover">'
        : '<span style="display:inline-flex;width:40px;height:40px;background:#e2e8e2;color:#14452F;align-items:center;justify-content:center;font-weight:700">'+esc((c.name||'?').charAt(0).toUpperCase())+'</span>';
      var $r = $('<tr data-id="'+c.id+'">'+
        '<td>'+media+'</td>'+
        '<td><input type="text" class="euro-name" value="'+esc(c.name)+'" style="width:100%"></td>'+
        '<td><input type="text" class="euro-grp" list="euro-groups" value="'+esc(c.group_label)+'" style="width:130px"></td>'+
        '<td><strong>'+(c.votes|0)+'</strong></td>'+
        '<td>'+
          '<button class="button button-small euro-photo">Nuotrauka</button> '+
          '<button class="button button-small euro-del" title="Pašalinti"><span class="dashicons dashicons-trash" style="vertical-align:middle"></span></button>'+
        '</td>'+
      '</tr>');
      $b.append($r);
    });
    $w.append($t);
  }
  renderCandidates(SS_EURO_DATA);

  /* ── Nustatymų išsaugojimas ── */
  $('#euro-save-settings').on('click', function(){
    var elig = [];
    $('.euro-elig:checked').each(function(){ elig.push($(this).val()); });
    post('ss_euro_save_settings', {
      enabled:     $('#euro-enabled').is(':checked') ? 1 : 0,
      open:        $('#euro-open').is(':checked') ? 1 : 0,
      mode:        $('input[name=euro-mode]:checked').val() || 'single',
      title:       $('#euro-title').val(),
      description: $('#euro-desc').val(),
      eligible:    JSON.stringify(elig),
      open_from:   $('#euro-from').val(),
      close_at:    $('#euro-to').val()
    }, function(res){ if (res.success) notify('Nustatymai išsaugoti.', true); });
  });

  /* ── Naujo dalyvio nuotrauka ── */
  var newPhoto = 0, frameNew = null;
  $('#euro-new-photo-btn').on('click', function(e){
    e.preventDefault();
    if (frameNew) frameNew.off('select');
    frameNew = wp.media({ title:'Pasirinkite nuotrauką', button:{text:'Naudoti'}, multiple:false, library:{type:'image'} });
    frameNew.on('select', function(){
      var att = frameNew.state().get('selection').first().toJSON();
      newPhoto = att.id;
      var u = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
      $('#euro-new-photo').val(newPhoto);
      $('#euro-new-photo-prev').html('<img src="'+esc(u)+'" style="width:40px;height:40px;object-fit:cover;vertical-align:middle">').show();
      $('#euro-new-photo-clear').show();
    });
    frameNew.open();
  });
  $('#euro-new-photo-clear').on('click', function(e){
    e.preventDefault();
    newPhoto = 0; $('#euro-new-photo').val(''); $('#euro-new-photo-prev').hide().empty(); $(this).hide();
  });

  /* ── Pridėti dalyvį ── */
  $('#euro-add-candidate').on('click', function(){
    var name = $('#euro-new-name').val();
    if (!name || !name.trim()){ notify('Įveskite dalyvio pavadinimą.', false); return; }
    post('ss_euro_add_candidate', {
      name: name, group_label: $('#euro-new-group').val(), photo_id: newPhoto
    }, function(res){
      if (res.success){
        renderCandidates(res.data.candidates);
        $('#euro-new-name').val(''); $('#euro-new-group').val('');
        newPhoto = 0; $('#euro-new-photo').val('');
        $('#euro-new-photo-prev').hide().empty(); $('#euro-new-photo-clear').hide();
        notify('Dalyvis pridėtas.', true);
      }
    });
  });

  /* ── Pavadinimo / grupės redagavimas (inline, be perpiešimo) ── */
  $('#euro-cand-wrap').on('change', '.euro-name', function(){
    var id = $(this).closest('tr').data('id');
    post('ss_euro_update_candidate', { id: id, name: $(this).val() }, function(res){
      if (res.success){ SS_EURO_DATA = res.data.candidates; notify('Atnaujinta.', true); }
    });
  });
  $('#euro-cand-wrap').on('change', '.euro-grp', function(){
    var id = $(this).closest('tr').data('id');
    post('ss_euro_update_candidate', { id: id, group_label: $(this).val() }, function(res){
      if (res.success){ SS_EURO_DATA = res.data.candidates; refreshGroupList(); notify('Atnaujinta.', true); }
    });
  });

  /* ── Esamo dalyvio nuotrauka (wp.media) ── */
  var frame = null;
  $('#euro-cand-wrap').on('click', '.euro-photo', function(e){
    e.preventDefault();
    var id = $(this).closest('tr').data('id');
    if (frame) frame.off('select');
    frame = wp.media({ title:'Pasirinkite nuotrauką', button:{text:'Naudoti'}, multiple:false, library:{type:'image'} });
    frame.on('select', function(){
      var att = frame.state().get('selection').first().toJSON();
      post('ss_euro_update_candidate', { id: id, photo_id: att.id }, function(res){
        if (res.success){ renderCandidates(res.data.candidates); notify('Nuotrauka atnaujinta.', true); }
      });
    });
    frame.open();
  });

  /* ── Šalinimas ── */
  $('#euro-cand-wrap').on('click', '.euro-del', function(){
    if (!confirm('Pašalinti šį dalyvį? Jo balsai taip pat bus ištrinti.')) return;
    var id = $(this).closest('tr').data('id');
    post('ss_euro_delete_candidate', { id: id }, function(res){
      if (res.success){ renderCandidates(res.data.candidates); $('#euro-total-voters').text(res.data.total_voters|0); notify('Pašalinta.', true); }
    });
  });

})(jQuery);
</script>
