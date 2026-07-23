<?php
if (!defined('ABSPATH')) exit;
 
class SS_Admin {
 
    public static function init() {
        add_action('admin_menu',            array(__CLASS__, 'register_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'drop_jquery_migrate'), 1); // before all enqueues
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        // AJAX
        $actions = array('save_teacher','delete_teacher','save_lesson','delete_lesson',
                         'save_room','delete_room','bulk_delete_rooms',
                         'save_class','delete_class','admin_get_class_schedule',
                         'admin_save_class_schedule','get_user_classes','save_user_classes',
                         'set_teacher_role','create_user','parse_import','commit_import',
                         'link_teacher_account','unlink_teacher_account','create_teacher_account',
                         'quick_import',
                         'get_changes','save_change','delete_change',
                         'save_absent','delete_absent',
                         'get_cell_subgroups','set_changes_published',
                         'approve_registration','reject_registration','delete_registration',
                         'bulk_registrations',
                         'regenerate_class_code',
                         'save_class_link','delete_class_link','import_class_links',
                         'export_class_codes_csv','export_class_links_csv',
                         'save_changes_comment',
                         'system_import_preview','system_import_commit',
                         'bulk_delete_teachers','bulk_delete_lessons',
                         'bulk_delete_classes','bulk_toggle_imp','bulk_delete_students',
                         'save_student_class','rollover_preview','rollover_commit');
        foreach ($actions as $a) {
            add_action("wp_ajax_ss_{$a}", array(__CLASS__, "ajax_{$a}"));
        }
        // CSV downloads (need full page response, not AJAX)
        add_action('admin_post_ss_export_class_codes_csv', array(__CLASS__, 'ajax_export_class_codes_csv'));
        add_action('admin_post_ss_export_class_links_csv', array(__CLASS__, 'ajax_export_class_links_csv'));
        add_action('admin_post_ss_system_export',          array(__CLASS__, 'handle_system_export'));
        add_action('admin_post_ss_log_export',             array(__CLASS__, 'handle_log_export'));
    }
 
    public static function register_menu() {
        add_menu_page('Ąžuolyno tvarkaraštis','Tvarkaraštis','manage_options',
            'school-schedule',array(__CLASS__,'page_dashboard'),'dashicons-calendar-alt',25);
        $sub = array(
            array('school-schedule', 'Suvestinė',       'page_dashboard'),
            array('ss-import',       'Importas',        'page_import'),
            array('ss-teachers',     'Mokytojai',       'page_teachers'),
            array('ss-rooms',        'Kabinetai',       'page_rooms'),
            array('ss-lessons',      'Pamokos',         'page_lessons'),
            array('ss-classes',      'Tvarkaraščiai',   'page_classes'),
            array('ss-registrations','Registracijos',   'page_registrations'),
            array('ss-schedule-codes','Tvarkaraščių kodai','page_schedule_codes'),
            array('ss-changes',      'Pakeitimai',      'page_changes'),
            array('ss-year-end',     'Mokslo metų pabaiga','page_year_end'),
            array('ss-settings',     'Nustatymai',      'page_settings'),
            array('ss-maintenance',  'Aptarnavimas',    'page_maintenance'),
            array('ss-security',     'Saugumas',        'page_security'),
        );
        foreach ($sub as $s) {
            add_submenu_page('school-schedule',$s[1],$s[1],'manage_options',$s[0],array(__CLASS__,$s[2]));
        }

        // ── Atskiras "Paskyros" meniu ────────────────────────────────────
        // „Mokiniai“ ir „Mokytojų paskyros“ perkelti čia (anksčiau buvo po Tvarkaraštis).
        add_menu_page(
            'Paskyros', 'Paskyros', 'manage_options',
            'ss-accounts', array(__CLASS__, 'page_accounts'),
            'dashicons-groups', 26
        );
        add_submenu_page('ss-accounts', 'Visos paskyros',    'Visos paskyros',    'manage_options', 'ss-accounts',         array(__CLASS__, 'page_accounts'));
        add_submenu_page('ss-accounts', 'Mokiniai',          'Mokiniai',          'manage_options', 'ss-students',         array(__CLASS__, 'page_students'));
        add_submenu_page('ss-accounts', 'Mokytojų paskyros', 'Mokytojų paskyros', 'manage_options', 'ss-teacher-accounts', array(__CLASS__, 'page_teacher_accounts'));
        add_submenu_page('ss-accounts', 'Rolės ir teisės',   'Rolės ir teisės',   'manage_options', 'ss-roles',            array(__CLASS__, 'page_roles'));
    }
 
    public static function enqueue($hook) {
        $pages = array('school-schedule','ss-import','ss-teachers','ss-rooms','ss-lessons',
                       'ss-classes','ss-students','ss-teacher-accounts','ss-registrations','ss-schedule-codes','ss-changes','ss-year-end','ss-settings','ss-maintenance','ss-surveys','ss-eurovizija','ss-imp','ss-security',
                       'ss-accounts','ss-roles');
        $match = false;
        foreach ($pages as $pg) { if (strpos($hook,$pg)!==false){$match=true;break;} }
        if (!$match) return;

        wp_enqueue_media();
        wp_enqueue_style('dashicons');
        wp_enqueue_style('ss-admin',  SS_PLUGIN_URL.'admin/css/admin.css',  array('dashicons'), SS_VERSION);
        wp_enqueue_script('ss-admin', SS_PLUGIN_URL.'admin/js/admin.js', array('jquery-core'), SS_VERSION, false); // head – inline scripts import.php relies on ss_admin being defined
        wp_localize_script('ss-admin','ss_admin',array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ss_admin_nonce'),
            'days'     => array('Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis'),
        ));
    }
 
    /**
     * Runs at priority 1 – before wp_enqueue_media() or any other enqueue –
     * and rewires the built-in 'jquery' handle so it depends only on
     * 'jquery-core', not 'jquery-migrate'.  This prevents the "JQMIGRATE:
     * Migrate is installed" console message on plugin admin pages.
     */
    public static function drop_jquery_migrate($hook) {
        $pages = array('school-schedule','ss-import','ss-teachers','ss-rooms','ss-lessons',
                       'ss-classes','ss-students','ss-teacher-accounts','ss-registrations',
                       'ss-schedule-codes','ss-changes','ss-year-end','ss-settings','ss-maintenance','ss-surveys','ss-eurovizija','ss-imp','ss-security',
                       'ss-accounts','ss-roles');
        $match = false;
        foreach ($pages as $pg) {
            if (strpos($hook, $pg) !== false) { $match = true; break; }
        }
        if (!$match) return;
        global $wp_scripts;
        if (isset($wp_scripts->registered['jquery'])) {
            $wp_scripts->registered['jquery']->deps = array('jquery-core');
        }
    }
 
    /* ── Pages ── */
    public static function page_dashboard() {
        $stats=$stats=SS_Database::get_stats(); $classes=SS_Database::get_classes();
        include SS_PLUGIN_DIR.'admin/views/dashboard.php';
    }
    public static function page_import()   { include SS_PLUGIN_DIR.'admin/views/import.php'; }
    public static function page_teachers() { $teachers=SS_Database::get_teachers(); include SS_PLUGIN_DIR.'admin/views/teachers.php'; }
    public static function page_rooms()    { $rooms=SS_Database::get_rooms(); $room_usage=SS_Database::get_room_usage_counts(); include SS_PLUGIN_DIR.'admin/views/rooms.php'; }
    public static function page_lessons()  { $lessons=SS_Database::get_lessons(); $teachers=SS_Database::get_teachers(); include SS_PLUGIN_DIR.'admin/views/lessons.php'; }
    public static function page_classes()  { $classes=SS_Database::get_classes(); $lessons=SS_Database::get_lessons(); $rooms=SS_Database::get_rooms(); $periods=SS_Database::get_periods(); include SS_PLUGIN_DIR.'admin/views/classes.php'; }
    public static function page_students() { SS_Roles::backfill_student_levels(); $students=SS_Roles::get_students(); $classes=SS_Database::get_classes(); include SS_PLUGIN_DIR.'admin/views/students.php'; }
    public static function page_teacher_accounts() {
        include SS_PLUGIN_DIR . 'admin/views/teacher_accounts.php';
    }
    public static function page_accounts() {
        include SS_PLUGIN_DIR . 'admin/views/accounts.php';
    }
    public static function page_year_end() {
        include SS_PLUGIN_DIR . 'admin/views/year_end.php';
    }
    public static function page_roles() {
        include SS_PLUGIN_DIR . 'admin/views/roles.php';
    }
    public static function page_changes() {
        SS_Database::migrate_publish_existing_changes(); // vienkartinė — pažymi senus pakeitimus paskelbtais
        $teachers = SS_Database::get_teachers();
        $classes  = SS_Database::get_classes();
        $periods  = SS_Database::get_periods();
        $notify_status = class_exists('SS_ChangeNotify') ? SS_ChangeNotify::sending_status() : null;
        include SS_PLUGIN_DIR . 'admin/views/changes.php';
    }
 
    public static function page_schedule_codes() {
        $classes = SS_Database::get_classes();
        $codes   = SS_Database::get_class_codes_list();
        $links   = SS_Database::get_all_class_links();
        include SS_PLUGIN_DIR . 'admin/views/schedule_codes.php';
    }
    
    public static function page_registrations() {
        $registrations = SS_Database::get_all_registrations();
        $classes = SS_Database::get_classes();
        include SS_PLUGIN_DIR . 'admin/views/registrations.php';
    }
 
    public static function page_settings() {
        if (isset($_POST['ss_settings_nonce']) && wp_verify_nonce($_POST['ss_settings_nonce'],'ss_settings')) {
            update_option('ss_school_name',      sanitize_text_field(wp_unslash($_POST['school_name']??SS_SCHOOL_NAME)));
            update_option('ss_schedule_title',   sanitize_text_field(wp_unslash($_POST['schedule_title']??'')));
            update_option('ss_school_year', sanitize_text_field($_POST['school_year']??''), false);
            update_option('ss_login_page_id',     (int)($_POST['login_page']     ?? 0));
            update_option('ss_dashboard_page_id', (int)($_POST['dashboard_page'] ?? 0));
            update_option('ss_changes_page_id',   (int)($_POST['changes_page']   ?? 0));
            update_option('ss_settings_page_id',  (int)($_POST['settings_page']  ?? 0));
            update_option('ss_imp_page_id',       (int)($_POST['imp_page']       ?? 0));
            update_option('ss_euro_page_id',      (int)($_POST['euro_page']      ?? 0));
            update_option('ss_profile_page_id',   (int)($_POST['profile_page']   ?? 0));
            update_option('ss_messages_page_id',  (int)($_POST['messages_page']  ?? 0));
            update_option('ss_login_image_id',    (int)($_POST['login_image_id'] ?? 0), false);
            update_option('ss_logo_url', esc_url_raw(wp_unslash($_POST['logo_url'] ?? '')));
            update_option('ss_logo_id',  (int)($_POST['logo_id'] ?? 0));
            update_option('ss_admin_email', sanitize_email(wp_unslash($_POST['admin_email'] ?? '')), false);
            update_option('ss_atspek_game_url', esc_url_raw(wp_unslash($_POST['atspek_game_url'] ?? '')), false);
            update_option('ss_atspek_game_description', sanitize_text_field(wp_unslash($_POST['atspek_game_description'] ?? '')), false);
            update_option('ss_atspek_image_id', (int)($_POST['atspek_image_id'] ?? 0), false);
            // ── QR Login ──
            update_option('ss_qr_login_enabled', isset($_POST['qr_login_enabled']) ? 1 : 0);
 
            // Google prisijungimas
            update_option('ss_google_login_enabled', isset($_POST['google_login_enabled']) ? 1 : 0);
            update_option('ss_google_client_id',     sanitize_text_field(wp_unslash($_POST['google_client_id']     ?? '')), false);
            // Secret: saugoti tik jei pakeistas (ne tušti laukai). Šifruojama, autoload išjungtas.
            $new_secret = sanitize_text_field(wp_unslash($_POST['google_client_secret'] ?? ''));
            if ($new_secret !== '' && class_exists('SS_Google_Auth')) {
                SS_Google_Auth::set_secret($new_secret);
            }
            if (!empty($_POST['periods']) && is_array($_POST['periods'])) SS_Database::save_periods($_POST['periods']);
            SS_AuditLog::write('settings_saved');
            echo '<div class="notice notice-success is-dismissible"><p>Nustatymai išsaugoti.</p></div>';
        }
        $pages=get_pages(array('post_status'=>'publish','sort_column'=>'post_title','sort_order'=>'ASC'));
        $periods=SS_Database::get_periods();
        include SS_PLUGIN_DIR.'admin/views/settings.php';
    }

    /* ── Aptarnavimo režimo puslapis (perkelta iš „Nustatymai") ─────── */
    public static function page_maintenance() {
        if (!current_user_can('manage_options')) wp_die('Nėra teisės.');

        if (isset($_POST['ss_maintenance_nonce']) && wp_verify_nonce($_POST['ss_maintenance_nonce'], 'ss_maintenance')) {
            // ── Sistemos uždarymas (off / indefinite / timed) ──
            $maint_type = sanitize_text_field($_POST['maint_type'] ?? 'off');
            if ($maint_type === 'off') {
                update_option('ss_maint_enabled', 0);
                update_option('ss_maint_type', 'indefinite');
            } elseif ($maint_type === 'indefinite') {
                update_option('ss_maint_enabled', 1);
                update_option('ss_maint_type', 'indefinite');
            } elseif ($maint_type === 'timed') {
                update_option('ss_maint_enabled', 1);
                update_option('ss_maint_type', 'timed');
                // Tolerantiškas datetime-local parsavimas: 'Y-m-d\TH:i' su/be sekundžių.
                // Konvertuojam iš WP laiko juostos į UTC saugojimui.
                $site_tz = SS_Time::tz();
                $utc_tz  = new DateTimeZone('UTC');
                $parse   = static function (string $raw) use ($site_tz, $utc_tz): string {
                    $raw = trim($raw);
                    if ($raw === '') return '';
                    $raw = str_replace('T', ' ', $raw);
                    try { $dt = new DateTime($raw, $site_tz); }
                    catch (Exception $e) { return ''; }
                    return $dt->setTimezone($utc_tz)->format('Y-m-d H:i:s');
                };
                update_option('ss_maint_from', $parse(sanitize_text_field($_POST['maint_from'] ?? '')));
                update_option('ss_maint_to',   $parse(sanitize_text_field($_POST['maint_to']   ?? '')));
            }
            update_option('ss_maint_warning',      isset($_POST['maint_warning']) ? 1 : 0);
            update_option('ss_maint_warning_text', sanitize_text_field(wp_unslash($_POST['maint_warning_text'] ?? '')));
            update_option('ss_maint_reason',       sanitize_text_field(wp_unslash($_POST['maint_reason'] ?? '')));
            $bypass_ids = array_filter(array_map('intval', (array)($_POST['maint_bypass_users'] ?? [])));
            update_option('ss_maint_bypass_users', implode(',', $bypass_ids));

            // ── Prieigos blokavimai ──
            update_option('ss_maint_lock_site',    isset($_POST['maint_lock_site'])    ? 1 : 0);
            update_option('ss_maint_block_qr',     isset($_POST['maint_block_qr'])     ? 1 : 0);
            update_option('ss_maint_block_google', isset($_POST['maint_block_google']) ? 1 : 0);

            SS_AuditLog::write('settings_saved', array('section' => 'maintenance'));
            echo '<div class="notice notice-success is-dismissible"><p>Aptarnavimo nustatymai išsaugoti.</p></div>';
        }

        include SS_PLUGIN_DIR . 'admin/views/maintenance.php';
    }
 
    /* ── AJAX helper ── */
    private static function verify() {
        if (!check_ajax_referer('ss_admin_nonce','nonce',false))
            wp_send_json_error(array('message'=>'Saugumo klaida. Atnaujinkite puslapį.'));
        if (!current_user_can('manage_options'))
            wp_send_json_error(array('message'=>'Neturite teisių.'));
    }
 
    /* ── Teacher CRUD ── */
    public static function ajax_save_teacher() {
        self::verify();
        if (empty(trim($_POST['name']??''))) wp_send_json_error(array('message'=>'Vardas privalomas.'));
        $id = SS_Database::save_teacher($_POST);
        if (!$id) wp_send_json_error(array('message'=>'DB klaida.'));
        wp_send_json_success(array('id'=>$id,'teacher'=>SS_Database::get_teacher($id)));
    }
    public static function ajax_delete_teacher() {
        self::verify(); SS_Database::delete_teacher((int)($_POST['id']??0)); wp_send_json_success();
    }

    /* ── Room (Kabinetas) CRUD ── */
    public static function ajax_save_room() {
        self::verify();
        if (empty(trim($_POST['name']??''))) wp_send_json_error(array('message'=>'Pavadinimas privalomas.'));
        $id = SS_Database::save_room($_POST);
        if (!$id) wp_send_json_error(array('message'=>'DB klaida.'));
        wp_send_json_success(array('id'=>$id,'room'=>SS_Database::get_room($id)));
    }
    public static function ajax_delete_room() {
        self::verify(); SS_Database::delete_room((int)($_POST['id']??0)); wp_send_json_success();
    }
    public static function ajax_bulk_delete_rooms() {
        self::verify();
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        foreach ($ids as $id) { SS_Database::delete_room($id); }
        wp_send_json_success(['deleted' => count($ids)]);
    }
 
    /* ── Lesson CRUD ── */
    public static function ajax_save_lesson() {
        self::verify();
        if (empty(trim($_POST['subject']??''))) wp_send_json_error(array('message'=>'Dalykas privalomas.'));
        $id = SS_Database::save_lesson($_POST);
        wp_send_json_success(array('id'=>$id));
    }
    public static function ajax_delete_lesson() {
        self::verify(); SS_Database::delete_lesson((int)($_POST['id']??0)); wp_send_json_success();
    }
 
    /* ── Class CRUD ── */
    public static function ajax_save_class() {
        self::verify();
        if (empty(trim($_POST['name']??''))) wp_send_json_error(array('message'=>'Pavadinimas privalomas.'));
        $id = SS_Database::save_class($_POST);
        wp_send_json_success(array('id'=>$id,'class'=>SS_Database::get_class($id)));
    }
    public static function ajax_delete_class() {
        self::verify(); SS_Database::delete_class((int)($_POST['id']??0)); wp_send_json_success();
    }
 
    /* ── Class schedule grid ── */
    public static function ajax_admin_get_class_schedule() {
        self::verify();
        $class_id = (int)($_POST['class_id']??0);
        $raw  = SS_Database::get_class_schedule_raw($class_id);
        $grid = array(); // [day][period_id][] = row
        foreach ($raw as $r) { $grid[$r->day_of_week][$r->period_id][] = $r; }
        
        // Get class code
        $class = SS_Database::get_class($class_id);
        $code = $class ? ($class->code ?? '') : '';
        
        wp_send_json_success(array('grid'=>$grid, 'code'=>$code));
    }
    public static function ajax_admin_save_class_schedule() {
        self::verify();
        $cid = (int)($_POST['class_id']??0);
        SS_Database::save_class_schedule_cells($cid, $_POST['cells']??array());
        SS_AuditLog::write('schedule_edited', array('class_id' => $cid));
        wp_send_json_success(array('message'=>'Tvarkaraštis išsaugotas!'));
    }
 
    /* ── User ↔ class ── */
    public static function ajax_get_user_classes() {
        self::verify();
        $rows = SS_Database::get_user_classes((int)($_POST['user_id']??0));
        wp_send_json_success(array('class_ids'=>array_map(fn($r)=>(int)$r->class_id,$rows)));
    }
    public static function ajax_save_user_classes() {
        self::verify();
        SS_Database::set_user_classes((int)($_POST['user_id']??0),
            array_map('intval',(array)($_POST['class_ids']??array())));
        wp_send_json_success();
    }
 
    /* ── Set teacher role ── */
    public static function ajax_set_teacher_role() {
        self::verify();
        $uid = intval($_POST['user_id'] ?? 0);
        if (!$uid) wp_send_json_error(array('message' => 'Neteisingas vartotojas.'));
        $user = new WP_User($uid);
        $user->set_role('ss_teacher');
        wp_send_json_success(array('message' => 'Rolė priskirta!'));
    }

    /* ── Teacher ↔ WP account linking ── */
    public static function ajax_link_teacher_account() {
        self::verify();
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $user_id    = (int)($_POST['user_id'] ?? 0);
        if (!$teacher_id || !$user_id) wp_send_json_error(array('message' => 'Pasirinkite mokytoją ir paskyrą.'));
        $teacher = SS_Database::get_teacher($teacher_id);
        if (!$teacher) wp_send_json_error(array('message' => 'Mokytojas nerastas.'));
        $wp_user = get_user_by('id', $user_id);
        if (!$wp_user) wp_send_json_error(array('message' => 'Paskyra nerasta.'));
        // Give the account teacher access (don't downgrade administrators)
        if (!in_array('administrator', (array)$wp_user->roles, true)) {
            $wp_user->set_role('ss_teacher');
        }
        SS_Database::link_teacher_to_user($teacher_id, $user_id);
        wp_send_json_success(array('message' => 'Paskyra susieta!'));
    }

    public static function ajax_unlink_teacher_account() {
        self::verify();
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        if (!$teacher_id) wp_send_json_error(array('message' => 'Trūksta mokytojo.'));
        SS_Database::unlink_teacher_user($teacher_id);
        wp_send_json_success(array('message' => 'Paskyra atsieta.'));
    }

    public static function ajax_create_teacher_account() {
        self::verify();
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        $login = sanitize_user($_POST['username'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $pass  = !empty($_POST['password']) ? (string)$_POST['password'] : wp_generate_password(12, false);
        if (!$teacher_id) wp_send_json_error(array('message' => 'Pasirinkite mokytoją.'));
        if (!$login || !$email) wp_send_json_error(array('message' => 'Vartotojo vardas ir el. paštas privalomi.'));
        $teacher = SS_Database::get_teacher($teacher_id);
        if (!$teacher) wp_send_json_error(array('message' => 'Mokytojas nerastas.'));
        if (username_exists($login)) wp_send_json_error(array('message' => 'Vartotojo vardas užimtas.'));
        if (email_exists($email))    wp_send_json_error(array('message' => 'El. paštas jau naudojamas.'));
        $uid = wp_create_user($login, $pass, $email);
        if (is_wp_error($uid)) wp_send_json_error(array('message' => $uid->get_error_message()));
        $u = new WP_User($uid);
        $u->set_role('ss_teacher');
        wp_update_user(array('ID' => $uid, 'display_name' => $teacher->name));
        SS_Database::link_teacher_to_user($teacher_id, $uid);
        wp_send_json_success(array('message' => 'Paskyra sukurta ir susieta!', 'user_id' => $uid));
    }
 
    /* ── Create user ── */
    public static function ajax_create_user() {
        self::verify();
        $un = sanitize_user($_POST['username']??'');
        $em = sanitize_email($_POST['email']??'');
        $pw = !empty($_POST['password'])?$_POST['password']:wp_generate_password(12,false);
        $rl = in_array($_POST['role']??'',array('ss_student','ss_teacher'))?$_POST['role']:'ss_student';
        $dn = sanitize_text_field($_POST['display_name']??$un);
        if (!$un||!$em) wp_send_json_error(array('message'=>'Vartotojas ir el. paštas privalomi.'));
        if (username_exists($un)) wp_send_json_error(array('message'=>'Vartotojo vardas užimtas.'));
        if (email_exists($em))    wp_send_json_error(array('message'=>'El. paštas jau naudojamas.'));
        $uid = wp_create_user($un,$pw,$em);
        if (is_wp_error($uid)) wp_send_json_error(array('message'=>$uid->get_error_message()));
        (new WP_User($uid))->set_role($rl);
        wp_update_user(array('ID'=>$uid,'display_name'=>$dn));
        wp_send_json_success(array('user_id'=>$uid,'message'=>'Vartotojas sukurtas!'));
    }
 
    /* ── IMPORT ── */
    public static function ajax_parse_import() {
        self::verify();
        $b64 = wp_unslash($_POST['html_base64'] ?? '');
        if (!$b64) wp_send_json_error(array('message' => 'Failas neįkeltas.'));
        $html = base64_decode($b64, true);
        if ($html === false || $html === '') wp_send_json_error(array('message' => 'Nepavyko nuskaityti failo.'));
        $result = SS_Import::parse_html($html);
        if (!empty($result['error'])) wp_send_json_error(array('message'=>$result['error']));
        wp_send_json_success($result);
    }
 
    public static function ajax_commit_import() {
        self::verify();
        $data_raw = wp_unslash($_POST['import_data'] ?? '');
        $data = json_decode($data_raw, true);
        if (!$data) wp_send_json_error(array('message'=>'Neteisinga importo informacija.'));
        $result = SS_Import::commit($data);
        wp_send_json_success(array(
            'message'    => 'Tvarkaraštis "' . $result['class_name'] . '" importuotas!',
            'class_id'   => $result['class_id'],
            'class_name' => $result['class_name'],
        ));
    }
 
    /**
     * Bulk import: parse + commit in a single AJAX request (no preview step).
     * Called once per HTML file by the bulk import JS.
     */
    public static function ajax_quick_import() {
        self::verify();
        $b64 = wp_unslash($_POST['html_base64'] ?? '');
        if (!$b64) wp_send_json_error(array('message' => 'Failas neįkeltas.'));
        $html = base64_decode($b64, true);
        if ($html === false || $html === '') { wp_send_json_error(array('message' => 'Nepavyko nuskaityti failo.')); }
        $parsed = SS_Import::parse_html($html);
        if (!empty($parsed['error'])) { wp_send_json_error(array('message' => $parsed['error'])); }
        $committed = SS_Import::commit($parsed);
        if (!empty($committed['error'])) { wp_send_json_error(array('message' => $committed['error'])); }
        wp_send_json_success(array(
            'class_name'    => $committed['class_name'],
            'class_id'      => $committed['class_id'],
            'new_teachers'  => count($parsed['new_teachers'] ?? array()),
            'new_lessons'   => count($parsed['new_lessons']  ?? array()),
            'teacher_stats' => $parsed['teacher_stats'] ?? array('existing' => array(), 'new' => array()),
        ));
    }
 
    /* ── CHANGES (admin) ── */
    public static function ajax_get_cell_subgroups() {
        self::verify();
        $class_id  = (int)($_POST['class_id']  ?? 0);
        $period_id = (int)($_POST['period_id'] ?? 0);
        $date      = sanitize_text_field($_POST['date'] ?? SS_Time::today_ymd());
        $dt_dow    = new DateTime($date, SS_Time::tz());
        $dow       = (int)$dt_dow->format('N'); // 1=Mon … 5=Fri
        $subgroups = SS_Database::get_cell_subgroups($class_id, $period_id, $dow);
        wp_send_json_success(array('subgroups' => $subgroups));
    }
 
    public static function ajax_get_changes() {
        self::verify();
        $date = sanitize_text_field($_POST['date'] ?? SS_Time::today_ymd());
        $changes = SS_Database::get_changes_for_date($date);
        $absent  = SS_Database::get_absent_for_date($date);
        $comment = get_option('ss_changes_comment_' . $date, '');
        wp_send_json_success(array(
            'changes'   => $changes,
            'absent'    => $absent,
            'comment'   => $comment,
            'published' => SS_Database::is_changes_published($date),
        ));
    }

    /** Paskelbti / atšaukti dienos pakeitimų paskelbimą. */
    public static function ajax_set_changes_published() {
        self::verify();
        $date = sanitize_text_field($_POST['date'] ?? '');
        $pub  = !empty($_POST['published']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(array('message' => 'Neteisinga data.'));
        }
        SS_Database::set_changes_published($date, $pub);
        SS_AuditLog::write('changes_published', array('date' => $date, 'published' => $pub ? 1 : 0));
        // Paskelbus — paleidžiam pakeitimų pranešimų siuntimą (immediate / laukiantiems).
        if ($pub && class_exists('SS_ChangeNotify')) {
            SS_ChangeNotify::on_publish($date);
        }
        wp_send_json_success(array(
            'published' => $pub,
            'message'   => $pub ? 'Paskelbta — pakeitimai matomi vartotojams.'
                                : 'Paskelbimas atšauktas — pakeitimai matomi tik administratoriui.',
        ));
    }
 
    public static function ajax_save_changes_comment() {
        self::verify();
        $date    = sanitize_text_field($_POST['date'] ?? SS_Time::today_ymd());
        $comment = wp_kses_post(wp_unslash($_POST['comment'] ?? ''));
        if ($comment === '') {
            delete_option('ss_changes_comment_' . $date);
        } else {
            update_option('ss_changes_comment_' . $date, $comment, false);
        }
        if (class_exists('SS_ChangeNotify')) SS_ChangeNotify::on_edit($date);
        wp_send_json_success();
    }
 
    public static function ajax_save_change() {
        self::verify();
        if (empty($_POST['period_id']) || empty($_POST['class_id'])) {
            wp_send_json_error(array('message' => 'Pasirinkite pamoką ir klasę.'));
        }
        $date = sanitize_text_field($_POST['change_date'] ?? SS_Time::today_ymd());
        $id = SS_Database::save_change(array(
            'id'             => (int)($_POST['id'] ?? 0),
            'change_date'    => $date,
            'period_id'      => (int)$_POST['period_id'],
            'class_id'       => (int)$_POST['class_id'],
            'subgroup_label' => sanitize_text_field($_POST['subgroup_label'] ?? ''),
            'comment'        => sanitize_textarea_field($_POST['comment'] ?? ''),
        ));
        if (class_exists('SS_ChangeNotify')) SS_ChangeNotify::on_edit($date);
        wp_send_json_success(array('id' => $id));
    }

    public static function ajax_delete_change() {
        self::verify();
        SS_Database::delete_change((int)($_POST['id'] ?? 0));
        if (class_exists('SS_ChangeNotify') && !empty($_POST['change_date'])) {
            SS_ChangeNotify::on_edit(sanitize_text_field($_POST['change_date']));
        }
        wp_send_json_success();
    }
 
    public static function ajax_save_absent() {
        self::verify();
        $tid = (int)($_POST['teacher_id'] ?? 0);
        if (!$tid) wp_send_json_error(array('message' => 'Pasirinkite mokytoją.'));
        $from_period = max(0, (int)($_POST['from_period'] ?? 0));
        $date = sanitize_text_field($_POST['absent_date'] ?? SS_Time::today_ymd());
        $id = SS_Database::save_absent($date, $tid, $from_period);
        if (class_exists('SS_ChangeNotify')) SS_ChangeNotify::on_edit($date);
        wp_send_json_success(array('id' => $id));
    }

    public static function ajax_delete_absent() {
        self::verify();
        SS_Database::delete_absent((int)($_POST['id'] ?? 0));
        if (class_exists('SS_ChangeNotify') && !empty($_POST['absent_date'])) {
            SS_ChangeNotify::on_edit(sanitize_text_field($_POST['absent_date']));
        }
        wp_send_json_success();
    }
 
    /* ── REGISTRATIONS (admin) ── */
    public static function ajax_get_registrations() {
        self::verify();
        wp_send_json_success(array(
            'registrations' => SS_Database::get_all_registrations(),
        ));
    }
 
    public static function ajax_approve_registration() {
        self::verify();
        $id         = (int)($_POST['id'] ?? 0);
        $class_id   = (int)($_POST['class_id'] ?? 0);
        $teacher_id = (int)($_POST['teacher_id'] ?? 0);
        if (!$id) wp_send_json_error(array('message' => 'Trūksta registracijos.'));
 
        $reg = SS_Database::get_registration($id);
        if (!$reg) wp_send_json_error(array('message' => 'Registracija nerasta.'));
        if ($reg->status === 'approved') wp_send_json_error(array('message' => 'Jau patvirtinta.'));
        if ((int)$reg->verified !== 1)   wp_send_json_error(array('message' => 'Vartotojas nepatvirtino el. pašto.'));
        if (empty($reg->password_hash))  wp_send_json_error(array('message' => 'Vartotojas dar nebaigė pildyti registracijos formos.'));
        if (email_exists($reg->email))   wp_send_json_error(array('message' => 'Šis el. paštas jau turi paskyrą.'));
 
        $reg_role = $reg->reg_role ?: 'student';
 
        // Resolve role + extras
        $wp_role = 'ss_student';
        $extra_status = array();
        $class = null;
 
        if ($reg_role === 'student') {
            if (!$class_id) wp_send_json_error(array('message' => 'Pasirinkite tvarkaraštį (klasę).'));
            $class = SS_Database::get_class($class_id);
            if (!$class) wp_send_json_error(array('message' => 'Klasė nerasta.'));
            $extra_status['assigned_class_id'] = $class_id;
        } elseif ($reg_role === 'teacher') {
            if (!$teacher_id) wp_send_json_error(array('message' => 'Parinkite mokytoją iš sąrašo.'));
            $wp_role = 'ss_teacher';
        } elseif ($reg_role === 'employee') {
            $wp_role = 'ss_employee';
        } elseif ($reg_role === 'admin') {
            $wp_role = 'administrator';
        }
 
        // Generate unique username from email local-part
        $local = strstr($reg->email, '@', true) ?: 'user';
        $base  = sanitize_user($local, true);
        $login = $base; $i = 1;
        while (username_exists($login)) {
            $login = $base . $i++;
            if ($i > 50) wp_send_json_error(array('message' => 'Nepavyko sugeneruoti vartotojo vardo.'));
        }
 
        $user_id = wp_insert_user(array(
            'user_login'    => $login,
            'user_email'    => $reg->email,
            'user_pass'     => '',
            'first_name'    => $reg->first_name,
            'last_name'     => $reg->last_name,
            'display_name'  => trim($reg->first_name . ' ' . $reg->last_name),
            'role'          => $wp_role,
        ));
        if (is_wp_error($user_id)) wp_send_json_error(array('message' => $user_id->get_error_message()));
 
        global $wpdb;
        $wpdb->update($wpdb->users,
            array('user_pass' => $reg->password_hash),
            array('ID' => $user_id),
            array('%s'), array('%d')
        );
        clean_user_cache($user_id);
 
        if ($reg_role === 'student') {
            SS_Database::set_user_classes($user_id, array($class_id));
            // Mokinio klasė (I–IV) — reikalinga mokslo metų pabaigos kėlimui.
            if (class_exists('SS_Roles')) {
                SS_Roles::set_student_level($user_id, (string)$reg->class_level, (string)$reg->class_letter);
            }
        } elseif ($reg_role === 'teacher') {
            // Link to teacher record (and update teacher email if missing)
            $wpdb->update("{$wpdb->prefix}ss_teachers", array('email' => $reg->email), array('id' => $teacher_id));
        }
 
        $extra_status['user_id']     = $user_id;
        $extra_status['approved_at'] = current_time('mysql');
        SS_Database::set_registration_status($id, 'approved', $extra_status);
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('reg_approved', ['user_id' => $user_id, 'approved_by' => get_current_user_id()]);
        }
 
        // Send branded confirmation email
        $school    = SS_Mail::school();
        $login_url = ($lp = get_option('ss_login_page_id')) ? get_permalink($lp) : wp_login_url();
        $role_detail_map = [
            'teacher'  => 'Mokytojas',
            'employee' => 'Darbuotojas',
            'admin'    => 'Administracija',
        ];
        $detail = $reg_role === 'student' && $class
                   ? array('label' => 'Tvarkaraštis', 'value' => esc_html($class->name))
                   : array('label' => 'Rolė', 'value' => $role_detail_map[$reg_role] ?? ucfirst($reg_role));
        SS_Mail::send($reg->email, "{$school} — Registracija patvirtinta", array(
            'title'    => 'Registracija patvirtinta',
            'subtitle' => 'Jūsų paskyra paruošta',
            'icon'     => '✓',
            'body'     => '<p style="margin:0 0 14px;">Sveiki, <strong>' . esc_html($reg->first_name) . '</strong>,</p>'
                        . '<p style="margin:0;">Jūsų registracija į <strong>' . esc_html($school) . '</strong> tvarkaraščių sistemą sėkmingai patvirtinta.</p>',
            'extra'    => SS_Mail::info_list(array(
                array('label' => 'Vartotojas', 'value' => esc_html($login)),
                array('label' => 'El. paštas', 'value' => esc_html($reg->email)),
                $detail,
            )),
            'button'   => array('text' => 'Prisijungti prie sistemos', 'url' => $login_url),
        ));
 
        wp_send_json_success(array('message' => 'Vartotojas sukurtas ir patvirtintas.', 'user_id' => $user_id));
    }
 
    public static function ajax_reject_registration() {
        self::verify();
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(array('message' => 'Trūksta registracijos.'));
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        $reg = SS_Database::get_registration($id);
        if (!$reg) wp_send_json_error(array('message' => 'Registracija nerasta.'));
        // Jau patvirtintos registracijos (paskyra sukurta) atmesti negalima —
        // tai tik supainiotų būsenas. Jei reikia, blokuokite/trinkite pačią paskyrą.
        if ($reg->status === 'approved') {
            wp_send_json_error(array('message' => 'Registracija jau patvirtinta — atmesti nebegalima. Jei reikia, tvarkykite pačią paskyrą („Paskyros").'));
        }
 
        SS_Database::set_registration_status($id, 'rejected', array('reject_reason' => $reason));
 
        if ($reg->email) {
            $school = SS_Mail::school();
            $reason_box = $reason !== ''
                ? SS_Mail::notice('<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#D05155;margin-bottom:6px;">Priežastis</div>' . esc_html($reason), 'error')
                : '';
            SS_Mail::send($reg->email, "{$school} — Registracija atmesta", array(
                'title'    => 'Registracija atmesta',
                'subtitle' => 'Jūsų paraiška nebuvo patvirtinta',
                'accent'   => SS_Mail::RED,
                'icon'     => '✕',
                'body'     => '<p style="margin:0 0 14px;">Sveiki,</p>'
                            . '<p style="margin:0;">Deja, jūsų registracija buvo atmesta.</p>',
                'extra'    => $reason_box,
                'note'     => 'Jei manote, kad tai klaida, arba turite papildomų klausimų — kreipkitės į mokyklos administratorių.',
                'note_type'=> 'info',
            ));
        }
 
        wp_send_json_success();
    }
 
 
    /**
     * Returns an <img> tag pointing at the publicly hosted logo URL.
     *
     * NB: we deliberately do NOT embed the logo as a base64 data: URI here —
     * Gmail (and the whole Google Workspace, which @azuolynogimnazija.lt uses)
     * strips `data:` image sources, so an embedded logo simply never renders.
     * A normal https:// URL is fetched by every major email client.
     * Falls back to a coloured placeholder div if no logo is set.
     */
    public static function logo_html_for_email(): string {
        $fallback = '<div style="background:#4F8952;width:44px;height:44px;border-radius:12px;text-align:center;vertical-align:middle;font-size:22px;font-weight:900;color:#ffffff;font-family:Georgia,serif;line-height:44px;">Ą</div>';

        $logo_id  = (int) get_option('ss_logo_id', 0);
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : '';
        if (!$logo_url) {
            $logo_url = get_option('ss_logo_url', '');
        }
        if (!$logo_url) return $fallback;

        // Force https so clients that block mixed/insecure content still load it.
        $logo_url = set_url_scheme($logo_url, 'https');
        return '<img src="' . esc_url($logo_url) . '" alt="Logo" height="44" style="height:44px;max-width:200px;width:auto;vertical-align:middle;border:0;display:block;">';
    }
 
    /**
     * Returns logo as base64 string + mime type for PDF generation.
     * Returns null if no logo is configured.
     */
    public static function get_logo_for_pdf(): ?array {
        $logo_id = (int) get_option('ss_logo_id', 0);
        if (!$logo_id) {
            $logo_url = get_option('ss_logo_url', '');
            if (!$logo_url) return null;
            $path = self::url_to_local_path($logo_url);
        } else {
            $path = get_attached_file($logo_id);
        }
        if (!$path || !file_exists($path)) return null;
        $data = @file_get_contents($path);
        if (!$data) return null;
        $mime = mime_content_type($path) ?: 'image/png';
        return array('b64' => base64_encode($data), 'mime' => $mime);
    }
 
    private static function url_to_local_path(string $url): string {
        $upload_dir = wp_upload_dir();
        $base_url   = $upload_dir['baseurl'];
        $base_dir   = $upload_dir['basedir'];
        if (strpos($url, $base_url) === 0) {
            return $base_dir . substr($url, strlen($base_url));
        }
        // Try site root
        return rtrim(ABSPATH, '/') . parse_url($url, PHP_URL_PATH);
    }
 
    public static function ajax_delete_registration() {
        self::verify();
        $id = (int)($_POST["id"] ?? 0);
        if (!$id) wp_send_json_error(array("message" => "Trūksta registracijos ID."));
        $reg = SS_Database::get_registration($id);
        if (!$reg) wp_send_json_error(array("message" => "Registracija nerasta."));
        SS_Database::delete_registration($id);
        wp_send_json_success(array("message" => "Registracija ištrinta."));
    }

    /* ── Registracijų masiniai veiksmai ──────────────────────────────
       op: 'reject' | 'delete' | 'approve_noclass'
       Netinkamos eilutės (jau patvirtintos, nebaigtos pildyti ir pan.)
       praleidžiamos — grąžinama suvestinė, kiek atlikta / praleista. */
    public static function ajax_bulk_registrations() {
        self::verify();
        $op  = sanitize_key($_POST['op'] ?? '');
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? array())));
        if (!$ids) wp_send_json_error(array('message' => 'Nepasirinkta nė viena registracija.'));
        if (!in_array($op, array('reject','delete','approve_noclass'), true)) {
            wp_send_json_error(array('message' => 'Nežinomas veiksmas.'));
        }
        $reason  = sanitize_text_field($_POST['reason'] ?? '');
        $done    = 0;
        $skipped = 0;

        foreach ($ids as $id) {
            $reg = SS_Database::get_registration($id);
            if (!$reg) { $skipped++; continue; }

            if ($op === 'delete') {
                SS_Database::delete_registration($id);
                $done++;
                continue;
            }

            if ($op === 'reject') {
                // Jau patvirtintų (sukurta paskyra) neatmetinėjam — praleidžiam.
                if ($reg->status === 'approved') { $skipped++; continue; }
                SS_Database::set_registration_status($id, 'rejected', array('reject_reason' => $reason));
                if ($reg->email) {
                    $school = SS_Mail::school();
                    $reason_box = $reason !== ''
                        ? SS_Mail::notice('<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#D05155;margin-bottom:6px;">Priežastis</div>' . esc_html($reason), 'error')
                        : '';
                    SS_Mail::send($reg->email, "{$school} — Registracija atmesta", array(
                        'title'    => 'Registracija atmesta',
                        'subtitle' => 'Jūsų paraiška nebuvo patvirtinta',
                        'accent'   => SS_Mail::RED,
                        'icon'     => '✕',
                        'body'     => '<p style="margin:0 0 14px;">Sveiki,</p>'
                                    . '<p style="margin:0;">Deja, jūsų registracija buvo atmesta.</p>',
                        'extra'    => $reason_box,
                        'note'     => 'Jei manote, kad tai klaida, arba turite papildomų klausimų — kreipkitės į mokyklos administratorių.',
                        'note_type'=> 'info',
                    ));
                }
                $done++;
                continue;
            }

            // op === 'approve_noclass' — patvirtinti BE tvarkaraščio priskyrimo.
            if (self::bulk_approve_without_class($reg)) $done++;
            else $skipped++;
        }

        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('reg_approved', array(
                'bulk' => $op, 'done' => $done, 'skipped' => $skipped,
                'user_id' => get_current_user_id(),
            ));
        }
        wp_send_json_success(array(
            'message' => sprintf('Atlikta: %d, praleista: %d.', $done, $skipped),
            'done'    => $done,
            'skipped' => $skipped,
        ));
    }

    /**
     * Patvirtina registraciją NEpriskiriant tvarkaraščio (suspausta
     * ajax_approve_registration versija be class_id/teacher_id reikalavimų).
     * Mokinys tvarkaraštį galės prisegti kodu nustatymuose.
     * @return bool  true — paskyra sukurta; false — praleista (netinkama).
     */
    private static function bulk_approve_without_class($reg): bool {
        if ($reg->status === 'approved') return false;
        if ((int)$reg->verified !== 1)   return false;
        if (empty($reg->password_hash))  return false;
        if (email_exists($reg->email))   return false;

        $reg_role = $reg->reg_role ?: 'student';
        $wp_role  = array(
            'student'  => 'ss_student',
            'teacher'  => 'ss_teacher',
            'employee' => 'ss_employee',
            'admin'    => 'administrator',
        )[$reg_role] ?? 'ss_student';

        $local = strstr($reg->email, '@', true) ?: 'user';
        $base  = sanitize_user($local, true);
        $login = $base; $i = 1;
        while (username_exists($login)) {
            $login = $base . $i++;
            if ($i > 50) return false;
        }

        $user_id = wp_insert_user(array(
            'user_login'   => $login,
            'user_email'   => $reg->email,
            'user_pass'    => '',
            'first_name'   => $reg->first_name,
            'last_name'    => $reg->last_name,
            'display_name' => trim($reg->first_name . ' ' . $reg->last_name),
            'role'         => $wp_role,
        ));
        if (is_wp_error($user_id)) return false;

        global $wpdb;
        $wpdb->update($wpdb->users, array('user_pass' => $reg->password_hash), array('ID' => $user_id), array('%s'), array('%d'));
        clean_user_cache($user_id);

        if ($reg_role === 'student' && class_exists('SS_Roles')) {
            SS_Roles::set_student_level($user_id, (string)$reg->class_level, (string)$reg->class_letter);
        }

        SS_Database::set_registration_status((int)$reg->id, 'approved', array(
            'user_id'     => $user_id,
            'approved_at' => current_time('mysql'),
        ));
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('reg_approved', array('user_id' => $user_id, 'approved_by' => get_current_user_id(), 'no_class' => 1));
        }

        $school    = SS_Mail::school();
        $login_url = ($lp = get_option('ss_login_page_id')) ? get_permalink($lp) : wp_login_url();
        SS_Mail::send($reg->email, "{$school} — Registracija patvirtinta", array(
            'title'    => 'Registracija patvirtinta',
            'subtitle' => 'Jūsų paskyra paruošta',
            'icon'     => '✓',
            'body'     => '<p style="margin:0 0 14px;">Sveiki, <strong>' . esc_html($reg->first_name) . '</strong>,</p>'
                        . '<p style="margin:0;">Jūsų registracija į <strong>' . esc_html($school) . '</strong> tvarkaraščių sistemą sėkmingai patvirtinta.</p>',
            'extra'    => SS_Mail::info_list(array(
                array('label' => 'Vartotojas', 'value' => esc_html($login)),
                array('label' => 'El. paštas', 'value' => esc_html($reg->email)),
                array('label' => 'Tvarkaraštis', 'value' => 'Nepriskirtas — prisegsite kodu nustatymuose'),
            )),
            'button'   => array('text' => 'Prisijungti prie sistemos', 'url' => $login_url),
        ));
        return true;
    }
 
    /* ───────────────────────────────────────────────
       TVARKARAŠČIŲ KODAI
    ─────���───────────────────────────────────────── */
 
    public static function ajax_regenerate_class_code() {
        self::verify();
        $class_id = (int)($_POST['class_id'] ?? 0);
        if (!$class_id) wp_send_json_error(array('message' => 'Trūksta klasės ID.'));
        $cls = SS_Database::get_class($class_id);
        if (!$cls) wp_send_json_error(array('message' => 'Klasė nerasta.'));
        $code = SS_Database::regenerate_class_code($class_id);
        wp_send_json_success(array('code' => $code, 'class_id' => $class_id));
    }
 
    public static function ajax_save_class_link() {
        self::verify();
        $email    = strtolower(sanitize_email($_POST['email'] ?? ''));
        $class_id = (int)($_POST['class_id'] ?? 0);
        if (!$email || !is_email($email)) wp_send_json_error(array('message' => 'Neteisingas el. paštas.'));
        if (!$class_id) wp_send_json_error(array('message' => 'Pasirinkite tvarkaraštį.'));
        $cls = SS_Database::get_class($class_id);
        if (!$cls) wp_send_json_error(array('message' => 'Tvarkaraštis nerastas.'));
        SS_Database::upsert_class_link($email, $class_id);
        $link = SS_Database::get_class_link_by_email($email);
        wp_send_json_success(array('link' => $link, 'message' => 'Išsaugota.'));
    }
 
    public static function ajax_delete_class_link() {
        self::verify();
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(array('message' => 'Trūksta ID.'));
        SS_Database::delete_class_link($id);
        wp_send_json_success(array('message' => 'Ištrinta.'));
    }
 
    public static function ajax_import_class_links() {
        self::verify();
        $raw = (string)($_POST['csv'] ?? '');
        if (!$raw) wp_send_json_error(array('message' => 'Tuščias CSV.'));
        // Detect separator (comma/semicolon/tab)
        $first_line = strtok($raw, "\r\n");
        $sep = ',';
        if (substr_count($first_line, ';') > substr_count($first_line, ',')) $sep = ';';
        elseif (substr_count($first_line, "\t") > 0)                          $sep = "\t";
 
        $rows = array(); $skipped_header = false;
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        foreach ($lines as $idx => $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = str_getcsv($line, $sep);
            if (count($parts) < 2) continue;
            $email = trim($parts[0]);
            $class = trim($parts[1]);
            // Skip header row
            if (!$skipped_header && (stripos($email, 'email') !== false || stripos($email, 'paš') !== false)) {
                $skipped_header = true;
                continue;
            }
            $rows[] = array('email' => $email, 'class' => $class);
        }
        $stats = SS_Database::bulk_upsert_class_links($rows);
        $links = SS_Database::get_class_links();
        wp_send_json_success(array(
            'message' => "Pridėta: {$stats['added']}, atnaujinta: {$stats['updated']}, praleista: {$stats['skipped']}.",
            'stats'   => $stats,
            'links'   => $links,
        ));
    }
 
    public static function ajax_export_class_codes_csv() {
        if (!current_user_can('manage_options')) wp_die('Neturite teisės.');
        check_admin_referer('ss_export_codes');
        $rows = SS_Database::get_class_codes_list();
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="tvarkarasciu-kodai-' . SS_Time::today_ymd() . '.csv"');
        $out = fopen('php://output', 'w');
        // BOM for Excel
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('Tvarkaraštis', 'Kodas', 'Mokinių skaičius', 'Prisegtų el. paštų', 'IMP', 'Sukurta'), ';');
        foreach ($rows as $r) {
            fputcsv($out, array(
                $r->name,
                $r->code,
                (int)$r->student_count,
                (int)$r->link_count,
                ((int)$r->is_imp === 1) ? 'Taip' : 'Ne',
                $r->created_at,
            ), ';');
        }
        fclose($out);
        exit;
    }
 
    public static function ajax_export_class_links_csv() {
        if (!current_user_can('manage_options')) wp_die('Neturite teisės.');
        check_admin_referer('ss_export_links');
        $rows = SS_Database::get_class_links();
        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="tvarkarasciu-prisegti-pastai-' . SS_Time::today_ymd() . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array('El. paštas', 'Tvarkaraštis', 'Kodas', 'Pridėta'), ';');
        foreach ($rows as $r) {
            fputcsv($out, array($r->email, $r->class_name, $r->class_code, $r->created_at), ';');
        }
        fclose($out);
        exit;
    }
 
    // ══════════════════════════════════════════════════════════════════════════
    // SISTEMOS EKSPORTAS / IMPORTAS
    // ══════════════════════════════════════════════════════════════════════════
 
    /* ─── Saugumo puslapis ──────────────────────────────────────────── */
    public static function page_security() {
        if (!current_user_can('manage_options')) wp_die('Nėra teisės.');
 
        // POST → išsaugoti ir persijungti (PRG pattern – prevent double-submit)
        if (isset($_POST['ss_security_nonce']) && wp_verify_nonce($_POST['ss_security_nonce'], 'ss_security_settings')) {
            // „Atnaujinti" — išvalyti visą kešą (atskira forma, be nustatymų saugojimo).
            if (!empty($_POST['ss_ts_purge']) && class_exists('SS_Turnstile')) {
                $res = SS_Turnstile::purge_all_caches();
                wp_safe_redirect(add_query_arg(
                    ['page' => 'ss-security', 'tab' => 'bot', 'purged' => 1, 'cf' => rawurlencode($res['cf'])],
                    admin_url('admin.php')
                ));
                exit;
            }
            // Pastaba: kritinės apsaugos (SS_Security::ALWAYS_ON) čia nebėra —
            // jos visada įjungtos ir per formą nekeičiamos.
            // SVARBU: bendrieji saugumo laukai saugomi TIK iš „Nustatymai" skirtuko —
            // kitaip pateikus „Apsauga nuo botų" formą (kur šių laukų nėra) visi
            // jungikliai būtų nustatyti į 0.
            if (($_POST['tab'] ?? '') === 'settings') {
                $bools = [
                    'ss_sec_trust_proxy', 'ss_sec_login_alert_enabled',
                    'ss_sec_pw_require_upper', 'ss_sec_pw_require_digit',
                    'ss_sec_ids_enabled', 'ss_sec_new_device_enabled',
                ];
                foreach ($bools as $k) {
                    update_option($k, isset($_POST[$k]) ? 1 : 0);
                }
                $ints = [
                    'ss_sec_lockout_threshold_1'  => [2, 200],
                    'ss_sec_lockout_threshold_2'  => [2, 500],
                    'ss_sec_lockout_threshold_3'  => [2, 999],
                    'ss_sec_lockout_minutes_1'    => [1, 60],
                    'ss_sec_lockout_minutes_2'    => [1, 240],
                    'ss_sec_lockout_minutes_3'    => [1, 2880],
                    'ss_sec_pw_min_length'        => [6, 64],
                    'ss_sec_reg_rate_limit'       => [1, 50],
                    'ss_sec_audit_retention_days' => [7, 730],
                    'ss_sec_ids_window_min'       => [5, 120],
                    'ss_sec_ids_threshold'        => [3, 200],
                    'ss_sec_max_sessions'         => [0, 50],
                    'ss_sec_login_alert_threshold'=> [1, 20],
                ];
                foreach ($ints as $k => [$min, $max]) {
                    if (isset($_POST[$k])) {
                        update_option($k, min($max, max($min, (int)$_POST[$k])));
                    }
                }
            }
            // ── Apsauga nuo botų (Turnstile) ─────────────────────────────
            if (($_POST['tab'] ?? '') === 'bot') {
                update_option('ss_ts_enabled',       isset($_POST['ss_ts_enabled']) ? 1 : 0);
                update_option('ss_ts_login_widget',  isset($_POST['ss_ts_login_widget']) ? 1 : 0);
                update_option('ss_ts_wpadmin',       isset($_POST['ss_ts_wpadmin']) ? 1 : 0);
                update_option('ss_ts_block_wpadmin', isset($_POST['ss_ts_block_wpadmin']) ? 1 : 0);
                update_option('ss_ts_allow_search',  isset($_POST['ss_ts_allow_search']) ? 1 : 0);
                update_option('ss_ts_challenge_new', isset($_POST['ss_ts_challenge_new']) ? 1 : 0);

                update_option('ss_ts_site_key', sanitize_text_field($_POST['ss_ts_site_key'] ?? ''));
                update_option('ss_ts_cf_zone',  sanitize_text_field($_POST['ss_ts_cf_zone']  ?? ''));
                // Slaptos reikšmės šifruojamos ir atnaujinamos TIK jei įvestas naujas
                // tekstas (tuščias laukas = palikti esamą). Taip jos nerodomos formoje.
                $ts_secret_in = sanitize_text_field($_POST['ss_ts_secret_key'] ?? '');
                if ($ts_secret_in !== '') SS_Turnstile::set_secret('ss_ts_secret_key', $ts_secret_in);
                $ts_cf_in = sanitize_text_field($_POST['ss_ts_cf_token'] ?? '');
                if ($ts_cf_in !== '') SS_Turnstile::set_secret('ss_ts_cf_token', $ts_cf_in);

                $login_mode = sanitize_key($_POST['ss_ts_login_mode'] ?? 'managed');
                if (!in_array($login_mode, ['managed','always','invisible'], true)) $login_mode = 'managed';
                update_option('ss_ts_login_mode', $login_mode);

                $sw_mode = sanitize_key($_POST['ss_ts_sitewide_mode'] ?? 'off');
                if (!in_array($sw_mode, ['off','background','interstitial'], true)) $sw_mode = 'off';
                update_option('ss_ts_sitewide_mode', $sw_mode);

                update_option('ss_ts_pass_ttl', min(10080, max(5, (int)($_POST['ss_ts_pass_ttl'] ?? 720))));

                // Kelių eilučių išimčių sąrašas (po vieną kelią eilutėje)
                $exempt = (string) wp_unslash($_POST['ss_ts_exempt_paths'] ?? '');
                $exempt = implode("\n", array_map('sanitize_text_field', preg_split('/\r\n|\r|\n/', $exempt)));
                update_option('ss_ts_exempt_paths', trim($exempt));
            }

            $cleared = false;
            if (!empty($_POST['ss_sec_clear_log'])) {
                SS_AuditLog::clear_all();
                $cleared = true;
            }
            // PRG – redirect su notice flag
            $tab = sanitize_key($_POST['tab'] ?? 'settings');
            $redirect = add_query_arg(
                array_filter(['page' => 'ss-security', 'tab' => $tab, 'saved' => 1, 'cleared' => $cleared ? 1 : null]),
                admin_url('admin.php')
            );
            wp_safe_redirect($redirect);
            exit;
        }
 
        // Rodyti notice po redirect
        if (!empty($_GET['saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p><span class="dashicons dashicons-yes" style="font-size:18px;width:18px;height:18px;vertical-align:text-bottom"></span> Nustatymai išsaugoti.</p></div>';
        }
        if (!empty($_GET['cleared'])) {
            echo '<div class="notice notice-warning is-dismissible"><p><span class="dashicons dashicons-trash" style="font-size:18px;width:18px;height:18px;vertical-align:text-bottom"></span> Žurnalas išvalytas.</p></div>';
        }
        if (!empty($_GET['purged'])) {
            $cf = sanitize_text_field(rawurldecode($_GET['cf'] ?? 'skip'));
            if ($cf === 'ok') {
                $cf_msg = ' Cloudflare kešas taip pat išvalytas.';
                $cls = 'notice-success';
            } elseif ($cf === 'skip') {
                $cf_msg = ' (Cloudflare nekonfigūruotas — jei naudojate CF, įrašykite API žetoną ir Zone ID žemiau arba išvalykite CF kešą rankiniu būdu.)';
                $cls = 'notice-success';
            } else {
                $cf_msg = ' Cloudflare klaida: ' . esc_html(preg_replace('/^error:/', '', $cf));
                $cls = 'notice-warning';
            }
            echo '<div class="notice ' . $cls . ' is-dismissible"><p><span class="dashicons dashicons-update" style="font-size:18px;width:18px;height:18px;vertical-align:text-bottom"></span> Vietinis kešas išvalytas.' . $cf_msg . '</p></div>';
        }
 
        $filters = array(
            'event'     => sanitize_text_field($_GET['event']     ?? ''),
            'severity'  => sanitize_text_field($_GET['severity']  ?? ''),
            'ip'        => sanitize_text_field($_GET['ip']        ?? ''),
            'user_id'   => (int)($_GET['user_id'] ?? 0),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to'   => sanitize_text_field($_GET['date_to']   ?? ''),
            'search'    => sanitize_text_field($_GET['search']    ?? ''),
        );
        foreach (array('date_from','date_to') as $dk) {
            if ($filters[$dk] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$dk])) $filters[$dk] = '';
        }
        $per_page       = 50;
        $paged          = max(1, (int)($_GET['paged'] ?? 1));
        $total_filtered = SS_AuditLog::count_filtered($filters);
        $total_pages    = max(1, (int)ceil($total_filtered / $per_page));
        if ($paged > $total_pages) $paged = $total_pages;
        $offset         = ($paged - 1) * $per_page;
        $events         = SS_AuditLog::query($filters, $per_page, $offset);
        $stats          = SS_AuditLog::stats_24h();
        $total          = SS_AuditLog::count_all();
        $event_filter   = $filters['event'];   // back-compat for existing markup
        $ip_filter      = $filters['ip'];
        $log_export_url = wp_nonce_url(
            add_query_arg(array_merge(array('action' => 'ss_log_export'), array_filter($filters)), admin_url('admin-post.php')),
            'ss_log_export'
        );
        $ts_stats = class_exists('SS_Turnstile') ? SS_Turnstile::stats_24h() : [];

        include SS_PLUGIN_DIR . 'admin/views/security.php';
    }
 
    /** admin_post: eksportuoja saugumo žurnalą (su aktyviais filtrais) į CSV. */
    public static function handle_log_export() {
        if (!current_user_can('manage_options')) wp_die('Neturite teisės.');
        check_admin_referer('ss_log_export');
        $filters = array(
            'event'     => sanitize_text_field($_GET['event']     ?? ''),
            'severity'  => sanitize_text_field($_GET['severity']  ?? ''),
            'ip'        => sanitize_text_field($_GET['ip']        ?? ''),
            'user_id'   => (int)($_GET['user_id'] ?? 0),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to'   => sanitize_text_field($_GET['date_to']   ?? ''),
            'search'    => sanitize_text_field($_GET['search']    ?? ''),
        );
        foreach (array('date_from','date_to') as $dk) {
            if ($filters[$dk] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters[$dk])) $filters[$dk] = '';
        }
        SS_AuditLog::write('data_export', array('kind' => 'security_log'));
        $rows = SS_AuditLog::query($filters, 10000, 0); // cap — bounded memory/time
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ss-zurnalas-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM (Excel)
        fputcsv($out, array_map(array('SS_AuditLog', 'csv_cell'),
            array('Laikas','Įvykis','Lygis','Aktorius','Tikslas','IP','Metodas','Naršyklė','Info')));
        foreach ($rows as $r) {
            $actor  = $r->username_tried ?: ($r->user_id ? '#' . (int)$r->user_id : '');
            $target = '';
            if (!empty($r->target_id)) {
                $tu = get_user_by('id', (int)$r->target_id);
                $target = $tu ? $tu->user_login : '#' . (int)$r->target_id;
            }
            fputcsv($out, array_map(array('SS_AuditLog', 'csv_cell'), array(
                $r->created_at, $r->event, $r->severity, $actor, $target, $r->ip, $r->method,
                SS_AuditLog::device_label($r->user_agent ?? ''), $r->extra,
            )));
        }
        fclose($out);
        exit;
    }

    /** admin_post: sugeneruoja ir parsiunčia .azuolynotvs failą */
    public static function handle_system_export() {
        if (!current_user_can('manage_options')) wp_die('Neturite teisės.');
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ss_system_export')) wp_die('Saugos klaida.');
 
        $allowed  = ['teachers','lessons','schedules','students'];
        $raw_secs = explode(',', sanitize_text_field($_POST['sections'] ?? ''));
        $sections = array_values(array_intersect($raw_secs, $allowed));
        if (!$sections) wp_die('Nepasirinktas nei vienas skyrius.');
 
        $data     = SS_SystemIO::export($sections);
        $json     = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $filename = 'azuolynotvs-' . SS_Time::date('Y-m-d_Hi') . '.azuolynotvs';
 
        nocache_headers();
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        echo $json;
        exit;
    }
 
    /** AJAX: peržiūri įkeltą .azuolynotvs failą (nieko nerašo į DB) */
    public static function ajax_system_import_preview() {
        self::verify();
        $json = wp_unslash($_POST['json'] ?? '');
        if (!$json) wp_send_json_error(['message' => 'Tuščias failas.']);
        $result = SS_SystemIO::preview($json);
        if ($result['error']) wp_send_json_error(['message' => $result['error']]);
        wp_send_json_success($result);
    }

    /** AJAX: įrašo įkeltą .azuolynotvs failą į DB (režimas merge | replace). */
    public static function ajax_system_import_commit() {
        self::verify();
        $raw = wp_unslash($_POST['pkg'] ?? '');
        if (!$raw) wp_send_json_error(['message' => 'Tuščias failas.']);
        $pkg = json_decode($raw, true);
        if (!is_array($pkg) || ($pkg['format'] ?? '') !== SS_SystemIO::FORMAT) {
            wp_send_json_error(['message' => 'Neteisingas failo formatas.']);
        }
        $mode     = (($_POST['mode'] ?? 'merge') === 'replace') ? 'replace' : 'merge';
        $allowed  = ['teachers','lessons','schedules','students'];
        $raw_secs = explode(',', sanitize_text_field($_POST['sections'] ?? ''));
        $sections = array_values(array_intersect($raw_secs, $allowed));
        if (!$sections) wp_send_json_error(['message' => 'Nepasirinktas nė vienas skyrius.']);
        $result = SS_SystemIO::commit($pkg, $mode, $sections);
        if (!empty($result['error'])) wp_send_json_error(['message' => $result['error']]);
        SS_AuditLog::write('data_import', ['kind' => 'system', 'mode' => $mode, 'sections' => $sections]);
        wp_send_json_success($result);
    }

    /* ══════════════════════════════════════════════════════════
       BULK ACTIONS
    ══════════════════════════════════════════════════════════ */
 
    public static function ajax_bulk_delete_teachers() {
        self::verify();
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        foreach ($ids as $id) { SS_Database::delete_teacher($id); }
        wp_send_json_success(['deleted' => count($ids)]);
    }
 
    public static function ajax_bulk_delete_lessons() {
        self::verify();
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        foreach ($ids as $id) { SS_Database::delete_lesson($id); }
        wp_send_json_success(['deleted' => count($ids)]);
    }
 
    public static function ajax_bulk_delete_classes() {
        self::verify();
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        foreach ($ids as $id) { SS_Database::delete_class($id); }
        wp_send_json_success(['deleted' => count($ids)]);
    }
 
    public static function ajax_bulk_toggle_imp() {
        self::verify();
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        global $wpdb;
        $table   = $wpdb->prefix . 'ss_classes';
        $results = [];
        foreach ($ids as $id) {
            $cls = SS_Database::get_class($id);
            if (!$cls) continue;
            $new = $cls->is_imp ? 0 : 1;
            $wpdb->update($table, ['is_imp' => $new], ['id' => $id], ['%d'], ['%d']);
            $results[$id] = $new;
        }
        wp_send_json_success(['results' => $results]);
    }
 
    public static function ajax_bulk_delete_students() {
        self::verify();
        $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])));
        if (!function_exists('wp_delete_user')) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        $deleted = 0;
        foreach ($ids as $uid) {
            SS_Database::set_user_classes($uid, []);
            if (wp_delete_user($uid)) $deleted++;
        }
        wp_send_json_success(['deleted' => $deleted]);
    }

    /* ══════════════════════════════════════════════════════════
       MOKINIO KLASĖ (I–IV) — redagavimas „Mokiniai" lentelėje
    ══════════════════════════════════════════════════════════ */
    public static function ajax_save_student_class() {
        self::verify();
        $uid    = (int)($_POST['user_id'] ?? 0);
        $level  = sanitize_text_field($_POST['level']  ?? '');
        $letter = sanitize_text_field($_POST['letter'] ?? '');
        if (!$uid || !get_userdata($uid)) wp_send_json_error(['message' => 'Vartotojas nerastas.']);
        if ($level !== '' && !in_array($level, SS_Roles::student_levels(), true)) {
            wp_send_json_error(['message' => 'Klasė turi būti I, II, III ar IV.']);
        }
        if ($letter !== '' && !preg_match('/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]{1,3}$/u', $letter)) {
            wp_send_json_error(['message' => 'Klasės raidė turi būti 1–3 raidės.']);
        }
        SS_Roles::set_student_level($uid, $level, $letter);
        $lvl = SS_Roles::get_student_level($uid);
        wp_send_json_success(['letter' => $lvl['letter'], 'label' => $lvl['label'] ?: '—']);
    }

    /* ══════════════════════════════════════════════════════════
       MOKSLO METŲ / PUSMEČIO PABAIGA
    ══════════════════════════════════════════════════════════ */

    /** Surenka mokinių kėlimo / baigimo sąrašus (be jokių pakeitimų DB). */
    private static function rollover_student_plan(): array {
        $plan = ['promote' => [], 'graduate' => [], 'skipped' => []];
        foreach (SS_Roles::get_students() as $u) {
            $lvl = (string) get_user_meta($u->ID, 'ss_class_level',  true);
            $ltr = (string) get_user_meta($u->ID, 'ss_class_letter', true);
            $label = $lvl . $ltr;
            if (!in_array($lvl, SS_Roles::student_levels(), true)) {
                $plan['skipped'][] = ['id' => $u->ID, 'name' => $u->display_name, 'email' => $u->user_email];
                continue;
            }
            $next = SS_Roles::next_level($lvl);
            if ($next === null) { // IV → laida baigta
                $plan['graduate'][] = ['id' => $u->ID, 'name' => $u->display_name, 'email' => $u->user_email, 'level' => $label];
            } else {
                $plan['promote'][] = ['id' => $u->ID, 'name' => $u->display_name, 'email' => $u->user_email, 'from' => $label, 'to' => $next . $ltr];
            }
        }
        return $plan;
    }

    public static function ajax_rollover_preview() {
        self::verify();
        $type = (($_POST['type'] ?? '') === 'semester') ? 'semester' : 'year';
        SS_Roles::backfill_student_levels();

        $resp = [
            'type'         => $type,
            'counts'       => SS_Database::count_schedule_data(),
            'classes'      => array_values(array_map(fn($c) => $c->name, SS_Database::get_classes())),
            'school_year'  => get_option('ss_school_year', ''),
            'promote'      => [],
            'graduate'     => [],
            'skipped'      => [],
        ];
        if ($type === 'year') {
            $plan = self::rollover_student_plan();
            $resp['promote']  = $plan['promote'];
            $resp['graduate'] = $plan['graduate'];
            $resp['skipped']  = $plan['skipped'];
        }
        wp_send_json_success($resp);
    }

    public static function ajax_rollover_commit() {
        self::verify();
        $type        = (($_POST['type'] ?? '') === 'semester') ? 'semester' : 'year';
        $school_year = sanitize_text_field(wp_unslash($_POST['school_year'] ?? ''));
        $confirm     = !empty($_POST['confirm']);
        if (!$confirm) wp_send_json_error(['message' => 'Patvirtinkite veiksmą.']);
        if ($type === 'year' && $school_year === '') {
            wp_send_json_error(['message' => 'Įveskite naujus mokslo metus.']);
        }

        $log = ['promoted' => [], 'graduated' => [], 'skipped' => []];

        if ($type === 'year') {
            SS_Roles::backfill_student_levels();
            if (!function_exists('wp_delete_user')) require_once ABSPATH . 'wp-admin/includes/user.php';
            // Naudojam iš anksto suskaičiuotą planą (kad trynimas nekeistų iteracijos).
            $plan = self::rollover_student_plan();
            foreach ($plan['graduate'] as $g) {
                SS_Database::set_user_classes((int)$g['id'], []);
                if (wp_delete_user((int)$g['id'])) {
                    $log['graduated'][] = ['name' => $g['name'], 'email' => $g['email'], 'level' => $g['level']];
                }
            }
            // Kėlimas: I→II→III (raidė nekinta).
            foreach ($plan['promote'] as $p) {
                $cur  = (string) get_user_meta((int)$p['id'], 'ss_class_level', true);
                $ltr  = (string) get_user_meta((int)$p['id'], 'ss_class_letter', true);
                $next = SS_Roles::next_level($cur);
                if ($next !== null) {
                    SS_Roles::set_student_level((int)$p['id'], $next, $ltr);
                    $log['promoted'][] = ['name' => $p['name'], 'from' => $p['from'], 'to' => $next . $ltr];
                }
            }
            $log['skipped'] = array_map(fn($s) => ['name' => $s['name'], 'email' => $s['email']], $plan['skipped']);
        }

        $deleted = SS_Database::wipe_all_schedules();

        if ($school_year !== '') update_option('ss_school_year', $school_year);

        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('year_rollover', [
                'type'      => $type,
                'graduated' => count($log['graduated']),
                'promoted'  => count($log['promoted']),
                'by'        => get_current_user_id(),
            ]);
        }

        wp_send_json_success([
            'type'       => $type,
            'deleted'    => $deleted,
            'log'        => $log,
            'school_year'=> $school_year,
            'import_url' => admin_url('admin.php?page=ss-import'),
        ]);
    }
}
 
