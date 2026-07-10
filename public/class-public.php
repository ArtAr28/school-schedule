<?php
if (!defined('ABSPATH')) exit;

class SS_Public {
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
    }
    public static function enqueue() {
        global $post;
        $login_id    = get_option('ss_login_page_id');
        $dash_id     = get_option('ss_dashboard_page_id');
        $changes_id  = get_option('ss_changes_page_id');
        $settings_id = get_option('ss_settings_page_id');
        $imp_id      = get_option('ss_imp_page_id');
        $euro_id     = get_option('ss_euro_page_id');
        $profile_id  = get_option('ss_profile_page_id');
        $msgs_id     = get_option('ss_messages_page_id');
        // Allow enqueue even when called manually from template_redirect (no $post context)
        $is_plugin_page = $post && is_page(array_filter([$login_id, $dash_id, $changes_id, $settings_id, $imp_id, $euro_id, $profile_id, $msgs_id]));
        $has_sc = $post && (
            has_shortcode($post->post_content, 'ss_login') ||
            has_shortcode($post->post_content, 'ss_dashboard') ||
            has_shortcode($post->post_content, 'ss_changes') ||
            has_shortcode($post->post_content, 'ss_imp_form')
        );
        if (!$is_plugin_page && !$has_sc) return;

        // Turinys dinaminis (asmeninis tvarkaraštis) — pasakom kešavimo įskiepiams
        // nekešuoti šio puslapio (veikia ir kai naudojamas trumpinys temos puslapyje).
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);

        wp_enqueue_style('ss-public', SS_PLUGIN_URL . 'public/css/style.css', [], SS_VERSION);

        // Bundle jsPDF + AutoTable locally (PDF export). Loaded same-origin so it
        // works even when external CDNs are blocked on the school network.
        $is_dashboard = $post && ( (int)$post->ID === (int)$dash_id
                       || has_shortcode($post->post_content, 'ss_dashboard') );
        // jsPDF reikalingas tvarkaraščio PDF (dashbordas) IR „Mano duomenys" PDF (nustatymai).
        if ($is_dashboard || ($post && (int)$post->ID === (int)$settings_id)) {
            wp_enqueue_script('ss-jspdf', SS_PLUGIN_URL . 'public/js/vendor/jspdf.umd.min.js', [], '2.5.1', true);
            wp_enqueue_script('ss-jspdf-autotable', SS_PLUGIN_URL . 'public/js/vendor/jspdf.plugin.autotable.min.js', ['ss-jspdf'], '3.5.25', true);
        }

        // QR generatorius — reikalingas TIK prisijungimo puslapyje (QR prisijungimas,
        // SS.qrLogin) ir nustatymų puslapyje (2FA QR). Kitur (dashboard/changes/imp/
        // eurovizija) nekraunam — balsavimo puslapį vienu metu krauna šimtai mokinių.
        $is_settings = $post && (int)$post->ID === (int)$settings_id;
        $is_login    = $post && ( (int)$post->ID === (int)$login_id
                       || has_shortcode($post->post_content, 'ss_login') );
        if ($is_settings || $is_login) {
            wp_enqueue_script('ss-qrcode', SS_PLUGIN_URL . 'public/js/qrcode.min.js', [], '1.4.4', true);
        }

        // Apsauga nuo botų — Turnstile API prisijungimo puslapyje (valdiklis login formoje).
        if ($is_login && class_exists('SS_Turnstile') && SS_Turnstile::is_enabled()
            && SS_Turnstile::get('ss_ts_login_widget')) {
            wp_enqueue_script('ss-turnstile-api',
                'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
        }

        // Cropper.js (avatar cropping) — self-hosted, loaded same-origin so it
        // works even when external CDNs are blocked on the school network.
        // Needed on the settings page (avatar crop) and on the dashboard
        // (first-run onboarding wizard reuses the same crop flow).
        $needs_cropper = $is_dashboard || $is_settings;
        $pub_deps      = ['jquery'];
        if ($needs_cropper) {
            wp_enqueue_style('ss-cropper', SS_PLUGIN_URL . 'public/css/vendor/cropper.min.css', [], '1.6.2');
            wp_enqueue_script('ss-cropper', SS_PLUGIN_URL . 'public/js/vendor/cropper.min.js', [], '1.6.2', true);
            $pub_deps[] = 'ss-cropper';
        }

        wp_enqueue_script('ss-public', SS_PLUGIN_URL . 'public/js/app.js', $pub_deps, SS_VERSION, true);
        $ss_real_dow = SS_Time::dow(); // 1=Pr … 7=Sk (WP timezone)
        $ss_hour     = SS_Time::hour(); // 0-23 (WP timezone)
        $ss_active   = SS_Time::active_day();
        wp_localize_script('ss-public', 'ss_pub', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'plugin_url'    => SS_PLUGIN_URL,
            'login_nonce'   => wp_create_nonce('ss_login_nonce'),
            'front_nonce'   => wp_create_nonce('ss_front_nonce'),
            'logout_nonce'  => wp_create_nonce('ss_logout_nonce'),
            'days'          => ['Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis'],
            'today'         => ($ss_real_dow >= 1 && $ss_real_dow <= 5) ? $ss_real_dow : 0,
            'active_day'    => $ss_active,
            'google_enabled'=> SS_Google_Auth::is_enabled() ? 1 : 0,
            'settings_url'  => get_permalink(get_option('ss_settings_page_id')) ?: home_url('/'),
            'ts_enabled'    => (class_exists('SS_Turnstile') && SS_Turnstile::is_enabled()
                                && SS_Turnstile::get('ss_ts_login_widget')) ? 1 : 0,
            'ts_sitekey'    => class_exists('SS_Turnstile') ? SS_Turnstile::site_key() : '',
            'ts_mode'       => class_exists('SS_Turnstile') ? SS_Turnstile::login_mode() : 'managed',
        ]);

        // Settings-page interactions (QR toggle + 2FA setup) — moved out of the
        // inline <script> blocks in settings.php for caching + CSP friendliness.
        if ($is_settings) {
            $uid = get_current_user_id();
            wp_enqueue_script('ss-settings', SS_PLUGIN_URL . 'public/js/settings.js', ['jquery', 'ss-public', 'ss-qrcode'], SS_VERSION, true);
            wp_localize_script('ss-settings', 'ss_settings', [
                'qr_enabled'  => get_option('ss_qr_login_enabled', 0) ? 1 : 0,
                'qr_nonce'    => wp_create_nonce('ss_qr_toggle_' . $uid),
                'twofa_nonce' => wp_create_nonce('ss_2fa_setup_' . $uid),
            ]);
        }

        // Socialinis modulis (profilis / draugai / žinutės) — tik profilio ir
        // žinučių puslapiuose.
        $is_profile  = $post && (int)$post->ID === (int)$profile_id;
        $is_messages = $post && (int)$post->ID === (int)$msgs_id;
        if ($is_profile || $is_messages) {
            wp_enqueue_style('dashicons');
            wp_enqueue_script('ss-social', SS_PLUGIN_URL . 'public/js/social.js', ['jquery', 'ss-public'], SS_VERSION, true);
            $ss_stickers = [];
            if (class_exists('SS_Social')) {
                foreach (SS_Social::sticker_set() as $sk) $ss_stickers[] = ['key' => $sk, 'url' => SS_Social::sticker_url($sk)];
            }
            wp_localize_script('ss-social', 'ss_social', [
                'ajax_url'     => admin_url('admin-ajax.php'),
                'nonce'        => wp_create_nonce('ss_front_nonce'),
                'me'           => get_current_user_id(),
                'profile_url'  => class_exists('SS_Social') ? SS_Social::profile_url() : '',
                'messages_url' => class_exists('SS_Social') ? SS_Social::messages_url() : '',
                'stickers'     => $ss_stickers,
                'reactions'    => class_exists('SS_Social') ? SS_Social::reaction_set() : [],
                'chat_bgs'     => class_exists('SS_Social') ? SS_Social::chat_backgrounds() : [],
            ]);
        }
    }
}
