<?php
if (!defined('ABSPATH')) exit;

/**
 * SS_Roles — rolių registravimas, wpadmin ribojimas ir pagalbiniai metodai.
 *
 * Rolės:
 *   ss_student   — Mokinys          (be wpadmin)
 *   ss_teacher   — Mokytojas        (be wpadmin)
 *   ss_employee  — Darbuotojas      (be wpadmin, mato visų tvarkaraščius)
 *   administrator — Administratorius (pilna prieiga)
 *
 * Mokinys + IMP — tai ss_student paskyra su papildoma teise ss_imp_access,
 *                 suteikiama per Paskyros → Rolė ir teisės.
 */
class SS_Roles {

    public static function init(): void {
        // Blokuojame wp-admin ne-administratoriams
        add_action('admin_init', [__CLASS__, 'block_non_admin_wpadmin'], 1);
    }

    /* ── Rolių kūrimas (aktyvacijos metu) ───────────────────────── */

    public static function add_roles(): void {
        // Mokinys
        if (!get_role('ss_student')) {
            add_role('ss_student', 'Mokinys', [
                'read' => true,
            ]);
        }

        // Mokytojas
        if (!get_role('ss_teacher')) {
            add_role('ss_teacher', 'Mokytojas', [
                'read'                  => true,
                'ss_teacher_access'     => true,
            ]);
        }

        // Darbuotojas
        if (!get_role('ss_employee')) {
            add_role('ss_employee', 'Darbuotojas', [
                'read'                  => true,
                'ss_employee_access'    => true,
                'ss_view_all_schedules' => true,
            ]);
        }

        // Administratorius — pridedame plugin-specifines teises
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('ss_manage_all');
            $admin->add_cap('ss_view_all_schedules');
            $admin->add_cap('ss_manage_eurovizija');
        }
    }

    public static function remove_roles(): void {
        remove_role('ss_student');
        remove_role('ss_teacher');
        remove_role('ss_employee');
    }

    /* ── WP-Admin prieigos ribojimas ────────────────────────────── */

    /**
     * Ne-administratoriai nukreipiami į priekinį puslapį.
     * Veikia ankstyvu prioritetu (1), kad spėtų prieš visus kitus admin hooks.
     */
    public static function block_non_admin_wpadmin(): void {
        // Leidžiame AJAX ir cron
        if (wp_doing_ajax()) return;
        if (defined('DOING_CRON') && DOING_CRON) return;
        if (!is_user_logged_in()) return;
        // Administratoriai - praleisti
        if (current_user_can('manage_options')) return;
        // admin-post.php - leidžiame (plugin gali naudoti)
        if (!empty($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'admin-post') !== false) return;

        // Praleisti vartotojus, kuriems administratorius „Rolės ir teisės" skiltyje
        // suteikė tikrą WordPress teisę (pvz. rašyti įrašus, įkelti failus). Be šito
        // suteikta teisė būtų beprasmė — vartotojas vis tiek nepatektų į wp-admin.
        if (self::user_can_use_wpadmin()) return;

        // Nukreipti į priekinį puslapį
        $dash_id  = (int) get_option('ss_dashboard_page_id');
        $dash_url = $dash_id ? get_permalink($dash_id) : home_url('/');
        wp_safe_redirect($dash_url ?: home_url('/'));
        exit;
    }

    /**
     * Ar dabartinis vartotojas turi bent vieną realią WordPress teisę, dėl kurios
     * verta įleisti į wp-admin? „read" praleidžiama (ją turi visi), plugin'o
     * teisės (ss_*) wp-admin neatveria.
     */
    public static function user_can_use_wpadmin(): bool {
        $wp_caps = [];
        if (class_exists('SS_Accounts')) {
            $groups  = SS_Accounts::managed_caps();
            $wp_caps = array_keys($groups['WordPress teisės'] ?? []);
        }
        if (!$wp_caps) {
            $wp_caps = ['edit_posts','publish_posts','edit_published_posts','upload_files',
                        'edit_pages','manage_categories','edit_users','create_users','delete_users','manage_options'];
        }
        foreach ($wp_caps as $cap) {
            if ($cap === 'read') continue;       // visi turi „read"
            if (current_user_can($cap)) return true;
        }
        return false;
    }

    /* ── Pagalbiniai metodai ────────────────────────────────────── */

    /** @return WP_User[] */
    public static function get_students(): array {
        return get_users([
            'role__in' => ['ss_student'],
            'number'   => -1,
            'orderby'  => 'display_name',
        ]);
    }

    /* ── Mokinio klasė (I–IV) — saugoma user_meta laukuose ──────────
     * ss_class_level  : 'I' | 'II' | 'III' | 'IV'
     * ss_class_letter : pvz. 'a'
     * Naudojama mokslo metų pabaigos perkėlime (klasės kėlimas +1). */

    public static function student_levels(): array {
        return ['I', 'II', 'III', 'IV'];
    }

    /** @return array{level:string,letter:string,label:string} */
    public static function get_student_level(int $uid): array {
        $level  = (string) get_user_meta($uid, 'ss_class_level',  true);
        $letter = (string) get_user_meta($uid, 'ss_class_letter', true);
        return [
            'level'  => $level,
            'letter' => $letter,
            'label'  => $level === '' ? '' : $level . $letter,
        ];
    }

    public static function set_student_level(int $uid, string $level, string $letter): void {
        $level  = in_array($level, self::student_levels(), true) ? $level : '';
        $letter = mb_strtolower(trim($letter));
        update_user_meta($uid, 'ss_class_level',  $level);
        update_user_meta($uid, 'ss_class_letter', $letter);
    }

    /** Kita klasė po kėlimo. IV → null (laida baigta). */
    public static function next_level(string $level): ?string {
        $i = array_search($level, self::student_levels(), true);
        if ($i === false) return null;
        return self::student_levels()[$i + 1] ?? null;
    }

    /**
     * Užpildo trūkstamus ss_class_level/letter mokiniams iš registracijų lentelės
     * (pagal el. paštą). Grąžina kiek paskyrų užpildyta.
     */
    public static function backfill_student_levels(): int {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT email, class_level, class_letter
             FROM {$wpdb->prefix}ss_registrations
             WHERE class_level <> ''"
        );
        $map = [];
        foreach ($rows as $r) $map[strtolower($r->email)] = $r;

        $n = 0;
        foreach (self::get_students() as $u) {
            if (get_user_meta($u->ID, 'ss_class_level', true) !== '') continue;
            $r = $map[strtolower($u->user_email)] ?? null;
            if ($r && $r->class_level !== '') {
                update_user_meta($u->ID, 'ss_class_level',  $r->class_level);
                update_user_meta($u->ID, 'ss_class_letter', $r->class_letter);
                $n++;
            }
        }
        return $n;
    }

    /** @return WP_User[] */
    public static function get_teachers_wp(): array {
        return get_users([
            'role__in' => ['ss_teacher', 'administrator'],
            'number'   => -1,
            'orderby'  => 'display_name',
        ]);
    }

    /** @return WP_User[] */
    public static function get_employees(): array {
        return get_users([
            'role'     => 'ss_employee',
            'number'   => -1,
            'orderby'  => 'display_name',
        ]);
    }

    /** Gauti visas valdomas roles (slug => label) */
    public static function all_roles(): array {
        return [
            'ss_student'    => 'Mokinys',
            'ss_teacher'    => 'Mokytojas',
            'ss_employee'   => 'Darbuotojas',
            'administrator' => 'Administratorius',
        ];
    }
}
