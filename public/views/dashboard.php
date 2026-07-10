<?php if (!defined('ABSPATH')) exit;
$days       = ['Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis'];
$days_short = ['Pr','An','Tr','Kt','Pn'];
$_ss_real_dow = SS_Time::dow(); // 1=Pr … 7=Sk (WP timezone)
$_ss_hour     = SS_Time::hour(); // 0-23 (WP timezone)
// today_num: realus šiandienos nr. tik darbo dienomis (0 = savaitgalis, nerodo „Šiandien")
$today_num = SS_Time::today_num();
// active_day: kuri diena rodoma automatiškai (centralizuota logika SS_Time)
$active_day = SS_Time::active_day();
// Use plugin logo (set in settings); fall back to theme custom logo
$logo_id    = (int) get_option('ss_logo_id', 0);
$logo_url   = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : get_option('ss_logo_url', '');
if (!$logo_url) {
    $_theme_lid = get_theme_mod('custom_logo');
    $logo_url   = $_theme_lid ? wp_get_attachment_image_url($_theme_lid, 'medium') : '';
}
$logo_full  = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : $logo_url;
$site_name  = get_option('ss_school_name', SS_SCHOOL_NAME);
$is_admin   = current_user_can('manage_options');
$is_teacher = in_array('ss_teacher', (array)($user->roles ?? []));
$roles      = (array)($user->roles ?? []);
$is_employee = !empty($is_employee);   // from setup_dashboard_vars
$can_view_all = !empty($can_view_all); // from setup_dashboard_vars (teisė „matyti visų tvarkaraščius")
$role_lbl   = $is_admin ? 'Administratorius'
            : ($is_teacher ? 'Mokytojas'
            : ($is_employee ? 'Darbuotojas' : 'Mokinys'));
/* Staff (admin / employee) have no personal class schedule → they get a
   "Dashboard" (the all-schedules overview) instead of a personal timetable. */
$staff      = $is_admin || $is_employee;
/* IMP atskiri (Mokytojų / Kabinetų) skirtukai rodomi tik kai vartotojas NEturi
   bendro „Visi tvarkaraščiai" — kitaip jie dubliuotųsi. */
$show_imp_tabs = !empty($is_imp) && !$can_view_all;
$_req_tab   = isset($_GET['tab']) ? preg_replace('/[^a-z]/', '', $_GET['tab']) : '';
$_role_tabs = array('personal');
$_role_tabs[] = $staff ? 'allschedules' : 'schedule';
if ($can_view_all) $_role_tabs[] = 'allschedules';
if ($show_imp_tabs) { $_role_tabs[] = 'teachers'; $_role_tabs[] = 'rooms'; }
$active_tab = in_array($_req_tab, $_role_tabs, true) ? $_req_tab : ($staff ? 'allschedules' : 'schedule');
$grid       = $sched['grid']    ?? [];
$sc_periods = $sched['periods'] ?? $periods;
/* Konkretūs HEX (ne var(--x)) — spalvos laukas ir sanitize_hex_color var() nepriima. */
$palette    = ['#4F8952','#14452F','#224768','#D05155','#37775B','#012A36','#F5D297','#6EAF93'];
$sg_prefs   = SS_Database::get_user_subgroup_prefs(get_current_user_id());
// Ątspėk ad user preference
$_atspek_uid  = get_current_user_id();
$sett_atspek_mode  = get_user_meta($_atspek_uid, 'ss_atspek_mode',  true) ?: 'day';
$sett_atspek_hours = (int)(get_user_meta($_atspek_uid, 'ss_atspek_hours', true) ?: 24);
if (!in_array($sett_atspek_mode, ['day','hours','off'], true)) $sett_atspek_mode = 'day';
$sett_atspek_hours = min(168, max(1, $sett_atspek_hours));

// Helper: pick best teacher label (full name preferred, fallback short)
if (!function_exists('ss_teacher_label')) {
    function ss_teacher_label($row) {
        if (!empty($row->teacher_name)) return $row->teacher_name;
        if (!empty($row->teacher_short)) return $row->teacher_short;
        return '';
    }
}

// Personal items grouped by weekday (already sorted by start_time) — woven into the schedule.
$personal_by_day = array();
foreach (($personal ?? array()) as $_pit) {
    $personal_by_day[(int)$_pit->day_of_week][] = $_pit;
}
// For the WEEK grid: place each personal item in exactly ONE period cell — the first
// period that ends after the item starts. Items before the first / in a gap / after the
// last lesson are clamped to the nearest period, so they never drift outside the grid.
$personal_cell = array();
foreach ($personal_by_day as $_pday => $_items) {
    foreach ($_items as $_it) {
        $_st = substr((string)$_it->start_time, 0, 5);
        $target = null; $last_pp = null;
        foreach (($sc_periods ?? array()) as $_pp) {
            $last_pp = $_pp;
            if ($_st < substr((string)$_pp->end_time, 0, 5)) { $target = $_pp; break; }
        }
        if ($target === null) $target = $last_pp;   // after the last lesson → last period
        if ($target) $personal_cell[$_pday][(int)$target->id][] = $_it;
    }
}

// Helper: render one personal item as a lesson card (interleaved with real lessons).
if (!function_exists('ss_personal_card')) {
    function ss_personal_card($it) {
        $color = (isset($it->color) && $it->color) ? $it->color : 'var(--g)';
        $start = substr((string)$it->start_time, 0, 5);
        $end   = substr((string)$it->end_time,   0, 5);
        ob_start(); ?>
        <div class="ss-lesson ss-lesson-personal" style="--lc:<?= esc_attr($color) ?>">
          <div class="ss-lesson-num" aria-hidden="true">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9.707 5.707a1 1 0 00-1.414-1.414L9 12.586l-1.293-1.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          </div>
          <div class="ss-lesson-time">
            <span><?= esc_html($start) ?></span>
            <span><?= esc_html($end) ?></span>
          </div>
          <div class="ss-lesson-body">
            <div class="ss-lesson-subj"><?= esc_html($it->subject ?: 'Asmeninis įrašas') ?></div>
            <?php if (!empty($it->notes)): ?><div class="ss-lesson-tchr"><?= esc_html($it->notes) ?></div><?php endif; ?>
            <span class="ss-lesson-personal-badge">Asmeninis</span>
          </div>
        </div>
        <?php return ob_get_clean();
    }
}
?>
<div class="ss-app" id="ss-app">

  <?php
    $ss_on_dashboard = true;
    $ss_active       = $active_tab;
    include SS_PLUGIN_DIR . 'public/views/partials/app-nav.php';
    include SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php';
    if (!get_user_meta(get_current_user_id(), 'ss_onboarding_done', true)) {
        include SS_PLUGIN_DIR . 'public/views/partials/onboarding.php';
    }
  ?>

  <!-- ════ MAIN ════ -->
  <main class="ss-main" id="ss-main">

    <!-- Spausdinimo antraštė (matoma tik @media print) -->
    <div class="ss-print-header">
      <h1><?= esc_html($site_name) ?></h1>
      <div class="ss-ph-sub"><?= esc_html($role_lbl) ?> — <?= esc_html($user->display_name) ?></div>
      <div class="ss-ph-meta">Spausdinta: <?= esc_html(SS_Time::date('Y-m-d H:i')) ?></div>
    </div>

    <?php
    /* Mokytojų sąrašą rodom tik AKTUALIAI dienai */
    $banner_day = $active_day; // jau apskaičiuota viršuje (po 16.00 → rytoj; penkt. po 16.00 / savaitgalį → pirmadienis)
    $banner_tids = array();
    if (!empty($absent_map[$banner_day])) {
      $banner_tids = $absent_map[$banner_day]; // [tid => from_period]
    }
    $absent_items = array();
    if (!empty($banner_tids)) {
      global $wpdb;
      $ids = array_keys($banner_tids);
      $ph  = implode(',', array_fill(0, count($ids), '%d'));
      $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}ss_teachers WHERE id IN ($ph) ORDER BY name ASC", $ids));
      foreach ($rows as $r) { $absent_items[(int)$r->id] = $r->name; }
    }
    // Bendras dienos komentaras (kaip pakeitimuose)
    $banner_date_dt     = new DateTime($active_monday, SS_Time::tz());
    $banner_date_dt->modify('+' . ($banner_day - 1) . ' days');
    $banner_date        = $banner_date_dt->format('Y-m-d');
    // Komentaras matomas tik kai diena paskelbta (admin mato visada — peržiūrai).
    $banner_day_comment = ($is_admin || SS_Database::is_changes_published($banner_date))
        ? get_option('ss_changes_comment_' . $banner_date, '') : '';
    $banner_has_comments = ($banner_day_comment !== '');
    // Antraštės tekstas pagal aktyvią dieną:
    //   Šiandien (jei aktyvi diena == real_dow ir savaitės diena),
    //   Pirmadienį (jei savaitgalis arba penkt. po 16.00 -> rodom pirmadienį),
    //   Rytoj (kitais atvejais — po 16.00).
    if ($_ss_real_dow >= 1 && $_ss_real_dow <= 5 && $banner_day == $_ss_real_dow) {
        $banner_when = 'Šiandien';
    } elseif ($banner_day === 1 && ($_ss_real_dow >= 6 || ($_ss_real_dow === 5 && $_ss_hour >= 16))) {
        $banner_when = 'Pirmadienį';
    } else {
        $banner_when = 'Rytoj';
    }
    ?>
    <?php
    // ════ ĄSPĖK + APKLAUSOS / VIKTORINOS KARUSELĖ ════
    $promo_inline       = false;
    $promo_atspek_mode  = $sett_atspek_mode;
    $promo_atspek_hours = $sett_atspek_hours;
    include SS_PLUGIN_DIR . 'public/views/partials/promo-carousel.php';
    ?>

    <!-- ════ PAKEITIMAI ════ -->
    <?php if (!empty($absent_items) || $banner_has_comments): ?>
    <div class="ss-absent-banner" style="margin-bottom:0px;">
      <div class="ss-absent-banner-head">
        <span><?= esc_html($banner_when) ?> nebus:</span>
      </div>
      <div class="ss-absent-banner-list">
        <?php foreach ($absent_items as $tid => $tname):
          $fp = (int)($banner_tids[$tid] ?? 0);
        ?>
        <span class="ss-absent-banner-item">
          <strong><?= esc_html($tname) ?></strong>
          <?php if ($fp > 0): ?>
            <span class="ss-absent-from-pamokos">nuo <?= (int)$fp ?> pamokos</span>
          <?php endif; ?>
        </span>
        <?php endforeach; ?>
      </div>
      <?php if ($banner_day_comment !== ''): ?>
      <div class="ss-absent-banner-comments">
        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" style="flex-shrink:0;margin-top:2px"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        <div class="ss-day-comment-html"><?= wp_kses_post($banner_day_comment) ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ════ TAB: TVARKARAŠTIS ════ -->
    <section class="ss-tab<?= $active_tab==='schedule'?' active':'' ?>" id="tab-schedule">
      <div class="ss-tab-inner">

        <?php if (empty($sc_periods) || empty($grid)): ?>
        <div class="ss-empty">
          <div class="ss-empty-ico"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="6" y="8" width="36" height="36" rx="4"/><path d="M16 4v8M32 4v8M6 20h36"/></svg></div>
          <h3>Tvarkaraštis nenustatytas</h3>
          <p>Jūs dar nepriskirtas(-a) jokiam tvarkaraščiui arba tvarkaraštis dar neįkeltas.<br>Kreipkitės į mokyklos administratorių.</p>
        </div>
        <?php else: ?>

        <div class="ss-sched-toolbar">
          <h2 class="ss-tab-title">Klasės tvarkaraštis</h2>
          <div class="ss-toolbar-right">
            <!-- View toggle -->
            <div class="ss-view-btns" role="group">
              <button class="ss-vbtn active" id="ss-view-day" title="Dienos rodinys">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                Diena
              </button>
              <button class="ss-vbtn" id="ss-view-week" title="Savaitės rodinys">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 4a2 2 0 00-2 2v1h14V6a2 2 0 00-2-2H5zm12 4H3v7a2 2 0 002 2h10a2 2 0 002-2V8z" clip-rule="evenodd"/></svg>
                Savaitė
              </button>
            </div>
            <!-- PDF download -->
            <button class="ss-pdf-btn" id="ss-download-pdf" title="Parsisiųsti tvarkaraštį PDF formatu">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
              PDF
            </button>
          </div>
        </div>

        <!-- ══ DAY VIEW ══ -->
        <div id="ss-day-view">
          <div class="ss-day-tabs" role="tablist">
            <?php foreach ($days as $i => $day):
              $d = $i + 1;
              $is_today  = ($today_num > 0 && $d === $today_num);
              $is_active = ($d === $active_day);
              $has = false;
              foreach ($grid as $pid => $dcol) { if (!empty($dcol[$d])) { $has=true; break; } }
            ?>
            <button class="ss-dtab <?= $is_active?'active':'' ?> <?= !$has?'empty':'' ?>"
                    data-day="<?= $d ?>" role="tab" aria-selected="<?= $is_active?'true':'false' ?>">
              <span class="ss-dtab-short"><?= esc_html($days_short[$i]) ?></span>
              <span class="ss-dtab-long"><?= esc_html($day) ?></span>
              <?php if ($is_today): ?><span class="ss-today-pip"></span><?php endif; ?>
            </button>
            <?php endforeach; ?>
          </div>

          <?php foreach ($days as $i => $day):
            $d = $i + 1;
            $is_today  = ($today_num > 0 && $d === $today_num);
            $is_active = ($d === $active_day);
          ?>
          <div class="ss-dpanel <?= $is_active?'active':'' ?>" data-day="<?= $d ?>">
            <div class="ss-dpanel-hd">
              <span class="ss-dpanel-name"><?= esc_html($day) ?></span>
              <?php if ($is_today): ?><span class="ss-today-badge">Šiandien</span><?php endif; ?>
            </div>
            <div class="ss-lessons-list">
              <?php
              $had = false;
              $pers = $personal_by_day[$d] ?? array();
              $pi = 0; $np = count($pers);
              foreach ($sc_periods as $p):
                // Weave in personal items that start before this period.
                while ($pi < $np && substr((string)$pers[$pi]->start_time,0,5) < substr((string)$p->start_time,0,5)) {
                  echo ss_personal_card($pers[$pi]); $pi++; $had = true;
                }
                if (empty($grid[$p->id][$d])) continue;
                $entries   = $grid[$p->id][$d];
                $had       = true;
                $has_sub   = count($entries) > 1 || !empty($entries[0]->subgroup_label ?? '');
                $class_id  = $entries[0]->class_id ?? 0;
                $pref_key  = "{$class_id}_{$d}_{$p->id}";
                $chosen_id = $sg_prefs[$pref_key] ?? null;
                $shown     = $entries[0];
                if ($chosen_id) {
                  foreach ($entries as $e) { if ((int)$e->id===(int)$chosen_id){$shown=$e;break;} }
                }
                $is_free_slot = !empty($shown->is_free) || (int)($shown->lesson_id ?? 1) === 0;
                $color = $is_free_slot ? '#888888' : ($shown->lesson_color ?: 'var(--g)');
                $tchr  = $is_free_slot ? '' : ss_teacher_label($shown);
                $tid   = $is_free_slot ? 0 : (isset($shown->lesson_teacher_id) ? (int)$shown->lesson_teacher_id : 0);
                $is_cancelled = false;
                if (!$is_free_slot && $tid && !empty($absent_map[$d]) && isset($absent_map[$d][$tid])) {
                  $from_p = (int)$absent_map[$d][$tid];
                  if ($from_p === 0 || (int)$p->period_number >= $from_p) {
                    $is_cancelled = true;
                  }
                }
                // Change matching: specific subgroup first, then fallback to all-subgroup ('')
                $sg_key = $shown->subgroup_label ?? '';
                $cell_changes = $is_free_slot ? [] : ($changes_map[$d][$class_id][$p->id] ?? []);
                $change_comment = $cell_changes[$sg_key] ?? $cell_changes[''] ?? null;
                $has_change = ($change_comment !== null && $change_comment !== '');
              ?>
              <div class="ss-lesson <?= $has_sub?'ss-has-sub':'' ?> <?= $is_free_slot?'ss-lesson-free':'' ?> <?= ($is_cancelled||$has_change)?'ss-lesson-cancelled':'' ?>" style="--lc:<?= esc_attr($color) ?>">
                <div class="ss-lesson-num"><?= esc_html($p->period_number) ?></div>
                <div class="ss-lesson-time">
                  <span><?= esc_html(substr($p->start_time,0,5)) ?></span>
                  <span><?= esc_html(substr($p->end_time,0,5)) ?></span>
                </div>
                <div class="ss-lesson-body">
                  <div class="ss-lesson-subj"><?= $is_free_slot ? 'Nėra pamokos' : esc_html($shown->subject) ?></div>
                  <?php if (!$is_free_slot && $tchr): ?><div class="ss-lesson-tchr"><?= esc_html($tchr) ?></div><?php endif; ?>
                  <?php if (!$is_free_slot && !empty($shown->room)): ?><div class="ss-lesson-room"><?= esc_html($shown->room) ?></div><?php endif; ?>
                  <?php if ($has_change): ?>
                    <div class="ss-lesson-cancel-badge" aria-label="Pakeitimas"><?= esc_html($change_comment) ?></div>
                  <?php elseif ($is_cancelled): ?>
                    <div class="ss-lesson-cancel-badge" aria-label="Mokytojas neatvyks">Mokytojo nebus</div>
                  <?php endif; ?>
                </div>
                <?php if ($has_sub): ?>
                <div class="ss-sub-picker">
                  <span class="ss-sub-label">Pogrupis:</span>
                  <?php foreach ($entries as $e):
                    $active = ((int)$e->id===(int)($chosen_id?:($entries[0]->id??0)));
                    $e_is_free = (!empty($e->is_free) || (int)($e->lesson_id??1)===0);
                    $bc = $e_is_free ? '#888888' : ($e->lesson_color?:'var(--g)');
                    $btn_label = $e->subgroup_label ?: ($e_is_free ? 'Nėra pamokos' : $e->subject);
                  ?>
                  <button class="ss-sub-btn <?= $active?'active':'' ?>"
                          data-id="<?= (int)$e->id ?>" data-class="<?= (int)$class_id ?>"
                          data-day="<?= $d ?>" data-period="<?= (int)$p->id ?>"
                          style="--bc:<?= esc_attr($bc) ?>">
                    <?= esc_html($btn_label) ?>
                  </button>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach;
              // Personal items that start after the last lesson.
              while ($pi < $np) { echo ss_personal_card($pers[$pi]); $pi++; $had = true; }
              if(!$had): ?><div class="ss-no-lessons">Šią dieną pamokų nėra</div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div><!-- /day-view -->

        <!-- ══ WEEK VIEW ══ -->
        <div id="ss-week-view" style="display:none">
          <div class="ss-week-scroll">
            <table class="ss-week-table" id="ss-week-table">
              <thead>
                <tr>
                  <th class="ss-wt-time">Laikas</th>
                  <?php foreach ($days as $i=>$day): $d=$i+1; ?>
                  <th class="ss-wt-dh <?= $d===$active_day?'ss-wt-today':'' ?>">
                    <div class="ss-wt-dh-inner">
                      <span class="ss-wt-dl"><?= esc_html($day) ?></span>
                      <span class="ss-wt-ds"><?= esc_html($days_short[$i]) ?></span>
                      <!--<?php if ($today_num > 0 && $d===$today_num): ?><span class="ss-wt-dot"></span><?php endif; ?>-->
                    </div>
                  </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sc_periods as $p): ?>
                <tr class="ss-wt-row">
                  <td class="ss-wt-time-cell">
                    <strong><?= esc_html($p->period_number) ?></strong>
                    <span><?= esc_html(substr($p->start_time,0,5)) ?></span>
                    <span><?= esc_html(substr($p->end_time,0,5)) ?></span>
                  </td>
                  <?php foreach ($days as $i=>$day): $d=$i+1;
                    $entries  = $grid[$p->id][$d] ?? [];
                    $class_id = !empty($entries)?($entries[0]->class_id??0):0;
                    $pref_key = "{$class_id}_{$d}_{$p->id}";
                    $chosen_id= $sg_prefs[$pref_key]??null;
                    $shown    = !empty($entries)?$entries[0]:null;
                    if($chosen_id&&$shown){foreach($entries as $e){if((int)$e->id===(int)$chosen_id){$shown=$e;break;}}}
                    $has_sub  = count($entries)>1||(!empty($entries)&&!empty($entries[0]->subgroup_label??''));
                    $wv_is_free = $shown && (!empty($shown->is_free) || (int)($shown->lesson_id??1)===0);
                    $color    = $shown?($wv_is_free?'#888888':($shown->lesson_color?:'var(--g)')):null;
                    $tchr     = ($shown && !$wv_is_free) ? ss_teacher_label($shown) : '';
                    $tid      = $shown && isset($shown->lesson_teacher_id) ? (int)$shown->lesson_teacher_id : 0;
                    $cell_cancelled = false;
                    if ($tid && !empty($absent_map[$d]) && isset($absent_map[$d][$tid])) {
                      $wv_from_p = (int)$absent_map[$d][$tid];
                      if ($wv_from_p === 0 || (int)$p->period_number >= $wv_from_p) {
                        $cell_cancelled = true;
                      }
                    }
                    $wv_sg_key    = $shown ? ($shown->subgroup_label ?? '') : '';
                    $wv_changes   = $changes_map[$d][$class_id][$p->id] ?? [];
                    $wv_change    = $wv_changes[$wv_sg_key] ?? $wv_changes[''] ?? null;
                    $wv_has_change = ($wv_change !== null && $wv_change !== '');
                  ?>
                  <td class="ss-wt-cell <?= $d===$active_day?'ss-wt-today':'' ?>"
                      data-period="<?= (int)$p->id ?>" data-day="<?= $d ?>"
                      data-class="<?= (int)$class_id ?>">
                    <?php if ($shown): ?>
                    <div class="ss-wt-lesson <?= ($cell_cancelled||$wv_has_change)?'ss-wt-cancelled':'' ?>" style="--lc:<?= esc_attr($color) ?>">
                      <span class="ss-wt-subj"><?= $wv_is_free ? 'Nėra pamokos' : esc_html($shown->subject) ?></span>
                      <?php if ($tchr): ?><span class="ss-wt-tchr"><?= esc_html($tchr) ?></span><?php endif; ?>
                      <?php if ($wv_has_change): ?>
                        <span class="ss-wt-change-badge"><?= esc_html($wv_change) ?></span>
                      <?php endif; ?>
                      <?php if ($has_sub): ?>
                      <!-- Subgroup entries for week view JS picker -->
                      <span class="ss-wt-sub-ico ss-wt-sub-trigger" title="Pasirinkite pogrupį"
                            data-period="<?= (int)$p->id ?>" data-day="<?= $d ?>"
                            data-class="<?= (int)$class_id ?>">⇌</span>
                      <div class="ss-wt-sub-data" style="display:none"><?php
                        foreach ($entries as $e) {
                          $act = ((int)$e->id===(int)($chosen_id?:($entries[0]->id??0)));
                          $e_is_free = (!empty($e->is_free) || (int)($e->lesson_id??1)===0);
                          $e_color = $e_is_free ? '#888888' : ($e->lesson_color?:'var(--g)');
                          $e_label = $e->subgroup_label ?: ($e_is_free ? 'Nėra pamokos' : $e->subject);
                          echo '<span class="ss-wt-sub-item'.($act?' active':'').'" '
                             . 'data-id="'.(int)$e->id.'" '
                             . 'data-color="'.esc_attr($e_color).'">'
                             . esc_html($e_label).'</span>';
                        }
                      ?></div>
                      <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php foreach (($personal_cell[$d][$p->id] ?? array()) as $pcit): ?>
                    <div class="ss-wt-lesson ss-wt-personal" style="--lc:<?= esc_attr($pcit->color ?: 'var(--g)') ?>">
                      <span class="ss-wt-subj"><?= esc_html($pcit->subject ?: 'Asmeninis') ?></span>
                      <span class="ss-wt-tchr"><?= esc_html(substr((string)$pcit->start_time,0,5)) ?>–<?= esc_html(substr((string)$pcit->end_time,0,5)) ?></span>
                    </div>
                    <?php endforeach; ?>
                  </td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="ss-week-hint">↔ Slinkite į šoną norėdami matyti visas dienas</p>
        </div><!-- /week-view -->

        <?php endif; ?>
      </div>
    </section>

    <?php if ($can_view_all): ?>
    <!-- ════ TAB: VISI TVARKARAŠČIAI (admin / darbuotojas / „matyti visų tvarkaraščius" teisė) ════ -->
    <section class="ss-tab<?= $active_tab==='allschedules'?' active':'' ?>" id="tab-allschedules">
      <div class="ss-tab-inner">
        <div class="ss-tab-toolbar">
          <h2 class="ss-tab-title">Visi tvarkaraščiai</h2>
        </div>

        <!-- Type selector -->
        <div class="ss-type-tabs" role="tablist">
          <button type="button" class="ss-type-tab active" data-type="students" role="tab">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
            Klasės
          </button>
          <button type="button" class="ss-type-tab" data-type="teachers" role="tab">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
            Mokytojai
          </button>
          <button type="button" class="ss-type-tab" data-type="rooms" role="tab">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v14a1 1 0 01-1 1h-3v-3a2 2 0 00-4 0v3H5a1 1 0 01-1-1V4zm3 1a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h.01a1 1 0 100-2H10zm3 0a1 1 0 100 2h.01a1 1 0 100-2H13zM7 9a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h.01a1 1 0 100-2H10zm3 0a1 1 0 100 2h.01a1 1 0 100-2H13z" clip-rule="evenodd"/></svg>
            Kabinetai
          </button>
        </div>

        <!-- Klasės -->
        <div class="ss-all-panel ss-all-students active">
          <div class="ss-search-box">
            <svg class="ss-search-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
            <input type="text" id="ss-class-search" class="ss-search-input" placeholder="Ieškoti klasės pagal pavadinimą...">
          </div>
          <div class="ss-classes-list" id="ss-classes-list">
            <?php if (!empty($all_classes)): ?>
              <?php foreach ($all_classes as $c): ?>
              <button type="button" class="ss-class-card" data-id="<?= (int)$c->id ?>" data-name="<?= esc_attr(mb_strtolower($c->name)) ?>">
                <div class="ss-cc-name"><?= esc_html($c->name) ?></div>
                <svg class="ss-tc-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
              </button>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="ss-empty"><p>Nėra klasių</p></div>
            <?php endif; ?>
          </div>
          <div id="ss-class-schedule-wrap" style="display:none;">
            <button type="button" class="ss-back-btn" id="ss-back-to-classes">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              Atgal į sąrašą
            </button>
            <div id="ss-class-schedule-loading" class="ss-empty" style="display:none;padding:24px;"><p>Kraunama...</p></div>
            <div id="ss-class-schedule-content"></div>
          </div>
        </div>

        <!-- Mokytojai -->
        <div class="ss-all-panel ss-all-teachers">
          <div class="ss-search-box">
            <svg class="ss-search-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
            <input type="text" id="ss-all-teacher-search" class="ss-search-input" placeholder="Ieškoti mokytojo pagal vardą ar pavardę...">
          </div>
          <div class="ss-teachers-list" id="ss-all-teachers-list">
            <?php if (!empty($all_teachers)): ?>
              <?php foreach ($all_teachers as $t): ?>
              <button type="button" class="ss-teacher-card" data-id="<?= (int)$t->id ?>" data-name="<?= esc_attr(mb_strtolower($t->name)) ?>">
                <div class="ss-tc-info">
                  <span class="ss-tc-name"><?= esc_html($t->name) ?></span>
                </div>
                <svg class="ss-tc-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
              </button>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="ss-empty"><p>Nėra mokytojų</p></div>
            <?php endif; ?>
          </div>
          <div id="ss-all-teacher-schedule-wrap" style="display:none;">
            <button type="button" class="ss-back-btn" id="ss-back-to-all-teachers">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              Atgal į sąrašą
            </button>
            <div id="ss-all-teacher-schedule-loading" class="ss-empty" style="display:none;padding:24px;"><p>Kraunama...</p></div>
            <div id="ss-all-teacher-schedule-content"></div>
          </div>
        </div>

        <!-- Kabinetai -->
        <div class="ss-all-panel ss-all-rooms">
          <div class="ss-search-box">
            <svg class="ss-search-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
            <input type="text" id="ss-all-room-search" class="ss-search-input" placeholder="Ieškoti kabineto pagal pavadinimą...">
          </div>
          <div class="ss-teachers-list" id="ss-all-rooms-list">
            <?php if (!empty($all_rooms)): ?>
              <?php foreach ($all_rooms as $r): ?>
              <button type="button" class="ss-room-card" data-id="<?= (int)$r->id ?>" data-name="<?= esc_attr(mb_strtolower($r->name)) ?>">
                <div class="ss-tc-info">
                  <span class="ss-tc-name"><?= esc_html($r->name) ?></span>
                </div>
                <svg class="ss-tc-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
              </button>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="ss-empty"><p>Nėra kabinetų</p></div>
            <?php endif; ?>
          </div>
          <div id="ss-all-room-schedule-wrap" style="display:none;">
            <button type="button" class="ss-back-btn" id="ss-back-to-all-rooms">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              Atgal į sąrašą
            </button>
            <div id="ss-all-room-schedule-loading" class="ss-empty" style="display:none;padding:24px;"><p>Kraunama...</p></div>
            <div id="ss-all-room-schedule-content"></div>
          </div>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <!-- ════ TAB: ASMENINIAI ════ -->
    <section class="ss-tab<?= $active_tab==='personal'?' active':'' ?>" id="tab-personal">
      <div class="ss-tab-inner">
        <div class="ss-tab-toolbar">
          <h2 class="ss-tab-title">Asmeniniai įrašai</h2>
          <button class="ss-add-btn" id="ss-open-personal">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
            Pridėti
          </button>
        </div>
        <?php if (empty($personal)): ?>
        <div class="ss-empty">
          <div class="ss-empty-ico"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 4h24a4 4 0 014 4v32a4 4 0 01-4 4H12a4 4 0 01-4-4V8a4 4 0 014-4z"/><path d="M16 16h16M16 24h16M16 32h8"/></svg></div>
          <h3>Nėra asmeninių įrašų</h3>
          <p>Pridėkite konsultacijas, renginius ar kitas asmenines pamokas</p>
        </div>
        <?php else:
          $by_day=[];foreach($personal as $it)$by_day[$it->day_of_week][]=$it;ksort($by_day);
          foreach($by_day as $d=>$items):
        ?>
        <div class="ss-p-block">
          <div class="ss-p-dlabel"><?= esc_html($days[$d-1]??"Diena $d") ?></div>
          <?php foreach($items as $it):$c=$it->color?:'var(--g)'; ?>
          <div class="ss-p-item" style="--lc:<?= esc_attr($c) ?>">
            <div class="ss-p-time">
              <?php if($it->start_time): ?>
              <span><?= esc_html(substr($it->start_time,0,5)) ?></span>
              <span><?= esc_html(substr($it->end_time,0,5)) ?></span>
              <?php else:?><span>–</span><?php endif;?>
            </div>
            <div class="ss-p-body">
              <div class="ss-p-subj"><?= esc_html($it->subject) ?></div>
              <?php if($it->notes):?><div class="ss-p-notes"><?= esc_html($it->notes) ?></div><?php endif;?>
            </div>
            <div class="ss-p-acts">
              <button class="ss-p-btn ss-p-edit" data-id="<?=$it->id?>" data-item='<?=esc_attr(json_encode($it))?>'><svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg></button>
              <button class="ss-p-btn ss-p-del" data-id="<?=$it->id?>"><svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
            </div>
          </div>
          <?php endforeach;?>
        </div>
        <?php endforeach;endif;?>
      </div>
    </section>

    <?php if ($show_imp_tabs): ?>
    <!-- ════ TAB: MOKYTOJŲ TVARKARAŠČIAI ════ -->
    <section class="ss-tab<?= $active_tab==='teachers'?' active':'' ?>" id="tab-teachers">
      <div class="ss-tab-inner">
        <div class="ss-tab-toolbar">
          <h2 class="ss-tab-title">Mokytojų tvarkaraščiai</h2>
        </div>

        <?php if (empty($all_teachers)): ?>
        <div class="ss-empty">
          <div class="ss-empty-ico"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="24" cy="16" r="6"/><path d="M12 40c0-6.6 5.4-12 12-12s12 5.4 12 12"/></svg></div>
          <h3>Nėra mokytojų su tvarkaraščiais</h3>
          <p>Kol kas nėra įkeltų mokytojų tvarkaraščių</p>
        </div>
        <?php else: ?>

        <!-- Search box -->
        <div class="ss-search-box">
          <svg class="ss-search-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
          <input type="text" id="ss-teacher-search" class="ss-search-input" placeholder="Ieškoti mokytojo pagal vardą ar pavardę...">
        </div>

        <!-- Teachers list -->
        <div class="ss-teachers-list" id="ss-teachers-list">
          <?php foreach ($all_teachers as $t): ?>
          <button type="button" class="ss-teacher-card" data-id="<?= (int)$t->id ?>" data-name="<?= esc_attr(mb_strtolower($t->name)) ?>">
            <div class="ss-tc-info">
              <span class="ss-tc-name"><?= esc_html($t->name) ?></span>
            </div>
            <svg class="ss-tc-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
          </button>
          <?php endforeach; ?>
        </div>

        <div id="ss-teacher-schedule-wrap" style="display:none;">
          <button type="button" class="ss-back-btn" id="ss-back-to-teachers">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            Atgal į sąrašą
          </button>
          <div id="ss-teacher-schedule-loading" class="ss-empty" style="display:none;padding:24px;">
            <p>Kraunama...</p>
          </div>
          <div id="ss-teacher-schedule-content"></div>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($show_imp_tabs): ?>
    <!-- ════ TAB: KABINETŲ TVARKARAŠČIAI (IMP / darbuotojams — admin mato per „Visi tvarkaraščiai“) ════ -->
    <section class="ss-tab<?= $active_tab==='rooms'?' active':'' ?>" id="tab-rooms">
      <div class="ss-tab-inner">
        <div class="ss-tab-toolbar">
          <h2 class="ss-tab-title">Kabinetų tvarkaraščiai</h2>
        </div>

        <?php if (empty($all_rooms)): ?>
        <div class="ss-empty">
          <div class="ss-empty-ico"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="10" y="6" width="28" height="36" rx="3"/><path d="M28 42v-8M20 14h.01M28 14h.01M20 22h.01M28 22h.01"/></svg></div>
          <h3>Nėra kabinetų su tvarkaraščiais</h3>
          <p>Kol kas nėra priskirtų kabinetų tvarkaraščiuose</p>
        </div>
        <?php else: ?>

        <!-- Search box -->
        <div class="ss-search-box">
          <svg class="ss-search-ico" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
          <input type="text" id="ss-room-search" class="ss-search-input" placeholder="Ieškoti kabineto pagal pavadinimą...">
        </div>

        <!-- Rooms list -->
        <div class="ss-teachers-list" id="ss-rooms-list">
          <?php foreach ($all_rooms as $r): ?>
          <button type="button" class="ss-room-card" data-id="<?= (int)$r->id ?>" data-name="<?= esc_attr(mb_strtolower($r->name)) ?>">
            <div class="ss-tc-info">
              <span class="ss-tc-name"><?= esc_html($r->name) ?></span>
            </div>
            <svg class="ss-tc-arrow" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
          </button>
          <?php endforeach; ?>
        </div>

        <div id="ss-room-schedule-wrap" style="display:none;">
          <button type="button" class="ss-back-btn" id="ss-back-to-rooms">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            Atgal į sąrašą
          </button>
          <div id="ss-room-schedule-loading" class="ss-empty" style="display:none;padding:24px;">
            <p>Kraunama...</p>
          </div>
          <div id="ss-room-schedule-content"></div>
        </div>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    

  </main>

  <!-- Hidden PDF data for JS -->
  <div id="ss-pdf-meta" style="display:none"
       data-logo="<?= esc_attr($logo_full) ?>"
       data-school="<?= esc_attr($site_name) ?>"
       data-user="<?= esc_attr($user->display_name) ?>"
       data-role="<?= esc_attr($role_lbl) ?>"
       data-date="<?= esc_attr(SS_Time::date('Y-m-d H:i')) ?>">
  </div>

  <!-- Week subgroup popup (shared) -->
  <div class="ss-wt-sub-popup" id="ss-wt-sub-popup" style="display:none"></div>
</div><!-- /ss-app -->
<script>
(function(){
  var t = new URLSearchParams(window.location.search).get('tab');
  if (!t) return;
  var tTab = document.getElementById('tab-'+t);
  if (!tTab) return;
  // Deactivate schedule (default active)
  var schBtn = document.querySelector('.ss-hnav-btn[data-tab="schedule"]');
  var schTab = document.getElementById('tab-schedule');
  if (schBtn) schBtn.classList.remove('active');
  if (schTab) schTab.classList.remove('active');
  // Activate requested tab
  var tBtn = document.querySelector('.ss-hnav-btn[data-tab="'+t+'"]');
  if (tBtn) tBtn.classList.add('active');
  tTab.classList.add('active');
})();
</script>

<!-- ════ PERSONAL MODAL ════ -->
<div class="ss-modal-wrap" id="ss-personal-modal" role="dialog" aria-modal="true" aria-labelledby="ss-modal-title">
  <div class="ss-modal-box">
    <div class="ss-modal-hd">
      <h3 id="ss-modal-title">Pridėti įrašą</h3>
      <button type="button" class="ss-modal-close" id="ss-modal-close" aria-label="Uždaryti"><svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
    </div>
    <form id="ss-personal-form" class="ss-modal-bd">
      <input type="hidden" id="ss-pid" value="">
      <div class="ss-mfld"><label class="ss-mlbl">Pavadinimas *</label>
        <input type="text" id="ss-psubj" class="ss-minp" placeholder="Pvz. Matematikos konsultacija" required></div>
      <div class="ss-mfld"><label class="ss-mlbl">Diena *</label>
        <select id="ss-pday" class="ss-minp">
          <?php foreach($days as $i=>$day):?><option value="<?=$i+1?>"><?=esc_html($day)?></option><?php endforeach;?>
        </select></div>
      <div class="ss-mrow2">
        <div class="ss-mfld"><label class="ss-mlbl">Pradžia</label><input type="time" id="ss-pstart" class="ss-minp" value="08:00"></div>
        <div class="ss-mfld"><label class="ss-mlbl">Pabaiga</label><input type="time" id="ss-pend" class="ss-minp" value="08:45"></div>
      </div>
      <div class="ss-mfld"><label class="ss-mlbl">Spalva</label>
        <div class="ss-cpicker-front">
          <div class="ss-swf"><?php foreach($palette as $c):?>
            <button type="button" class="ss-fswatch" data-color="<?=$c?>" style="background:<?=$c?>" aria-label="Spalva <?=$c?>"></button>
          <?php endforeach;?></div>
          <div class="ss-hexrow">
            <input type="color" id="ss-p-native" value="#4F8952" aria-label="Pasirinkti spalvą">
            <input type="text" id="ss-p-hex" class="ss-minp" style="width:110px;font-family:monospace" value="#4F8952" placeholder="#4F8952" maxlength="7">
          </div>
          <input type="hidden" id="ss-pcolor" value="#4F8952">
        </div></div>
      <div class="ss-mfld"><label class="ss-mlbl">Pastabos</label>
        <textarea id="ss-pnotes" class="ss-minp" style="height:auto;padding:10px;resize:vertical" rows="2" placeholder="Papildoma informacija..."></textarea></div>
      <div id="ss-perr" class="ss-merr" style="display:none"></div>
      <div class="ss-modal-ft">
        <button type="button" class="ss-btn-cancel" id="ss-cancel-personal">Uždaryti</button>
        <button type="submit" class="ss-btn-save" id="ss-save-personal">Išsaugoti</button>
      </div>
    </form>
  </div>
</div>
