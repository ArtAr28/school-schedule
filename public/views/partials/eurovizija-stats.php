<?php
/**
 * „Ąžuolyno Eurovizija" — statistikos skydelis (tik valdytojams).
 * Įtraukiamas iš public/views/eurovizija.php. JS (atnaujinti / obnulinti)
 * tvarkomas eurovizija-manage.php skripte (delegavimu).
 */
if (!defined('ABSPATH')) exit;
if (!SS_Eurovizija::user_can_manage(get_current_user_id())) return;

$st = SS_Eurovizija::stats();
?>
<div id="ss-euro-stats" class="ss-euro-pane ss-est" style="display:none">

  <div class="ss-est-top">
    <div class="ss-est-summary">
      <div class="ss-est-metric"><span class="ss-est-num"><?= (int)$st['total_voters'] ?></span><span class="ss-est-lbl">Balsavusių</span></div>
      <div class="ss-est-metric"><span class="ss-est-num"><?= (int)$st['total_votes'] ?></span><span class="ss-est-lbl">Iš viso balsų</span></div>
    </div>
    <div class="ss-est-tools">
      <button type="button" id="euro-stats-refresh" class="ss-btn ss-btn-sec ss-btn-sm">Atnaujinti</button>
      <button type="button" id="euro-reset" class="ss-btn ss-btn-sm ss-est-reset">Ištrinti visus balsus</button>
    </div>
  </div>

  <?php if ((int)$st['total_votes'] < 1): ?>

    <p class="ss-em-note">Balsų dar nėra — statistika atsiras, kai bus atiduotas pirmas balsas.</p>

  <?php else: ?>

    <?php foreach ($st['groups'] as $g): if (empty($g['items'])) continue; ?>
      <section class="ss-est-group">
        <?php if ($st['mode'] === 'parallel'): ?>
          <h3 class="ss-est-gtitle">
            <span><?= esc_html($g['label'] !== '' ? $g['label'] : 'Be grupės') ?></span>
            <span class="ss-est-gtotal"><?= (int)$g['total'] ?> balsų</span>
          </h3>
        <?php endif; ?>
        <div class="ss-est-bars">
          <?php foreach ($g['items'] as $i => $it): $pct = (float)$it['pct']; ?>
            <div class="ss-est-row<?= $i === 0 && $it['votes'] > 0 ? ' is-lead' : '' ?>">
              <span class="ss-est-thumb"<?= $it['photo_url'] ? ' style="background-image:url(\'' . esc_url($it['photo_url']) . '\')"' : '' ?>>
                <?= $it['photo_url'] ? '' : esc_html(mb_strtoupper(mb_substr($it['name'], 0, 1))) ?>
              </span>
              <div class="ss-est-barwrap">
                <div class="ss-est-barhead">
                  <span class="ss-est-name"><?= esc_html($it['name']) ?></span>
                  <span class="ss-est-val"><strong><?= esc_html(number_format($pct, 1)) ?>%</strong> · <?= (int)$it['votes'] ?> bals.</span>
                </div>
                <div class="ss-est-bar"><span class="ss-est-fill" style="width:<?= esc_attr(max(0, min(100, $pct))) ?>%"></span></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

  <?php endif; ?>
</div>
