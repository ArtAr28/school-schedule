<?php
/**
 * Shared app chrome: the left sidebar + the mobile drawer.
 * Used by every plugin page so the menu is IDENTICAL everywhere.
 *
 * Set before include:
 *   $ss_on_dashboard (bool)   — true only on the dashboard page; there the
 *                               schedule/personal/… items are <button data-tab>
 *                               (JS tab switching). Elsewhere they are links to
 *                               the dashboard (?tab=…).
 *   $ss_active       (string) — active item key:
 *                               schedule|allschedules|teachers|rooms|personal|changes|settings
 */
if (!defined('ABSPATH')) exit;

$ss_on_dashboard = !empty($ss_on_dashboard);
$ss_active       = isset($ss_active) ? $ss_active : '';

$ss_logged   = is_user_logged_in();
$ss_user     = $ss_logged ? wp_get_current_user() : null;
$ss_is_admin = $ss_logged && (current_user_can('manage_options') || current_user_can('ss_manage_all'));
$ss_is_teach = $ss_logged && in_array('ss_teacher',  (array)$ss_user->roles, true);
$ss_is_empl  = $ss_logged && (in_array('ss_employee', (array)$ss_user->roles, true) || current_user_can('ss_employee_access'));
/* Staff (admin / employee) have no personal class schedule → their first item is a Dashboard. */
$ss_staff    = $ss_is_admin || $ss_is_empl;
/* „Matyti visų tvarkaraščius" teisė — pagal capability, kad individualiai suteikta teisė veiktų. */
$ss_can_all  = $ss_logged && ($ss_is_admin || current_user_can('ss_view_all_schedules'));
/* IMP atskiri skirtukai — kai NEturi bendro „Visi tvarkaraščiai" (kitaip dubliuotųsi). */
$ss_is_imp   = ($ss_logged && !$ss_is_admin && !$ss_is_teach && !$ss_is_empl && !$ss_can_all)
                 ? (SS_Database::user_is_imp($ss_user->ID) || current_user_can('ss_imp_access')) : false;

$ss_role_lbl = $ss_is_admin ? 'Administratorius'
             : ($ss_is_teach ? 'Mokytojas'
             : ($ss_is_empl  ? 'Darbuotojas' : 'Mokinys'));

$ss_logo_id  = (int) get_option('ss_logo_id', 0);
$ss_logo_url = $ss_logo_id ? wp_get_attachment_image_url($ss_logo_id, 'medium') : get_option('ss_logo_url', '');
if (!$ss_logo_url) { $ss_tl = get_theme_mod('custom_logo'); $ss_logo_url = $ss_tl ? wp_get_attachment_image_url($ss_tl, 'medium') : ''; }
$ss_site     = stripslashes(get_option('ss_school_name', SS_SCHOOL_NAME));

$ss_dash_url  = ($ss_pid = get_option('ss_dashboard_page_id')) ? get_permalink($ss_pid) : home_url('/');
$ss_chg_url   = ($ss_pid = get_option('ss_changes_page_id'))   ? get_permalink($ss_pid) : '';
$ss_set_url   = ($ss_pid = get_option('ss_settings_page_id'))  ? get_permalink($ss_pid) : '';
$ss_login_url = ($ss_pid = get_option('ss_login_page_id'))     ? get_permalink($ss_pid) : wp_login_url();

$ss_ic = array(
  'dash' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM13 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2zM13 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"/></svg>',
  'cal'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
  'all'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>',
  'tch'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>',
  'room' => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v14a1 1 0 01-1 1h-3v-3a2 2 0 00-4 0v3H5a1 1 0 01-1-1V4zm3 1a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h.01a1 1 0 100-2H10zm3 0a1 1 0 100 2h.01a1 1 0 100-2H13zM7 9a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h.01a1 1 0 100-2H10zm3 0a1 1 0 100 2h.01a1 1 0 100-2H13z" clip-rule="evenodd"/></svg>',
  'prs'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/></svg>',
  'chg'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
  'set'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>',
  'adm'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/></svg>',
  'home' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>',
  'doc'  => '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>',
  'star' => '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.605 9.8c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>',
);

if (!function_exists('ss_nav_tab')) {
  /* A tab item: a <button data-tab> on the dashboard, a link to ?tab= elsewhere. */
  function ss_nav_tab($key, $label, $icon, $is_active, $on_dash, $dash_url, $cls = '') {
    $c = 'ss-hnav-btn' . ($cls ? ' '.$cls : '') . ($is_active ? ' active' : '');
    if ($on_dash) {
      return '<button class="'.esc_attr($c).'" data-tab="'.esc_attr($key).'">'.$icon.' '.esc_html($label).'</button>';
    }
    $href = ($key === 'schedule') ? $dash_url : add_query_arg('tab', $key, $dash_url);
    return '<a class="'.esc_attr($c).'" href="'.esc_url($href).'">'.$icon.' '.esc_html($label).'</a>';
  }
}

/* ── Build the shared item list (same for sidebar + drawer) ── */
$ss_items = '';
if (!$ss_logged) {
  $ss_items .= ss_nav_tab('schedule', 'Tvarkaraštis', $ss_ic['cal'], $ss_active === 'schedule', false, $ss_login_url);
  if ($ss_chg_url) $ss_items .= '<a class="ss-hnav-btn ss-hnav-changes'.($ss_active==='changes'?' active':'').'" href="'.esc_url($ss_chg_url).'">'.$ss_ic['chg'].' Pakeitimai</a>';
} else {
  if ($ss_staff) {
    $ss_items .= ss_nav_tab('allschedules', 'Dashboard', $ss_ic['dash'], in_array($ss_active, array('allschedules','schedule'), true), $ss_on_dashboard, $ss_dash_url);
  } else {
    $ss_items .= ss_nav_tab('schedule', 'Tvarkaraštis', $ss_ic['cal'], $ss_active === 'schedule', $ss_on_dashboard, $ss_dash_url);
  }
  if ($ss_chg_url) $ss_items .= '<a class="ss-hnav-btn ss-hnav-changes'.($ss_active==='changes'?' active':'').'" href="'.esc_url($ss_chg_url).'">'.$ss_ic['chg'].' Pakeitimai</a>';
  $ss_items .= ss_nav_tab('personal', 'Asmeniniai', $ss_ic['prs'], $ss_active === 'personal', $ss_on_dashboard, $ss_dash_url);
  // „Visi tvarkaraščiai" — mokytojams ir visiems, kas turi „matyti visų tvarkaraščius"
  // teisę (bet ne staff'ui — jiems tai jau pirmas „Dashboard" punktas).
  if (!$ss_staff && ($ss_is_teach || $ss_can_all)) {
    $ss_items .= ss_nav_tab('allschedules', 'Visi tvarkaraščiai', $ss_ic['all'], $ss_active === 'allschedules', $ss_on_dashboard, $ss_dash_url);
  }
  if ($ss_is_imp) {
    $ss_items .= ss_nav_tab('teachers', 'Mokytojų tvarkaraščiai', $ss_ic['tch'], $ss_active === 'teachers', $ss_on_dashboard, $ss_dash_url);
    $ss_items .= ss_nav_tab('rooms',    'Kabinetų tvarkaraščiai', $ss_ic['room'], $ss_active === 'rooms', $ss_on_dashboard, $ss_dash_url);
  }
  // „Individualus planas" — kai mokinio klasė įtraukta į priėmimą (langą tikrina pats puslapis).
  $ss_imp_url = ($ss_imp_pid = get_option('ss_imp_page_id')) ? get_permalink($ss_imp_pid) : '';
  if ($ss_imp_url && class_exists('SS_IMP') && SS_IMP::user_can_see($ss_user->ID)) {
    $ss_items .= '<a class="ss-hnav-btn ss-hnav-imp'.($ss_active==='imp'?' active':'').'" href="'.esc_url($ss_imp_url).'">'.$ss_ic['doc'].' Individualus planas</a>';
  }
  // „Ąžuolyno Eurovizija" — matoma balsuojantiems (kai įjungta) ARBA turintiems
  // teisę valdyti balsavimą (tada mygtukas veda į priekinį valdymo skydelį).
  $ss_euro_url = ($ss_pid = get_option('ss_euro_page_id')) ? get_permalink($ss_pid) : '';
  if ($ss_euro_url && class_exists('SS_Eurovizija') && (
        SS_Eurovizija::user_can_manage($ss_user->ID) ||
        (SS_Eurovizija::is_enabled() && SS_Eurovizija::user_can_vote($ss_user->ID))
      )) {
    // Logotipas (apvalus) vietoj žvaigždės, jei įkeltas.
    $ss_euro_logo = SS_Eurovizija::logo_url('thumbnail');
    $ss_euro_icon = $ss_euro_logo
      ? '<img src="'.esc_url($ss_euro_logo).'" alt="" class="ss-hnav-euro-logo">'
      : $ss_ic['star'];
    $ss_items .= '<a class="ss-hnav-btn ss-hnav-euro'.($ss_active==='eurovizija'?' active':'').'" href="'.esc_url($ss_euro_url).'">'.$ss_euro_icon.' '.esc_html(SS_Eurovizija::title()).'</a>';
  }
  // ── Žinutės (socialinis modulis). Profilis atveriamas paspaudus savo vardą. ──
  $ss_profile_url = ($ss_pid = get_option('ss_profile_page_id')) ? get_permalink($ss_pid) : '';
  $ss_msgs_url    = ($ss_pid = get_option('ss_messages_page_id')) ? get_permalink($ss_pid) : '';
  $ss_ic_chat = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.84 8.84 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/></svg>';
  if ($ss_msgs_url) {
    $ss_unread = class_exists('SS_Social') ? SS_Social::unread_total($ss_user->ID) : 0;
    $ss_badge  = $ss_unread > 0 ? '<span class="ss-hnav-badge">'.($ss_unread > 99 ? '99+' : (int)$ss_unread).'</span>' : '';
    $ss_items .= '<a class="ss-hnav-btn ss-hnav-msgs'.($ss_active==='messages'?' active':'').'" href="'.esc_url($ss_msgs_url).'">'.$ss_ic_chat.' Žinutės'.$ss_badge.'</a>';
  }
  if ($ss_set_url) $ss_items .= '<a class="ss-hnav-btn'.($ss_active==='settings'?' active':'').'" href="'.esc_url($ss_set_url).'">'.$ss_ic['set'].' Nustatymai</a>';
  if ($ss_is_admin) $ss_items .= '<a class="ss-hnav-btn ss-hnav-admin" href="'.esc_url(admin_url('admin.php?page=school-schedule')).'" target="_blank">'.$ss_ic['adm'].' Admin</a>';
}
$ss_home   = '<a class="ss-hnav-btn ss-hnav-home" href="'.esc_url(home_url('/')).'">'.$ss_ic['home'].' Į pagrindinį</a>';
$ss_brand  = '<div class="ss-header-brand">'
           . ($ss_logo_url
                ? '<img src="'.esc_url($ss_logo_url).'" alt="'.esc_attr($ss_site).'" class="ss-header-logo">'
                : '<span class="ss-header-logo-fb">'.esc_html(mb_substr($ss_site,0,1)).'</span>')
           . '<span class="ss-header-school">'.esc_html($ss_site).'</span></div>';
$ss_logout_svg = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd"/></svg>';
// Shared user block (avatar + name + role) — same on the sidebar and in the drawer.
// Paspaudus atveriamas SAVO profilis (jei profilio puslapis sukonfigūruotas).
$ss_ubx_tag  = $ss_profile_url ? 'a' : 'div';
$ss_ubx_attr = $ss_profile_url ? ' href="'.esc_url($ss_profile_url).'"' : '';
$ss_userbox = $ss_logged
  ? '<'.$ss_ubx_tag.' class="ss-header-user'.($ss_profile_url ? ' ss-header-user-link' : '').($ss_active==='profile' ? ' active' : '').'"'.$ss_ubx_attr.'>'
      . get_avatar($ss_user->ID, 36, '', '', array('class' => 'ss-hdr-avatar'))
      . '<div class="ss-header-uinfo"><span class="ss-header-uname">'.esc_html($ss_user->display_name).'</span>'
      . '<span class="ss-header-urole">'.esc_html($ss_role_lbl).'</span></div></'.$ss_ubx_tag.'>'
  : '';
?>
<header class="ss-header ss-many-nav">
  <?= $ss_brand ?>
  <nav class="ss-header-nav" id="ss-header-nav"><?= $ss_items . $ss_home ?></nav>
  <div class="ss-header-right">
    <?php if ($ss_logged): ?>
      <?= $ss_userbox ?>
      <button class="ss-logout-btn" id="ss-do-logout" title="Atsijungti">
        <?= $ss_logout_svg ?><span class="ss-logout-label">Atsijungti</span>
      </button>
    <?php else: ?>
      <a href="<?= esc_url($ss_login_url) ?>" class="ss-logout-btn" style="text-decoration:none;">
        <span class="ss-logout-label">Prisijungti</span>
        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
      </a>
    <?php endif; ?>
    <button class="ss-hamburger" id="ss-hamburger" aria-label="Meniu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>

<!-- Mobile drawer — mirrors the desktop sidebar (brand · nav · user + logout) -->
<div class="ss-mobile-nav" id="ss-mobile-nav">
  <?= $ss_brand ?>
  <nav class="ss-mobile-items"><?= $ss_items . $ss_home ?></nav>
  <?php if ($ss_logged): ?>
  <div class="ss-mobile-foot">
    <?= $ss_userbox ?>
    <button class="ss-logout-btn" id="ss-do-logout-mob">
      <?= $ss_logout_svg ?><span class="ss-logout-label">Atsijungti</span>
    </button>
  </div>
  <?php endif; ?>
</div>
<div class="ss-nav-backdrop" id="ss-nav-backdrop"></div>
