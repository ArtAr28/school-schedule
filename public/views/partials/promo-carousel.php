<?php
/**
 * Promo karuselė: „Ąspėk" reklama + aktyvios apklausos / viktorinos.
 *
 * Parametrai (nustatomi prieš include):
 *   $promo_inline      bool   – jei true, naudoja inline wrapper klasę.
 *   $promo_atspek_mode string – 'day' | 'hours' | 'off'
 *   $promo_atspek_hours int   – kiek valandų laikyti uždarytą (mode='hours').
 */
if (!defined('ABSPATH')) exit;

$promo_inline       = isset($promo_inline) ? (bool)$promo_inline : false;
$promo_atspek_mode  = isset($promo_atspek_mode) && in_array($promo_atspek_mode, ['day','hours','off'], true)
    ? $promo_atspek_mode : 'day';
$promo_atspek_hours = isset($promo_atspek_hours) ? max(1, min(168, (int)$promo_atspek_hours)) : 24;

$promo_user_id        = get_current_user_id();
$promo_atspek_url     = get_option('ss_atspek_game_url', '');
$promo_atspek_desc    = get_option('ss_atspek_game_description', '');
$promo_atspek_img_id  = (int)get_option('ss_atspek_image_id', 0);
$promo_atspek_img_url = $promo_atspek_img_id ? wp_get_attachment_image_url($promo_atspek_img_id, 'large') : '';

$promo_surveys = $promo_user_id ? SS_Database::get_active_surveys_for_user($promo_user_id) : array();

$promo_show_atspek  = !empty($promo_atspek_url);
$promo_show_surveys = !empty($promo_surveys);

// Modalas visada renderiuojamas – JS turi rasti #ss-survey-modal nepriklausomai nuo carousel
?>
<!-- Apklausos / viktorinos modalas -->
<div id="ss-survey-modal" class="ss-fr-modal-overlay" style="display:none" aria-hidden="true">
  <div class="ss-fr-modal" role="dialog" aria-modal="true" aria-labelledby="ss-svf-title">
    <div class="ss-fr-modal-head">
      <div>
        <span class="ss-survey-pill" id="ss-svf-pill" style="display:none">Apklausa</span>
        <h2 id="ss-svf-title">—</h2>
        <div id="ss-svf-times" class="ss-survey-times" style="margin-top:6px"></div>
      </div>
      <button type="button" class="ss-fr-modal-x" data-fr-close aria-label="Uždaryti">
        <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
      </button>
    </div>
    <div class="ss-fr-modal-body" id="ss-svf-body">
      <p class="ss-svf-loading">Kraunama...</p>
    </div>
    <div class="ss-fr-modal-foot" id="ss-svf-foot">
      <button type="button" class="ss-fr-btn ss-fr-btn-ghost" data-fr-close>Uždaryti</button>
      <button type="button" class="ss-fr-btn ss-fr-btn-primary" id="ss-svf-submit">Pateikti</button>
    </div>
  </div>
</div>
<?php

if (!$promo_show_atspek && !$promo_show_surveys) return;

$promo_items = array();
if ($promo_show_atspek) {
    $promo_items[] = array('type' => 'atspek');
}
foreach ($promo_surveys as $s) {
    $promo_items[] = array('type' => 'survey', 'survey' => $s);
}
$promo_total       = count($promo_items);
$promo_has_carousel = $promo_total > 1;

$promo_wrap_classes = 'ss-promo-wrap ss-atspek-wrap';
if ($promo_inline) $promo_wrap_classes .= ' ss-atspek-wrap-inline ss-promo-wrap-inline';
?>
<div class="<?= esc_attr($promo_wrap_classes) ?>"
     id="ss-promo-wrap"
     data-total="<?= (int)$promo_total ?>"
     data-rotate="15000"
     data-atspek-mode="<?= esc_attr($promo_atspek_mode) ?>"
     data-atspek-hours="<?= (int)$promo_atspek_hours ?>"
     style="visibility:hidden">

  <div class="ss-promo-track">
    <?php foreach ($promo_items as $idx => $it):
        $slide_class = 'ss-promo-slide' . ($idx === 0 ? ' is-active' : '');
        if ($it['type'] === 'atspek'): ?>
      <div class="<?= esc_attr($slide_class) ?>" data-slide-type="atspek" data-idx="<?= (int)$idx ?>">
        <button class="ss-atspek-close" id="ss-atspek-close" type="button" aria-label="Uždaryti Ąspėk reklamą">
          <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
        </button>
        <a href="<?= esc_url($promo_atspek_url) ?>" target="_blank" rel="noopener" class="ss-atspek-banner"
           <?php if ($promo_atspek_img_url): ?>style="background-image:url('<?= esc_url($promo_atspek_img_url) ?>')"<?php endif; ?>>
          <div class="ss-atspek-overlay"></div>
          <div class="ss-atspek-content">
            <div class="ss-atspek-tiles" aria-hidden="true">
              <span class="ss-at-tile ss-at-green">A</span>
              <span class="ss-at-tile ss-at-yellow">T</span>
              <span class="ss-at-tile ss-at-grey">S</span>
              <span class="ss-at-tile ss-at-green">P</span>
              <span class="ss-at-tile ss-at-yellow">Ė</span>
              <span class="ss-at-tile ss-at-grey">K</span>
            </div>
            <div class="ss-atspek-text">
              <?php if ($promo_atspek_desc): ?>
              <span class="ss-atspek-sub"><?= esc_html($promo_atspek_desc) ?></span>
              <?php endif; ?>
            </div>
            <span class="ss-atspek-btn">
              Žaisti
              <svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
            </span>
          </div>
        </a>
      </div>
    <?php else:
        $s = $it['survey'];
        $is_quiz = ($s->type === 'quiz');
        $q_count = is_array(json_decode($s->questions, true)) ? count(json_decode($s->questions, true)) : 0;
        $accent  = $is_quiz ? 'var(--nb)' : 'var(--g)';
        // Determine phase for initial button state
        $now_ts          = current_time('mysql');
        $can_answer      = (empty($s->open_from) || $s->open_from <= $now_ts)
                        && (empty($s->close_at)  || $s->close_at  >= $now_ts);
        // Use the SAME rule as the survey modal (SS_Surveys_Admin::user_can_see_results),
        // so the carousel button and the modal never disagree. Results may be revealed by
        // the „rodyti vartotojams" checkbox OR by a results_show_from time window alone.
        $results_visible = class_exists('SS_Surveys_Admin')
            ? SS_Surveys_Admin::user_can_see_results($s)
            : (!empty($s->show_results_users)
                && (empty($s->results_show_from) || $s->results_show_from <= $now_ts)
                && (empty($s->results_close_at)  || $s->results_close_at  >= $now_ts));
        $user_answered   = !empty(SS_Database::get_user_response($s->id, $promo_user_id));
        // show_results_users=1 reiškia rezultatai matomi VISIMS – net jei balsavimas dar atviras
        if ($results_visible) {
            $btn_label = 'Rezultatai'; $btn_extra = ' is-results'; $btn_arrow = false;
        } elseif ($user_answered) {
            $btn_label = 'Atsakyta'; $btn_extra = ' is-answered'; $btn_arrow = false; $btn_check = true;
        } elseif ($can_answer) {
            $btn_label = $is_quiz ? 'Dalyvauti' : 'Atsakyti'; $btn_extra = ''; $btn_arrow = true;
        } else {
            $btn_label = 'Laukiama…';  $btn_extra = ' is-waiting'; $btn_arrow = false;
        }

        // Timing: show only what is relevant right now (one label max)
        $relevant_time = null;
        if ($can_answer && !empty($s->close_at)) {
            $relevant_time = array('Iki', $s->close_at, 'active');
        } elseif (!$can_answer && !$results_visible && !empty($s->results_show_from)) {
            $relevant_time = array('Rezultatai nuo', $s->results_show_from, 'future');
        } elseif ($results_visible && !empty($s->results_close_at)) {
            $relevant_time = array('Rezultatai iki', $s->results_close_at, 'active');
        }
    ?>
      <div class="<?= esc_attr($slide_class) ?>" data-slide-type="survey" data-survey-id="<?= (int)$s->id ?>" data-idx="<?= (int)$idx ?>">
        <?php
        $sv_img_id  = (int)($s->image_id ?? 0);
        $sv_img_url = $sv_img_id ? wp_get_attachment_image_url($sv_img_id, 'large') : '';
        $sv_has_img = !empty($sv_img_url);
        ?>
        <div class="ss-survey-banner<?= $sv_has_img ? ' has-bg-img' : '' ?>" style="--sv-accent: <?= esc_attr($accent) ?><?= $sv_has_img ? ';--sv-bg:url(\'' . esc_url($sv_img_url) . '\')' : '' ?>">
          <div class="ss-survey-banner-inner">
            <div class="ss-survey-banner-meta">
              <span class="ss-survey-pill <?= $is_quiz ? 'is-quiz' : '' ?>">
                <?= $is_quiz ? 'Viktorina' : 'Apklausa' ?>
              </span>
            </div>
            <h3 class="ss-survey-banner-title"><?= esc_html($s->title ?: ($is_quiz ? 'Viktorina' : 'Apklausa')) ?></h3>
            <?php if (!empty($s->description)): ?>
              <p class="ss-survey-banner-desc"><?= esc_html(wp_strip_all_tags($s->description)) ?></p>
            <?php endif; ?>

            <?php if ($relevant_time): ?>
            <div class="ss-survey-times">
              <span class="ss-stime is-<?= esc_attr($relevant_time[2]) ?>">
                <span class="ss-stime-label"><?= esc_html($relevant_time[0]) ?></span>
                <span class="ss-stime-val"><?= esc_html(mysql2date('Y-m-d H:i', $relevant_time[1])) ?></span>
              </span>
            </div>
            <?php endif; ?>
            <div class="ss-survey-banner-foot">
              <span class="ss-survey-banner-count">
                <svg viewBox="0 0 20 20" fill="currentColor" width="13" height="13"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9zM4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z"/></svg>
                <?= (int)$q_count ?> klausimai
              </span>
              <button type="button" class="ss-survey-banner-btn<?= esc_attr($btn_extra) ?>" data-survey-open="<?= (int)$s->id ?>">
                <?php if (!empty($btn_check)): ?>
                <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                <?php endif; ?>
                <?= esc_html($btn_label) ?>
                <?php if ($btn_arrow): ?>
                <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                <?php endif; ?>
              </button>
            </div>
          </div>
        </div>
      </div>
    <?php endif; endforeach; ?>
  </div>

  <?php if ($promo_has_carousel): ?>
  <button type="button" class="ss-promo-arrow ss-promo-arrow-prev" aria-label="Ankstesnis">
    <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M12.707 4.293a1 1 0 010 1.414L8.414 10l4.293 4.293a1 1 0 01-1.414 1.414l-5-5a1 1 0 010-1.414l5-5a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
  </button>
  <button type="button" class="ss-promo-arrow ss-promo-arrow-next" aria-label="Kitas">
    <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M7.293 4.293a1 1 0 011.414 0l5 5a1 1 0 010 1.414l-5 5a1 1 0 01-1.414-1.414L11.586 10 7.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
  </button>
  <div class="ss-promo-dots" aria-hidden="true">
    <?php for ($i = 0; $i < $promo_total; $i++): ?>
      <button type="button" class="ss-promo-dot<?= $i === 0 ? ' is-active' : '' ?>" data-dot-idx="<?= (int)$i ?>" aria-label="Slide <?= (int)($i+1) ?>"></button>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
