<?php if (!defined('ABSPATH')) exit;
$ss_page_title    = 'Apklausos / Viktorinos';
$ss_page_subtitle = 'Kurkite apklausas ir viktorinas — jos rodomos mokinių ir mokytojų skydeliuose.';
$ss_page_actions  = '<button class="button button-primary" id="ss-survey-new-btn"><span class="dashicons dashicons-plus-alt2"></span> Nauja</button>';

// Būsenų etiketės
$status_label = array('draft'=>'Juodraštis','active'=>'Aktyvi','closed'=>'Uždaryta');
$status_class = array('draft'=>'ss-quiz-status-draft','active'=>'ss-quiz-status-active','closed'=>'ss-quiz-status-closed');

// Klasių vardai (auditorijos etiketėms)
$ss_class_names = array();
foreach ($classes as $c) $ss_class_names[(int)$c->id] = $c->name;

// Auditorijos santrauka
$ss_aud_label = function($s) use ($ss_class_names) {
    $aud = json_decode($s->audience, true);
    if (!is_array($aud)) $aud = array($s->audience ?: 'all');
    if (in_array('all', $aud, true)) return 'Visi vartotojai';
    $parts = array();
    foreach ($aud as $a) {
        if ($a === 'students')      $parts[] = 'Mokiniai';
        elseif ($a === 'teachers')  $parts[] = 'Mokytojai';
        elseif (strpos($a, 'class:') === 0) {
            $cid = (int)substr($a, 6);
            $parts[] = $ss_class_names[$cid] ?? ('Klasė #' . $cid);
        }
    }
    return $parts ? implode(', ', $parts) : 'Visi vartotojai';
};

$ss_now = current_time('mysql');
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<div class="ss-quiz-toolbar">
  <div class="ss-tabs" role="tablist">
    <button class="ss-tab-btn active" data-filter="all">Visos</button>
    <button class="ss-tab-btn" data-filter="survey">Apklausos</button>
    <button class="ss-tab-btn" data-filter="quiz">Viktorinos</button>
  </div>
  <div class="ss-quiz-search">
    <span class="dashicons dashicons-search"></span>
    <input type="search" id="ss-sv-search" placeholder="Ieškoti pagal pavadinimą…" autocomplete="off">
  </div>
</div>

<?php if (empty($surveys)): ?>
  <div class="ss-quiz-empty">
    <span class="dashicons dashicons-clipboard"></span>
    <h3>Dar nėra sukurtų apklausų ar viktorinų</h3>
    <p>Spauskite „Nauja“ ir sukurkite pirmąją.</p>
  </div>
<?php else: ?>
  <div class="ss-quiz-grid">
    <?php foreach ($surveys as $s):
      $is_quiz = ($s->type === 'quiz');
      global $wpdb;
      $resp_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ss_survey_responses WHERE survey_id=%d", $s->id));
      $q_data  = json_decode($s->questions, true) ?: array();
      $q_count = count($q_data);
      $scheduled = ($s->status === 'active' && !empty($s->open_from) && $s->open_from > $ss_now);
      if ($scheduled)      { $st_cls = 'ss-quiz-status-scheduled'; $st_lbl = 'Suplanuota'; }
      else                 { $st_cls = $status_class[$s->status] ?? 'ss-quiz-status-draft'; $st_lbl = $status_label[$s->status] ?? $s->status; }
    ?>
    <div class="ss-quiz-card<?= $is_quiz?' is-quiz':'' ?>" data-type="<?= esc_attr($s->type) ?>" data-title="<?= esc_attr($s->title ?: '') ?>">
      <div class="ss-quiz-card-head">
        <div class="ss-quiz-card-headl">
          <span class="ss-quiz-card-type<?= $is_quiz?' is-quiz':'' ?>"><?= $is_quiz?'Viktorina':'Apklausa' ?></span>
          <h3 class="ss-quiz-card-title"><?= esc_html($s->title ?: '(be pavadinimo)') ?></h3>
        </div>
        <span class="ss-quiz-card-status <?= $st_cls ?>"><?= esc_html($st_lbl) ?></span>
      </div>
      <div class="ss-quiz-card-meta">
        <span title="Klausimai"><span class="dashicons dashicons-editor-ul"></span> <?= (int)$q_count ?></span>
        <span title="Atsakymai"><span class="dashicons dashicons-groups"></span> <?= $resp_count ?></span>
        <span title="Auditorija"><span class="dashicons dashicons-visibility"></span> <?= esc_html($ss_aud_label($s)) ?></span>
        <?php if ($s->close_at): ?>
          <span title="Uždaroma"><span class="dashicons dashicons-clock"></span> <?= esc_html(mysql2date('Y-m-d H:i', $s->close_at)) ?></span>
        <?php endif; ?>
      </div>
      <div class="ss-quiz-card-actions">
        <button class="button ss-survey-edit" data-id="<?= esc_attr($s->id) ?>"><span class="dashicons dashicons-edit"></span> Redaguoti</button>
        <button class="button ss-survey-results" data-id="<?= esc_attr($s->id) ?>"><span class="dashicons dashicons-chart-bar"></span> Rezultatai</button>
        <?php if ($s->status !== 'active'): ?>
          <button class="button button-primary ss-survey-toggle" data-id="<?= esc_attr($s->id) ?>" data-status="active">Įjungti</button>
        <?php else: ?>
          <button class="button ss-survey-toggle" data-id="<?= esc_attr($s->id) ?>" data-status="closed">Uždaryti</button>
        <?php endif; ?>
        <button class="button ss-btn-icon ss-btn-danger ss-survey-delete" data-id="<?= esc_attr($s->id) ?>" title="Ištrinti"><span class="dashicons dashicons-trash"></span></button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="ss-quiz-noresults" style="display:none">
    <span class="dashicons dashicons-search"></span> Pagal paiešką nieko nerasta.
  </div>
<?php endif; ?>

<!-- ═════ REDAGAVIMO MODALAS ═════ -->
<div id="ss-survey-modal" class="ss-modal-overlay ss-modal-wide" style="display:none">
<div class="ss-modal ss-sv-editor">
  <div class="ss-modal-head">
    <h2 id="ss-survey-modal-title">Nauja apklausa</h2>
    <button class="ss-modal-x" data-close><span class="dashicons dashicons-no-alt"></span></button>
  </div>
  <div class="ss-modal-body">
    <input type="hidden" id="ss-sv-id" value="0">
    <input type="hidden" id="ss-sv-type" value="survey">

    <!-- 1. Tipas + pagrindai -->
    <div class="ss-sv-section">
      <div class="ss-seg" id="ss-sv-type-seg">
        <button type="button" class="ss-seg-btn is-active" data-type="survey"><span class="dashicons dashicons-feedback"></span> Apklausa</button>
        <button type="button" class="ss-seg-btn" data-type="quiz"><span class="dashicons dashicons-awards"></span> Viktorina</button>
      </div>
      <p class="ss-sv-seg-hint" id="ss-sv-seg-hint">Apklausa — surenka nuomones, be teisingų atsakymų ir taškų.</p>

      <div class="ss-field">
        <label>Pavadinimas *</label>
        <input type="text" id="ss-sv-title" class="regular-text" placeholder="Pvz. Mokinių nuomonės apklausa">
      </div>
      <div class="ss-field" style="margin-bottom:0">
        <label>Aprašymas <span class="ss-lbl-soft">(matomas vartotojui)</span></label>
        <textarea id="ss-sv-description" rows="2" placeholder="Trumpas paaiškinimas dalyviams…"></textarea>
      </div>
    </div>

    <!-- 2. Auditorija ir laikas -->
    <div class="ss-sv-section">
      <h4 class="ss-sv-section-title"><span class="dashicons dashicons-groups"></span> Kam ir kada</h4>
      <div class="ss-sv-cols">
        <div class="ss-field" style="margin:0">
          <label>Auditorija <span class="ss-lbl-soft">(galima kelias)</span></label>
          <div id="ss-sv-audience-wrap" class="ss-sv-aud">
            <label class="ss-sv-aud-row is-head"><input type="checkbox" class="ss-sv-aud-chk" value="all"> Visi vartotojai</label>
            <label class="ss-sv-aud-row"><input type="checkbox" class="ss-sv-aud-chk" value="teachers"> Mokytojai</label>
            <label class="ss-sv-aud-row"><input type="checkbox" class="ss-sv-aud-chk" value="students"> Visi mokiniai</label>
            <?php foreach ($classes as $cls): ?>
            <label class="ss-sv-aud-row"><input type="checkbox" class="ss-sv-aud-chk ss-sv-aud-class" value="class:<?= (int)$cls->id ?>"> <?= esc_html($cls->name) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="ss-sv-col-right">
          <div class="ss-field">
            <label>Atidaryti nuo <span class="ss-lbl-soft">(neprivaloma)</span></label>
            <input type="datetime-local" id="ss-sv-open-from" class="regular-text">
          </div>
          <div class="ss-field">
            <label>Uždaryti <span class="ss-lbl-soft">(neprivaloma)</span></label>
            <input type="datetime-local" id="ss-sv-close-at" class="regular-text">
          </div>
          <div class="ss-field" style="margin:0">
            <label>Būsena</label>
            <select id="ss-sv-status" class="regular-text">
              <option value="draft">Juodraštis</option>
              <option value="active">Aktyvi</option>
              <option value="closed">Uždaryta</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- 3. Rezultatų matomumas -->
    <div class="ss-sv-section">
      <h4 class="ss-sv-section-title"><span class="dashicons dashicons-chart-bar"></span> Rezultatų matomumas</h4>
      <label class="ss-sv-toggle">
        <input type="checkbox" id="ss-sv-show-users">
        <span><strong>Rodyti rezultatus vartotojams</strong><br><span class="ss-lbl-soft">Įjungus, dalyviai (ir neatsakę) matys suvestinę / balą.</span></span>
      </label>
      <div id="ss-sv-results-times" class="ss-sv-subbox" style="display:none">
        <label class="ss-sv-toggle">
          <input type="checkbox" id="ss-sv-show-answers">
          <span><strong>Leisti peržiūrėti klausimus ir atsakymus</strong><br><span class="ss-lbl-soft">Išjungus — matys tik balą<span class="ss-sv-when-quiz"> ir rezultatų lentelę</span>, be klausimų peržiūros.</span></span>
        </label>
        <div class="ss-sv-cols2">
          <div class="ss-field" style="margin:0">
            <label>Rezultatai nuo <span class="ss-lbl-soft">(neprivaloma)</span></label>
            <input type="datetime-local" id="ss-sv-results-from" class="regular-text">
          </div>
          <div class="ss-field" style="margin:0">
            <label>Rezultatai iki <span class="ss-lbl-soft">(neprivaloma)</span></label>
            <input type="datetime-local" id="ss-sv-results-close" class="regular-text">
          </div>
        </div>
        <p class="ss-sv-hint-soft">Nenustačius laikų — rezultatai rodomi iškart ir kol būseną pakeisite rankiniu būdu.</p>
      </div>
    </div>

    <!-- 4. Reklaminis blokas -->
    <div class="ss-sv-section">
      <h4 class="ss-sv-section-title"><span class="dashicons dashicons-format-image"></span> Reklaminio bloko fonas <span class="ss-lbl-soft" style="text-transform:none;letter-spacing:0;font-weight:500">(neprivaloma)</span></h4>
      <div class="ss-sv-image">
        <input type="hidden" id="ss-sv-image-id" value="0">
        <div id="ss-sv-image-preview" class="ss-sv-image-prev"><span>Nėra</span></div>
        <div class="ss-sv-image-btns">
          <button type="button" class="button" id="ss-sv-image-pick">Pasirinkti nuotrauką</button>
          <button type="button" class="button ss-btn-danger" id="ss-sv-image-clear" style="display:none">Pašalinti</button>
        </div>
      </div>
    </div>

    <!-- 5. Klausimai -->
    <div class="ss-sv-section">
      <h4 class="ss-sv-section-title"><span class="dashicons dashicons-editor-ul"></span> Klausimai</h4>
      <div class="ss-q-editor" id="ss-sv-questions"></div>
      <button type="button" class="button ss-q-add-main" id="ss-sv-add-question"><span class="dashicons dashicons-plus-alt2"></span> Pridėti klausimą</button>
    </div>
  </div>
  <div class="ss-modal-foot">
    <button class="button" data-close>Atšaukti</button>
    <button class="button button-primary" id="ss-sv-save">Išsaugoti</button>
  </div>
</div>
</div>

<!-- ═════ REZULTATŲ MODALAS ═════ -->
<div id="ss-survey-results-modal" class="ss-modal-overlay ss-modal-wide" style="display:none">
<div class="ss-modal" style="max-width:780px">
  <div class="ss-modal-head">
    <h2 id="ss-svr-title">Rezultatai</h2>
    <button class="ss-modal-x" data-close><span class="dashicons dashicons-no-alt"></span></button>
  </div>
  <div class="ss-modal-body" id="ss-svr-body">
    <p style="color:var(--sa-green-lt)">Kraunama…</p>
  </div>
  <div class="ss-modal-foot">
    <a href="#" class="button" id="ss-svr-export" target="_blank"><span class="dashicons dashicons-media-spreadsheet"></span> Eksportuoti CSV</a>
    <button class="button" data-close>Uždaryti</button>
  </div>
</div>
</div>

<!-- Puslapio loading uždanga -->
<div id="ss-page-loading" class="ss-page-loading" aria-live="polite" aria-label="Kraunama">
  <div class="ss-page-loading-inner">
    <div class="ss-page-spinner"></div>
    <div class="ss-page-loading-label" id="ss-page-loading-label">Kraunama…</div>
  </div>
</div>

<script>
jQuery(function($){
  var nonce = ss_admin.nonce;

  /* ── Loading ── */
  function pageLoad(label){ $('#ss-page-loading-label').text(label||'Kraunama…'); $('#ss-page-loading').addClass('is-active'); }
  function pageUnload(){ $('#ss-page-loading').removeClass('is-active'); }
  var skeletonHtml =
    '<div class="ss-modal-skel"><div class="ss-skel-line w60"></div><div class="ss-skel-block"></div>' +
    '<div class="ss-skel-block"></div><div class="ss-skel-line w40"></div><div class="ss-skel-block"></div></div>';
  function showNotice(m, type){
    var $n = $('#ss-notice'); $n.removeClass('notice-success notice-error').addClass('notice-'+(type||'success')).find('p').text(m);
    $n.show(); setTimeout(function(){$n.fadeOut();}, 3000);
  }
  function escAttr(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function p2(n){ return n<10?'0'+n:''+n; }
  function ltDate(dt){
    if(!dt) return '—';
    var d = new Date((dt+'').replace(' ','T'));
    if (isNaN(d)) return (dt+'').slice(0,16).replace('T',' ');
    return d.getFullYear()+'-'+p2(d.getMonth()+1)+'-'+p2(d.getDate())+' '+p2(d.getHours())+':'+p2(d.getMinutes());
  }

  /* ── Filtravimas: tab + paieška ── */
  function applyFilter(){
    var f = $('.ss-quiz-toolbar .ss-tab-btn.active').data('filter') || 'all';
    var q = ($('#ss-sv-search').val()||'').trim().toLowerCase();
    var shown = 0;
    $('.ss-quiz-card').each(function(){
      var $c = $(this);
      var okType = (f==='all' || f===$c.data('type'));
      var okText = (!q || (''+$c.data('title')).toLowerCase().indexOf(q) !== -1);
      var vis = okType && okText;
      $c.toggle(vis); if (vis) shown++;
    });
    $('.ss-quiz-noresults').toggle(shown===0 && $('.ss-quiz-card').length>0);
  }
  $('.ss-quiz-toolbar .ss-tab-btn').on('click', function(){
    $('.ss-quiz-toolbar .ss-tab-btn').removeClass('active'); $(this).addClass('active'); applyFilter();
  });
  $('#ss-sv-search').on('input', applyFilter);

  /* ── Tipo perjungiklis ── */
  function setType(t){
    t = (t==='quiz') ? 'quiz' : 'survey';
    $('#ss-sv-type').val(t);
    $('#ss-sv-type-seg .ss-seg-btn').removeClass('is-active').filter('[data-type="'+t+'"]').addClass('is-active');
    $('#ss-survey-modal').toggleClass('is-quiz', t==='quiz');
    $('#ss-sv-seg-hint').text(t==='quiz'
      ? 'Viktorina — su teisingais atsakymais, taškais ir rezultatų lentele.'
      : 'Apklausa — surenka nuomones, be teisingų atsakymų ir taškų.');
  }
  $('#ss-sv-type-seg').on('click', '.ss-seg-btn', function(){ setType($(this).data('type')); });

  /* ── Klausimų redaktorius ── */
  function questionRow(q, i){
    q = q || {text:'', type:'single', options:[{text:'',correct:0},{text:'',correct:0}], points:1};
    var pts = parseInt(q.points)||1, t = q.type||'single';
    var optsHtml = ''; (q.options||[]).forEach(function(o,oi){ optsHtml += optionRowHtml(o,oi); });
    return '<div class="ss-q-item" data-idx="'+i+'" data-qtype="'+t+'">' +
      '<button class="ss-q-remove" type="button" title="Pašalinti"><span class="dashicons dashicons-no-alt"></span></button>' +
      '<div class="ss-q-item-head">' +
        '<span class="ss-q-num">Klausimas '+(i+1)+'</span>' +
        '<select class="ss-q-type-select">' +
          '<option value="single"'+(t==='single'?' selected':'')+'>Vienas atsakymas</option>' +
          '<option value="multi"'+(t==='multi'?' selected':'')+'>Keli atsakymai</option>' +
          '<option value="rank"'+(t==='rank'?' selected':'')+'>Surikiuoti</option>' +
          '<option value="text"'+(t==='text'?' selected':'')+'>Tekstinis</option>' +
        '</select>' +
        '<label class="ss-q-pts-wrap">Taškai <input type="number" class="ss-q-points" value="'+pts+'" min="1" max="100"></label>' +
      '</div>' +
      '<input type="text" class="ss-q-text" value="'+escAttr(q.text||'')+'" placeholder="Įveskite klausimą…">' +
      (t==='rank'?rankHint():'') +
      '<div class="ss-q-options"'+(t==='text'?' style="display:none"':'')+'>'+optsHtml+'</div>' +
      (t==='text'?'':'<button class="button ss-q-add-opt" type="button"><span class="dashicons dashicons-plus"></span> Pridėti variantą</button>') +
    '</div>';
  }
  function rankHint(){ return '<div class="ss-q-hint">Variantus suveskite <strong>teisinga tvarka</strong> — dalyviui jie rodomi sumaišyti. Viktorinoje ši tvarka laikoma teisingu atsakymu.</div>'; }
  function optionRowHtml(o, oi){
    o = o || {text:'',correct:0};
    return '<div class="ss-q-opt-row" data-oi="'+oi+'">' +
      '<input type="checkbox" class="ss-q-correct" '+(o.correct?'checked':'')+' title="Teisingas atsakymas">' +
      '<input type="text" value="'+escAttr(o.text||'')+'" placeholder="Atsakymo variantas">' +
      '<button class="ss-q-opt-del" type="button" title="Šalinti"><span class="dashicons dashicons-no-alt"></span></button>' +
    '</div>';
  }

  /* ── Auditorija ── */
  function getAudienceArr(){ var a=[]; $('.ss-sv-aud-chk:checked').each(function(){ a.push($(this).val()); }); return a.length?a:['all']; }
  function setAudienceArr(arr){
    if (!Array.isArray(arr)) arr=[arr||'all'];
    if (arr.length===1 && !(''+arr[0]).startsWith('class:') && arr[0]!=='all' && arr[0]!=='students' && arr[0]!=='teachers') arr=['all'];
    $('.ss-sv-aud-chk').prop('checked',false);
    arr.forEach(function(v){ $('.ss-sv-aud-chk[value="'+v+'"]').prop('checked',true); });
    if (!$('.ss-sv-aud-chk:checked').length) $('.ss-sv-aud-chk[value="all"]').prop('checked',true);
  }
  // „Visi" išjungia kitus; pasirinkus kitą – „Visi" nusiima
  $(document).on('change', '.ss-sv-aud-chk', function(){
    if ($(this).val()==='all' && this.checked) { $('.ss-sv-aud-chk').not(this).prop('checked',false); }
    else if (this.checked) { $('.ss-sv-aud-chk[value="all"]').prop('checked',false); }
    if (!$('.ss-sv-aud-chk:checked').length) $('.ss-sv-aud-chk[value="all"]').prop('checked',true);
  });

  function updateResultsTimes(){ $('#ss-sv-results-times').toggle($('#ss-sv-show-users').is(':checked')); }
  $('#ss-sv-show-users').on('change', updateResultsTimes);

  function renderQuestions(arr){
    var $w = $('#ss-sv-questions').empty();
    (arr&&arr.length?arr:[null]).forEach(function(q,i){ $w.append(questionRow(q,i)); });
  }
  $('#ss-sv-add-question').on('click', function(){
    var i = $('#ss-sv-questions .ss-q-item').length;
    var $row = $(questionRow(null,i)); $('#ss-sv-questions').append($row);
    $row[0].scrollIntoView({behavior:'smooth', block:'nearest'});
  });
  $(document).on('click', '.ss-q-remove', function(){
    if ($('#ss-sv-questions .ss-q-item').length<=1){ showNotice('Turi likti bent vienas klausimas','error'); return; }
    if (!confirm('Pašalinti klausimą?')) return;
    $(this).closest('.ss-q-item').remove(); renumberQuestions();
  });
  function renumberQuestions(){
    $('#ss-sv-questions .ss-q-item').each(function(i){ $(this).attr('data-idx',i).find('.ss-q-num').text('Klausimas '+(i+1)); });
  }
  $(document).on('change', '.ss-q-type-select', function(){
    var $item=$(this).closest('.ss-q-item'), t=$(this).val();
    $item.attr('data-qtype',t);
    $item.find('.ss-q-options').toggle(t!=='text');
    $item.find('.ss-q-hint').remove();
    if (t==='rank') $item.find('.ss-q-text').after(rankHint());
    var $btn=$item.find('.ss-q-add-opt');
    if (t==='text'){ $btn.hide(); }
    else if ($btn.length===0){ $item.append('<button class="button ss-q-add-opt" type="button"><span class="dashicons dashicons-plus"></span> Pridėti variantą</button>'); }
    else { $btn.show(); }
  });
  $(document).on('click', '.ss-q-add-opt', function(){
    var $item=$(this).closest('.ss-q-item'); var oi=$item.find('.ss-q-opt-row').length;
    $item.find('.ss-q-options').append(optionRowHtml(null,oi));
  });
  $(document).on('click', '.ss-q-opt-del', function(){ $(this).closest('.ss-q-opt-row').remove(); });

  /* ── Naujas ── */
  $('#ss-survey-new-btn').on('click', function(){
    $('#ss-survey-modal-title').text('Nauja apklausa');
    $('#ss-sv-id').val(0);
    $('#ss-sv-title').val(''); $('#ss-sv-description').val('');
    $('#ss-sv-status').val('draft');
    $('#ss-sv-open-from,#ss-sv-close-at,#ss-sv-results-from,#ss-sv-results-close').val('');
    $('#ss-sv-show-answers').prop('checked', true);
    $('#ss-sv-show-users').prop('checked', false);
    $('#ss-sv-image-id').val(0);
    $('#ss-sv-image-preview').html('<span>Nėra</span>'); $('#ss-sv-image-clear').hide();
    setType('survey'); setAudienceArr(['all']); updateResultsTimes();
    renderQuestions([{text:'',type:'single',options:[{text:'',correct:0},{text:'',correct:0}],points:1}]);
    $('#ss-survey-modal').show(); $('.ss-modal-body').scrollTop(0);
  });

  /* ── Redaguoti ── */
  $(document).on('click', '.ss-survey-edit', function(){
    var id=$(this).data('id'), $btn=$(this);
    $btn.addClass('is-loading').prop('disabled',true); pageLoad('Kraunamas redaktorius…');
    $.post(ss_admin.ajax_url, {action:'ss_survey_get', nonce:nonce, id:id}, function(res){
      pageUnload(); $btn.removeClass('is-loading').prop('disabled',false);
      if (!res.success){ showNotice(res.data.message||'Klaida','error'); return; }
      var s=res.data;
      $('#ss-survey-modal-title').text('Redaguoti: '+(s.title||''));
      $('#ss-sv-id').val(s.id);
      $('#ss-sv-title').val(s.title); $('#ss-sv-description').val(s.description);
      $('#ss-sv-status').val(s.status);
      $('#ss-sv-open-from').val((s.open_from||'').replace(' ','T').slice(0,16));
      $('#ss-sv-close-at').val((s.close_at||'').replace(' ','T').slice(0,16));
      $('#ss-sv-results-from').val((s.results_show_from||'').replace(' ','T').slice(0,16));
      $('#ss-sv-results-close').val((s.results_close_at||'').replace(' ','T').slice(0,16));
      $('#ss-sv-show-users').prop('checked', !!parseInt(s.show_results_users));
      $('#ss-sv-show-answers').prop('checked', !!parseInt(s.show_answers_users));
      setType(s.type); setAudienceArr(s.audience_arr || [s.audience||'all']);
      var imgId=parseInt(s.image_id)||0; $('#ss-sv-image-id').val(imgId);
      if (imgId){ $('#ss-sv-image-preview').html('<img src="'+s.image_url+'" alt="">'); $('#ss-sv-image-clear').show(); }
      else { $('#ss-sv-image-preview').html('<span>Nėra</span>'); $('#ss-sv-image-clear').hide(); }
      updateResultsTimes(); renderQuestions(s.questions);
      $('#ss-survey-modal').show(); $('.ss-modal-body').scrollTop(0);
    });
  });

  /* ── Išsaugoti ── */
  $('#ss-sv-save').on('click', function(){
    if (!($('#ss-sv-title').val()||'').trim()){ showNotice('Įveskite pavadinimą','error'); $('#ss-sv-title').focus(); return; }
    var qs=[];
    $('#ss-sv-questions .ss-q-item').each(function(){
      var $i=$(this), type=$i.find('.ss-q-type-select').val();
      var opts=[];
      if (type!=='text'){ $i.find('.ss-q-opt-row').each(function(){
        var $r=$(this); opts.push({text:$r.find('input[type=text]').val(), correct:$r.find('.ss-q-correct').is(':checked')?1:0});
      }); }
      qs.push({text:$i.find('.ss-q-text').val(), type:type, options:opts, points:parseInt($i.find('.ss-q-points').val())||1});
    });
    var data={
      action:'ss_survey_save', nonce:nonce,
      id:$('#ss-sv-id').val(), title:$('#ss-sv-title').val(), description:$('#ss-sv-description').val(),
      type:$('#ss-sv-type').val(), audience:JSON.stringify(getAudienceArr()), status:$('#ss-sv-status').val(),
      open_from:($('#ss-sv-open-from').val()||'').replace('T',' '),
      close_at:($('#ss-sv-close-at').val()||'').replace('T',' '),
      results_show_from:($('#ss-sv-results-from').val()||'').replace('T',' '),
      results_close_at:($('#ss-sv-results-close').val()||'').replace('T',' '),
      show_results_users:$('#ss-sv-show-users').is(':checked')?1:0,
      show_answers_users:$('#ss-sv-show-answers').is(':checked')?1:0,
      image_id:$('#ss-sv-image-id').val()||0, questions:JSON.stringify(qs)
    };
    var $btn=$(this); $btn.prop('disabled',true).text('Saugoma…'); pageLoad('Saugoma…');
    $.post(ss_admin.ajax_url, data).done(function(res){
      pageUnload(); $btn.prop('disabled',false).text('Išsaugoti');
      if (res && res.success){ showNotice('Išsaugota.'); setTimeout(function(){location.reload();},500); }
      else showNotice((res&&res.data&&res.data.message)||'AJAX klaida','error');
    }).fail(function(xhr){ pageUnload(); $btn.prop('disabled',false).text('Išsaugoti'); showNotice('HTTP '+xhr.status+' klaida','error'); });
  });

  /* ── Trinti ── */
  $(document).on('click', '.ss-survey-delete', function(){
    if (!confirm('Ištrinti šią apklausą ir visus jos atsakymus?')) return;
    var id=$(this).data('id'), $btn=$(this);
    $btn.addClass('is-loading').prop('disabled',true); pageLoad('Trinama…');
    $.post(ss_admin.ajax_url, {action:'ss_survey_delete', nonce:nonce, id:id}, function(res){
      if (res.success){ location.reload(); }
      else { pageUnload(); $btn.removeClass('is-loading').prop('disabled',false); showNotice('Klaida trinant','error'); }
    }).fail(function(){ pageUnload(); $btn.removeClass('is-loading').prop('disabled',false); showNotice('Ryšio klaida','error'); });
  });

  /* ── Būsenos perjungimas ── */
  $(document).on('click', '.ss-survey-toggle', function(){
    var id=$(this).data('id'), st=$(this).data('status'), $btn=$(this);
    $btn.addClass('is-loading').prop('disabled',true); pageLoad(st==='active'?'Įjungiama…':'Uždaroma…');
    $.post(ss_admin.ajax_url, {action:'ss_survey_toggle_status', nonce:nonce, id:id, status:st}, function(res){
      if (res.success){ location.reload(); }
      else { pageUnload(); $btn.removeClass('is-loading').prop('disabled',false); showNotice('Klaida keičiant būseną','error'); }
    }).fail(function(){ pageUnload(); $btn.removeClass('is-loading').prop('disabled',false); showNotice('Ryšio klaida','error'); });
  });

  /* ── Rezultatai ── */
  function avatarChip(name, url, cls){
    if (url) return '<img class="ss-svr-av '+(cls||'')+'" src="'+escAttr(url)+'" alt="">';
    var ini = (String(name||'?').trim().charAt(0)||'?').toUpperCase();
    return '<span class="ss-svr-av ss-svr-av-fb '+(cls||'')+'">'+escAttr(ini)+'</span>';
  }
  $(document).on('click', '.ss-survey-results', function(){
    var id=$(this).data('id'), $btn=$(this);
    $btn.addClass('is-loading').prop('disabled',true);
    $('#ss-svr-body').html(skeletonHtml); $('#ss-survey-results-modal').show();
    $('#ss-svr-export').attr('href', ss_admin.ajax_url.replace('admin-ajax.php','admin-post.php')
      + '?action=ss_survey_export_csv&id='+id+'&_wpnonce='+'<?= esc_js(wp_create_nonce('ss_export_survey')) ?>');
    $.post(ss_admin.ajax_url, {action:'ss_survey_get', nonce:nonce, id:id}, function(res){
      $btn.removeClass('is-loading').prop('disabled',false);
      if (!res.success){ $('#ss-svr-body').html('<p>Klaida.</p>'); return; }
      var s=res.data, isQuiz=(s.type==='quiz');
      $('#ss-svr-title').text((isQuiz?'Viktorina: ':'Apklausa: ')+s.title);

      // Statistikos juosta
      var scored = (s.responses||[]).filter(function(r){ return parseInt(r.max_score)>0; });
      var avgPct = '—';
      if (isQuiz && scored.length){
        var sum=0; scored.forEach(function(r){ var mx=parseInt(r.max_score); sum += mx>0?(parseInt(r.score)*100/mx):0; });
        avgPct = Math.round(sum/scored.length)+'%';
      }
      var html = '<div class="ss-svr-stats">';
      html += '<div class="ss-svr-stat"><span class="ss-svr-stat-n">'+s.responses_count+'</span><span class="ss-svr-stat-l">Atsakymai</span></div>';
      html += '<div class="ss-svr-stat"><span class="ss-svr-stat-n">'+((s.questions||[]).length)+'</span><span class="ss-svr-stat-l">Klausimai</span></div>';
      if (isQuiz) html += '<div class="ss-svr-stat"><span class="ss-svr-stat-n">'+avgPct+'</span><span class="ss-svr-stat-l">Vid. rezultatas</span></div>';
      html += '</div>';

      if (!s.responses_count){
        html += '<div class="ss-svr-empty"><span class="dashicons dashicons-info-outline"></span> Atsakymų dar nėra.</div>';
        $('#ss-svr-body').html(html); return;
      }

      // Pjedestalas + lentelė (viktorinos)
      if (isQuiz && scored.length){
        var sorted = scored.slice().sort(function(a,b){
          var sd=parseInt(b.score)-parseInt(a.score); if(sd!==0) return sd;
          return new Date(a.submitted_at)-new Date(b.submitted_at);
        });
        var top = sorted.slice(0,3);
        var byPos = {}; top.forEach(function(r,i){ byPos[i+1]=r; });
        function pcol(r, rank){
          if (!r) return '';
          var mx=parseInt(r.max_score), pct=mx>0?Math.round(parseInt(r.score)*100/mx):0;
          return '<div class="ss-svr-pod-col p'+rank+'">'
            + '<div class="ss-svr-pod-top">'+avatarChip(r.display_name||r.user_email, r.avatar)
            + '<div class="ss-svr-pod-name" title="'+escAttr(r.display_name||r.user_email)+'">'+escAttr(r.display_name||r.user_email||'—')+'</div>'
            + '<div class="ss-svr-pod-sc">'+parseInt(r.score)+'/'+mx+' · '+pct+'%</div></div>'
            + '<div class="ss-svr-pod-base"><span>'+rank+'</span></div></div>';
        }
        html += '<div class="ss-svr-block"><div class="ss-svr-block-h"><span class="dashicons dashicons-awards"></span> Rezultatų lentelė</div>';
        html += '<div class="ss-svr-podium">'+pcol(byPos[2],2)+pcol(byPos[1],1)+pcol(byPos[3],3)+'</div>';
        // Pilna lentelė
        html += '<table class="ss-svr-table"><thead><tr><th>#</th><th>Dalyvis</th><th class="c">Taškai</th><th class="c">%</th><th class="r">Laikas</th></tr></thead><tbody>';
        sorted.forEach(function(r,i){
          var mx=parseInt(r.max_score), pct=mx>0?Math.round(parseInt(r.score)*100/mx):0;
          html += '<tr'+(i<3?' class="is-top"':'')+'><td class="ss-svr-rk">'+(i+1)+'</td>'
            + '<td><span class="ss-svr-uc">'+avatarChip(r.display_name||r.user_email, r.avatar,'sm')+' '+escAttr(r.display_name||r.user_email||'—')+'</span></td>'
            + '<td class="c"><strong>'+parseInt(r.score)+'</strong>/'+mx+'</td><td class="c">'+pct+'%</td>'
            + '<td class="r">'+ltDate(r.submitted_at)+'</td></tr>';
        });
        html += '</tbody></table></div>';
      }

      // Klausimų suvestinė
      html += '<div class="ss-svr-block"><div class="ss-svr-block-h"><span class="dashicons dashicons-editor-ul"></span> Klausimai</div><div class="ss-quiz-results">';
      (s.questions||[]).forEach(function(q, qi){
        var pts=parseInt(q.points)||1;
        var ptsBadge = isQuiz ? ' <span class="ss-q-pts-badge">'+pts+' t.</span>' : '';
        html += '<div class="ss-q-result"><h4 class="ss-q-result-title">'+(qi+1)+'. '+escAttr(q.text)+ptsBadge+'</h4>';
        if (q.type==='text'){
          var arr=(s.aggregate[qi]&&s.aggregate[qi].text)||[];
          if (!arr.length){ html+='<p class="ss-svr-muted">Atsakymų nėra</p>'; }
          else {
            if (isQuiz) html += '<p class="ss-svr-muted">Tikrinimo režimas — pažymėkite teisingus atsakymus.</p>';
            arr.forEach(function(ta){
              var rid=ta.response_id, granted=ta.granted, autoSc=ta.auto_score;
              var isC=(granted!==null&&granted>0), isW=(granted!==null&&granted===0);
              var gradeBtns='';
              if (isQuiz){
                gradeBtns = '<span class="ss-grade-btns">'
                  + '<button class="ss-grade-btn ok'+(isC?' is-on':'')+'" type="button" data-rid="'+rid+'" data-qi="'+qi+'" data-grant="'+pts+'" data-autoscore="'+autoSc+'" title="Teisingas (+'+pts+' t.)"><span class="dashicons dashicons-yes"></span></button>'
                  + '<button class="ss-grade-btn no'+(isW?' is-on':'')+'" type="button" data-rid="'+rid+'" data-qi="'+qi+'" data-grant="0" data-autoscore="'+autoSc+'" title="Neteisingas (0 t.)"><span class="dashicons dashicons-no-alt"></span></button>'
                  + '</span>';
              }
              html += '<div class="ss-text-ans-row'+(isC?' is-correct':isW?' is-wrong':'')+'" data-rid="'+rid+'">'
                + '<span class="ss-text-ans-t">'+escAttr(ta.text)+'</span>'
                + '<span class="ss-text-ans-u">'+escAttr(ta.user_name)+'</span>'+gradeBtns+'</div>';
            });
          }
        } else if (q.type==='rank'){
          var rankAvg=(s.aggregate[qi]&&s.aggregate[qi].rank_avg)||{};
          var so=(q.options||[]).map(function(opt,oi){ return {oi:oi,opt:opt,avg:rankAvg[oi]}; })
            .sort(function(a,b){ if(a.avg==null&&b.avg==null)return 0; if(a.avg==null)return 1; if(b.avg==null)return -1; return a.avg-b.avg; });
          html += '<p class="ss-svr-muted">Vidutinė pozicija (mažesnis = aukštesnė vieta)</p>';
          so.forEach(function(it,idx){
            var avg=it.avg!=null?it.avg.toFixed(2):'—';
            html += '<div class="ss-q-bar-row"><div class="ss-q-bar-label"><strong>'+(idx+1)+'.</strong> '+escAttr(it.opt.text)+'</div><div class="ss-q-bar-track"></div><div class="ss-q-bar-count">Ø '+avg+'</div></div>';
          });
        } else {
          var counts=(s.aggregate[qi]&&s.aggregate[qi].counts)||{}, total=0; for(var k in counts) total+=counts[k];
          (q.options||[]).forEach(function(opt,oi){
            var c=counts[oi]||0, pct=total?Math.round(c*100/total):0;
            html += '<div class="ss-q-bar-row"><div class="ss-q-bar-label">'+escAttr(opt.text)
              + (opt.correct?' <span class="dashicons dashicons-yes ss-ok-ico"></span>':'')+'</div>'
              + '<div class="ss-q-bar-track"><div class="ss-q-bar-fill'+(opt.correct?' is-correct':'')+'" style="width:'+pct+'%"></div></div>'
              + '<div class="ss-q-bar-count">'+c+' ('+pct+'%)</div></div>';
          });
        }
        html += '</div>';
      });
      html += '</div></div>';
      $('#ss-svr-body').html(html);
    });
  });

  /* ── Tekstinių atsakymų vertinimas ── */
  $(document).on('click', '.ss-grade-btn', function(){
    var $btn=$(this), rid=$btn.data('rid'), qi=$btn.data('qi');
    var grant=parseInt($btn.data('grant')), autoSc=parseInt($btn.data('autoscore'))||0;
    var $row=$btn.closest('.ss-text-ans-row'), $all=$row.find('.ss-grade-btn');
    $all.prop('disabled',true);
    $.post(ss_admin.ajax_url, {action:'ss_survey_grade_text', nonce:nonce, response_id:rid, qi:qi, granted:grant, auto_score:autoSc}, function(res){
      $all.prop('disabled',false);
      if (!res.success){ showNotice('Klaida įrašant vertinimą','error'); return; }
      var isC=(grant>0);
      $row.removeClass('is-correct is-wrong').addClass(isC?'is-correct':'is-wrong');
      $row.find('.ss-grade-btn').removeClass('is-on');
      $row.find('.ss-grade-btn.'+(isC?'ok':'no')).addClass('is-on');
      showNotice(isC?('Taškai suteikti (viso '+res.data.new_score+')'):'Pažymėta kaip neteisinga');
    }).fail(function(){ $all.prop('disabled',false); showNotice('HTTP klaida','error'); });
  });

  /* ── Nuotraukos parinkiklis ── */
  var ssMediaFrame;
  $(document).on('click', '#ss-sv-image-pick', function(){
    if (ssMediaFrame){ ssMediaFrame.open(); return; }
    ssMediaFrame = wp.media({title:'Pasirinkti fono nuotrauką', button:{text:'Naudoti'}, multiple:false, library:{type:'image'}});
    ssMediaFrame.on('select', function(){
      var att=ssMediaFrame.state().get('selection').first().toJSON();
      $('#ss-sv-image-id').val(att.id);
      var u=(att.sizes&&att.sizes.medium)?att.sizes.medium.url:att.url;
      $('#ss-sv-image-preview').html('<img src="'+u+'" alt="">'); $('#ss-sv-image-clear').show();
    });
    ssMediaFrame.open();
  });
  $(document).on('click', '#ss-sv-image-clear', function(){
    $('#ss-sv-image-id').val(0); $('#ss-sv-image-preview').html('<span>Nėra</span>'); $(this).hide();
  });

  /* ── Modalų uždarymas ── */
  $(document).on('click', '[data-close]', function(){ $(this).closest('.ss-modal-overlay').hide(); });
  $('.ss-modal-overlay').on('click', function(e){ if (e.target===this) $(this).hide(); });
  $(document).on('keydown', function(e){ if (e.key==='Escape') $('.ss-modal-overlay:visible').hide(); });
});
</script>
</div>
