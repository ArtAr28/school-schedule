<?php
/**
 * Standalone „Individualus planas" puslapis (atvaizduojamas per template_redirect).
 * Naudoja bendrą navbar (app-nav.php) ir įdeda mokinio įkėlimo formą (imp-form.php).
 */
if (!defined('ABSPATH')) exit;
?>
<div class="ss-app ss-imp-page" id="ss-app">

  <?php
    $ss_on_dashboard = false;
    $ss_active       = 'imp';
    include SS_PLUGIN_DIR . 'public/views/partials/app-nav.php';
    if (file_exists(SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php')) {
        include SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php';
    }
  ?>

  <main class="ss-main" style="padding-top:18px;">
    <div style="max-width:920px;margin:0 auto;padding:0 14px 40px;">
      <?php include SS_PLUGIN_DIR . 'public/views/imp-form.php'; ?>
    </div>
  </main>
</div>
