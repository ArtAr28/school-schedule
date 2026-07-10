<?php
/**
 * „Ąžuolyno Eurovizija" — balsavimo puslapis (atskira plėtinio dalis).
 * Pateikiama per template_redirect, kai $post->ID === ss_euro_page_id.
 */
if (!defined('ABSPATH')) exit;

// Šį puslapį vienu metu krauna šimtai mokinių — visus ss_euro_* nustatymus
// įkeliam viena užklausa, kad žemiau esantys is_enabled()/is_open()/mode()/…
// nedarytų po atskirą DB užklausą.
SS_Eurovizija::prime_settings();

$uid        = get_current_user_id();
$enabled    = SS_Eurovizija::is_enabled();
$open       = SS_Eurovizija::is_open();
$can_vote   = SS_Eurovizija::user_can_vote($uid);
$can_manage = SS_Eurovizija::user_can_manage($uid);
$mode       = SS_Eurovizija::mode();
$title      = SS_Eurovizija::title();
$desc       = SS_Eurovizija::description();
$hero_url   = SS_Eurovizija::hero_url('large');
$my_votes   = SS_Eurovizija::get_user_votes($uid);
$has_voted  = !empty($my_votes);

$candidates = SS_Eurovizija::voting_candidates(); // kešuotas sąrašas su paruoštais nuotraukų URL

// Valdymo skydelio matomumas: vadybininkai, kurie negali balsuoti, iškart patenka į valdymą.
$only_manage = $can_manage && !$can_vote;

// Sugrupuoti pagal paralelę
$groups = array();
foreach ($candidates as $c) {
    $groups[(string)$c->group_label][] = $c;
}
ksort($groups);

// Greitas kandidatų indeksas (perskaityto balso atvaizdavimui)
$cand_by_id = array();
foreach ($candidates as $c) { $cand_by_id[(int)$c->id] = $c; }
$voted_ids = array();
foreach ($my_votes as $v) { $voted_ids[] = (int)$v->candidate_id; }

// Kortelės atvaizdavimo pagalbinė — nuotrauka per visą foną (kaip dashbordo karuselėje).
$render_card = function($c, $radio_name, $selectable, $checked = false) {
    $url   = $c->photo_large ?? SS_Eurovizija::candidate_photo_url($c, 'large');
    $tag   = $selectable ? 'label' : 'div';
    $cls   = 'ss-euro-card';
    if ($checked)     $cls .= ' is-selected';
    if (!$selectable) $cls .= ' is-readonly';
    if ($url)         $cls .= ' has-img';
    $style = $url ? ' style="background-image:url(\'' . esc_url($url) . '\')"' : '';
    ob_start(); ?>
    <<?= $tag ?> class="<?= $cls ?>"<?= $style ?>>
      <?php if ($selectable): ?>
        <input type="radio" name="<?= esc_attr($radio_name) ?>" value="<?= (int)$c->id ?>"<?= $checked ? ' checked' : '' ?>>
      <?php endif; ?>
      <?php if (!$url): ?>
        <span class="ss-euro-card-initial" aria-hidden="true"><?= esc_html(mb_strtoupper(mb_substr($c->name, 0, 1))) ?></span>
      <?php endif; ?>
      <span class="ss-euro-card-overlay" aria-hidden="true"></span>
      <span class="ss-euro-card-check" aria-hidden="true">
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
      </span>
      <span class="ss-euro-card-name"><?= esc_html($c->name) ?></span>
    </<?= $tag ?>>
    <?php
    return ob_get_clean();
};

$group_title = function($label) {
    $label = (string)$label;
    if ($label === '') return 'Kiti dalyviai';
    if (preg_match('/^[0-9]+$/', $label)) return $label . '-os klasės';
    return $label . ' grupė';
};
?>
<div class="ss-app" id="ss-app">

  <?php
    $ss_on_dashboard = false;
    $ss_active       = 'eurovizija';
    include SS_PLUGIN_DIR . 'public/views/partials/app-nav.php';
    include SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php';
  ?>

  <main class="ss-main">
    <div class="ss-euro" id="ss-euro" data-mode="<?= esc_attr($mode) ?>">

      <div class="ss-euro-hero<?= $hero_url ? ' has-hero' : '' ?>"<?= $hero_url ? ' style="background-image:url(\'' . esc_url($hero_url) . '\')"' : '' ?>>
        <?php if ($hero_url): ?><span class="ss-euro-hero-ov" aria-hidden="true"></span><?php endif; ?>
        <h1 class="ss-euro-title"><?= esc_html($title) ?></h1>
        <?php if ($desc !== ''): ?>
          <p class="ss-euro-desc"><?= esc_html($desc) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($can_manage): ?>
      <div class="ss-euro-tabs" role="tablist">
        <?php if ($can_vote): ?><button type="button" class="ss-euro-tab active" data-euro-tab="vote">Balsuoti</button><?php endif; ?>
        <button type="button" class="ss-euro-tab<?= $can_vote ? '' : ' active' ?>" data-euro-tab="manage">Valdymas</button>
        <button type="button" class="ss-euro-tab" data-euro-tab="stats">Statistika</button>
      </div>
      <?php endif; ?>

      <div id="ss-euro-vote" class="ss-euro-pane"<?= $only_manage ? ' style="display:none"' : '' ?>>

      <?php if (!$enabled): ?>

        <div class="ss-euro-state">
          <div class="ss-euro-state-ic ss-euro-state-muted">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
          </div>
          <h2>Šiuo metu balsavimo nėra</h2>
        </div>

      <?php elseif (!$can_vote): ?>

        <div class="ss-euro-state">
          <div class="ss-euro-state-ic ss-euro-state-muted">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
          </div>
          <h2>Jūs negalite balsuoti šiame balsavime</h2>
        </div>

      <?php elseif ($has_voted): ?>

        <div class="ss-euro-state">
          <div class="ss-euro-state-ic ss-euro-state-ok">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
          </div>
          <h2>Ačiū! Jūsų balsas priimtas</h2>
          <p>Jūs jau balsavote. Jūsų pasirinkimas:</p>
        </div>

        <div class="ss-euro-mychoice">
          <?php foreach ($voted_ids as $vid): if (!isset($cand_by_id[$vid])) continue; $c = $cand_by_id[$vid]; ?>
            <?= $render_card($c, '', false, false) ?>
          <?php endforeach; ?>
        </div>

      <?php elseif (!$open): ?>

        <div class="ss-euro-state">
          <div class="ss-euro-state-ic ss-euro-state-muted">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
          </div>
          <h2>Balsavimas dar neprasidėjo</h2>
          <p>Sugrįžkite, kai balsavimas bus atidarytas.</p>
        </div>

      <?php elseif (empty($candidates)): ?>

        <div class="ss-euro-state">
          <div class="ss-euro-state-ic ss-euro-state-muted">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>
          </div>
          <h2>Dalyvių dar nėra</h2>
        </div>

      <?php else: ?>

        <form id="ss-euro-form" class="ss-euro-form">
          <?php if ($mode === 'parallel'): ?>
            <p class="ss-euro-hint">Pasirinkite po vieną dalyvį iš kiekvienos grupės.</p>
            <?php $gi = 0; foreach ($groups as $label => $list): $gi++; ?>
              <section class="ss-euro-group">
                <h3 class="ss-euro-group-title"><?= esc_html($group_title($label)) ?></h3>
                <div class="ss-euro-grid">
                  <?php foreach ($list as $c) { echo $render_card($c, 'grp_' . $gi, true); } ?>
                </div>
              </section>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="ss-euro-hint">Pasirinkite vieną dalyvį, už kurį atiduodate savo balsą.</p>
            <div class="ss-euro-grid">
              <?php foreach ($candidates as $c) { echo $render_card($c, 'single', true); } ?>
            </div>
          <?php endif; ?>

          <div class="ss-euro-actions">
            <button type="submit" id="ss-euro-submit" class="ss-btn ss-btn-pri">
              <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:-3px"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              Balsuoti
            </button>
            <span id="ss-euro-msg" class="ss-euro-msg" style="display:none"></span>
          </div>
        </form>

      <?php endif; ?>

      </div><!-- /#ss-euro-vote -->

      <?php if ($can_manage) {
        include SS_PLUGIN_DIR . 'public/views/partials/eurovizija-manage.php';
        include SS_PLUGIN_DIR . 'public/views/partials/eurovizija-stats.php';
      } ?>

    </div>
  </main>
</div>
