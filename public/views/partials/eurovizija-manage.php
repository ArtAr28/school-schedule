<?php
/**
 * „Ąžuolyno Eurovizija" — priekinis valdymo skydelis.
 * Rodomas tik turintiems teisę valdyti (SS_Eurovizija::user_can_manage()).
 * Įtraukiamas iš public/views/eurovizija.php; tikisi $only_manage kintamojo.
 */
if (!defined('ABSPATH')) exit;
if (!SS_Eurovizija::user_can_manage(get_current_user_id())) return;

$em_eligible = SS_Eurovizija::eligible();
$em_is_elig  = function ($k) use ($em_eligible) { return in_array($k, $em_eligible, true); };
$em_enabled  = SS_Eurovizija::is_enabled();
$em_open     = (bool) get_option(SS_Eurovizija::OPT_OPEN, 0);
$em_mode     = SS_Eurovizija::mode();
$em_title    = SS_Eurovizija::title();
$em_desc     = SS_Eurovizija::description();
$em_dt = function ($v) { $v = trim((string) $v); return $v === '' ? '' : substr(str_replace(' ', 'T', $v), 0, 16); };
$em_from   = $em_dt(get_option(SS_Eurovizija::OPT_OPEN_FROM, ''));
$em_to     = $em_dt(get_option(SS_Eurovizija::OPT_CLOSE_AT, ''));
$em_classes = SS_Database::get_classes();
$em_cands  = SS_Eurovizija::candidates_payload();
$em_total  = SS_Eurovizija::total_voters();
$em_nonce  = wp_create_nonce('ss_euro_manage');
$em_export = wp_nonce_url(admin_url('admin-post.php?action=ss_euro_export_csv'), 'ss_euro_export');
$em_logo_id  = (int) get_option(SS_Eurovizija::OPT_LOGO, 0);
$em_logo_url = $em_logo_id ? wp_get_attachment_image_url($em_logo_id, 'thumbnail') : '';
$em_hero_id  = (int) get_option(SS_Eurovizija::OPT_HERO, 0);
$em_hero_url = $em_hero_id ? wp_get_attachment_image_url($em_hero_id, 'medium') : '';
$only_manage = !empty($only_manage);
?>
<div id="ss-euro-manage" class="ss-euro-pane ss-em" style="display:<?= $only_manage ? 'block' : 'none' ?>">

  <section class="ss-em-card">
    <h2 class="ss-em-h">Nustatymai</h2>
    <div class="ss-em-field">
      <label class="ss-em-check"><input type="checkbox" id="em-enabled" <?= checked($em_enabled, true, false) ?>> Įjungta (matomas meniu mygtukas)</label>
      <label class="ss-em-check"><input type="checkbox" id="em-open" <?= checked($em_open, true, false) ?>> Atidaryta (priimami balsai)</label>
    </div>
    <div class="ss-em-field">
      <span class="ss-em-label">Balsavimo režimas</span>
      <label class="ss-em-radio"><input type="radio" name="em-mode" value="single" <?= checked($em_mode, 'single', false) ?>> 1 balsas iš viso</label>
      <label class="ss-em-radio"><input type="radio" name="em-mode" value="parallel" <?= checked($em_mode, 'parallel', false) ?>> Po 1 iš kiekvienos grupės</label>
    </div>
    <div class="ss-em-field"><span class="ss-em-label">Pavadinimas</span><input type="text" id="em-title" class="ss-em-input" value="<?= esc_attr($em_title) ?>"></div>
    <div class="ss-em-field"><span class="ss-em-label">Aprašymas</span><textarea id="em-desc" class="ss-em-input" rows="2"><?= esc_textarea($em_desc) ?></textarea></div>
    <div class="ss-em-field">
      <span class="ss-em-label">Logotipas (apvalus meniu mygtukui)</span>
      <span id="em-logo-prev" class="ss-em-prev ss-em-prev-round"<?= $em_logo_url ? '' : ' style="display:none"' ?>><?php if ($em_logo_url): ?><span class="ss-em-thumb" style="background-image:url('<?= esc_url($em_logo_url) ?>')"></span><?php endif; ?></span>
      <button type="button" id="em-logo-btn" class="ss-btn ss-btn-sec ss-btn-sm">Įkelti logotipą</button>
      <button type="button" id="em-logo-clear" class="ss-btn ss-btn-sm ss-em-clearbtn"<?= $em_logo_url ? '' : ' style="display:none"' ?>>Pašalinti</button>
      <input type="hidden" id="em-logo" value="<?= (int)$em_logo_id ?>">
    </div>
    <div class="ss-em-field">
      <span class="ss-em-label">Fono paveikslėlis (po pavadinimu balsavimo puslapyje)</span>
      <span id="em-hero-prev" class="ss-em-prev ss-em-prev-wide"<?= $em_hero_url ? '' : ' style="display:none"' ?>><?php if ($em_hero_url): ?><span class="ss-em-thumb ss-em-thumb-wide" style="background-image:url('<?= esc_url($em_hero_url) ?>')"></span><?php endif; ?></span>
      <button type="button" id="em-hero-btn" class="ss-btn ss-btn-sec ss-btn-sm">Įkelti foną</button>
      <button type="button" id="em-hero-clear" class="ss-btn ss-btn-sm ss-em-clearbtn"<?= $em_hero_url ? '' : ' style="display:none"' ?>>Pašalinti</button>
      <input type="hidden" id="em-hero" value="<?= (int)$em_hero_id ?>">
    </div>
    <div class="ss-em-field">
      <span class="ss-em-label">Kas gali balsuoti</span>
      <label class="ss-em-check"><input type="checkbox" class="em-elig" value="all" <?= $em_is_elig('all') ? 'checked' : '' ?>> Visi</label>
      <label class="ss-em-check"><input type="checkbox" class="em-elig" value="students" <?= $em_is_elig('students') ? 'checked' : '' ?>> Mokiniai</label>
      <label class="ss-em-check"><input type="checkbox" class="em-elig" value="teachers" <?= $em_is_elig('teachers') ? 'checked' : '' ?>> Mokytojai/darbuotojai</label>
      <details class="ss-em-details">
        <summary>Konkrečios klasės…</summary>
        <div class="ss-em-classes">
          <?php foreach ($em_classes as $cl): ?>
            <label class="ss-em-check"><input type="checkbox" class="em-elig" value="class:<?= (int)$cl->id ?>" <?= $em_is_elig('class:' . (int)$cl->id) ? 'checked' : '' ?>> <?= esc_html($cl->name) ?></label>
          <?php endforeach; ?>
        </div>
      </details>
    </div>
    <div class="ss-em-field ss-em-dates">
      <span class="ss-em-label">Laiko langas (nebūtina)</span>
      <span>Nuo <input type="datetime-local" id="em-from" value="<?= esc_attr($em_from) ?>"></span>
      <span>Iki <input type="datetime-local" id="em-to" value="<?= esc_attr($em_to) ?>"></span>
    </div>
    <div class="ss-em-actions">
      <button type="button" id="em-save" class="ss-btn ss-btn-pri">Išsaugoti nustatymus</button>
      <span id="em-msg" class="ss-em-msg" style="display:none"></span>
    </div>
  </section>

  <section class="ss-em-card">
    <h2 class="ss-em-h">Pridėti dalyvį</h2>
    <div class="ss-em-add">
      <input type="text" id="em-new-name" class="ss-em-input" placeholder="Pavadinimas">
      <input type="text" id="em-new-group" class="ss-em-input" list="em-groups" placeholder="Grupė / paralelė">
      <datalist id="em-groups"></datalist>
      <span id="em-new-photo-prev" class="ss-em-prev" style="display:none"></span>
      <button type="button" id="em-new-photo-btn" class="ss-btn ss-btn-sec">Nuotrauka</button>
      <button type="button" id="em-add" class="ss-btn ss-btn-pri">Pridėti</button>
    </div>
    <input type="hidden" id="em-new-photo">
  </section>

  <section class="ss-em-card">
    <h2 class="ss-em-h">Dalyviai ir rezultatai</h2>
    <p class="ss-em-total">Iš viso balsavo: <strong id="em-total"><?= (int)$em_total ?></strong> ·
      <a href="<?= esc_url($em_export) ?>" class="ss-em-link">Eksportuoti CSV</a></p>
    <div id="em-list"></div>
  </section>

  <input type="file" id="em-file" accept="image/*" style="display:none">
</div>

<script>
var SS_EURO_M = <?= wp_json_encode($em_cands, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
(function($){
  'use strict';
  var NONCE = '<?= esc_js($em_nonce) ?>';
  // ss_pub lokalizuojamas footeryje (po šio inline skripto), todėl URL imame
  // kvietimo metu, o ne inicializacijos — kitaip POST eitų į patį puslapį.
  function ajaxUrl(){ return (window.ss_pub && ss_pub.ajax_url) || (window.ajaxurl || ''); }

  function esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function flash($el, msg, ok){
    $el.text(msg).css('color', ok ? 'var(--g)' : 'var(--r)').show();
    if (ok) setTimeout(function(){ $el.fadeOut(); }, 2200);
  }
  function post(action, data, cb){
    data = data || {}; data.action = action; data.nonce = NONCE;
    $.post(ajaxUrl(), data, function(res){
      if (cb) cb(res);
      if (res && !res.success) flash($('#em-msg'), (res.data && res.data.message) || 'Klaida.', false);
    }).fail(function(){ flash($('#em-msg'), 'Ryšio klaida.', false); });
  }

  /* ── Skirtukai: Balsuoti / Valdymas / Statistika ── */
  function euroTab(t){
    var $btn = $('.ss-euro-tab[data-euro-tab="' + t + '"]');
    if (!$btn.length) return false;
    $('.ss-euro-tab').removeClass('active'); $btn.addClass('active');
    $('#ss-euro-vote,#ss-euro-manage,#ss-euro-stats').hide();
    $('#ss-euro-' + t).show();
    return true;
  }
  $('.ss-euro-tab').on('click', function(){ euroTab($(this).data('euro-tab')); });
  if (location.hash === '#euro-stats') euroTab('stats');

  /* ── Statistika: atnaujinti / obnulinti balsavimą ── */
  $(document).on('click', '#euro-stats-refresh', function(){ location.hash = '#euro-stats'; location.reload(); });
  $(document).on('click', '#euro-reset', function(){
    if (!confirm('Ištrinti VISUS balsus? Šio veiksmo atšaukti negalima.')) return;
    if (!confirm('Ar tikrai? Visi balsai bus negrįžtamai ištrinti.')) return;
    post('ss_euro_reset', {}, function(res){
      if (res.success){ location.hash = '#euro-stats'; location.reload(); }
    });
  });

  /* ── Grupių datalist + dalyvių sąrašas ── */
  function refreshGroups(){
    var seen = {}, $dl = $('#em-groups').empty();
    $.each(SS_EURO_M, function(i,c){
      if (c.group_label && !seen[c.group_label]){ seen[c.group_label]=1; $dl.append('<option value="'+esc(c.group_label)+'">'); }
    });
  }
  function renderList(list){
    SS_EURO_M = list || [];
    refreshGroups();
    var $w = $('#em-list').empty();
    if (!SS_EURO_M.length){ $w.html('<p class="ss-em-note">Dalyvių dar nėra.</p>'); return; }
    $.each(SS_EURO_M, function(i, c){
      var thumb = c.photo_url
        ? '<span class="ss-em-thumb" style="background-image:url(\''+esc(c.photo_url)+'\')"></span>'
        : '<span class="ss-em-thumb ss-em-thumb-empty">'+esc((c.name||'?').charAt(0).toUpperCase())+'</span>';
      $('<div class="ss-em-item" data-id="'+c.id+'">'+
          thumb+
          '<input type="text" class="ss-em-input em-name" value="'+esc(c.name)+'">'+
          '<input type="text" class="ss-em-input em-grp" list="em-groups" value="'+esc(c.group_label)+'" placeholder="grupė">'+
          '<span class="ss-em-votes" title="Balsai">'+(c.votes|0)+'</span>'+
          '<button type="button" class="ss-btn ss-btn-sec ss-btn-sm em-photo">Nuotrauka</button>'+
          '<button type="button" class="ss-btn ss-btn-sm em-del">Šalinti</button>'+
        '</div>').appendTo($w);
    });
  }
  renderList(SS_EURO_M);

  /* ── Nustatymų išsaugojimas ── */
  $('#em-save').on('click', function(){
    var elig = [];
    $('.em-elig:checked').each(function(){ elig.push($(this).val()); });
    post('ss_euro_save_settings', {
      enabled:     $('#em-enabled').is(':checked') ? 1 : 0,
      open:        $('#em-open').is(':checked') ? 1 : 0,
      mode:        $('input[name=em-mode]:checked').val() || 'single',
      title:       $('#em-title').val(),
      description: $('#em-desc').val(),
      eligible:    JSON.stringify(elig),
      open_from:   $('#em-from').val(),
      close_at:    $('#em-to').val(),
      logo_id:     $('#em-logo').val() || 0,
      hero_id:     $('#em-hero').val() || 0
    }, function(res){ if (res.success) flash($('#em-msg'), 'Nustatymai išsaugoti.', true); });
  });

  /* ── Nuotraukų įkėlimas (bendras failo laukas) ── */
  var uploadTarget = null, newPhoto = 0;
  $('#em-new-photo-btn').on('click', function(){ uploadTarget = 'new'; $('#em-file').val('').click(); });
  $('#em-logo-btn').on('click', function(){ uploadTarget = 'logo'; $('#em-file').val('').click(); });
  $('#em-logo-clear').on('click', function(){ $('#em-logo').val(''); $('#em-logo-prev').hide().empty(); $(this).hide(); });
  $('#em-hero-btn').on('click', function(){ uploadTarget = 'hero'; $('#em-file').val('').click(); });
  $('#em-hero-clear').on('click', function(){ $('#em-hero').val(''); $('#em-hero-prev').hide().empty(); $(this).hide(); });
  $('#em-list').on('click', '.em-photo', function(){ uploadTarget = $(this).closest('.ss-em-item').data('id'); $('#em-file').val('').click(); });
  $('#em-file').on('change', function(){
    var f = this.files[0]; if (!f) return;
    if (f.size > 5*1024*1024){ flash($('#em-msg'), 'Failas per didelis (maks. 5 MB).', false); return; }
    var fd = new FormData();
    fd.append('action', 'ss_euro_upload_photo'); fd.append('nonce', NONCE); fd.append('photo', f);
    $.ajax({ url: ajaxUrl(), method:'POST', data: fd, processData:false, contentType:false, dataType:'json',
      success: function(res){
        if (!res || !res.success){ flash($('#em-msg'), (res&&res.data&&res.data.message)||'Įkelti nepavyko.', false); return; }
        if (uploadTarget === 'new'){
          newPhoto = res.data.photo_id;
          $('#em-new-photo').val(newPhoto);
          $('#em-new-photo-prev').html('<span class="ss-em-thumb" style="background-image:url(\''+esc(res.data.url)+'\')"></span>').show();
        } else if (uploadTarget === 'logo'){
          $('#em-logo').val(res.data.photo_id);
          $('#em-logo-prev').html('<span class="ss-em-thumb" style="background-image:url(\''+esc(res.data.url)+'\')"></span>').show();
          $('#em-logo-clear').show();
          flash($('#em-msg'), 'Logotipas įkeltas. Nepamirškite „Išsaugoti nustatymus".', true);
        } else if (uploadTarget === 'hero'){
          $('#em-hero').val(res.data.photo_id);
          $('#em-hero-prev').html('<span class="ss-em-thumb ss-em-thumb-wide" style="background-image:url(\''+esc(res.data.url)+'\')"></span>').show();
          $('#em-hero-clear').show();
          flash($('#em-msg'), 'Fonas įkeltas. Nepamirškite „Išsaugoti nustatymus".', true);
        } else {
          post('ss_euro_update_candidate', { id: uploadTarget, photo_id: res.data.photo_id }, function(r){
            if (r.success){ renderList(r.data.candidates); flash($('#em-msg'), 'Nuotrauka atnaujinta.', true); }
          });
        }
      },
      error: function(){ flash($('#em-msg'), 'Įkelti nepavyko.', false); }
    });
  });

  /* ── Pridėti dalyvį ── */
  $('#em-add').on('click', function(){
    var name = $('#em-new-name').val();
    if (!name || !name.trim()){ flash($('#em-msg'), 'Įveskite pavadinimą.', false); return; }
    post('ss_euro_add_candidate', { name: name, group_label: $('#em-new-group').val(), photo_id: newPhoto }, function(res){
      if (res.success){
        renderList(res.data.candidates);
        $('#em-new-name').val(''); $('#em-new-group').val('');
        newPhoto = 0; $('#em-new-photo').val(''); $('#em-new-photo-prev').hide().empty();
        flash($('#em-msg'), 'Dalyvis pridėtas.', true);
      }
    });
  });

  /* ── Redagavimas (be perpiešimo, kad neprarastume fokuso) ── */
  $('#em-list').on('change', '.em-name', function(){
    post('ss_euro_update_candidate', { id: $(this).closest('.ss-em-item').data('id'), name: $(this).val() }, function(r){
      if (r.success){ SS_EURO_M = r.data.candidates; }
    });
  });
  $('#em-list').on('change', '.em-grp', function(){
    post('ss_euro_update_candidate', { id: $(this).closest('.ss-em-item').data('id'), group_label: $(this).val() }, function(r){
      if (r.success){ SS_EURO_M = r.data.candidates; refreshGroups(); }
    });
  });

  /* ── Šalinimas ── */
  $('#em-list').on('click', '.em-del', function(){
    if (!confirm('Pašalinti šį dalyvį? Jo balsai taip pat bus ištrinti.')) return;
    post('ss_euro_delete_candidate', { id: $(this).closest('.ss-em-item').data('id') }, function(res){
      if (res.success){ renderList(res.data.candidates); $('#em-total').text(res.data.total_voters|0); flash($('#em-msg'), 'Pašalinta.', true); }
    });
  });

})(jQuery);
</script>
