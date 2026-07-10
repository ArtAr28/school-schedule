<?php
/**
 * Uninstall — SS-11: Išvalyti VISUS duomenis (GDPR)
 *
 * Pašalina:
 *   • Visas 15 plėtinio lentelių (įskaitant ankstesnio kodo praleistas)
 *   • Visus WP options su 'ss_' prefiksu
 *   • Visas user meta su 'ss_' prefiksu
 *   • WP-Cron užduotis
 *   • Roles
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

/* ── 1. Visas lentelės ─────────────────────────────────────────────── */
$tables = [
    'ss_class_schedule',
    'ss_user_subgroup_prefs',
    'ss_personal_items',
    'ss_user_classes',
    'ss_classes',
    'ss_lessons',
    'ss_periods',
    'ss_teachers',
    'ss_period_changes',
    'ss_absent_teachers',
    'ss_registrations',
    'ss_surveys',
    'ss_survey_responses',
    'ss_class_email_links',
    'ss_security_log',      // audito žurnalas
    'ss_rooms',            // kabinetai
    'ss_imp_catalog',      // IMP katalogas
    'ss_imp_submissions',  // IMP pateikti planai
    'ss_euro_candidates',  // Eurovizija — dalyviai
    'ss_euro_votes',       // Eurovizija — balsai
    'ss_friends',          // draugai
    'ss_chats',            // pokalbiai
    'ss_chat_members',     // pokalbių nariai
    'ss_chat_messages',    // žinutės
    'ss_chat_poll_votes',  // balsavimų balsai
    'ss_chat_reactions',   // reakcijos
    'ss_achievements',     // pasiekimai
];

foreach ($tables as $t) {
    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}{$t}`");
}

/* ── 2. Visos options su 'ss_' prefiksu ────────────────────────────── */
$options = $wpdb->get_col(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'ss\_%'"
);
foreach ($options as $opt) {
    delete_option($opt);
}

/* ── 3. Visos user meta su 'ss_' prefiksu ──────────────────────────── */
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ss\_%'"
);

/* ── 4. WP-Cron ────────────────────────────────────────────────────── */
foreach (['ss_daily_maintenance', 'ss_ch_notify_cron'] as $hook) {
    $ts = wp_next_scheduled($hook);
    if ($ts) wp_unschedule_event($ts, $hook);
    wp_clear_scheduled_hook($hook);
}

/* ── 5. Roles ──────────────────────────────────────────────────────── */
remove_role('ss_student');
remove_role('ss_teacher');
remove_role('ss_employee');
