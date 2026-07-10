<?php
/**
 * Profilio puslapis: avataras + fonas, Atspek reitingas, pasiekimai, draugai.
 * Kito vartotojo profilis atveriamas ?u=<ID> (matomas visiems prisijungusiems).
 */
if (!defined('ABSPATH')) exit;

$me      = (int) get_current_user_id();
$view_id = (int) ($_GET['u'] ?? 0);
if (!$view_id) $view_id = $me;
$u = get_userdata($view_id);
if (!$u) { $view_id = $me; $u = get_userdata($me); }

$is_self = ($view_id === $me);
$role    = SS_Social::role_label($view_id);
$cover   = SS_Social::cover_css($view_id);
$atspek  = SS_Achievements::atspek_stats($view_id);
$ach     = SS_Achievements::for_user($view_id, $is_self); // pilnas sync tik savo profiliui
$cats    = SS_Achievements::categories();
$ach_unlocked = 0; foreach ($ach as $a) if ($a['unlocked']) $ach_unlocked++;
$friends = array_map(fn($fid) => SS_Social::user_card($fid), SS_Social::friends_of($view_id));
usort($friends, fn($x, $y) => strcasecmp($x['name'], $y['name']));

$rel = $is_self ? 'self' : SS_Social::rel_status($me, $view_id);
$mutual = $is_self ? 0 : SS_Social::mutual_count($me, $view_id);
$presence = SS_Social::presence_for($view_id, $me);
?>
<div class="ss-app" id="ss-app">
  <?php
    $ss_on_dashboard = false;
    $ss_active       = 'profile';
    include SS_PLUGIN_DIR . 'public/views/partials/app-nav.php';
    include SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php';
  ?>
  <main class="ss-main">
    <div class="ss-prof-wrap">

      <!-- ══ Antraštė ══ -->
      <section class="ss-prof-card ss-prof-head">
        <div class="ss-prof-cover" id="ss-prof-cover" style="background:<?= esc_attr($cover) ?>">
          <?php if ($is_self): ?>
          <button type="button" class="ss-prof-edit ss-prof-edit-cover" id="ss-edit-cover" title="Keisti foną"><?= SS_Social::icon('pencil', 15) ?></button>
          <div class="ss-cover-pop" id="ss-cover-pop">
            <?php foreach (SS_Social::covers() as $ck => $css): ?>
            <button type="button" class="ss-cover-swatch<?= $ck === (get_user_meta($view_id, 'ss_profile_cover', true) ?: 'g1') ? ' active' : '' ?>"
                    data-cover="<?= esc_attr($ck) ?>" style="background:<?= esc_attr($css) ?>" aria-label="Fonas <?= esc_attr($ck) ?>"></button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="ss-prof-head-body">
          <div class="ss-prof-avatar">
            <?= get_avatar($view_id, 132, '', $u->display_name, ['class' => 'ss-prof-av-img', 'extra_attr' => 'id="ss-prof-av-img"']) ?>
            <?php if ($is_self): ?>
            <button type="button" class="ss-prof-edit ss-prof-edit-av" id="ss-edit-avatar" title="Keisti nuotrauką"><?= SS_Social::icon('pencil', 15) ?></button>
            <input type="file" id="ss-prof-av-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
            <?php endif; ?>
          </div>
          <div class="ss-prof-id">
            <h1 class="ss-prof-name"><?= esc_html($u->display_name) ?></h1>
            <div class="ss-prof-role"><span class="ss-prof-role-pill"><?= esc_html($role) ?></span></div>
            <?php if (!$is_self && $presence['label'] !== ''): ?>
            <div class="ss-prof-presence ss-pr-<?= esc_attr($presence['status']) ?>"><span class="ss-pr-dot"></span><?= esc_html($presence['label']) ?></div>
            <?php endif; ?>
            <?php if (!$is_self): ?>
            <div class="ss-prof-mutual"><?= (int)$mutual ?> <?= ($mutual % 10 === 1 && $mutual % 100 !== 11) ? 'bendras draugas' : (($mutual % 10 >= 2 && $mutual % 10 <= 9 && ($mutual % 100 < 10 || $mutual % 100 >= 20)) ? 'bendri draugai' : 'bendrų draugų') ?></div>
            <?php endif; ?>
          </div>
          <?php if (!$is_self): ?>
          <div class="ss-prof-actions" id="ss-prof-actions" data-uid="<?= (int)$view_id ?>" data-rel="<?= esc_attr($rel) ?>">
            <button type="button" class="ss-prof-btn ss-prof-friend" data-rel="<?= esc_attr($rel) ?>"></button>
            <?php if ($rel === 'friends'): ?>
              <button type="button" class="ss-prof-btn ss-prof-btn-primary ss-prof-msg" data-uid="<?= (int)$view_id ?>"><?= SS_Social::icon('chat', 16) ?> Rašyti žinutę</button>
            <?php endif; ?>
          </div>
          <?php else: ?>
          <div class="ss-prof-actions" id="ss-prof-actions" data-uid="<?= (int)$view_id ?>" data-rel="self"></div>
          <?php endif; ?>
        </div>
      </section>

      <!-- ══ Atspek reitingas ══ -->
      <section class="ss-prof-card">
        <div class="ss-prof-sec-title"><?= SS_Social::icon('target', 19) ?> Atspėk reitingas</div>
        <div class="ss-prof-stats">
          <div class="ss-prof-stat"><div class="ss-prof-stat-n"><?= (int)$atspek['points'] ?></div><div class="ss-prof-stat-l">Taškai</div></div>
          <div class="ss-prof-stat"><div class="ss-prof-stat-n"><?= (int)$atspek['games'] ?></div><div class="ss-prof-stat-l">Partijos</div></div>
          <div class="ss-prof-stat"><div class="ss-prof-stat-n"><?= (int)$atspek['winrate'] ?>%</div><div class="ss-prof-stat-l">Pergalės</div></div>
          <div class="ss-prof-stat"><div class="ss-prof-stat-n"><?= (int)$atspek['streak'] ?></div><div class="ss-prof-stat-l">Geriausia serija</div></div>
        </div>
        <?php if ($atspek['games'] === 0): ?>
        <p class="ss-prof-empty" style="margin:12px 0 0">Kol kas nė vienos Atspėk partijos.</p>
        <?php endif; ?>
      </section>

      <!-- ══ Pasiekimai ══ -->
      <section class="ss-prof-card">
        <div class="ss-prof-sec-title"><?= SS_Social::icon('trophy', 19) ?> Pasiekimai <span class="ss-prof-sec-count"><?= (int)$ach_unlocked ?>/<?= count($ach) ?></span></div>
        <?php
        // Grupuojam pagal kategoriją
        $by_cat = [];
        foreach ($ach as $a) $by_cat[$a['category']][] = $a;
        foreach ($cats as $ckey => $clabel):
          if (empty($by_cat[$ckey])) continue;
        ?>
        <div class="ss-prof-ach-cat"><?= esc_html($clabel) ?></div>
        <div class="ss-prof-ach-grid">
          <?php foreach ($by_cat[$ckey] as $a):
            $pct = $a['target'] > 0 ? min(100, round($a['progress'] / $a['target'] * 100)) : 0;
          ?>
          <div class="ss-ach<?= $a['unlocked'] ? ' is-unlocked' : '' ?><?= $a['hidden'] && !$a['unlocked'] ? ' is-hidden' : '' ?>"
               title="<?= esc_attr($a['desc']) ?>">
            <div class="ss-ach-ico"><?= esc_html($a['icon']) ?><?php if ($a['unlocked']): ?><span class="ss-ach-check">✓</span><?php endif; ?></div>
            <div class="ss-ach-body">
              <div class="ss-ach-title"><?= esc_html($a['title']) ?></div>
              <div class="ss-ach-desc"><?= esc_html($a['desc']) ?></div>
              <div class="ss-ach-bar"><span style="width:<?= (int)$pct ?>%"></span></div>
              <div class="ss-ach-prog"><?= (int)$a['progress'] ?> / <?= (int)$a['target'] ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </section>

      <!-- ══ Draugai ══ -->
      <section class="ss-prof-card">
        <div class="ss-prof-sec-title"><?= SS_Social::icon('users', 19) ?> Draugai <span class="ss-prof-sec-count"><?= count($friends) ?></span>
          <?php if ($is_self): ?>
          <button type="button" class="ss-prof-mini-btn" id="ss-open-find-friends" style="margin-left:auto">Rasti draugų</button>
          <?php endif; ?>
        </div>

        <?php if ($is_self): ?>
        <!-- Draugystės kvietimai (tik savo profilyje) -->
        <div id="ss-friend-requests" class="ss-friend-reqs" style="display:none"></div>
        <?php endif; ?>

        <?php if (empty($friends)): ?>
        <p class="ss-prof-empty">Draugų kol kas nėra.</p>
        <?php else: ?>
        <div class="ss-friends-grid">
          <?php foreach ($friends as $f): ?>
          <a class="ss-friend-card" href="<?= esc_url(SS_Social::profile_url($f['id'])) ?>">
            <img class="ss-friend-av" src="<?= esc_url($f['avatar']) ?>" alt="">
            <span class="ss-friend-name"><?= esc_html($f['name']) ?></span>
            <span class="ss-friend-role"><?= esc_html($f['role']) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </section>

    </div>
  </main>

  <?php if ($is_self): ?>
  <!-- Draugų paieškos modalas -->
  <div class="ss-modal-wrap" id="ss-find-friends-modal" role="dialog" aria-modal="true">
    <div class="ss-modal-box">
      <div class="ss-modal-hd">
        <h3>Rasti draugų</h3>
        <button type="button" class="ss-modal-close" id="ss-ff-close" aria-label="Uždaryti">&times;</button>
      </div>
      <div class="ss-modal-bd">
        <input type="text" id="ss-ff-search" class="ss-minp" placeholder="Ieškoti pagal vardą ar el. paštą…" autocomplete="off">
        <div id="ss-ff-results" class="ss-ff-results"></div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
