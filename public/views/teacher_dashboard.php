<?php if (!defined('ABSPATH')) exit;
$days       = array('Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis');
$days_short = array('Pr','An','Tr','Kt','Pn');
$_ss_real_dow = SS_Time::dow();
$_ss_hour     = SS_Time::hour();
$today_num    = SS_Time::today_num();
$active_day   = SS_Time::active_day();
$logo_id    = (int) get_option('ss_logo_id', 0);
$logo_url   = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : get_option('ss_logo_url', '');
if (!$logo_url) {
    $_theme_lid = get_theme_mod('custom_logo');
    $logo_url   = $_theme_lid ? wp_get_attachment_image_url($_theme_lid, 'medium') : '';
}
$logo_full  = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : $logo_url;
$site_name  = get_option('ss_school_name', SS_SCHOOL_NAME);
$is_admin   = current_user_can('manage_options');
$grid       = $sched['grid']    ?? array();
$sc_periods = $sched['periods'] ?? $periods;
/* Konkretūs HEX (ne var(--x)) — spalvos laukas ir sanitize_hex_color var() nepriima. */
$palette    = array('#4F8952','#14452F','#224768','#D05155','#37775B','#012A36','#F5D297','#6EAF93');
$teacher_name = $teacher_rec ? $teacher_rec->name : $user->display_name;

/* Lithuanian student-count word form: 1 mokinys, 2-9 mokiniai, 10+/11-19 mokinių */
if (!function_exists('ss_lt_student_word')) {
    function ss_lt_student_word($n) {
        $n = (int)$n; $m10 = $n % 10; $m100 = $n % 100;
        if ($m10 === 1 && $m100 !== 11) return 'mokinys';
        if ($m10 >= 2 && $m10 <= 9 && ($m100 < 10 || $m100 >= 20)) return 'mokiniai';
        return 'mokinių';
    }
}
/**
 * Split a teacher's slot entries (same period+day) into IMP vs regular,
 * then return a [subject, regular_label, imp_total] tuple plus rendering hints.
 * Regular classes: comma-list of class names (+ subgroup).
 * IMP classes:    sum of student_count (clickable for the teacher to see names).
 */
if (!function_exists('ss_aggregate_teacher_slot')) {
    function ss_aggregate_teacher_slot($entries) {
        $imp = array(); $reg = array();
        foreach ($entries as $e) {
            if (!empty($e->is_imp) && (int)$e->is_imp === 1) $imp[] = $e;
            else $reg[] = $e;
        }
        $reg_label = '';
        if ($reg) {
            $names = array();
            foreach ($reg as $e) {
                $n = $e->class_name;
                if (!empty($e->subgroup_label)) $n .= ' · ' . $e->subgroup_label;
                $names[] = $n;
            }
            $reg_label = implode(', ', $names);
        }
        // Mokinių skaičius = unikalių IMP tvarkaraščių/klasių skaičius slot'e
        // (NE WordPress vartotojų skaičius). Vienas IMP įrašas = vienas mokinys.
        $imp_class_ids = array();
        foreach ($imp as $e) {
            $cid = (int)($e->class_id ?? 0);
            if ($cid) $imp_class_ids[$cid] = true;
        }
        $imp_total = count($imp_class_ids) ?: count($imp);
        return array(
            'subject'    => isset($entries[0]->subject) ? $entries[0]->subject : '',
            'color'      => isset($entries[0]->lesson_color) && $entries[0]->lesson_color
                              ? $entries[0]->lesson_color : 'var(--g)',
            'reg_label'  => $reg_label,
            'imp_total'  => $imp_total,
            'imp_count'  => count($imp),
            'has_imp'    => !empty($imp),
            'room'       => isset($entries[0]->room) ? $entries[0]->room : '',
        );
    }
}
?>
<div class="ss-app" id="ss-app">

  <!-- Header -->
  <?php
    $active_tab = isset($_GET['tab']) ? preg_replace('/[^a-z]/', '', $_GET['tab']) : '';
    if (!in_array($active_tab, array('schedule','personal','allschedules'), true)) $active_tab = 'schedule';
    $ss_on_dashboard = true;
    $ss_active       = $active_tab;
    include SS_PLUGIN_DIR . 'public/views/partials/app-nav.php';
    include SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php';
    if (!get_user_meta(get_current_user_id(), 'ss_onboarding_done', true)) {
        include SS_PLUGIN_DIR . 'public/views/partials/onboarding.php';
    }
  ?>

  <main class="ss-main">

    <!-- Spausdinimo antraštė (matoma tik @media print) -->
    <div class="ss-print-header">
      <h1><?= esc_html($site_name) ?></h1>
      <div class="ss-ph-sub">Mokytojo tvarkaraštis — <?= esc_html($teacher_name) ?></div>
      <div class="ss-ph-meta">Spausdinta: <?= esc_html(SS_Time::date('Y-m-d H:i')) ?></div>
    </div>


    <?php
    // ════ ĄSPĖK + APKLAUSOS / VIKTORINOS KARUSELĖ ════
    $promo_inline       = false;
    $promo_atspek_mode  = 'day';
    $promo_atspek_hours = 24;
    include SS_PLUGIN_DIR . 'public/views/partials/promo-carousel.php';
    ?>
    <!-- ═══ TAB: MOKYTOJO TVARKARAŠTIS ═══ -->
    <section class="ss-tab<?= $active_tab==='schedule'?' active':'' ?>" id="tab-schedule">
      <div class="ss-tab-inner">

        <div class="ss-sched-toolbar">
          <div>
            <h2 class="ss-tab-title">Mokytojo tvarkaraštis</h2>
            <p style="font-size:13px;color:var(--gl);margin-top:2px"><?= esc_html($teacher_name) ?></p>
          </div>
          <div class="ss-toolbar-right">
            <div class="ss-view-btns" role="group">
              <button class="ss-vbtn active" id="ss-view-day">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                Diena
              </button>
              <button class="ss-vbtn" id="ss-view-week">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 4a2 2 0 00-2 2v1h14V6a2 2 0 00-2-2H5zm12 4H3v7a2 2 0 002 2h10a2 2 0 002-2V8z" clip-rule="evenodd"/></svg>
                Savaitė
              </button>
            </div>
            <button class="ss-pdf-btn" id="ss-download-pdf" title="Parsisiųsti PDF">
              <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
              PDF
            </button>
          </div>
        </div>

        <?php if (empty($sc_periods) || empty($grid)): ?>
        <div class="ss-empty">
          <div class="ss-empty-ico"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="6" y="8" width="36" height="36" rx="4"/><path d="M16 4v8M32 4v8M6 20h36"/></svg></div>
          <h3>Tvarkaraštis nenustatytas</h3>
          <p>
            <?php if (!$teacher_rec): ?>
              Jūsų paskyra nesusijusi su mokytoju sistemoje.<br>
              Kreipkitės į administratorių, kad sukonfiguruotų Jūsų paskyrą.
            <?php else: ?>
              Jums dar nėra priskirtų pamokų tvarkaraštyje.
            <?php endif; ?>
          </p>
        </div>
        <?php else: ?>

        <!-- DAY VIEW -->
        <div id="ss-day-view">
          <div class="ss-day-tabs" role="tablist">
            <?php foreach ($days as $i => $day):
              $d = $i+1;
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

          <?php foreach ($days as $i => $day): $d=$i+1; $is_today=($today_num>0&&$d===$today_num); $is_active=($d===$active_day); ?>
          <div class="ss-dpanel <?= $is_active?'active':'' ?>" data-day="<?= $d ?>">
            <div class="ss-dpanel-hd">
              <span class="ss-dpanel-name"><?= esc_html($day) ?></span>
              <?php if ($is_today): ?><span class="ss-today-badge">Šiandien</span><?php endif; ?>
            </div>
            <div class="ss-lessons-list">
              <?php
              $had = false;
              foreach ($sc_periods as $p):
                if (empty($grid[$p->id][$d])) continue;
                $entries = $grid[$p->id][$d]; // array — may have multiple classes
                $had = true;
              ?>
              <?php
                $agg = ss_aggregate_teacher_slot($entries);
                $imp_label = $agg['has_imp']
                    ? ($agg['imp_total'] . ' ' . ss_lt_student_word($agg['imp_total']))
                    : '';
              ?>
              <div class="ss-lesson ss-teacher-lesson" style="--lc:<?= esc_attr($agg['color']) ?>">
                <div class="ss-lesson-num"><?= esc_html($p->period_number) ?></div>
                <div class="ss-lesson-time">
                  <span><?= esc_html(substr($p->start_time,0,5)) ?></span>
                  <span><?= esc_html(substr($p->end_time,0,5)) ?></span>
                </div>
                <div class="ss-lesson-body">
                  <div class="ss-lesson-subj"><?= esc_html($agg['subject']) ?></div>
                  <?php
                    $info_parts = array();
                    if ($agg['reg_label'] !== '') {
                        $info_parts[] = '<span>' . esc_html($agg['reg_label']) . '</span>';
                    }
                    if ($agg['has_imp']) {
                        $info_parts[] = '<button type="button" class="ss-wt-imp-trigger" '
                            . 'data-day="' . (int)$d . '" data-period="' . (int)$p->id . '">'
                            . esc_html($imp_label) . '</button>';
                    }
                  ?>
                  <?php if (!empty($info_parts)): ?>
                    <div class="ss-lesson-tchr"><?= implode(' <span class="ss-wt-sep">·</span> ', $info_parts) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($agg['room'])): ?>
                    <div class="ss-lesson-room"><?= esc_html($agg['room']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach;
              if (!$had): ?><div class="ss-no-lessons">Šią dieną pamokų nėra</div><?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- WEEK VIEW -->
        <div id="ss-week-view" style="display:none">
          <div class="ss-week-scroll">
            <table class="ss-week-table" id="ss-week-table">
              <thead>
                <tr>
                  <th class="ss-wt-time">Laikas</th>
                  <?php foreach ($days as $i=>$day): $d=$i+1; ?>
                  <th class="ss-wt-dh <?= $d===$today_num?'ss-wt-today':'' ?>">
                    <div class="ss-wt-dh-inner">
                      <span class="ss-wt-dl"><?= esc_html($day) ?></span>
                      <span class="ss-wt-ds"><?= esc_html($days_short[$i]) ?></span>
                      <!--<?php if ($d===$today_num): ?><span class="ss-wt-dot"></span><?php endif; ?>-->
                    </div>
                  </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sc_periods as $p): ?>
                <tr>
                  <td class="ss-wt-time-cell">
                    <strong><?= esc_html($p->period_number) ?></strong>
                    <span><?= esc_html(substr($p->start_time,0,5)) ?></span>
                    <span><?= esc_html(substr($p->end_time,0,5)) ?></span>
                  </td>
                  <?php foreach ($days as $i=>$day): $d=$i+1;
                    $entries = $grid[$p->id][$d] ?? array();
                  ?>
                  <td class="ss-wt-cell <?= $d===$today_num?'ss-wt-today':'' ?>">
                    <?php if (!empty($entries)):
                      $agg = ss_aggregate_teacher_slot($entries);
                      $imp_label = $agg['has_imp']
                          ? ($agg['imp_total'] . ' ' . ss_lt_student_word($agg['imp_total']))
                          : '';
                    ?>
                    <div class="ss-wt-lesson" style="--lc:<?= esc_attr($agg['color']) ?>">
                      <span class="ss-wt-subj"><?= esc_html($agg['subject']) ?></span>
                      <?php
                        $line = array();
                        if ($agg['reg_label'] !== '') {
                            $line[] = '<span class="ss-wt-tchr">' . esc_html($agg['reg_label']) . '</span>';
                        }
                        if ($agg['has_imp']) {
                            $line[] = '<button type="button" class="ss-wt-imp-trigger" '
                                . 'data-day="' . (int)$d . '" data-period="' . (int)$p->id . '">'
                                . esc_html($imp_label) . '</button>';
                        }
                      ?>
                      <?php if (!empty($line)) echo implode('<span class="ss-wt-sep"> · </span>', $line); ?>
                    </div>
                    <?php endif; ?>
                  </td>
                  <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <p class="ss-week-hint">↔ Slinkite į šoną norėdami matyti visas dienas</p>
        </div>

        <?php endif; ?>
      </div>
    </section>

    <!-- ═══ TAB: VISI TVARKARAŠČIAI ═══ -->
    <section class="ss-tab<?= $active_tab==='allschedules'?' active':'' ?>" id="tab-allschedules">
      <div class="ss-tab-inner">
        <div class="ss-tab-toolbar">
          <h2 class="ss-tab-title">Visi tvarkaraščiai</h2>
        </div>

        <!-- Type selector -->
        <div class="ss-type-tabs" role="tablist">
          <button type="button" class="ss-type-tab active" data-type="students" role="tab">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
            Mokiniai
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
            <input type="text" id="ss-class-search" class="ss-search-input" placeholder="Ieškoti tvarkaraščio pagal pavadinimą...">
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
              <div class="ss-empty"><p>Nėra tvarkaraščių</p></div>
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

    <!-- TAB: PERSONAL -->
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
          <p>Pridėkite susitikimus, konsultacijas ar kitus įvykius</p>
        </div>
        <?php else:
          $by_day=array();foreach($personal as $it)$by_day[$it->day_of_week][]=$it;ksort($by_day);
          foreach($by_day as $d=>$items):
        ?>
        <div class="ss-p-block">
          <div class="ss-p-dlabel"><?= esc_html($days[$d-1]??"Diena $d") ?></div>
          <?php foreach($items as $it):$c=$it->color?:'var(--g)'; ?>
          <div class="ss-p-item" style="--lc:<?= esc_attr($c) ?>">
            <div class="ss-p-time">
              <?php if($it->start_time):?><span><?= esc_html(substr($it->start_time,0,5)) ?></span><span><?= esc_html(substr($it->end_time,0,5)) ?></span><?php else:?><span>–</span><?php endif;?>
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

  </main>

  <div id="ss-pdf-meta" style="display:none"
       data-logo="<?= esc_attr($logo_full) ?>"
       data-school="<?= esc_attr($site_name) ?>"
       data-user="<?= esc_attr($user->display_name) ?>"
       data-role="Mokytojas"
       data-date="<?= esc_attr(SS_Time::date('Y-m-d H:i')) ?>"></div>
  <div class="ss-wt-sub-popup" id="ss-wt-sub-popup" style="display:none"></div>
</div>
<script>
(function(){
  var t = new URLSearchParams(window.location.search).get('tab');
  if (!t) return;
  var tTab = document.getElementById('tab-'+t);
  if (!tTab) return;
  var schBtn = document.querySelector('.ss-hnav-btn[data-tab="schedule"]');
  var schTab = document.getElementById('tab-schedule');
  if (schBtn) schBtn.classList.remove('active');
  if (schTab) schTab.classList.remove('active');
  var tBtn = document.querySelector('.ss-hnav-btn[data-tab="'+t+'"]');
  if (tBtn) tBtn.classList.add('active');
  tTab.classList.add('active');
})();
</script>

<!-- Personal modal -->
<div class="ss-modal-wrap" id="ss-personal-modal" role="dialog" aria-modal="true" aria-labelledby="ss-modal-title">
  <div class="ss-modal-box">
    <div class="ss-modal-hd">
      <h3 id="ss-modal-title">Pridėti įrašą</h3>
      <button type="button" class="ss-modal-close" id="ss-modal-close" aria-label="Uždaryti"><svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg></button>
    </div>
    <form id="ss-personal-form" class="ss-modal-bd">
      <input type="hidden" id="ss-pid" value="">
      <div class="ss-mfld"><label class="ss-mlbl">Pavadinimas *</label><input type="text" id="ss-psubj" class="ss-minp" placeholder="Pvz. Konsultacija" required></div>
      <div class="ss-mfld"><label class="ss-mlbl">Diena *</label><select id="ss-pday" class="ss-minp"><?php foreach($days as $i=>$day):?><option value="<?=$i+1?>"><?=esc_html($day)?></option><?php endforeach;?></select></div>
      <div class="ss-mrow2">
        <div class="ss-mfld"><label class="ss-mlbl">Pradžia</label><input type="time" id="ss-pstart" class="ss-minp" value="08:00"></div>
        <div class="ss-mfld"><label class="ss-mlbl">Pabaiga</label><input type="time" id="ss-pend" class="ss-minp" value="08:45"></div>
      </div>
      <div class="ss-mfld"><label class="ss-mlbl">Spalva</label>
        <div class="ss-cpicker-front">
          <div class="ss-swf"><?php foreach($palette as $c):?><button type="button" class="ss-fswatch" data-color="<?=$c?>" style="background:<?=$c?>" aria-label="Spalva <?=$c?>"></button><?php endforeach;?></div>
          <div class="ss-hexrow"><input type="color" id="ss-p-native" value="#4F8952" aria-label="Pasirinkti spalvą"><input type="text" id="ss-p-hex" class="ss-minp" style="width:110px;font-family:monospace" value="#4F8952" placeholder="#4F8952" maxlength="7"></div>
          <input type="hidden" id="ss-pcolor" value="#4F8952">
        </div></div>
      <div class="ss-mfld"><label class="ss-mlbl">Pastabos</label><textarea id="ss-pnotes" class="ss-minp" style="height:auto;padding:10px;resize:vertical" rows="2" placeholder="Papildoma informacija..."></textarea></div>
      <div id="ss-perr" class="ss-merr" style="display:none"></div>
      <div class="ss-modal-ft">
        <button type="button" class="ss-btn-cancel" id="ss-cancel-personal">Uždaryti</button>
        <button type="submit" class="ss-btn-save" id="ss-save-personal">Išsaugoti</button>
      </div>
    </form>
  </div>
</div>
