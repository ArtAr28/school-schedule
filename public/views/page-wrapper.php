<?php
/**
 * Universal standalone wrapper for all plugin pages.
 * Loaded via template_redirect — theme header/footer/navbar never rendered.
 * $ss_view_file must be set before including this file.
 */
if (!defined('ABSPATH')) exit;

// ── Security headers for these standalone pages (login/dashboard/settings/…) ──
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');                 // anti-clickjacking
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ── Strip theme stylesheets to prevent CSS conflicts ──────────────────────────
add_action('wp_print_styles', function () {
    global $wp_styles;
    $tpl = get_template_directory_uri();
    $sty = get_stylesheet_directory_uri();
    foreach (array_keys($wp_styles->registered) as $h) {
        $src = $wp_styles->registered[$h]->src ?? '';
        if ($src && (strpos($src, $tpl) !== false || strpos($src, $sty) !== false)) {
            wp_dequeue_style($h);
            wp_deregister_style($h);
        }
    }
}, 999);

// ── Remove theme hooks that inject HTML into <body> start ────────────────────
remove_all_actions('wp_body_open');

// ── Hide the WordPress admin bar on these standalone pages ───────────────────
// Jis yra fixed top:0 ir uždengtų plėtinio fiksuotą antraštę / šoninę juostą.
add_filter('show_admin_bar', '__return_false');   // neberodom juostos (wp_footer)
remove_action('wp_head', '_admin_bar_bump_cb');   // pašalinam html{margin-top:32px/46px} poslinkį
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html(get_option('ss_school_name', SS_SCHOOL_NAME)); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,wght@0,400;0,500;0,700;1,400&family=Google+Sans+Display:wght@400;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;}
  html,body{margin:0;padding:0;height:100%;}
  body{background:var(--bg);font-family:'Google Sans',sans-serif;}

  /* ── Hide theme-injected navbars / headers / footers ─────────────────────── *
   * wp_footer() runs fully (so plugins like Google One Tap still work),       *
   * but common theme shell elements are hidden with CSS.                       */
  body > header:not([class*="ss-"]),
  body > nav:not([class*="ss-"]),
  body > footer:not([class*="ss-"]),
  .site-header, .site-footer,
  #masthead, #colophon,
  #site-header, #site-footer,
  .navbar:not([class*="ss-"]),
  .nav-primary, .navigation-top,
  #main-navigation, .main-navigation,
  #primary-menu, .header-main,
  .wp-site-blocks > header,
  .wp-site-blocks > footer,
  /* Astra theme */
  .ast-above-header-wrap, .ast-header-wrap,
  .ast-primary-header-bar, #ast-fixed-footer,
  /* GeneratePress */
  .generate-back-to-top,
  /* OceanWP */
  #ocean-header, #ocean-footer,
  /* Divi */
  #main-header, #footer-bottom,
  /* Hello Elementor */
  .elementor-location-header,
  .elementor-location-footer { display: none !important; }
</style>
<?php wp_head(); ?>
<?php /* Saugiklis: jei WP vis tiek įterpia admin-bar „bump" (html{margin-top:32px})
         arba pačią juostą — neutralizuojam PO wp_head(), kad nugalėtų kaskadoje. */ ?>
<style id="ss-no-adminbar">
  html{margin-top:0 !important;}
  @media screen and (max-width:782px){ html{margin-top:0 !important;} }
  #wpadminbar{display:none !important;}
</style>
</head>
<body>
<?php include $ss_view_file; ?>
<?php wp_footer(); ?>
</body>
</html>
