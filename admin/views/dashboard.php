<?php if (!defined('ABSPATH')) exit;
$ss_page_title = 'Tvarkaraščių valdymo skydelis';
$ss_page_subtitle = stripslashes(get_option('ss_school_name', SS_SCHOOL_NAME));
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>

<div class="ss-stats-row">
  <?php $sc=[
    ['Tvarkaraščių','classes','var(--sa-navy)','dashicons-groups'],
    ['Mokytojų','teachers','var(--sa-green)','dashicons-admin-users'],
    ['Skirtingų pamokų','lessons','var(--sa-forest)','dashicons-book'],
    ['Mokinių','students','var(--sa-dark)','dashicons-welcome-learn-more']
  ];
  foreach($sc as[$l,$k,$c,$i]):?>
  <div class="ss-stat" style="--sc:<?=$c?>">
    <div class="ss-stat-ico"><span class="dashicons <?=$i?>"></span></div>
    <div><div class="ss-stat-n"><?= (int)($stats[$k] ?? 0) ?></div><div class="ss-stat-l"><?=$l?></div></div>
  </div>
  <?php endforeach;?>
</div>

<div class="ss-two-col">
<div class="ss-card">
  <h2>Greitos nuorodos</h2>
  <?php $links=[
    ['ss-import','Importuoti tvarkaraštį','dashicons-upload'],
    ['ss-teachers','Mokytojai','dashicons-admin-users'],
    ['ss-teacher-accounts','Mokytojų paskyros','dashicons-id-alt'],
    ['ss-lessons','Pamokos','dashicons-book'],
    ['ss-classes','Tvarkaraščiai','dashicons-calendar-alt'],
    ['ss-schedule-codes','Tvarkaraščių kodai','dashicons-admin-network'],
    ['ss-students','Mokiniai','dashicons-welcome-learn-more'],
    ['ss-registrations','Registracijos','dashicons-id'],
    ['ss-changes','Pakeitimai','dashicons-update'],
    ['ss-surveys','Apklausos / Viktorinos','dashicons-clipboard'],
    ['ss-settings','Sistemos nustatymai','dashicons-admin-generic'],
  ];
  foreach($links as[$p,$l,$i]):?>
  <a href="<?=admin_url("admin.php?page=$p")?>" class="ss-quick-link">
    <span class="dashicons <?=$i?>"></span><?=$l?><span class="dashicons dashicons-arrow-right-alt2" style="margin-left:auto;color:var(--sa-green-pale)"></span>
  </a>
  <?php endforeach;?>
</div>
<div class="ss-card">
  <h2>Sistemos puslapiai</h2>
  <?php foreach([
      ['ss_login_page_id','Prisijungimas','dashicons-admin-network'],
      ['ss_dashboard_page_id','Tvarkaraštis','dashicons-calendar-alt'],
      ['ss_changes_page_id','Pakeitimai','dashicons-forms'],
      ['ss_settings_page_id','Vartotojų nustatymai','dashicons-id'],
      ['ss_imp_page_id','Individualus planas (IMP)','dashicons-clipboard']
    ] as[$opt,$label,$icon]):
    $pid = (int)get_option($opt);
    $url = $pid ? get_permalink($pid) : '';
  ?>
  <div class="ss-page-row">
    <span class="dashicons <?=$icon?>" style="color:var(--sa-green)"></span>
    <div>
      <strong><?= esc_html($label) ?></strong><br>
      <?php if ($url): ?>
        <a href="<?= esc_url($url) ?>" target="_blank" style="font-size:12px;color:var(--sa-green);word-break:break-all"><?= esc_html($url) ?></a>
      <?php else: ?>
        <span style="font-size:12px;color:var(--sa-red)">Nepriskirta</span>
      <?php endif; ?>
    </div>
    <?php if ($url): ?>
      <a href="<?= esc_url($url) ?>" target="_blank" class="button button-small">Atidaryti</a>
    <?php else: ?>
      <a href="<?= admin_url('admin.php?page=ss-settings') ?>" class="button button-small">Nustatyti</a>
    <?php endif; ?>
  </div>
  <?php endforeach;?>
</div>
</div>

<div class="ss-card">
  <h2>Apie sistemą</h2>
  <p style="margin:0;color:var(--sa-green-lt);font-size:13px;line-height:1.6">
    Versija: <strong style="color:var(--sa-dark)"><?= esc_html(SS_VERSION) ?></strong> ·
    Mokslo metai: <strong style="color:var(--sa-dark)"><?= esc_html(get_option('ss_school_year', SS_Time::date('Y').'–'.((int)SS_Time::date('Y')+1))) ?></strong>
  </p>
</div>
</div>
