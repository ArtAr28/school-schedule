<?php if (!defined('ABSPATH')) exit;
$days = array('Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis','Šeštadienis','Sekmadienis');
// Calculate week dates (use WP timezone)
$today = SS_Time::now();
$day_of_week = (int)$today->format('N');
$monday = clone $today;
$monday->modify('-'.($day_of_week-1).' days');

$weeks = array(
    'prev' => (clone $monday)->modify('-7 days'),
    'curr' => clone $monday,
    'next' => (clone $monday)->modify('+7 days'),
);
$week_labels = array(
    'prev' => 'Praeita savaitė',
    'curr' => 'Ši savaitė',
    'next' => 'Sekanti savaitė',
);
?>
<div class="wrap ss-wrap ss-changes-page">
<?php
$ss_page_title    = 'Pakeitimai';
$ss_page_subtitle = 'Pamokų pakeitimai ir mokytojų neatvykimai pagal dieną.';
include SS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<div class="ss-changes-wrap">

  <?php if (!empty($notify_status)): ?>
  <!-- Pakeitimų pranešimų siuntimo būsena (rytojaus diena; gyvai atnaujinama) -->
  <div class="ss-notify-status<?= $notify_status['published'] ? ' is-pub' : '' ?>" id="ss-notify-status">
    <span class="ss-ns-ico dashicons dashicons-email-alt" aria-hidden="true"></span>
    <div class="ss-ns-body">
      <strong class="ss-ns-title">Pakeitimų pranešimai — <span id="ss-ns-day"><?= esc_html($notify_status['target_label']) ?></span></strong>
      <div class="ss-ns-lines" id="ss-ns-line"><?= SS_ChangeNotify::status_html($notify_status) ?></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Week selector -->
  <div class="ss-week-selector">
    <?php foreach ($weeks as $key => $start):
      $end = (clone $start)->modify('+6 days');
    ?>
    <button class="ss-week-btn <?= $key==='curr'?'active':'' ?>" data-week="<?= esc_attr($key) ?>"
            data-start="<?= esc_attr($start->format('Y-m-d')) ?>" data-end="<?= esc_attr($end->format('Y-m-d')) ?>">
      <span class="ss-wb-label"><?= esc_html($week_labels[$key]) ?></span>
      <span class="ss-wb-dates"><?= esc_html($start->format('m-d')) ?> – <?= esc_html($end->format('m-d')) ?></span>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- Day selector within week -->
  <div class="ss-day-selector" id="ss-day-selector">
    <?php
    $curr_start = $weeks['curr'];
    for ($i = 0; $i < 5; $i++): // Only weekdays
      $day_date = (clone $curr_start)->modify("+{$i} days");
      $is_today = $day_date->format('Y-m-d') === $today->format('Y-m-d');
    ?>
    <button class="ss-day-btn" data-date="<?= esc_attr($day_date->format('Y-m-d')) ?>">
      <span class="ss-db-name"><?= esc_html($days[$i]) ?></span>
      <span class="ss-db-date"><?= esc_html($day_date->format('m-d')) ?></span>
      <?php if ($is_today): ?><span class="ss-db-today">Šiandien</span><?php endif; ?>
    </button>
    <?php endfor; ?>
  </div>

  <div class="ss-changes-content">

    <!-- Prašymas pasirinkti dieną (rodoma kol diena nepasirinkta) -->
    <div class="ss-no-day" id="ss-no-day">
      <span class="dashicons dashicons-calendar-alt"></span>
      <p>Pasirinkite dieną, kad galėtumėte peržiūrėti ir redaguoti pakeitimus.</p>
    </div>

    <!-- Dienos turinys (rodomas tik pasirinkus dieną) -->
    <div class="ss-day-body" id="ss-day-body" style="display:none;position:relative;">

    <!-- Įkėlimo animacija -->
    <div class="ss-ch-loading" id="ss-ch-loading" style="display:none;">
      <span class="ss-ch-spinner" aria-hidden="true"></span>
      <span>Kraunama…</span>
    </div>

    <!-- Publikavimo juosta: pakeitimai matomi vartotojams tik paskelbus -->
    <div class="ss-pub-bar" id="ss-pub-bar">
      <div class="ss-pub-status">
        <span class="ss-pub-dot"></span>
        <div class="ss-pub-texts">
          <strong class="ss-pub-text" id="ss-pub-text">Kraunama…</strong>
          <span class="ss-pub-hint" id="ss-pub-hint"></span>
        </div>
      </div>
      <button class="button button-primary ss-pub-btn" id="ss-pub-toggle" type="button">Paskelbti</button>
    </div>

    <div class="ss-two-col">

      <!-- Left: Period changes table -->
      <div class="ss-card">
        <h2>Pamokų pakeitimai <span id="ss-selected-date-label"></span></h2>
        <p class="description">Paspauskite + prie pamokos norėdami pridėti pakeitimą klasei.</p>

        <table class="ss-changes-table" id="ss-changes-table">
          <thead>
            <tr>
              <th>Pamoka</th>
              <th>Pakeitimai</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($periods as $p): ?>
            <tr data-period="<?= (int)$p->id ?>">
              <td class="ss-ct-period">
                <strong><?= esc_html($p->period_number) ?></strong>
                <span><?= esc_html(substr($p->start_time,0,5)) ?> – <?= esc_html(substr($p->end_time,0,5)) ?></span>
              </td>
              <td class="ss-ct-changes" id="ss-changes-<?= (int)$p->id ?>"></td>
              <td class="ss-ct-action">
                <button class="button ss-add-change" type="button" data-period="<?= (int)$p->id ?>" title="Pridėti pakeitimą">+</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Right: Absent teachers -->
      <div class="ss-card">
        <h2>Šiandien nebus</h2>
        <p class="description">Šių mokytojų pamokos bus pažymėtos raudonai mokinių tvarkaraščiuose.</p>

        <div id="ss-absent-list" class="ss-absent-list"></div>

        <div class="ss-absent-add">
          <select id="ss-absent-teacher-select" class="regular-text">
            <option value="">Pasirinkite mokytoją</option>
            <?php foreach ($teachers as $t): ?>
            <option value="<?= (int)$t->id ?>"><?= esc_html($t->name) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="ss-absent-from-period" class="regular-text" title="Nuo kurios pamokos mokytojo nebus">
            <option value="0">Visą dieną</option>
            <?php foreach ($periods as $p): ?>
            <option value="<?= (int)$p->period_number ?>">Nuo <?= (int)$p->period_number ?> pamokos</option>
            <?php endforeach; ?>
          </select>
          <button class="button button-primary" id="ss-add-absent" type="button">Pridėti</button>
        </div>

        <div class="ss-absent-comment-wrap" style="margin-top:18px;border-top:1px solid var(--sa-light);padding-top:14px;">
          <label style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.4px;color:var(--sa-green-lt);display:block;margin-bottom:8px;">
            Bendras komentaras šiai dienai
          </label>

          <textarea id="ss-jodit-comment" placeholder="Bendras komentaras šiai dienai…"></textarea>

          <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
            <button class="button button-primary" id="ss-save-comment" type="button">Išsaugoti</button>
            <span id="ss-comment-saved" style="display:none;color:#46b450;font-size:13px;font-weight:600;"><span class="dashicons dashicons-yes" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span> Išsaugota</span>
          </div>
        </div>
      </div>

    </div>
    </div><!-- /#ss-day-body -->
  </div>
</div>

<!-- Add Change Modal -->
<div id="ss-change-modal" class="ss-modal-overlay" style="display:none;">
<div class="ss-modal">
  <div class="ss-modal-head">
    <h2 id="ss-change-modal-title">Pridėti</h2>
    <button class="ss-modal-x ss-close-change-modal" type="button" aria-label="Uždaryti">×</button>
  </div>
  <div class="ss-modal-body">
    <input type="hidden" id="ss-change-id">
    <input type="hidden" id="ss-change-period">
    <div class="ss-field">
      <label>Klasė(tvarkaraštis)</label>
      <select id="ss-change-class" class="regular-text">
        <option value="">Pasirinkite klasę(tvarkaraštį)</option>
        <?php foreach ($classes as $c): ?>
        <option value="<?= (int)$c->id ?>"><?= esc_html($c->name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ss-field" id="ss-change-subgroup-wrap" style="display:none">
      <label>Pogrupis</label>
      <div id="ss-change-subgroup-notice" class="notice notice-warning inline" style="margin:0 0 8px;padding:6px 10px;">
        <p style="margin:0"><span class="dashicons dashicons-warning" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span> Ši pamoka skirstoma į pogrupius — nurodykite kuriai skirtas pakeitimas.</p>
      </div>
      <select id="ss-change-subgroup" class="regular-text">
        <option value="">Visiems pogrupiams</option>
      </select>
    </div>
    <div class="ss-field">
      <label>Komentaras / Pakeitimas</label>
      <textarea id="ss-change-comment" rows="3" placeholder="Pvz.: Pamoka nevyks, Kabinetas 205, Pakeista į kitą dalyką..."></textarea>
    </div>
  </div>
  <div class="ss-modal-foot">
    <button class="button ss-close-change-modal" type="button">Atšaukti</button>
    <button class="button button-primary" id="ss-save-change" type="button">Išsaugoti</button>
  </div>
</div>
</div>

<!-- Jodit – MIT-licencijuotas open-source WYSIWYG redaktorius -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jodit@3/build/jodit.min.css">
<script src="https://cdn.jsdelivr.net/npm/jodit@3/build/jodit.min.js"></script>

<style>
  /* Jodit container adjustments for WP admin */
  .ss-absent-comment-wrap .jodit-container {
    border: 1.5px solid var(--sa-light) !important;
    
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
  }
  .ss-absent-comment-wrap .jodit-toolbar__box {
    background: #f6f7f7 !important;
    border-bottom: 1px solid #dcdcde !important;
  }
  /* Force consistent text colour — prevents paste-in colour bleeds */
  .ss-absent-comment-wrap .jodit-wysiwyg,
  .ss-absent-comment-wrap .jodit-wysiwyg * {
    color: #1d2327 !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
  }
  /* Table styling inside the editor */
  .ss-absent-comment-wrap .jodit-wysiwyg table {
    border-collapse: collapse;
    width: 100%;
    margin: 4px 0;
  }
  .ss-absent-comment-wrap .jodit-wysiwyg td,
  .ss-absent-comment-wrap .jodit-wysiwyg th {
    border: 1px solid #dcdcde;
    padding: 4px 8px;
    font-size: 13px;
  }
  .ss-absent-comment-wrap .jodit-wysiwyg th {
    background: #f6f7f7;
    font-weight: 600;
  }
  /* Hide status bar */
  .ss-absent-comment-wrap .jodit-status-bar { display: none !important; }
</style>

<style>
/* ════ Pakeitimai — atnaujintas dizainas ════ */
.ss-changes-page .ss-changes-wrap{margin-top:16px;}

/* ── Pranešimų siuntimo būsena ── */
.ss-changes-page .ss-notify-status{
  display:flex;align-items:flex-start;gap:12px;
  background:#F1F6FB;border:1px solid #CBE0F2;border-left:4px solid #224768;
  padding:12px 16px;margin:0 0 16px;box-shadow:var(--sa-shadow);
}
.ss-changes-page .ss-notify-status.is-pub{background:#F1F8F1;border-color:#BFE3C6;border-left-color:var(--sa-green);}
.ss-changes-page .ss-ns-ico{color:#224768;font-size:20px;width:20px;height:20px;margin-top:1px;flex-shrink:0;}
.ss-changes-page .ss-notify-status.is-pub .ss-ns-ico{color:var(--sa-green);}
.ss-changes-page .ss-ns-body{display:flex;flex-direction:column;gap:3px;min-width:0;}
.ss-changes-page .ss-ns-title{font-size:13px;font-weight:800;color:var(--sa-dark);}
.ss-changes-page .ss-ns-lines{display:flex;flex-direction:column;gap:2px;font-size:12px;color:#5B6B5C;line-height:1.5;}
.ss-changes-page .ss-ns-lines strong{color:var(--sa-dark);font-weight:700;}

/* Savaitės pasirinkimas — kortelės */
.ss-changes-page .ss-week-selector{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;border:none;padding:0;}
.ss-changes-page .ss-week-btn{
  display:flex;flex-direction:column;gap:2px;align-items:flex-start;
  padding:9px 16px;border:1.5px solid var(--sa-border);background:var(--sa-white);
  cursor:pointer;transition:border-color .15s,background .15s;box-shadow:var(--sa-shadow);min-width:auto;
}
.ss-changes-page .ss-week-btn:hover{border-color:var(--sa-green-pale);}
.ss-changes-page .ss-week-btn.active{border-color:var(--sa-green);background:#f1f8f1;color:var(--sa-dark);}
.ss-changes-page .ss-wb-label{font-weight:700;font-size:13px;}
.ss-changes-page .ss-wb-dates{font-size:11px;color:var(--sa-green-lt);}
.ss-changes-page .ss-week-btn.active .ss-wb-dates{color:var(--sa-green);}

/* Dienų juosta — apvalios kortelės */
.ss-changes-page .ss-day-selector{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;border:none;padding:0;}
.ss-changes-page .ss-day-btn{
  position:relative;display:flex;flex-direction:column;gap:1px;align-items:flex-start;
  padding:9px 16px;border:1.5px solid var(--sa-border);background:var(--sa-white);
  cursor:pointer;transition:border-color .15s,background .15s;min-width:96px;
}
.ss-changes-page .ss-day-btn:hover{border-color:var(--sa-green-pale);}
.ss-changes-page .ss-day-btn.active{border-color:var(--sa-green);background:var(--sa-green);color:var(--sa-white);}
.ss-changes-page .ss-db-name{font-weight:700;font-size:13px;}
.ss-changes-page .ss-db-date{font-size:11px;color:var(--sa-green-lt);}
.ss-changes-page .ss-day-btn.active .ss-db-date{color:rgba(255,255,255,.85);}
.ss-changes-page .ss-db-today{margin-top:3px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:var(--sa-green);color:var(--sa-white);padding:1px 7px;}
.ss-changes-page .ss-day-btn.active .ss-db-today{background:var(--sa-white);color:var(--sa-green);}

/* ── Publikavimo juosta ── */
.ss-pub-bar{
  display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;
  background:#FFF8E8;border:1px solid #F2D78A;border-left:4px solid var(--sa-yellow);
  padding:14px 18px;margin:0 0 18px;box-shadow:var(--sa-shadow);
}
.ss-pub-bar.is-pub{background:#F1F8F1;border-color:#BFE3C6;border-left-color:var(--sa-green);}
.ss-pub-status{display:flex;align-items:center;gap:12px;min-width:0;}
.ss-pub-dot{width:12px;height:12px;background:#E0A92E;flex-shrink:0;box-shadow:0 0 0 4px rgba(224,169,46,.18);}
.ss-pub-bar.is-pub .ss-pub-dot{background:var(--sa-green);box-shadow:0 0 0 4px rgba(79,137,82,.18);}
.ss-pub-texts{display:flex;flex-direction:column;gap:2px;min-width:0;}
.ss-pub-text{font-size:14px;font-weight:800;color:var(--sa-dark);}
.ss-pub-hint{font-size:12px;color:var(--sa-green-lt);}
.ss-pub-bar .ss-pub-btn{flex-shrink:0;}
.ss-pub-bar.is-pub .ss-pub-btn{
  background:var(--sa-white)!important;border:1px solid var(--sa-border)!important;color:var(--sa-dark)!important;
  box-shadow:none!important;text-shadow:none!important;
}
.ss-pub-bar.is-pub .ss-pub-btn:hover{border-color:var(--sa-red)!important;color:var(--sa-red)!important;}

/* Kortelės ir lentelės — šiek tiek švaresnės */
.ss-changes-page .ss-changes-wrap .ss-card{box-shadow:var(--sa-shadow);}
.ss-changes-page .ss-ct-period strong{display:block;font-size:15px;color:var(--sa-green);}
.ss-changes-page .ss-ct-period span{font-size:11px;color:var(--sa-green-lt);}
.ss-changes-page .ss-add-change{
  font-weight:700;line-height:1;min-width:30px;
}
.ss-changes-page .ss-absent-row{
  display:inline-flex;align-items:center;gap:6px;background:#FFF1F1;border:1px solid #F5C2C2;
  padding:5px 12px;font-size:13px;color:#7a2b2e;
}
.ss-changes-page .ss-absent-row .ss-absent-del{color:#9F2226;cursor:pointer;font-weight:700;}
.ss-changes-page .ss-absent-list{flex-direction:row;flex-wrap:wrap;gap:8px;}

/* Raginimas pasirinkti dieną */
.ss-no-day{
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;
  padding:48px 20px;text-align:center;color:var(--sa-green-lt);
  background:var(--sa-white);border:1.5px dashed var(--sa-border);
}
.ss-no-day .dashicons{font-size:42px;width:42px;height:42px;color:var(--sa-green-pale);}
.ss-no-day p{margin:0;font-size:14px;max-width:360px;}

/* Įkėlimo animacija (uždanga virš dienos turinio) */
.ss-ch-loading{
  position:absolute;inset:0;z-index:5;display:flex;align-items:center;justify-content:center;gap:12px;
  background:rgba(255,255,255,.75);backdrop-filter:blur(1px);
  color:var(--sa-green-lt);font-size:13px;font-weight:600;
}
.ss-ch-spinner{
  width:28px;height:28px;border:3px solid var(--sa-border);border-top-color:var(--sa-green);
  border-radius:50%;animation:ss-ch-spin .7s linear infinite;
}
@keyframes ss-ch-spin{to{transform:rotate(360deg);}}
</style>

<script>
window.ss_changes_data = {
    periods: <?= wp_json_encode(array_map(function($p){ return array('id'=>(int)$p->id,'number'=>(int)$p->period_number); }, $periods)) ?>,
    classes: <?= wp_json_encode(array_map(function($c){ return array('id'=>(int)$c->id,'name'=>$c->name); }, $classes)) ?>,
    teachers: <?= wp_json_encode(array_map(function($t){ return array('id'=>(int)$t->id,'name'=>$t->name); }, $teachers)) ?>
};

jQuery(function($){
  // Wait for ss_admin (localized via wp_footer) to be present
  function init(){
    if (typeof window.ss_admin === 'undefined') {
      return setTimeout(init, 50);
    }

    var SC = {
      currentDate: $('.ss-day-btn.active').data('date') || '',
      published: false,

      setPublished: function(p){
        this.published = !!p;
        $('#ss-pub-bar').toggleClass('is-pub', this.published);
        $('#ss-pub-text').text(this.published
          ? 'Paskelbta — pakeitimai matomi vartotojams'
          : 'Nepaskelbta — pakeitimai matomi tik administratoriui');
        $('#ss-pub-hint').text(this.published
          ? 'Mokiniai ir mokytojai mato šios dienos pakeitimus.'
          : 'Paspauskite „Paskelbti", kad pakeitimai taptų matomi mokiniams ir mokytojams.');
        $('#ss-pub-toggle').text(this.published ? 'Atšaukti paskelbimą' : 'Paskelbti vartotojams');
      },

      run: function(){
        var self = this;
        self.refreshLabel();
        self.loadDay();

        $('#ss-pub-toggle').on('click', function(){
          if (!self.currentDate) return;
          var makePub = self.published ? 0 : 1;
          var $btn = $(this).prop('disabled', true);
          $.post(ss_admin.ajax_url, {
            action: 'ss_set_changes_published', nonce: ss_admin.nonce,
            date: self.currentDate, published: makePub
          }, function(res){
            $btn.prop('disabled', false);
            if (res.success) { self.setPublished(res.data.published); refreshNotifyStatus(); }
            else alert((res.data && res.data.message) || 'Klaida');
          }).fail(function(){ $btn.prop('disabled', false); alert('Serverio klaida'); });
        });

        $(document).on('click', '.ss-week-btn', function(){
          $('.ss-week-btn').removeClass('active');
          $(this).addClass('active');
          self.rebuildDays($(this).data('start'));
        });

        $(document).on('click', '.ss-day-btn', function(){
          $('.ss-day-btn').removeClass('active');
          $(this).addClass('active');
          self.currentDate = $(this).data('date');
          self.refreshLabel();
          self.loadDay();
        });

        $(document).on('click', '.ss-add-change', function(){
          if (!self.currentDate) { alert('Pirmiausia pasirinkite dieną.'); return; }
          $('#ss-change-modal-title').text('Pridėti pakeitimą');
          $('#ss-change-id').val('');
          $('#ss-change-period').val($(this).data('period'));
          $('#ss-change-class').val('');
          $('#ss-change-comment').val('');
          $('#ss-change-subgroup-wrap').hide();
          $('#ss-change-subgroup').html('<option value="">Visiems pogrupiams</option>');
          $('#ss-change-modal').show();
        });

        $(document).on('click', '.ss-change-edit', function(){
          var $row = $(this).closest('.ss-change-row');
          $('#ss-change-modal-title').text('Redaguoti pakeitimą');
          $('#ss-change-id').val($row.data('id'));
          $('#ss-change-period').val($row.data('period'));
          $('#ss-change-class').val($row.data('class'));
          $('#ss-change-comment').val($row.data('comment'));
          // Restore subgroup
          var savedSg = $row.data('subgroup') || '';
          $('#ss-change-subgroup-wrap').hide();
          $('#ss-change-subgroup').html('<option value="">Visiems pogrupiams</option>');
          if ($row.data('class') && $row.data('period')) {
            SC.fetchSubgroups($row.data('class'), $row.data('period'), savedSg);
          }
          $('#ss-change-modal').show();
        });

        $(document).on('click', '.ss-close-change-modal', function(){
          $('#ss-change-modal').hide();
        });

        // When class changes in modal, check for subgroups
        $('#ss-change-class').on('change', function(){
          var classId  = $(this).val();
          var periodId = $('#ss-change-period').val();
          $('#ss-change-subgroup-wrap').hide();
          $('#ss-change-subgroup').html('<option value="">Visiems pogrupiams</option>');
          if (classId && periodId) SC.fetchSubgroups(classId, periodId, '');
        });

        $('#ss-save-change').on('click', function(){
          var $btn = $(this);
          $btn.prop('disabled', true).text('Saugoma...');
          $.post(ss_admin.ajax_url, {
            action: 'ss_save_change',
            nonce: ss_admin.nonce,
            id: $('#ss-change-id').val(),
            change_date: self.currentDate,
            period_id: $('#ss-change-period').val(),
            class_id: $('#ss-change-class').val(),
            subgroup_label: $('#ss-change-subgroup-wrap').is(':visible') ? $('#ss-change-subgroup').val() : '',
            comment: $('#ss-change-comment').val()
          }, function(res){
            $btn.prop('disabled', false).text('Išsaugoti');
            if (res.success) {
              $('#ss-change-modal').hide();
              self.loadDay();
            } else {
              alert((res.data && res.data.message) || 'Klaida');
            }
          }).fail(function(){
            $btn.prop('disabled', false).text('Išsaugoti');
            alert('Serverio klaida');
          });
        });

        $(document).on('click', '.ss-change-del', function(){
          if (!confirm('Ištrinti šį pakeitimą?')) return;
          $.post(ss_admin.ajax_url, {
            action: 'ss_delete_change', nonce: ss_admin.nonce, id: $(this).data('id'), change_date: self.currentDate
          }, function(res){ if (res.success) self.loadDay(); });
        });

        $('#ss-add-absent').on('click', function(){
          if (!self.currentDate) { alert('Pirmiausia pasirinkite dieną.'); return; }
          var tid = $('#ss-absent-teacher-select').val();
          if (!tid) { alert('Pasirinkite mokytoją.'); return; }
          var fromP = $('#ss-absent-from-period').val() || 0;
          $.post(ss_admin.ajax_url, {
            action: 'ss_save_absent', nonce: ss_admin.nonce,
            absent_date: self.currentDate, teacher_id: tid,
            from_period: fromP
          }, function(res){
            if (res.success) {
              $('#ss-absent-teacher-select').val('');
              $('#ss-absent-from-period').val('0');
              self.loadDay();
            } else {
              alert((res.data && res.data.message) || 'Klaida');
            }
          });
        });

        $(document).on('click', '.ss-absent-del', function(){
          if (!confirm('Pašalinti?')) return;
          $.post(ss_admin.ajax_url, {
            action: 'ss_delete_absent', nonce: ss_admin.nonce, id: $(this).data('id'), absent_date: self.currentDate
          }, function(res){ if (res.success) self.loadDay(); });
        });
      },

      refreshLabel: function(){
        var d = this.currentDate;
        $('#ss-selected-date-label').text(d ? '— ' + d : '');
      },

      rebuildDays: function(weekStart){
        if (!weekStart) return;
        var $sel = $('#ss-day-selector').empty();
        var dayNames = ['Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis'];
        var today = new Date();
        var todayStr = today.getFullYear() + '-' +
          String(today.getMonth()+1).padStart(2,'0') + '-' +
          String(today.getDate()).padStart(2,'0');
        var parts = weekStart.split('-');
        var base = new Date(+parts[0], +parts[1]-1, +parts[2]);
        for (var i = 0; i < 5; i++) {
          var d = new Date(base);
          d.setDate(base.getDate() + i);
          var ds = d.getFullYear() + '-' +
            String(d.getMonth()+1).padStart(2,'0') + '-' +
            String(d.getDate()).padStart(2,'0');
          var isToday = (ds === todayStr);
          var $btn = $('<button type="button" class="ss-day-btn"></button>')
            .attr('data-date', ds)
            .append('<span class="ss-db-name">'+dayNames[i]+'</span>')
            .append('<span class="ss-db-date">'+(d.getMonth()+1+'').padStart(2,'0')+'-'+(d.getDate()+'').padStart(2,'0')+'</span>');
          if (isToday) $btn.append('<span class="ss-db-today">Šiandien</span>');
          $sel.append($btn);
        }
        // Pakeitus savaitę — diena nebepasirinkta, vartotojas turi pasirinkti iš naujo.
        this.currentDate = '';
        this.refreshLabel();
        this.toggleBody();
      },

      // Rodyti dienos turinį tik kai diena pasirinkta; kitaip — raginimą pasirinkti.
      toggleBody: function(){
        var has = !!this.currentDate;
        $('#ss-day-body').toggle(has);
        $('#ss-no-day').toggle(!has);
      },

      loadDay: function(){
        var self = this;
        self.toggleBody();
        if (!self.currentDate) return;
        $('#ss-ch-loading').css('display', 'flex');
        $.post(ss_admin.ajax_url, {
          action: 'ss_get_changes', nonce: ss_admin.nonce, date: self.currentDate
        }, function(res){
          if (!res.success) return;
          self.renderChanges(res.data.changes || []);
          self.renderAbsent(res.data.absent || []);
          self.setPublished(!!res.data.published);
          if (typeof RTE !== 'undefined') RTE.setHTML(res.data.comment || '');
          else $('#ss-changes-comment').val(res.data.comment || '');
          $('#ss-comment-saved').hide();
        }).always(function(){ $('#ss-ch-loading').hide(); });
      },

      esc: function(s){ return $('<div>').text(s == null ? '' : s).html(); },

      renderChanges: function(changes){
        var byPeriod = {};
        changes.forEach(function(c){
          if (!byPeriod[c.period_id]) byPeriod[c.period_id] = [];
          byPeriod[c.period_id].push(c);
        });
        ss_changes_data.periods.forEach(function(p){
          var rows = byPeriod[p.id] || [];
          var $cell = $('#ss-changes-' + p.id).empty();
          if (!rows.length) {
            $cell.append('<span class="ss-empty-text">Pakeitimų nėra</span>');
            return;
          }
          rows.forEach(function(c){
            var sgLabel = c.subgroup_label ? ' <em style="color:#888;font-size:11px;">[' + SC.esc(c.subgroup_label) + ']</em>' : '';
            var $row = $('<div class="ss-change-row"></div>')
              .attr('data-id', c.id)
              .attr('data-period', c.period_id)
              .attr('data-class', c.class_id)
              .attr('data-subgroup', c.subgroup_label || '')
              .attr('data-comment', c.comment || '');
            $row.append('<strong>' + SC.esc(c.class_name) + '</strong>' + sgLabel);
            $row.append('<span class="ss-change-comment">' + SC.esc(c.comment) + '</span>');
            $row.append(' <button class="button-link ss-change-edit" type="button">Redaguoti</button>');
            $row.append(' <button class="button-link ss-change-del" data-id="' + c.id + '" type="button" style="color:var(--sa-red)">Ištrinti</button>');
            $cell.append($row);
          });
        });
      },

      fetchSubgroups: function(classId, periodId, selectedSg){
        var self = this;
        $.post(ss_admin.ajax_url, {
          action: 'ss_get_cell_subgroups',
          nonce: ss_admin.nonce,
          class_id: classId,
          period_id: periodId,
          date: self.currentDate
        }, function(res){
          if (!res.success || !res.data.subgroups.length) {
            $('#ss-change-subgroup-wrap').hide();
            return;
          }
          var $sel = $('#ss-change-subgroup').html('<option value="">Visiems pogrupiams</option>');
          res.data.subgroups.forEach(function(sg){
            var opt = $('<option></option>').val(sg).text(sg);
            if (sg === selectedSg) opt.prop('selected', true);
            $sel.append(opt);
          });
          $('#ss-change-subgroup-wrap').show();
        });
      },

      renderAbsent: function(absent){
        var $list = $('#ss-absent-list').empty();
        if (!absent.length) {
          $list.html('<span class="ss-empty-text">Šiandien visi mokytojai bus mokykloje.</span>');
          return;
        }
        absent.forEach(function(a){
          var $row = $('<span class="ss-absent-row"></span>');
          var fp = parseInt(a.from_period || 0, 10);
          var label = SC.esc(a.teacher_name);
          if (fp > 0) {
            label += ' <em style="color:#888;font-size:11px;">(nuo ' + fp + ' pamokos)</em>';
          }
          $row.append('<span>' + label + '</span>');
          $row.append(' <button class="button-link ss-absent-del" data-id="' + a.id + '" type="button">×</button>');
          $list.append($row);
        });
      }
    };

    // ── Jodit rich-text editor ───────────────────────────────────────────────
    var RTE = { getHTML: function(){ return ''; }, setHTML: function(){} };

    (function initJodit(){
      if (typeof Jodit === 'undefined') {
        // Jodit CDN not yet loaded — retry in 80ms (should never happen in practice)
        return setTimeout(initJodit, 80);
      }
      var j = Jodit.make('#ss-jodit-comment', {
        /* ── Layout ── */
        height: 260,
        minHeight: 100,
        enter: 'BR',                // stay compatible with existing <br>-based data
        language: 'en',
        toolbar: true,
        toolbarAdaptive: false,
        toolbarSticky: false,
        statusbar: false,
        showXPathInStatusbar: false,
        showCharsCounter: false,
        showWordsCounter: false,

        /* ── Toolbar — explicit list, no colour picker, no image/media ── */
        buttons: [
          'bold', 'italic', 'underline', 'strikethrough', '|',
          'ul', 'ol', '|',
          'table', '|',
          'link', '|',
          'source', '|',
          'eraser', 'selectall'
        ],
        /* Explicitly remove anything not in our list that Jodit adds by default */
        removeButtons: [
          'brush', 'font', 'fontsize', 'paragraph',
          'align', 'indent', 'outdent',
          'image', 'video', 'file',
          'copyformat', 'hr', 'symbols',
          'classSpan', 'spellcheck', 'about',
          'superscript', 'subscript',
          'cut', 'copy', 'paste',
          'print', 'preview', 'find'
        ],

        /* ── Paste: strip inline colours/fonts, keep semantic tags ── */
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
        defaultActionOnPaste: 'insert_clear_html',

        /* ── HTML cleanup ── */
        cleanHTML: {
          fillEmptyParagraph: false,
          replaceNBSP: true,
          removeEmptyElements: false
        },

        /* ── Events ── */
        events: {
          /* After any paste: strip leftover inline colour / font declarations */
          afterPaste: function () {
            var val = this.value;
            /* Remove color/background-color/font-family/font-size from style="" */
            val = val.replace(/\bcolor\s*:\s*[^;"']+;?\s*/gi, '');
            val = val.replace(/\bbackground(?:-color)?\s*:\s*[^;"']+;?\s*/gi, '');
            val = val.replace(/\bfont-family\s*:\s*[^;"']+;?\s*/gi, '');
            val = val.replace(/\bfont-size\s*:\s*[^;"']+;?\s*/gi, '');
            /* Drop empty style attributes */
            val = val.replace(/\s*style\s*=\s*["']\s*["']/gi, '');
            this.value = val;
          }
        },

        /* ── Table options ── */
        table: {
          allowCellResize: true,
          allowCellMerge: true
        }
      });

      /* Keep the same RTE interface so the rest of the code needs no changes */
      RTE = {
        getHTML: function () { return j.value; },
        setHTML: function (html) { j.value = html || ''; }
      };
    })();

    SC.run();

    // Save the comment HTML
    $('#ss-save-comment').off('click').on('click', function(){
      if (!SC.currentDate) { alert('Pirmiausia pasirinkite dieną.'); return; }
      var $btn = $(this);
      $btn.prop('disabled', true);
      $.post(ss_admin.ajax_url, {
        action: 'ss_save_changes_comment',
        nonce: ss_admin.nonce,
        date: SC.currentDate,
        comment: RTE.getHTML()
      }, function(res){
        $btn.prop('disabled', false);
        if (res.success) {
          $('#ss-comment-saved').show();
          setTimeout(function(){ $('#ss-comment-saved').fadeOut(); }, 2500);
        }
      }).fail(function(){ $btn.prop('disabled', false); });
    });

    // ── Pakeitimų pranešimų siuntimo būsena (gyvas atnaujinimas) ──
    function renderNotifyStatus(d){
      if (!d) return;
      $('#ss-notify-status').toggleClass('is-pub', !!d.published);
      if (d.target_label) $('#ss-ns-day').text(d.target_label);
      if (typeof d.html === 'string') $('#ss-ns-line').html(d.html);
    }
    function refreshNotifyStatus(){
      if (!$('#ss-notify-status').length) return;
      $.post(ss_admin.ajax_url, { action: 'ss_ch_notify_status', nonce: ss_admin.nonce }, function(res){
        if (res && res.success) renderNotifyStatus(res.data);
      });
    }
    if ($('#ss-notify-status').length) setInterval(refreshNotifyStatus, 30000);
  }

  init();
});
</script>
</div>
