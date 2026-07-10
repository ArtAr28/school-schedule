<?php if (!defined('ABSPATH')) exit;
 
/* ── Laukų renderinimo pagalbinė funkcija ──────────────────────── */
function _ss_sec_f(string $k, string $lbl, string $type): void {
    $val = SS_Security::get($k);
    echo '<div style="display:flex;align-items:center;justify-content:space-between;'
       . 'padding:9px 0;border-bottom:1px solid var(--sa-border);gap:12px">';
    echo '<label for="'.esc_attr($k).'" style="font-size:13px;color:var(--sa-dark);cursor:pointer;flex:1">'
       . esc_html($lbl).'</label>';
    if ($type === 'bool') {
        $ch = $val ? ' checked' : '';
        echo '<label class="ss-switch" style="flex-shrink:0">'
           . '<input type="checkbox" id="'.esc_attr($k).'" name="'.esc_attr($k).'"'.$ch.'>'
           . '<span class="ss-slider"></span></label>';
    } else {
        echo '<input type="number" id="'.esc_attr($k).'" name="'.esc_attr($k).'" value="'.(int)$val.'"'
           . ' min="0" max="9999" style="width:72px;height:32px;text-align:center;'
           . 'border:1.5px solid var(--sa-border);font-size:13px;'
           . 'color:var(--sa-dark);font-family:inherit;padding:0 8px;outline:none">';
    }
    echo '</div>';
}
 
/* ── Aktyvus skirtukas ─────────────────────────────────────────── */
$active_tab = sanitize_text_field($_GET['tab'] ?? 'log');
$base_url   = admin_url('admin.php?page=ss-security');
 
/* ── Page header vars ──────────────────────────────────────────── */
$ss_page_title    = 'Saugumas';
$ss_page_subtitle = 'Audito žurnalas, saugumo nustatymai.';/*
$ss_page_actions  = '<a class="button button-secondary ss-btn-ghost" href="'
    . esc_url(add_query_arg(['page'=>'ss-security','tab'=>'settings'], admin_url('admin.php')))
    . '">Nustatymai</a>';*/
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>
 
<?php
/* ── Statistikos kortelės ──────────────────────────────────────── */
$logins_ok   = (int)($stats['login_ok']        ?? 0);
$logins_fail = (int)($stats['login_failed']     ?? 0) + (int)($stats['login_blocked'] ?? 0);
$twofa_ok    = (int)($stats['2fa_ok']           ?? 0);
$alerts      = (int)($stats['ids_alert']        ?? 0);
?>
<div class="ss-stats-row" style="margin-top:20px">
  <div class="ss-stat" style="--sc:var(--sa-green)">
    <div class="ss-stat-ico"><span class="dashicons dashicons-yes-alt"></span></div>
    <div>
      <div class="ss-stat-n"><?= $logins_ok ?></div>
      <div class="ss-stat-l">Prisijungimai (per 24val)</div>
    </div>
  </div>
  <div class="ss-stat" style="--sc:var(--sa-red)">
    <div class="ss-stat-ico"><span class="dashicons dashicons-warning"></span></div>
    <div>
      <div class="ss-stat-n"><?= $logins_fail ?></div>
      <div class="ss-stat-l">Nesėkmingi / blokuoti (per 24val)</div>
    </div>
  </div>
  <div class="ss-stat" style="--sc:var(--sa-navy)">
    <div class="ss-stat-ico"><span class="dashicons dashicons-smartphone"></span></div>
    <div>
      <div class="ss-stat-n"><?= $twofa_ok ?></div>
      <div class="ss-stat-l">2FA patvirtinimai (per 24val)</div>
    </div>
  </div>
  <div class="ss-stat" style="--sc:<?= $alerts > 0 ? 'var(--sa-red)' : 'var(--sa-green)' ?>">
    <div class="ss-stat-ico"><span class="dashicons dashicons-shield-alt"></span></div>
    <div>
      <div class="ss-stat-n"><?= $alerts ?></div>
      <div class="ss-stat-l">IDS įspėjimai (per 24val)</div>
    </div>
  </div>
</div>
 
<!-- ══ Skirtukų navigacija ══ -->
<nav class="ss-sec-tabs" style="display:flex;gap:4px;margin:22px 0 0;border-bottom:2px solid var(--sa-border)">
  <?php
  $tabs = [
    'log'      => ['', 'Žurnalas'],
    'settings' => ['',  'Nustatymai'],
    'bot'      => ['',  'Apsauga nuo botų'],
  ];
  foreach ($tabs as $slug => [$icon, $label]):
    $active = ($active_tab === $slug);
  ?>
  <a href="<?= esc_url(add_query_arg('tab', $slug, $base_url)) ?>"
     style="display:inline-flex;align-items:center;gap:6px;padding:10px 18px;font-size:13px;font-weight:700;text-decoration:none;margin-bottom:-2px;border:2px solid transparent;<?= $active
       ? 'background:var(--sa-white);color:var(--sa-dark);border-color:var(--sa-border);border-bottom-color:var(--sa-white);'
       : 'color:var(--sa-green-lt);' ?>">
    <?= $icon ?> <?= esc_html($label) ?>
  </a>
  <?php endforeach; ?>
</nav>
 
<!-- ══════════════════════════════════════════════════════════════
     SKIRTUKAS: ŽURNALAS
     ══════════════════════════════════════════════════════════════ -->
<?php if ($active_tab === 'log'): ?>
<div style="background:var(--sa-white);border:1px solid var(--sa-border);border-top:none;padding:0">
 
  <!-- Filtro juosta -->
  <form method="get" class="ss-bulk-bar" style="border-top:none;margin:0;flex-wrap:wrap;gap:8px">
    <input type="hidden" name="page" value="ss-security">
    <input type="hidden" name="tab"  value="log">
    <?php if (!empty($filters['user_id'])): ?><input type="hidden" name="user_id" value="<?= (int)$filters['user_id'] ?>"><?php endif; ?>
    <div class="ss-bulk-left" style="flex-wrap:wrap;gap:8px">
      <select name="event" class="ss-bulk-select" style="min-width:150px!important">
        <option value="">Visi įvykiai</option>
        <?php
        $event_types = [
          'login_ok','login_failed','login_blocked','logout',
          '2fa_ok','2fa_failed','2fa_enabled','2fa_disabled','2fa_backup_used',
          'password_changed','password_reset','registration','reg_approved',
          'settings_saved','schedule_edited','entity_changed','changes_published',
          'survey_status','data_import','data_export',
          'role_changed','caps_changed','account_updated','account_blocked','account_unblocked',
          'device_blocked','device_unblocked','session_kicked','session_revoked',
          'schedule_changed','year_rollover','ids_alert','google_token_invalid',
          'turnstile_pass','turnstile_fail','turnstile_block',
        ];
        foreach ($event_types as $et): ?>
        <option value="<?= esc_attr($et) ?>" <?= $event_filter === $et ? 'selected' : '' ?>><?= esc_html($et) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="severity" class="ss-bulk-select" style="min-width:120px!important">
        <?php foreach (['' => 'Visi lygiai', 'critical' => 'Kritiniai', 'warning' => 'Įspėjimai', 'notice' => 'Pranešimai', 'info' => 'Info'] as $sv => $lbl): ?>
        <option value="<?= esc_attr($sv) ?>" <?= ($filters['severity'] ?? '') === $sv ? 'selected' : '' ?>><?= esc_html($lbl) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="ip" class="ss-search-inp" placeholder="IP…" value="<?= esc_attr($ip_filter) ?>" style="padding-left:12px!important;width:120px!important">
      <input type="date" name="date_from" value="<?= esc_attr($filters['date_from'] ?? '') ?>" class="ss-search-inp" style="width:140px!important" title="Nuo">
      <input type="date" name="date_to"   value="<?= esc_attr($filters['date_to'] ?? '') ?>"   class="ss-search-inp" style="width:140px!important" title="Iki">
      <input type="text" name="search" class="ss-search-inp" placeholder="Paieška (vardas/IP/info)…" value="<?= esc_attr($filters['search'] ?? '') ?>" style="padding-left:12px!important;width:170px!important">
    </div>
    <div class="ss-bulk-right" style="gap:8px">
      <button type="submit" class="button ss-bulk-apply">Filtruoti</button>
      <?php if (array_filter($filters)): ?>
      <a href="<?= esc_url(add_query_arg('tab','log',$base_url)) ?>" class="button"><span class="dashicons dashicons-no-alt"></span> Išvalyti</a>
      <?php endif; ?>
      <a href="<?= esc_url($log_export_url) ?>" class="button" title="Eksportuoti į CSV"><span class="dashicons dashicons-download"></span> CSV</a>
      <span style="font-size:12px;color:var(--sa-green-lt);white-space:nowrap">
        Rasta: <strong><?= number_format($total_filtered) ?></strong> / <?= number_format($total) ?>
      </span>
    </div>
  </form>
 
  <?php if (empty($events)): ?>
  <div style="text-align:center;padding:60px 20px;color:var(--sa-green-lt)">
    <span class="dashicons dashicons-shield" style="font-size:42px;width:42px;height:42px;display:block;margin:0 auto 12px;color:var(--sa-green-pale)"></span>
    <?= array_filter($filters) ? 'Įvykių pagal pasirinktą filtrą nerasta.' : 'Žurnalas tuščias — tai gera žinia.' ?>
  </div>
  <?php else: ?>
 
  <table class="wp-list-table widefat fixed striped ss-table"
         style="margin:0;border-top:none">
    <thead>
      <tr>
        <th style="width:148px">Laikas</th>
        <th style="width:160px">Įvykis</th>
        <th style="width:130px">Vartotojas</th>
        <th style="width:130px">IP / Vieta</th>
        <th style="width:80px">Metodas</th>
        <th>Įrenginys</th>
        <th style="width:180px">Papildoma info</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $badges = [
      'login_ok'            => ['#E5F4E8','var(--sa-forest)'],
      'login_failed'        => ['#FFE9E9','#9F2226'],
      'login_blocked'       => ['#FFE9E9','#9F2226'],
      'logout'              => ['#F0F4F8','#4A5568'],
      '2fa_ok'              => ['#E7EEF7','var(--sa-navy)'],
      '2fa_failed'          => ['#FFF4D6','#8A6A1F'],
      '2fa_enabled'         => ['#E5F4E8','var(--sa-forest)'],
      '2fa_disabled'        => ['#FFE9E9','#9F2226'],
      '2fa_backup_used'     => ['#FFF4D6','#8A6A1F'],
      'password_changed'    => ['#F3E8FF','#6B21A8'],
      'password_reset'      => ['#F3E8FF','#6B21A8'],
      'registration'        => ['#E7EEF7','var(--sa-navy)'],
      'reg_approved'        => ['#E5F4E8','var(--sa-forest)'],
      'ids_alert'           => ['#FFE9E9','#9F2226'],
      'google_token_invalid'=> ['#FFE9E9','#9F2226'],
      'turnstile_pass'      => ['#E5F4E8','var(--sa-forest)'],
      'turnstile_fail'      => ['#FFF4D6','#8A6A1F'],
      'turnstile_block'     => ['#FFE9E9','#9F2226'],
    ];
    // Severity → colour (fallback for events not in $badges, esp. the new audit events).
    $sev_badge = [
      'critical' => ['#FFE0E0','#9F2226'],
      'warning'  => ['#FFF4D6','#8A6A1F'],
      'notice'   => ['#E7EEF7','var(--sa-navy)'],
      'info'     => ['#F0F4F8','#4A5568'],
    ];
    foreach ($events as $row):
      [$bg, $fg] = $badges[$row->event] ?? $sev_badge[$row->severity ?? 'info'] ?? ['var(--sa-bg)','#4A5568'];
      $u   = $row->user_id ? get_user_by('id', (int)$row->user_id) : null;
      $target = (!empty($row->target_id)) ? get_user_by('id', (int)$row->target_id) : null;
      $ua  = esc_html(substr($row->user_agent ?? '', 0, 55));

      // Naršyklė + OS iš User-Agent (bendra pagalbinė funkcija)
      $ua_short = class_exists('SS_AuditLog')
          ? SS_AuditLog::device_label($row->user_agent ?? '')
          : trim(substr($row->user_agent ?? '', 0, 48));
      // Apytikslė vieta pagal IP — TIK iš talpyklos (kad 500 eilučių nesukeltų
      // 500 tinklo užklausų ir neviršytų ipapi limito). Nepažinti IP – tušti.
      $geo_label = ($row->ip && class_exists('SS_AuditLog')) ? SS_AuditLog::geo_label_cached($row->ip) : '';
 
      $extra_parts = [];
      if ($row->extra && $row->extra !== '[]' && $row->extra !== '{}') {
        $ex = json_decode($row->extra, true);
        if (is_array($ex)) {
          foreach ($ex as $k => $v) {
            if (in_array($k,['user_id','ip','method','username'],true)) continue;
            $extra_parts[] = '<span style="color:var(--sa-green-lt);font-size:10px">'
              . esc_html($k) . ':</span> <strong style="font-size:11px">'
              . esc_html(is_array($v) ? json_encode($v) : (string)$v) . '</strong>';
          }
        }
      }
    ?>
    <tr>
      <td style="font-family:monospace;font-size:11px;color:var(--sa-green-lt);white-space:nowrap">
        <?= esc_html($row->created_at) ?>
      </td>
      <td>
        <span style="display:inline-flex;align-items:center;gap:4px;background:<?= esc_attr($bg) ?>;color:<?= esc_attr($fg) ?>;font-size:10px;font-weight:700;padding:3px 9px;font-family:monospace;letter-spacing:.2px;white-space:nowrap">
          <?= esc_html($row->event) ?>
        </span>
      </td>
      <td style="font-size:12px">
        <?php if ($u): ?>
          <strong><?= esc_html($u->user_login) ?></strong>
        <?php elseif ((int)$row->user_id > 0): ?>
          <?php /* Paskyra turėjo user_id, bet šiuo metu ištrinta — rodome momentinę kopiją. */ ?>
          <?php if ($row->username_tried): ?>
            <strong style="color:var(--sa-dark)"><?= esc_html($row->username_tried) ?></strong>
          <?php else: ?>
            <span style="color:var(--sa-green-lt)">#<?= (int)$row->user_id ?></span>
          <?php endif; ?>
          <span style="display:block;color:var(--sa-green-pale);font-size:10px;font-style:italic">ištrinta paskyra</span>
        <?php elseif ($row->username_tried): ?>
          <span style="color:var(--sa-red);font-size:11px;font-style:italic"><?= esc_html($row->username_tried) ?></span>
        <?php else: ?>
          <span style="color:var(--sa-green-pale)">—</span>
        <?php endif; ?>
        <?php if (!empty($row->target_id)): ?>
          <span style="display:block;color:var(--sa-green-lt);font-size:10px;margin-top:2px" title="Paveikta paskyra">
            &rarr; <?= $target ? esc_html($target->user_login) : '#' . (int)$row->target_id ?>
          </span>
        <?php endif; ?>
      </td>
      <td style="font-family:monospace;font-size:11px">
        <?php if ($row->ip): ?>
        <a href="<?= esc_url(add_query_arg(['tab'=>'log','ip'=>$row->ip], $base_url)) ?>"
           style="text-decoration:none;color:var(--sa-navy)"><?= esc_html($row->ip) ?></a>
        <?php if ($geo_label): ?>
        <div style="font-family:inherit;font-size:10px;color:var(--sa-green-lt);margin-top:2px;white-space:nowrap"><span class="dashicons dashicons-location" style="font-size:12px;width:12px;height:12px;vertical-align:text-bottom"></span> <?= esc_html($geo_label) ?></div>
        <?php endif; ?>
        <?php else: ?>
        <span style="color:var(--sa-green-pale)">—</span>
        <?php endif; ?>
      </td>
      <td style="font-size:11px;color:var(--sa-green-lt);font-family:monospace">
        <?= esc_html($row->method ?: '—') ?>
      </td>
      <td style="font-size:11px;color:var(--sa-green-lt);max-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
          title="<?= esc_attr($row->user_agent ?? '') ?>">
        <?= esc_html($ua_short ?: '—') ?>
      </td>
      <td style="font-size:11px;line-height:1.6">
        <?= $extra_parts ? implode('<br>', $extra_parts) : '<span style="color:var(--sa-green-pale)">—</span>' ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php
  // Pagination — preserves active filters.
  if ($total_pages > 1):
    $pg_base = add_query_arg(array_merge(['page' => 'ss-security', 'tab' => 'log'], array_filter($filters)), admin_url('admin.php'));
    $win = 2;
  ?>
  <div style="display:flex;align-items:center;justify-content:center;gap:5px;padding:14px;flex-wrap:wrap">
    <?php if ($paged > 1): ?><a class="button" href="<?= esc_url(add_query_arg('paged', $paged - 1, $pg_base)) ?>">‹</a><?php endif; ?>
    <?php for ($p = 1; $p <= $total_pages; $p++):
      if ($p != 1 && $p != $total_pages && abs($p - $paged) > $win) {
        if ($p == 2 || $p == $total_pages - 1) echo '<span style="color:var(--sa-green-pale)">…</span>';
        continue;
      } ?>
      <a class="button <?= $p == $paged ? 'button-primary' : '' ?>" href="<?= esc_url(add_query_arg('paged', $p, $pg_base)) ?>"><?= $p ?></a>
    <?php endfor; ?>
    <?php if ($paged < $total_pages): ?><a class="button" href="<?= esc_url(add_query_arg('paged', $paged + 1, $pg_base)) ?>">›</a><?php endif; ?>
    <span style="font-size:12px;color:var(--sa-green-lt);margin-left:8px">Psl. <?= (int)$paged ?> / <?= (int)$total_pages ?></span>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
 
<!-- ══════════════════════════════════════════════════════════════
     SKIRTUKAS: NUSTATYMAI
     ══════════════════════════════════════════════════════════════ -->
<?php elseif ($active_tab === 'settings'): ?>
<div style="background:var(--sa-white);border:1px solid var(--sa-border);border-top:none;padding:24px">
 
  <?php
  // Visada įjungtos apsaugos (SS_Security::ALWAYS_ON) — jų išjungti negalima
  $always_on = [
    'Brute-force apsauga',
    'Honeypot laukas',
    'Audito žurnalas',
    '2FA Replay apsauga',
    '2FA atsarginiai kodai',
    'Sesijų panaikinimas pakeitus slaptažodį',
    'Dažnų slaptažodžių blokavimas',
    'Rolė registruojantis nustatoma serveryje',
  ];
  // Įjungiami / išjungiami moduliai
  $modules_on  = 0;
  $modules_all = 0;
  $module_list = [
    'ss_sec_ids_enabled'         => 'IDS',
    'ss_sec_new_device_enabled'  => 'Naujas įrenginys',
    'ss_sec_login_alert_enabled' => 'El. pašto perspėjimai',
  ];
  foreach ($module_list as $k => $lbl) {
    $modules_all++;
    if (SS_Security::get($k)) $modules_on++;
  }
  ?>

  <!-- Visada įjungtos apsaugos -->
  <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:12px;padding:14px 16px;background:#E5F4E8;border:1px solid #BFE3C6">
    <strong style="font-size:12px;color:var(--sa-forest);margin-right:4px"><span class="dashicons dashicons-lock" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom"></span> Visada įjungta:</strong>
    <?php foreach ($always_on as $lbl): ?>
    <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 10px;background:var(--sa-white);color:var(--sa-forest)">
      <span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px"></span> <?= esc_html($lbl) ?>
    </span>
    <?php endforeach; ?>
  </div>

  <!-- Modulių būsenos juosta -->
  <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:24px;padding:14px 16px;background:var(--sa-bg);border:1px solid var(--sa-border)">
    <strong style="font-size:12px;color:var(--sa-dark);margin-right:4px">Pasirenkami moduliai:</strong>
    <?php foreach ($module_list as $k => $lbl):
      $on = (bool) SS_Security::get($k); ?>
    <span style="display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:700;padding:3px 10px;background:<?= $on ? '#E5F4E8' : '#F0F0F0' ?>;color:<?= $on ? 'var(--sa-forest)' : '#999' ?>">
      <?= $on ? '<span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px"></span>' : '<span class="dashicons dashicons-minus" style="font-size:14px;width:14px;height:14px"></span>' ?> <?= esc_html($lbl) ?>
    </span>
    <?php endforeach; ?>
    <span style="margin-left:auto;font-size:12px;color:var(--sa-green-lt)"><?= $modules_on ?>/<?= $modules_all ?> įjungta</span>
  </div>
 
  <form method="post">
    <?php wp_nonce_field('ss_security_settings','ss_security_nonce'); ?>
    <input type="hidden" name="tab" value="settings">
 
    <div class="ss-two-col" style="gap:24px">
 
      <!-- ─── Brute-force ─────────────────────────────────── -->
      <div class="ss-card" style="margin-bottom:0">
        <h2>Brute-force apsauga</h2>

        <p style="font-size:12px;color:var(--sa-green-lt);margin:0 0 10px">Brute-force apsauga ir honeypot laukas visada įjungti — čia derinami tik slenksčiai.</p>
        <?php _ss_sec_f('ss_sec_login_alert_enabled','Perspėjimas vartotojui el. paštu', 'bool'); ?>
        <?php _ss_sec_f('ss_sec_login_alert_threshold','Perspėti po tiek nesėkmingų bandymų', 'int'); ?>
        <?php _ss_sec_f('ss_sec_max_sessions',       'Maks. aktyvių sesijų (0 = neribotas)', 'int'); ?>
 
        <hr style="border:none;border-top:1px solid var(--sa-border);margin:16px 0 12px">
 
        <!-- Įrenginio lygis -->
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--sa-green-lt);margin:0 0 4px">Įrenginio blokavimas (IP + naršyklė)</p>
        <p style="font-size:12px;color:var(--sa-green-lt);margin:0 0 10px">Po tiek klaidingų bandymų laikinai blokuojamas prisijungimas iš to įrenginio į <strong>visas</strong> paskyras. Kiekvienas paskesnis blokas vis ilgesnis. Skirtingi įrenginiai už to paties IP blokuojami atskirai.</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <?php
          $thr = [
            ['ss_sec_lockout_threshold_1','Klaidų iki blokavimo','int'],
            ['ss_sec_lockout_minutes_1',  '1-as blokas (min)',  'int'],
            ['ss_sec_lockout_minutes_2',  '2-as blokas (min)',  'int'],
            ['ss_sec_lockout_minutes_3',  '3-ias+ blokas (min)','int'],
          ];
          foreach ($thr as [$k,$lbl,$t]) _ss_sec_f($k,$lbl,$t);
          ?>
        </div>
      </div>
 
      <!-- ─── Slaptažodžiai ───────────────────────────────── -->
      <div class="ss-card" style="margin-bottom:0">
        <h2>Slaptažodžiai ir 2FA</h2>
        <p style="font-size:12px;color:var(--sa-green-lt);margin:0 0 10px">Dažnų slaptažodžių blokavimas, sesijų panaikinimas po keitimo, 2FA Replay apsauga ir atsarginiai kodai visada įjungti.</p>
        <?php
        $pw = [
          ['ss_sec_pw_min_length',      'Min. ilgis (simboliai)',             'int'],
          ['ss_sec_pw_require_upper',   'Reikalauti didžiosios raidės',       'bool'],
          ['ss_sec_pw_require_digit',   'Reikalauti skaičiaus',               'bool'],
        ];
        foreach ($pw as [$k,$lbl,$t]) _ss_sec_f($k,$lbl,$t);
        ?>

        <hr style="border:none;border-top:1px solid var(--sa-border);margin:16px 0 14px">
        <h2 style="font-size:14px!important;margin:0 0 12px!important">Registracija</h2>
        <p style="font-size:12px;color:var(--sa-green-lt);margin:0 0 10px">Rolė registruojantis visada nustatoma serveryje (SS-07) — iš formos jos pakeisti negalima.</p>
        <?php _ss_sec_f('ss_sec_reg_rate_limit', 'Maks. registracijų iš vieno IP (val)', 'int'); ?>
      </div>
 
      <!-- ─── Auditas ir IDS ──────────────────────────────── -->
      <div class="ss-card" style="margin-bottom:0">
        <h2>Logai ir IDS</h2>
        <p style="font-size:12px;color:var(--sa-green-lt);margin:0 0 10px">Audito žurnalas visada įjungtas — keičiamas tik įrašų saugojimo laikas.</p>
        <?php
        $ids = [
          ['ss_sec_audit_retention_days','Saugoti įrašus (dienų)',             'int'],
          ['ss_sec_ids_enabled',         'Automatinis IDS',                   'bool'],
          ['ss_sec_ids_window_min',      'IDS lango trukmė (min)',             'int'],
          ['ss_sec_ids_threshold',       'IDS slenkstis (unikalių paskyrų)',   'int'],
          ['ss_sec_new_device_enabled',  'Naujo įrenginio aptikimas',          'bool'],
        ];
        foreach ($ids as [$k,$lbl,$t]) _ss_sec_f($k,$lbl,$t);
        ?>
        <hr style="border:none;border-top:1px solid var(--sa-border);margin:16px 0 12px">
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--sa-green-lt);margin:0 0 4px">Tinklo nustatymai</p>
        <?php _ss_sec_f('ss_sec_trust_proxy', 'Tikėti X-Forwarded-For antraštei (gerai veikia tik jei naudojate Cloudflare ar nginx reverse proxy, kitais atvejais labai lengvai suklastoti IP, geriau išjungti)', 'bool'); ?>
      </div>
 
      <!-- ─── Pavojingi veiksmai ──────────────────────────── -->
      <div class="ss-card" style="margin-bottom:0;border-top:3px solid var(--sa-red)">
 
        <div style="padding:14px;background:#FFF5F5;border:1px solid #FFCDD2;">
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
            <input type="checkbox" name="ss_sec_clear_log"
                   style="width:18px;height:18px;accent-color:var(--sa-red)">
            <span>
              <strong style="display:block;font-size:13px;color:var(--sa-red)">Išvalyti visą žurnalą</strong>
              <span style="font-size:12px;color:var(--sa-green-lt)">Panaikinami visi <?= number_format($total) ?> įrašai</span>
            </span>
          </label>
        </div>
      </div>
 
    </div><!-- .ss-two-col -->
 
    <div style="margin-top:20px;display:flex;gap:10px;align-items:center">
      <button type="submit" class="button button-primary" style="padding:8px 22px;font-size:14px; line-height: 1.5!important;">Išsaugoti nustatymus</button>
    </div>
  </form>
</div>

<!-- ══════════════════════════════════════════════════════════════
     SKIRTUKAS: APSAUGA NUO BOTŲ (Cloudflare Turnstile)
     ══════════════════════════════════════════════════════════════ -->
<?php elseif ($active_tab === 'bot'):
  $ts_enabled    = (bool) SS_Turnstile::get('ss_ts_enabled');
  $ts_configured = SS_Turnstile::is_configured();
  $ts_site       = (string) SS_Turnstile::get('ss_ts_site_key');
  $ts_sw_mode    = SS_Turnstile::sitewide_mode();
  $ts_login_mode = SS_Turnstile::login_mode();

  // Vietinis jungiklio (toggle) atvaizdavimo pagalbininkas
  $ts_toggle = function(string $k, string $lbl, string $hint = '') {
    $on = (bool) SS_Turnstile::get($k);
    echo '<div style="display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid var(--sa-border);gap:12px">';
    echo '<label for="'.esc_attr($k).'" style="cursor:pointer;flex:1">'
       . '<span style="font-size:13px;color:var(--sa-dark);display:block">'.esc_html($lbl).'</span>';
    if ($hint) echo '<span style="font-size:11px;color:var(--sa-green-lt);display:block;margin-top:2px">'.esc_html($hint).'</span>';
    echo '</label>';
    echo '<label class="ss-switch" style="flex-shrink:0"><input type="checkbox" id="'.esc_attr($k).'" name="'.esc_attr($k).'"'.($on?' checked':'').'><span class="ss-slider"></span></label>';
    echo '</div>';
  };

  // Statistikos reikšmės
  $ts_pass  = (int)($ts_stats['pass']  ?? 0);
  $ts_fail  = (int)($ts_stats['fail']  ?? 0);
  $ts_block = (int)($ts_stats['block'] ?? 0);
  $ts_rate  = (int)($ts_stats['pass_rate'] ?? 0);
?>
<div style="background:var(--sa-white);border:1px solid var(--sa-border);border-top:none;padding:24px">

  <?php if (!$ts_configured): ?>
  <div style="padding:14px 16px;background:#FFF4D6;border:1px solid #E8D68A;margin-bottom:20px">
    <strong style="font-size:13px;color:#8A6A1F"><span class="dashicons dashicons-info" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span> Reikia sukonfigūruoti.</strong>
    <p style="font-size:12px;color:#8A6A1F;margin:6px 0 0">
      Sukurkite nemokamą Turnstile valdiklį
      <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">Cloudflare skydelyje</a>
      ir įklijuokite <em>Site Key</em> bei <em>Secret Key</em> žemiau. Rekomenduojamas valdiklio tipas — <strong>Managed</strong>.
    </p>
  </div>
  <?php endif; ?>

  <!-- Statistikos kortelės (24 val.) -->
  <div class="ss-stats-row" style="margin-bottom:22px">
    <div class="ss-stat" style="--sc:var(--sa-green)">
      <div class="ss-stat-ico"><span class="dashicons dashicons-yes-alt"></span></div>
      <div><div class="ss-stat-n"><?= $ts_pass ?></div><div class="ss-stat-l">Praėję patikrą (24val)</div></div>
    </div>
    <div class="ss-stat" style="--sc:<?= $ts_block > 0 ? 'var(--sa-red)' : 'var(--sa-green-lt)' ?>">
      <div class="ss-stat-ico"><span class="dashicons dashicons-shield-alt"></span></div>
      <div><div class="ss-stat-n"><?= $ts_block ?></div><div class="ss-stat-l">Užblokuoti botai (24val)</div></div>
    </div>
    <div class="ss-stat" style="--sc:<?= $ts_fail > 0 ? 'var(--sa-red)' : 'var(--sa-green-lt)' ?>">
      <div class="ss-stat-ico"><span class="dashicons dashicons-dismiss"></span></div>
      <div><div class="ss-stat-n"><?= $ts_fail ?></div><div class="ss-stat-l">Nepavykusios patikros (24val)</div></div>
    </div>
    <div class="ss-stat" style="--sc:var(--sa-navy)">
      <div class="ss-stat-ico"><span class="dashicons dashicons-chart-bar"></span></div>
      <div><div class="ss-stat-n"><?= $ts_rate ?>%</div><div class="ss-stat-l">Sėkmingų patikrų dalis</div></div>
    </div>
  </div>

  <?php if (!SS_Security::get('ss_sec_trust_proxy')): ?>
  <div style="padding:12px 16px;background:#E7EEF7;border:1px solid #B9CCE4;margin-bottom:18px">
    <strong style="font-size:12px;color:var(--sa-navy)"><span class="dashicons dashicons-info" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom"></span> Naudojate Cloudflare?</strong>
    <p style="font-size:12px;color:var(--sa-navy);margin:6px 0 0">
      Skirtuke <strong>Nustatymai → Tinklo nustatymai</strong> įjunkite „Tikėti X-Forwarded-For antraštei" — kitaip visų lankytojų IP bus rodomas kaip Cloudflare serverio IP (sugadinamas rate-limit, IDS ir žurnalas). Įjunkite TIK jei tikrai esate už Cloudflare/nginx.
    </p>
  </div>
  <?php endif; ?>

  <form method="post">
    <?php wp_nonce_field('ss_security_settings','ss_security_nonce'); ?>
    <input type="hidden" name="tab" value="bot">

    <div class="ss-two-col" style="gap:24px">

      <!-- ─── Pagrindiniai raktai ───────────────────────────── -->
      <div class="ss-card" style="margin-bottom:0">
        <h2>Cloudflare Turnstile</h2>
        <?php $ts_toggle('ss_ts_enabled', 'Įjungti apsaugą nuo botų', 'Pagrindinis jungiklis. Be raktų neveiks.'); ?>

        <div class="ss-fld" style="margin-top:14px">
          <label for="ss_ts_site_key" style="font-size:12px;font-weight:700;color:var(--sa-dark);display:block;margin-bottom:5px">Site Key</label>
          <input type="text" id="ss_ts_site_key" name="ss_ts_site_key" value="<?= esc_attr($ts_site) ?>"
                 placeholder="0x4AAAAAAA…" autocomplete="off" spellcheck="false"
                 style="width:100%;height:34px;border:1.5px solid var(--sa-border);padding:0 10px;font-family:monospace;font-size:12px;outline:none">
        </div>
        <div class="ss-fld" style="margin-top:12px">
          <label for="ss_ts_secret_key" style="font-size:12px;font-weight:700;color:var(--sa-dark);display:block;margin-bottom:5px">Secret Key <?php if (SS_Turnstile::has_secret()): ?><span style="color:var(--sa-forest);font-weight:600">· išsaugotas</span><?php endif; ?></label>
          <input type="password" id="ss_ts_secret_key" name="ss_ts_secret_key" value=""
                 placeholder="<?= SS_Turnstile::has_secret() ? '•••••••• (palikite tuščią, kad nekeistumėte)' : '0x4AAAAAAA…' ?>" autocomplete="new-password" spellcheck="false"
                 style="width:100%;height:34px;border:1.5px solid var(--sa-border);padding:0 10px;font-family:monospace;font-size:12px;outline:none">
          <p style="font-size:11px;color:var(--sa-green-lt);margin:6px 0 0">Šifruojamas ir saugomas serveryje; niekada nerodomas iš naujo. Įveskite naują reikšmę tik jei norite pakeisti.</p>
        </div>
      </div>

      <!-- ─── Prisijungimo formos ───────────────────────────── -->
      <div class="ss-card" style="margin-bottom:0">
        <h2>Prisijungimo formos</h2>
        <?php $ts_toggle('ss_ts_login_widget', 'Valdiklis mokinių prisijungimo formoje', 'Matomas valdiklis pagrindinėje login kortelėje.'); ?>
        <?php $ts_toggle('ss_ts_wpadmin', 'Ginti wp-admin prisijungimą', 'Turnstile valdiklis wp-login.php formoje (veikia ir pakeistu adresu).'); ?>
        <?php $ts_toggle('ss_ts_block_wpadmin', 'Blokuoti wp-admin prisijungimą', 'Visi wp-login.php prisijungimai nukreipiami į plėtinio login. Administratoriai jungiasi per plėtinio formą.'); ?>

        <div class="ss-fld" style="margin-top:14px">
          <label for="ss_ts_login_mode" style="font-size:12px;font-weight:700;color:var(--sa-dark);display:block;margin-bottom:5px">Valdiklio režimas (login)</label>
          <select id="ss_ts_login_mode" name="ss_ts_login_mode" style="width:100%;height:34px;border:1.5px solid var(--sa-border);padding:0 8px;font-size:13px;outline:none">
            <option value="managed"   <?= $ts_login_mode==='managed'  ?'selected':'' ?>>Managed — CF sprendžia (rekomenduojama)</option>
            <option value="invisible" <?= $ts_login_mode==='invisible'?'selected':'' ?>>Nematomas — iššoka tik esant įtarimui</option>
            <option value="always"    <?= $ts_login_mode==='always'   ?'selected':'' ?>>Visada matomas žymimasis langelis</option>
          </select>
        </div>
      </div>

      <!-- ─── Viso saito gynyba ─────────────────────────────── -->
      <div class="ss-card" style="margin-bottom:0">
        <h2>Viso saito gynyba</h2>
        <div class="ss-fld">
          <label for="ss_ts_sitewide_mode" style="font-size:12px;font-weight:700;color:var(--sa-dark);display:block;margin-bottom:5px">Režimas visose svetainės puslapiuose</label>
          <select id="ss_ts_sitewide_mode" name="ss_ts_sitewide_mode" style="width:100%;height:34px;border:1.5px solid var(--sa-border);padding:0 8px;font-size:13px;outline:none">
            <option value="off"          <?= $ts_sw_mode==='off'         ?'selected':'' ?>>Išjungta (tik login formos)</option>
            <option value="background"   <?= $ts_sw_mode==='background'  ?'selected':'' ?>>Fone — turinys matomas, iššūkis iššoka tik prireikus</option>
            <option value="interstitial" <?= $ts_sw_mode==='interstitial'?'selected':'' ?>>Pilnas ekranas — patikra prieš turinį + slapukas</option>
          </select>
          <p style="font-size:11px;color:var(--sa-green-lt);margin:6px 0 0">
            <strong>Fone:</strong> minimalus trukdis, gera SEO. &nbsp;<strong>Pilnas ekranas:</strong> kietas blokas botams — praėjus, slapukas leidžia naršyti be pakartotinių patikrų.
          </p>
        </div>
        <div style="margin-top:6px">
          <?php $ts_toggle('ss_ts_challenge_new', 'Mesti iššūkį visiems naujiems lankytojams', 'Pilno ekrano režime: kiekvienas be „žmogaus" slapuko gauna patikrą. Išjungus — tik įtartiniems (rate-limit / IDS / naujas įrenginys).'); ?>
          <?php $ts_toggle('ss_ts_allow_search', 'Praleisti paieškos robotus', 'Google, Bing ir kt. patikrinti pagal atvirkštinį DNS.'); ?>
        </div>
        <div class="ss-fld" style="margin-top:14px">
          <label for="ss_ts_pass_ttl" style="font-size:12px;font-weight:700;color:var(--sa-dark);display:block;margin-bottom:5px">Slapuko galiojimas (min.)</label>
          <input type="number" id="ss_ts_pass_ttl" name="ss_ts_pass_ttl" min="5" max="10080"
                 value="<?= (int) SS_Turnstile::get('ss_ts_pass_ttl') ?>"
                 style="width:120px;height:34px;border:1.5px solid var(--sa-border);padding:0 10px;font-size:13px;text-align:center;outline:none">
          <p style="font-size:11px;color:var(--sa-green-lt);margin:6px 0 0">Kiek laiko lankytojui nebekartojama patikra po sėkmingo praėjimo.</p>
        </div>
        <div class="ss-fld" style="margin-top:12px">
          <label for="ss_ts_exempt_paths" style="font-size:12px;font-weight:700;color:var(--sa-dark);display:block;margin-bottom:5px">Neginami keliai (po vieną eilutėje)</label>
          <textarea id="ss_ts_exempt_paths" name="ss_ts_exempt_paths" rows="3"
                    placeholder="/wp-json/&#10;/robots.txt&#10;/mano-slaptas-login"
                    style="width:100%;border:1.5px solid var(--sa-border);padding:8px 10px;font-family:monospace;font-size:12px;outline:none;resize:vertical"><?= esc_textarea((string) SS_Turnstile::get('ss_ts_exempt_paths')) ?></textarea>
          <p style="font-size:11px;color:var(--sa-green-lt);margin:6px 0 0">Naudinga, jei naudojate „paslėpto login" įskiepį — įrašykite jo kelią, kad tarpinis puslapis jo neblokuotų.</p>
        </div>
      </div>

      <!-- ─── Kešas (Cloudflare) ─────────────────────────────── -->
      <div class="ss-card" style="margin-bottom:0">
        <h2>Kešas</h2>
        <p style="font-size:12px;color:var(--sa-green-lt);margin:0 0 10px">
          Iššūkio puslapis nekešuojamas automatiškai (Set-Cookie + CDN-Cache-Control). Jei naudojate Cloudflare, įrašykite API žetoną ir Zone ID — tada mygtukas „Atnaujinti" išvalys ir Cloudflare kešą.
        </p>
        <div class="ss-fld">
          <label for="ss_ts_cf_zone" style="font-size:12px;font-weight:700;color:var(--sa-dark);display:block;margin-bottom:5px">Cloudflare Zone ID</label>
          <input type="text" id="ss_ts_cf_zone" name="ss_ts_cf_zone" value="<?= esc_attr((string) SS_Turnstile::get('ss_ts_cf_zone')) ?>"
                 placeholder="pvz. 023e105f4ecef8ad9ca31a8372d0c353" autocomplete="off" spellcheck="false"
                 style="width:100%;height:34px;border:1.5px solid var(--sa-border);padding:0 10px;font-family:monospace;font-size:12px;outline:none">
        </div>
        <div class="ss-fld" style="margin-top:12px">
          <label for="ss_ts_cf_token" style="font-size:12px;font-weight:700;color:var(--sa-dark);display:block;margin-bottom:5px">Cloudflare API žetonas <?php if (SS_Turnstile::has_cf_token()): ?><span style="color:var(--sa-forest);font-weight:600">· išsaugotas</span><?php endif; ?></label>
          <input type="password" id="ss_ts_cf_token" name="ss_ts_cf_token" value=""
                 placeholder="<?= SS_Turnstile::has_cf_token() ? '•••••••• (palikite tuščią, kad nekeistumėte)' : 'Token su Cache Purge teise' ?>" autocomplete="new-password" spellcheck="false"
                 style="width:100%;height:34px;border:1.5px solid var(--sa-border);padding:0 10px;font-family:monospace;font-size:12px;outline:none">
          <p style="font-size:11px;color:var(--sa-green-lt);margin:6px 0 0">Cloudflare → My Profile → API Tokens → sukurkite žetoną su teise <em>Zone · Cache Purge</em>.</p>
        </div>
      </div>

    </div><!-- .ss-two-col -->

    <div style="margin-top:20px;display:flex;gap:10px;align-items:center">
      <button type="submit" class="button button-primary" style="padding:8px 22px;font-size:14px;line-height:1.5!important;">Išsaugoti nustatymus</button>
    </div>
  </form>

  <!-- Atskira forma: kešo valymas (nesaugo nustatymų) -->
  <form method="post" style="margin-top:18px;padding-top:18px;border-top:1px solid var(--sa-border);display:flex;align-items:center;gap:12px;flex-wrap:wrap"
        onsubmit="return confirm('Išvalyti visą kešą? Tai priverstinai atnaujins visų puslapių turinį.');">
    <?php wp_nonce_field('ss_security_settings','ss_security_nonce'); ?>
    <input type="hidden" name="ss_ts_purge" value="1">
    <button type="submit" class="button button-secondary" style="padding:8px 20px;font-size:14px;line-height:1.5!important;">
      <span class="dashicons dashicons-update" style="vertical-align:text-bottom"></span> Atnaujinti (išvalyti kešą)
    </button>
    <span style="font-size:12px;color:var(--sa-green-lt)">Išvalo WP ir kešavimo įskiepių kešą; jei nurodyti Cloudflare raktai — ir CF edge kešą.</span>
  </form>
</div>
<?php endif; ?>
</div>