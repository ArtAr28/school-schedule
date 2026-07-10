<?php
if (!defined('ABSPATH')) exit;
/**
 * Shared admin page header partial.
 * Variables expected:
 *   $ss_page_title    – Page H1
 *   $ss_page_subtitle – (optional) page subtitle / description
 *   $ss_page_actions  – (optional) HTML rendered after the title (buttons etc.)
 */
$logo_url = get_option('ss_logo_url', '');
$ss_page_title    = $ss_page_title    ?? '';
$ss_page_subtitle = $ss_page_subtitle ?? '';
$ss_page_actions  = $ss_page_actions  ?? '';
?>
<div class="ss-page-header">
  <?php if ($logo_url): ?>
    <img src="<?= esc_url($logo_url) ?>" alt="" class="ss-page-header-logo">
  <?php endif; ?>
  <div class="ss-page-header-text">
    <h1 class="wp-heading-inline ss-page-title"><?= esc_html($ss_page_title) ?></h1>
    <?php if ($ss_page_subtitle): ?>
      <p class="ss-page-subtitle"><?= esc_html($ss_page_subtitle) ?></p>
    <?php endif; ?>
  </div>
  <?php if ($ss_page_actions): ?>
    <div class="ss-page-header-actions"><?= $ss_page_actions /* trusted */ ?></div>
  <?php endif; ?>
</div>
<hr class="wp-header-end">
