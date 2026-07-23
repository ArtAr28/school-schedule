<?php
/**
 * Plugin Name: Gimnazijos tvarkaraščių valdymo sistema
 * Plugin URI: nera
 * Description: Gimnazijos tvarkaraščių valdymo sistema
 * Version: 6.3.3.6
 * Author: ArtAr28 2026
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Text Domain: school-schedule
 */

if (!defined('ABSPATH')) exit;

define('SS_VERSION',     '6.3.3.6');
define('SS_PLUGIN_DIR',  plugin_dir_path(__FILE__));
define('SS_PLUGIN_URL',  plugin_dir_url(__FILE__));
define('SS_SCHOOL_NAME', 'Gimnazija');

require_once SS_PLUGIN_DIR . 'includes/class-time.php';
require_once SS_PLUGIN_DIR . 'includes/class-mail.php';
require_once SS_PLUGIN_DIR . 'includes/class-database.php';
require_once SS_PLUGIN_DIR . 'includes/class-rate-limit.php';
require_once SS_PLUGIN_DIR . 'includes/class-audit-log.php';
require_once SS_PLUGIN_DIR . 'includes/class-security.php';
require_once SS_PLUGIN_DIR . 'includes/class-turnstile.php';
require_once SS_PLUGIN_DIR . 'includes/class-change-notify.php';
require_once SS_PLUGIN_DIR . 'includes/class-roles.php';
require_once SS_PLUGIN_DIR . 'includes/class-accounts.php';
require_once SS_PLUGIN_DIR . 'includes/class-import.php';
require_once SS_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once SS_PLUGIN_DIR . 'includes/class-data-export.php';
require_once SS_PLUGIN_DIR . 'includes/class-wp-login.php';
require_once SS_PLUGIN_DIR . 'includes/class-2fa.php';
require_once SS_PLUGIN_DIR . 'includes/class-maintenance.php';
require_once SS_PLUGIN_DIR . 'includes/class-system-io.php';
require_once SS_PLUGIN_DIR . 'includes/class-surveys.php';
require_once SS_PLUGIN_DIR . 'includes/class-eurovizija.php';
require_once SS_PLUGIN_DIR . 'includes/class-imp-xlsx.php';
require_once SS_PLUGIN_DIR . 'includes/class-imp.php';
require_once SS_PLUGIN_DIR . 'includes/class-google-auth.php';
require_once SS_PLUGIN_DIR . 'includes/class-achievements.php';
require_once SS_PLUGIN_DIR . 'includes/class-social.php';
require_once SS_PLUGIN_DIR . 'admin/class-admin.php';
require_once SS_PLUGIN_DIR . 'public/class-public.php';

register_activation_hook(__FILE__, array('SS_Database', 'install'));
register_activation_hook(__FILE__, array('SS_IMP', 'install'));
register_activation_hook(__FILE__, array('SS_Eurovizija', 'install'));
register_activation_hook(__FILE__, array('SS_Security', 'activate'));
register_activation_hook(__FILE__, array('SS_Achievements', 'install'));
register_activation_hook(__FILE__, array('SS_Social', 'install'));
register_activation_hook(__FILE__, array('SS_Roles',    'add_roles'));
register_deactivation_hook(__FILE__, array('SS_Roles', 'remove_roles'));
register_deactivation_hook(__FILE__, array('SS_Security', 'unschedule_cron'));
register_deactivation_hook(__FILE__, array('SS_ChangeNotify', 'unschedule'));
register_deactivation_hook(__FILE__, array('SS_Social', 'unschedule'));

class School_Schedule_Plugin {
    public function __construct() {
        add_action('init',       array($this, 'init'));
        add_action('admin_init', array($this, 'maybe_upgrade'));
    }
    public function maybe_upgrade() {
        if (get_option('ss_db_version') !== SS_VERSION) {
            SS_Database::install();
            SS_IMP::install();
            SS_Eurovizija::install();
            SS_AuditLog::install();
            SS_Security::install_defaults();
            SS_Turnstile::install_defaults();
            SS_Achievements::install();
            SS_Social::install();
            SS_Security::schedule_cron();
            SS_Roles::add_roles();
            SS_Database::migrate_publish_existing_changes(); // pažymi esamus pakeitimus paskelbtais
            SS_Database::trim_option_autoload();             // E1: cold/accumulating options out of autoload cache
            update_option('ss_db_version', SS_VERSION);
        }
    }
    public function init() {
        SS_Roles::init();
        SS_Security::init();
        SS_Turnstile::init();
        SS_Maintenance::init();
        SS_Accounts::init();
        SS_ChangeNotify::init();
        SS_Shortcodes::init();
        SS_Data_Export::init();
        SS_Eurovizija::init();
        SS_Admin::init();
        SS_Public::init();
        SS_WP_Login::init();
	SS_2FA::init();
        SS_Google_Auth::init();
        SS_Achievements::init();
        SS_Social::init();
        add_action('template_redirect', array($this, 'template_redirect'));
        // Custom avatar override
        add_filter('pre_get_avatar_data', array($this, 'custom_avatar'), 10, 2);
    }
    public function custom_avatar($args, $id_or_email) {
        $user_id = 0;
        if (is_numeric($id_or_email))            $user_id = (int)$id_or_email;
        elseif ($id_or_email instanceof WP_User) $user_id = $id_or_email->ID;
        elseif (is_string($id_or_email) && is_email($id_or_email)) {
            $u = get_user_by('email', $id_or_email);
            if ($u) $user_id = $u->ID;
        }
        if ($user_id) {
            $att_id = (int)get_user_meta($user_id, 'ss_avatar_id', true);
            if ($att_id) {
                $url = wp_get_attachment_image_url($att_id, 'thumbnail');
                if ($url) {
                    $att_ts = (int)get_user_meta($user_id, 'ss_avatar_ts', true);
                    if ($att_ts) $url .= '?v=' . $att_ts;
                    $args['url'] = $url; $args['found_avatar'] = true;
                }
            }
        }
        return $args;
    }
    public function template_redirect() {
        global $post;
        if (!is_page() || !$post) return;

        $login_id    = (int)get_option('ss_login_page_id');
        $dash_id     = (int)get_option('ss_dashboard_page_id');
        $changes_id  = (int)get_option('ss_changes_page_id');
        $settings_id = (int)get_option('ss_settings_page_id');
        $imp_id      = (int)get_option('ss_imp_page_id');
        $euro_id     = (int)get_option('ss_euro_page_id');
        $profile_id  = (int)get_option('ss_profile_page_id');
        $msgs_id     = (int)get_option('ss_messages_page_id');
        $plugin_pages = array_filter([$login_id, $dash_id, $changes_id, $settings_id, $imp_id, $euro_id, $profile_id, $msgs_id]);

        if (!in_array((int)$post->ID, $plugin_pages, true)) return;

        // Visi plėtinio puslapiai yra dinaminiai (asmeninis tvarkaraštis, nonce'ai),
        // todėl jų NIEKADA nekešuojame — nei naršyklėje / bfcache, nei kešavimo
        // įskiepiuose ar CDN. Kitaip mokiniai matytų pasenusį (kešuotą) tvarkaraštį,
        // o login puslapyje pasenęs nonce sukeltų 403.
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        nocache_headers();
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
        }

        // Redirect logged-in users away from login page
        if ((int)$post->ID === $login_id && is_user_logged_in()) {
            $ss_qr_tok = sanitize_text_field($_GET['ss_qr'] ?? '');
            if ($ss_qr_tok && get_option('ss_qr_login_enabled', 0) && !SS_Maintenance::qr_blocked()) {
                // Tarnaujame QR patvirtinimo puslapį ir SUSTOJAME
                // (kitaip $ss_view_file būtų perrašytas į login.php žemiau)
                do_action('wp_enqueue_scripts');
                $ss_view_file = SS_PLUGIN_DIR . 'public/views/qr-confirm.php';
                include SS_PLUGIN_DIR . 'public/views/page-wrapper.php';
                exit;
            }
            wp_redirect(get_permalink($dash_id));
            exit;
        }

        // Redirect guests away from dashboard/changes/settings
        if ((int)$post->ID !== $login_id && !is_user_logged_in()) {
            wp_redirect(get_permalink($login_id));
            exit;
        }

        // Maintenance mode: block all non-login pages for non-bypass users
        if ((int)$post->ID !== $login_id) {
            if (SS_Maintenance::is_active() && !SS_Maintenance::user_can_bypass(get_current_user_id())) {
                // let the view render — the banner partial will overlay the page
            }
        }

        // Set up view variables (same data as shortcodes use)
        $pid = (int)$post->ID;
        if ($pid === $login_id) {
            $ss_view_file = SS_PLUGIN_DIR . 'public/views/login.php';
        } elseif ($pid === $changes_id) {
            extract(SS_Shortcodes::setup_changes_vars());
            $ss_view_file = SS_PLUGIN_DIR . 'public/views/changes.php';
        } elseif ($pid === $settings_id) {
            $ss_view_file = SS_PLUGIN_DIR . 'public/views/settings.php';
        } elseif ($pid === $imp_id) {
            $ss_view_file = SS_PLUGIN_DIR . 'public/views/imp-page.php';
        } elseif ($pid === $euro_id) {
            $ss_view_file = SS_PLUGIN_DIR . 'public/views/eurovizija.php';
        } elseif ($pid === $profile_id) {
            $ss_view_file = SS_PLUGIN_DIR . 'public/views/profile.php';
        } elseif ($pid === $msgs_id) {
            $ss_view_file = SS_PLUGIN_DIR . 'public/views/messages.php';
        } elseif ($pid === $dash_id) {
            $user = wp_get_current_user();
            if (in_array('ss_teacher', (array)$user->roles, true)) {
                extract(SS_Shortcodes::setup_teacher_dashboard_vars($user));
                $ss_view_file = SS_PLUGIN_DIR . 'public/views/teacher_dashboard.php';
            } else {
                extract(SS_Shortcodes::setup_dashboard_vars());
                $ss_view_file = SS_PLUGIN_DIR . 'public/views/dashboard.php';
            }
        }

        // Force plugin styles/scripts to enqueue
        do_action('wp_enqueue_scripts');

        // Serve standalone page — no theme navbar/header/footer
        include SS_PLUGIN_DIR . 'public/views/page-wrapper.php';
        exit;
    }
}
new School_Schedule_Plugin();
