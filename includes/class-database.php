<?php
if (!defined('ABSPATH')) exit;

class SS_Database {

    public static function install() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $p = $wpdb->prefix;

        dbDelta("CREATE TABLE {$p}ss_teachers (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL DEFAULT '',
  short varchar(30) NOT NULL DEFAULT '',
  email varchar(255) NOT NULL DEFAULT '',
  color varchar(7) NOT NULL DEFAULT '#4F8952',
  user_id bigint(20) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY user_id (user_id)
) $c;");

        // Safety net: ensure `user_id` column exists on older installs (links WP account ↔ teacher)
        $has_teacher_uid = $wpdb->get_var("SHOW COLUMNS FROM {$p}ss_teachers LIKE 'user_id'");
        if (!$has_teacher_uid) {
            $wpdb->query("ALTER TABLE {$p}ss_teachers ADD COLUMN user_id BIGINT(20) NOT NULL DEFAULT 0 AFTER color");
            $wpdb->query("ALTER TABLE {$p}ss_teachers ADD KEY user_id (user_id)");
        }
        // Backfill: link teachers to existing WP users by matching display_name / email
        $unlinked = $wpdb->get_results("SELECT id, name, email FROM {$p}ss_teachers WHERE user_id = 0");
        if ($unlinked) {
            foreach ($unlinked as $ut) {
                $u = null;
                if (!empty($ut->email)) $u = get_user_by('email', $ut->email);
                if (!$u && !empty($ut->name)) {
                    $found = get_users(array('search' => $ut->name, 'search_columns' => array('display_name'), 'number' => 1));
                    if ($found) $u = $found[0];
                }
                if ($u && in_array('ss_teacher', (array)$u->roles, true)) {
                    // Only auto-link if this WP user isn't already linked elsewhere
                    $taken = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$p}ss_teachers WHERE user_id=%d", $u->ID));
                    if (!$taken) {
                        $wpdb->update("{$p}ss_teachers", array('user_id' => $u->ID), array('id' => (int)$ut->id));
                    }
                }
            }
        }

        dbDelta("CREATE TABLE {$p}ss_lessons (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  subject varchar(255) NOT NULL DEFAULT '',
  teacher_id bigint(20) NOT NULL DEFAULT 0,
  color varchar(7) NOT NULL DEFAULT '#4F8952',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id)
) $c;");

        dbDelta("CREATE TABLE {$p}ss_periods (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  period_number tinyint(2) NOT NULL DEFAULT 0,
  start_time varchar(5) NOT NULL DEFAULT '',
  end_time varchar(5) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY period_number (period_number)
) $c;");

        dbDelta("CREATE TABLE {$p}ss_rooms (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL DEFAULT '',
  color varchar(7) NOT NULL DEFAULT '#224768',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY name (name)
) $c;");

        dbDelta("CREATE TABLE {$p}ss_classes (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL DEFAULT '',
  color varchar(7) NOT NULL DEFAULT '#4F8952',
  is_imp tinyint(1) NOT NULL DEFAULT 0,
  code varchar(12) NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY code (code)
) $c;");

        // Safety net: ensure `code` column exists on older installs
        $has_code = $wpdb->get_var("SHOW COLUMNS FROM {$p}ss_classes LIKE 'code'");
        if (!$has_code) {
            $wpdb->query("ALTER TABLE {$p}ss_classes ADD COLUMN code VARCHAR(12) NOT NULL DEFAULT '' AFTER is_imp");
            $wpdb->query("ALTER TABLE {$p}ss_classes ADD UNIQUE KEY code (code)");
        }

        /* Pre-attached emails → class (registration bypass) */
        dbDelta("CREATE TABLE {$p}ss_class_email_links (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  email varchar(190) NOT NULL DEFAULT '',
  class_id bigint(20) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY email (email),
  KEY class_id (class_id)
) $c;");

        // Safety net: ensure ss_class_email_links table exists
        $links_exists = $wpdb->get_var("SHOW TABLES LIKE '{$p}ss_class_email_links'");
        if (!$links_exists) {
            $wpdb->query("CREATE TABLE {$p}ss_class_email_links (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                email varchar(190) NOT NULL DEFAULT '',
                class_id bigint(20) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (id),
                UNIQUE KEY email (email),
                KEY class_id (class_id)
            ) $c;");
        }

        // Backfill codes for existing classes
        $missing = $wpdb->get_col("SELECT id FROM {$p}ss_classes WHERE code='' OR code IS NULL");
        if ($missing) {
            foreach ($missing as $cid) self::ensure_class_code((int)$cid);
        }

        /* Each cell can hold MULTIPLE lesson options (subgroups).
           subgroup_label: e.g. "1 grupė", "2 grupė", "" = no subgroup */
        dbDelta("CREATE TABLE {$p}ss_class_schedule (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  class_id bigint(20) NOT NULL DEFAULT 0,
  day_of_week tinyint(1) NOT NULL DEFAULT 1,
  period_id bigint(20) NOT NULL DEFAULT 0,
  lesson_id bigint(20) NOT NULL DEFAULT 0,
  subgroup_label varchar(100) NOT NULL DEFAULT '',
  sort_order tinyint(2) NOT NULL DEFAULT 0,
  room varchar(50) NOT NULL DEFAULT '',
  room_id bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  KEY class_day_period (class_id,day_of_week,period_id),
  KEY room_id (room_id)
) $c;");

        // Safety net: ensure `room_id` column exists on older installs
        $has_room_id = $wpdb->get_var("SHOW COLUMNS FROM {$p}ss_class_schedule LIKE 'room_id'");
        if (!$has_room_id) {
            $wpdb->query("ALTER TABLE {$p}ss_class_schedule ADD COLUMN room_id BIGINT(20) NOT NULL DEFAULT 0 AFTER room");
            $wpdb->query("ALTER TABLE {$p}ss_class_schedule ADD KEY room_id (room_id)");
        }

        // Backfill: convert legacy free-text rooms into ss_rooms records and link by room_id
        $legacy_rooms = $wpdb->get_col(
            "SELECT DISTINCT room FROM {$p}ss_class_schedule WHERE room <> '' AND room_id = 0"
        );
        if ($legacy_rooms) {
            foreach ($legacy_rooms as $rname) {
                $rid = self::find_or_create_room($rname);
                if ($rid) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$p}ss_class_schedule SET room_id=%d WHERE room=%s AND room_id=0",
                        $rid, $rname
                    ));
                }
            }
        }

        dbDelta("CREATE TABLE {$p}ss_user_classes (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  user_id bigint(20) NOT NULL DEFAULT 0,
  class_id bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY user_class (user_id,class_id)
) $c;");

        /* Stores which subgroup a user picked for a given class+day+period cell */
        dbDelta("CREATE TABLE {$p}ss_user_subgroup_prefs (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  user_id bigint(20) NOT NULL DEFAULT 0,
  class_id bigint(20) NOT NULL DEFAULT 0,
  day_of_week tinyint(1) NOT NULL DEFAULT 1,
  period_id bigint(20) NOT NULL DEFAULT 0,
  schedule_id bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY user_cell (user_id,class_id,day_of_week,period_id)
) $c;");

        dbDelta("CREATE TABLE {$p}ss_personal_items (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  user_id bigint(20) NOT NULL DEFAULT 0,
  day_of_week tinyint(1) NOT NULL DEFAULT 1,
  start_time varchar(5) NOT NULL DEFAULT '',
  end_time varchar(5) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL DEFAULT '',
  notes text NOT NULL,
  color varchar(7) NOT NULL DEFAULT '#4F8952',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY user_id (user_id)
) $c;");

        /* Per-period changes (e.g. "kabinetas pakeistas", "pamoka nevyks") */
        dbDelta("CREATE TABLE {$p}ss_period_changes (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  change_date date NOT NULL,
  period_id bigint(20) NOT NULL DEFAULT 0,
  class_id bigint(20) NOT NULL DEFAULT 0,
  subgroup_label varchar(100) NOT NULL DEFAULT '',
  comment text NOT NULL,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY date_period (change_date,period_id)
) $c;");

        /* Absent teachers list per day.
           from_period: 0 = visą dieną; N>0 = nuo N-osios pamokos (imtinai). */
        dbDelta("CREATE TABLE {$p}ss_absent_teachers (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  absent_date date NOT NULL,
  teacher_id bigint(20) NOT NULL DEFAULT 0,
  from_period tinyint(2) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY date_teacher (absent_date,teacher_id)
) $c;");
        // Safety net for older installs where dbDelta might miss the new column
        $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$p}ss_absent_teachers LIKE 'from_period'");
        if (!$col_exists) {
            $wpdb->query("ALTER TABLE {$p}ss_absent_teachers ADD COLUMN from_period TINYINT(2) NOT NULL DEFAULT 0 AFTER teacher_id");
        }

        /* Mokinių registracijos (su el. pašto patvirtinimu + admin patvirtinimu) */
        dbDelta("CREATE TABLE {$p}ss_registrations (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  email varchar(190) NOT NULL DEFAULT '',
  code varchar(10) NOT NULL DEFAULT '',
  code_expires_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  code_attempts tinyint(1) NOT NULL DEFAULT 0,
  verified tinyint(1) NOT NULL DEFAULT 0,
  reg_role varchar(20) NOT NULL DEFAULT 'student',
  first_name varchar(100) NOT NULL DEFAULT '',
  last_name varchar(100) NOT NULL DEFAULT '',
  class_level varchar(8) NOT NULL DEFAULT '',
  class_letter varchar(8) NOT NULL DEFAULT '',
  class_label varchar(32) NOT NULL DEFAULT '',
  matched_teacher_id bigint(20) NOT NULL DEFAULT 0,
  password_hash varchar(255) NOT NULL DEFAULT '',
  status varchar(20) NOT NULL DEFAULT 'pending',
  assigned_class_id bigint(20) NOT NULL DEFAULT 0,
  user_id bigint(20) NOT NULL DEFAULT 0,
  reject_reason varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  approved_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY email (email),
  KEY status (status)
) $c;");

        // Safety net for older installs: ensure new columns exist
        $reg_cols = $wpdb->get_col("SHOW COLUMNS FROM {$p}ss_registrations", 0);
        if (!in_array('reg_role', $reg_cols, true)) {
            $wpdb->query("ALTER TABLE {$p}ss_registrations ADD COLUMN reg_role VARCHAR(20) NOT NULL DEFAULT 'student' AFTER verified");
        }
        if (!in_array('matched_teacher_id', $reg_cols, true)) {
            $wpdb->query("ALTER TABLE {$p}ss_registrations ADD COLUMN matched_teacher_id BIGINT(20) NOT NULL DEFAULT 0 AFTER class_label");
        }

        /* Apklausos / Viktorinos */
        dbDelta("CREATE TABLE {$p}ss_surveys (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  title varchar(255) NOT NULL DEFAULT '',
  description text NOT NULL,
  type varchar(20) NOT NULL DEFAULT 'survey',
  status varchar(20) NOT NULL DEFAULT 'draft',
  show_results_admin tinyint(1) NOT NULL DEFAULT 1,
  show_results_users tinyint(1) NOT NULL DEFAULT 0,
  show_answers_users tinyint(1) NOT NULL DEFAULT 0,
  results_show_from datetime NULL DEFAULT NULL,
  results_close_at  datetime NULL DEFAULT NULL,
  open_from datetime NULL DEFAULT NULL,
  close_at datetime NULL DEFAULT NULL,
  audience varchar(1024) NOT NULL DEFAULT 'all',
  image_id bigint(20) NOT NULL DEFAULT 0,
  questions longtext NOT NULL,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY status (status),
  KEY type (type)
) $c;");

        dbDelta("CREATE TABLE {$p}ss_survey_responses (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  survey_id bigint(20) NOT NULL DEFAULT 0,
  user_id bigint(20) NOT NULL DEFAULT 0,
  answers longtext NOT NULL,
  score int(11) NOT NULL DEFAULT 0,
  max_score int(11) NOT NULL DEFAULT 0,
  text_scores longtext NOT NULL DEFAULT '',
  submitted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  UNIQUE KEY survey_user (survey_id,user_id),
  KEY survey_id (survey_id)
) $c;");
        // Migrate: widen audience column if it still has varchar(20)
        $wpdb->query("ALTER TABLE {$p}ss_surveys MODIFY COLUMN audience varchar(1024) NOT NULL DEFAULT 'all'");
        // Migrate: add text_scores column if missing
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$p}ss_survey_responses");
        if (!in_array('text_scores', $cols, true)) {
            $wpdb->query("ALTER TABLE {$p}ss_survey_responses ADD COLUMN text_scores longtext NOT NULL DEFAULT '' AFTER max_score");
        }
        // Migrate: add show_answers_users column if missing (klausimų/atsakymų peržiūra).
        $scols = $wpdb->get_col("SHOW COLUMNS FROM {$p}ss_surveys");
        if (!in_array('show_answers_users', $scols, true)) {
            $wpdb->query("ALTER TABLE {$p}ss_surveys ADD COLUMN show_answers_users tinyint(1) NOT NULL DEFAULT 0 AFTER show_results_users");
            // Esamos apklausos, rodžiusios rezultatus, išlaiko pilną peržiūrą.
            $wpdb->query("UPDATE {$p}ss_surveys SET show_answers_users=1 WHERE show_results_users=1");
        }

        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ss_periods");
        if ($count === 0) {
            $def = array(
                array(1,'08:00','08:45'), array(2,'08:55','09:40'),
                array(3,'09:50','10:35'), array(4,'10:50','11:35'),
                array(5,'12:05','12:50'), array(6,'13:00','13:45'),
                array(7,'13:55','14:40'), array(8,'14:45','15:30'),
            );
            foreach ($def as $r) {
                $wpdb->insert("{$p}ss_periods", array(
                    'period_number' => $r[0], 'start_time' => $r[1], 'end_time' => $r[2]
                ));
            }
        }

        self::create_pages();
        update_option('ss_db_version', SS_VERSION);
        update_option('ss_version',    SS_VERSION);
        update_option('ss_school_name', sanitize_text_field(wp_unslash(get_option('ss_school_name', SS_SCHOOL_NAME))));
        SS_Roles::add_roles();
    }

    public static function create_pages() {
        $map = array(
            'ss_login_page_id'     => array('Prisijungimas',       '[ss_login]',     'azuolynas-prisijungimas'),
            'ss_dashboard_page_id' => array('Mano tvarkaraštis',   '[ss_dashboard]', 'azuolynas-tvarkarastis'),
            'ss_changes_page_id'   => array('Pakeitimai',          '[ss_changes]',   'azuolynas-pakeitimai'),
            'ss_imp_page_id'       => array('Individualus planas', '[ss_imp_form]',  'azuolynas-individualus-planas'),
        );
        foreach ($map as $opt => $page) {
            if (!get_option($opt)) {
                $id = wp_insert_post(array('post_title' => $page[0], 'post_content' => $page[1],
                    'post_status' => 'publish', 'post_type' => 'page', 'post_name' => $page[2]));
                if (!is_wp_error($id)) update_option($opt, $id);
            }
        }
    }

    /* ── TEACHERS ─────────────────────────── */
    public static function get_teachers() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ss_teachers ORDER BY name ASC");
    }
    public static function get_teacher($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_teachers WHERE id=%d", (int)$id));
    }
    public static function find_or_create_teacher($name, $short = '') {
        global $wpdb;
        $name  = trim($name);
        $short = trim($short);
        $row   = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_teachers WHERE name=%s", $name));
        if ($row) return $row->id;
        $wpdb->insert("{$wpdb->prefix}ss_teachers", array(
            'name' => $name, 'short' => $short ?: self::make_short($name),
            'color' => '#4F8952', 'created_at' => current_time('mysql')
        ));
        return $wpdb->insert_id;
    }
    private static function make_short($name) {
        $parts = explode(' ', $name);
        $s = '';
        foreach ($parts as $p) $s .= mb_strtoupper(mb_substr($p,0,1));
        return mb_substr($s,0,4);
    }
    public static function save_teacher($d) {
        global $wpdb; $t = "{$wpdb->prefix}ss_teachers";
        $color = sanitize_hex_color($d['color'] ?? '') ?: '#4F8952';
        $f = array('name' => sanitize_text_field($d['name'] ?? ''),
                   'short' => sanitize_text_field($d['short'] ?? ''),
                   'email' => sanitize_email($d['email'] ?? ''),
                   'color' => $color);
        $id = (int)($d['id'] ?? 0);
        if ($id) { $wpdb->update($t, $f, array('id'=>$id)); return $id; }
        $f['created_at'] = current_time('mysql');
        $wpdb->insert($t, $f); return $wpdb->insert_id;
    }
    public static function delete_teacher($id) {
        global $wpdb; $id = (int)$id;
        $wpdb->delete("{$wpdb->prefix}ss_teachers", array('id'=>$id));
        $wpdb->update("{$wpdb->prefix}ss_lessons", array('teacher_id'=>0), array('teacher_id'=>$id));
    }

    /* ── TEACHER ↔ WP ACCOUNT LINK ─────────── */
    public static function get_teacher_by_user_id($user_id) {
        global $wpdb;
        $user_id = (int)$user_id;
        if (!$user_id) return null;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_teachers WHERE user_id=%d", $user_id));
    }
    /**
     * Persistently link a teacher record to a WP user account.
     * Clears the user_id from any other teacher first (one account = one teacher),
     * and copies the WP user's email into the teacher record if it has none.
     */
    public static function link_teacher_to_user($teacher_id, $user_id) {
        global $wpdb; $t = "{$wpdb->prefix}ss_teachers";
        $teacher_id = (int)$teacher_id; $user_id = (int)$user_id;
        if (!$teacher_id || !$user_id) return false;
        // Ensure the account isn't linked to a different teacher
        $wpdb->update($t, array('user_id' => 0), array('user_id' => $user_id));
        $fields = array('user_id' => $user_id);
        $teacher = self::get_teacher($teacher_id);
        $wp_user = get_user_by('id', $user_id);
        if ($teacher && empty($teacher->email) && $wp_user && $wp_user->user_email) {
            $fields['email'] = $wp_user->user_email;
        }
        $wpdb->update($t, $fields, array('id' => $teacher_id));
        return true;
    }
    public static function unlink_teacher_user($teacher_id) {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}ss_teachers", array('user_id' => 0), array('id' => (int)$teacher_id));
        return true;
    }

    /* ── LESSONS ──────────────────────────── */
    public static function get_lessons() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT l.*, t.name AS teacher_name, t.short AS teacher_short
             FROM {$wpdb->prefix}ss_lessons l
             LEFT JOIN {$wpdb->prefix}ss_teachers t ON l.teacher_id=t.id
             ORDER BY l.subject ASC");
    }
    public static function find_or_create_lesson($subject, $teacher_id = 0) {
        global $wpdb;
        $subject = trim($subject);
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_lessons WHERE subject=%s AND teacher_id=%d",
            $subject, (int)$teacher_id));
        if ($row) return $row->id;
        $wpdb->insert("{$wpdb->prefix}ss_lessons", array(
            'subject' => $subject, 'teacher_id' => (int)$teacher_id,
            'color' => '#6FA672', 'created_at' => current_time('mysql')
        ));
        return $wpdb->insert_id;
    }
    public static function save_lesson($d) {
        global $wpdb; $t = "{$wpdb->prefix}ss_lessons";
        $color = sanitize_hex_color($d['color'] ?? '') ?: '#6FA672';
        $f = array('subject' => sanitize_text_field($d['subject'] ?? ''),
                   'teacher_id' => (int)($d['teacher_id'] ?? 0), 'color' => $color);
        $id = (int)($d['id'] ?? 0);
        if ($id) { $wpdb->update($t, $f, array('id'=>$id)); return $id; }
        $f['created_at'] = current_time('mysql');
        $wpdb->insert($t, $f); return $wpdb->insert_id;
    }
    public static function delete_lesson($id) {
        global $wpdb; $id = (int)$id;
        $wpdb->delete("{$wpdb->prefix}ss_lessons", array('id'=>$id));
        $wpdb->delete("{$wpdb->prefix}ss_class_schedule", array('lesson_id'=>$id));
    }

    /* ── PERIODS ──────────────────────────── */
    public static function get_periods() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ss_periods ORDER BY period_number ASC");
    }
    public static function get_period_by_number($n) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_periods WHERE period_number=%d", (int)$n));
    }
    public static function save_periods($periods) {
        global $wpdb; $t = "{$wpdb->prefix}ss_periods";
        $wpdb->query("TRUNCATE TABLE $t");
        foreach ($periods as $i => $p) {
            if (empty($p['start_time']) || empty($p['end_time'])) continue;
            $wpdb->insert($t, array('period_number'=>$i+1,
                'start_time' => sanitize_text_field($p['start_time']),
                'end_time'   => sanitize_text_field($p['end_time'])));
        }
    }

    /* ── CLASSES ──────────────────────────── */
    public static function get_classes() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ss_classes ORDER BY name ASC");
    }
    public static function get_class($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_classes WHERE id=%d", (int)$id));
    }
    public static function get_all_classes() {
        return self::get_classes();
    }
    public static function get_class_schedule($class_id) {
        return array(
            'grid'    => self::get_class_schedule_grid($class_id),
            'periods' => self::get_periods()
        );
    }
    public static function find_or_create_class($name) {
        global $wpdb; $name = trim($name);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_classes WHERE name=%s", $name));
        if ($row) return $row->id;
        // Generate a unique code to satisfy UNIQUE KEY constraint on `code` column
        $wpdb->insert("{$wpdb->prefix}ss_classes", array(
            'name'       => $name,
            'color'      => '#224768',
            'code'       => self::generate_unique_class_code(),
            'created_at' => current_time('mysql'),
        ));
        return $wpdb->insert_id;
    }
    public static function save_class($d) {
        global $wpdb; $t = "{$wpdb->prefix}ss_classes";
        $color = sanitize_hex_color($d['color'] ?? '') ?: '#224768';
        $f = array(
            'name'   => sanitize_text_field($d['name'] ?? ''),
            'color'  => $color,
            'is_imp' => isset($d['is_imp']) ? (int)$d['is_imp'] : 0,
        );
        $id = (int)($d['id'] ?? 0);
        if ($id) {
            $wpdb->update($t, $f, array('id' => $id));
            self::ensure_class_code($id);
            return $id;
        }
        $f['created_at'] = current_time('mysql');
        $f['code']       = self::generate_unique_class_code();
        $wpdb->insert($t, $f);
        return $wpdb->insert_id;
    }

    /* ── CLASS CODES (registration bypass) ─────────── */
    public static function generate_unique_class_code() {
        global $wpdb;
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I
        for ($try = 0; $try < 25; $try++) {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[ random_int(0, strlen($alphabet) - 1) ];
            }
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ss_classes WHERE code=%s", $code
            ));
            if (!$exists) return $code;
        }
        // Fallback: 12-char random
        return strtoupper(substr(md5(uniqid('', true)), 0, 12));
    }

    public static function ensure_class_code($class_id) {
        global $wpdb; $class_id = (int)$class_id;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, code FROM {$wpdb->prefix}ss_classes WHERE id=%d", $class_id
        ));
        if (!$row) return '';
        if (!empty($row->code)) return $row->code;
        $code = self::generate_unique_class_code();
        $wpdb->update("{$wpdb->prefix}ss_classes", array('code' => $code), array('id' => $class_id));
        return $code;
    }

    public static function regenerate_class_code($class_id) {
        global $wpdb; $class_id = (int)$class_id;
        $code = self::generate_unique_class_code();
        $wpdb->update("{$wpdb->prefix}ss_classes", array('code' => $code), array('id' => $class_id));
        return $code;
    }

    public static function get_class_by_code($code) {
        global $wpdb;
        $code = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$code));
        if (strlen($code) < 6) return null;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_classes WHERE code=%s", $code
        ));
    }

    public static function get_class_codes_list() {
        global $wpdb; $p = $wpdb->prefix;
        return $wpdb->get_results(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM {$p}ss_user_classes uc WHERE uc.class_id=c.id) AS student_count,
                    (SELECT COUNT(*) FROM {$p}ss_class_email_links l WHERE l.class_id=c.id) AS link_count
             FROM {$p}ss_classes c
             ORDER BY c.is_imp ASC, c.name ASC"
        );
    }

    /* ── CLASS ↔ EMAIL LINKS ───────────────────────── */
    public static function get_class_links() {
        global $wpdb; $p = $wpdb->prefix;
        return $wpdb->get_results(
            "SELECT l.*, c.name AS class_name, c.code AS class_code
             FROM {$p}ss_class_email_links l
             LEFT JOIN {$p}ss_classes c ON c.id = l.class_id
             ORDER BY l.created_at DESC, l.id DESC"
        );
    }

    public static function get_all_class_links() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT l.*, c.name AS class_name, c.code AS class_code
             FROM {$wpdb->prefix}ss_class_email_links l
             LEFT JOIN {$wpdb->prefix}ss_classes c ON c.id = l.class_id
             ORDER BY l.created_at DESC"
        );
    }

    public static function get_class_link_by_email($email) {
        global $wpdb;
        $email = strtolower(sanitize_email($email));
        if (!$email) return null;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, c.name AS class_name, c.code AS class_code
             FROM {$wpdb->prefix}ss_class_email_links l
             LEFT JOIN {$wpdb->prefix}ss_classes c ON c.id=l.class_id
             WHERE l.email=%s",
            $email
        ));
    }

    public static function upsert_class_link($email, $class_id) {
        global $wpdb;
        $email = strtolower(sanitize_email($email));
        $class_id = (int)$class_id;
        if (!$email || !$class_id) return 0;
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ss_class_email_links WHERE email=%s", $email
        ));
        if ($existing) {
            $wpdb->update("{$wpdb->prefix}ss_class_email_links",
                array('class_id' => $class_id),
                array('id' => (int)$existing->id)
            );
            return (int)$existing->id;
        }
        $wpdb->insert("{$wpdb->prefix}ss_class_email_links", array(
            'email'      => $email,
            'class_id'   => $class_id,
            'created_at' => current_time('mysql'),
        ));
        return (int)$wpdb->insert_id;
    }

    public static function delete_class_link($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}ss_class_email_links", array('id' => (int)$id));
    }

    public static function bulk_upsert_class_links($rows) {
        $added = 0; $updated = 0; $skipped = 0;
        // Build class lookup by name and code
        $classes = self::get_classes();
        $by_name = array(); $by_code = array();
        foreach ($classes as $c) {
            $by_name[ mb_strtolower(trim($c->name)) ] = (int)$c->id;
            if (!empty($c->code)) $by_code[ strtoupper($c->code) ] = (int)$c->id;
        }
        global $wpdb;
        foreach ($rows as $r) {
            $email = strtolower(sanitize_email($r['email'] ?? ''));
            $hint  = trim((string)($r['class'] ?? ''));
            if (!$email || !$hint) { $skipped++; continue; }
            $cid = $by_code[ strtoupper($hint) ] ?? $by_name[ mb_strtolower($hint) ] ?? 0;
            if (!$cid) { $skipped++; continue; }
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ss_class_email_links WHERE email=%s", $email
            ));
            self::upsert_class_link($email, $cid);
            if ($exists) $updated++; else $added++;
        }
        return array('added' => $added, 'updated' => $updated, 'skipped' => $skipped);
    }
    public static function delete_class($id) {
        global $wpdb; $id = (int)$id;
        $wpdb->delete("{$wpdb->prefix}ss_classes",            array('id'       => $id));
        $wpdb->delete("{$wpdb->prefix}ss_class_schedule",     array('class_id' => $id));
        $wpdb->delete("{$wpdb->prefix}ss_user_classes",       array('class_id' => $id));
        $wpdb->delete("{$wpdb->prefix}ss_class_email_links",  array('class_id' => $id));
    }

    /* ── CLASS SCHEDULE (with subgroups) ──── */
    public static function get_class_schedule_raw($class_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT cs.*,
                    COALESCE(l.subject,'') AS subject,
                    COALESCE(l.color,'#888888') AS lesson_color,
                    t.name AS teacher_name, t.short AS teacher_short,
                    p.period_number, p.start_time, p.end_time,
                    IF(cs.lesson_id=0,1,0) AS is_free
             FROM {$wpdb->prefix}ss_class_schedule cs
             LEFT JOIN {$wpdb->prefix}ss_lessons l  ON cs.lesson_id=l.id AND cs.lesson_id>0
             JOIN {$wpdb->prefix}ss_periods p  ON cs.period_id=p.id
             LEFT JOIN {$wpdb->prefix}ss_teachers t ON l.teacher_id=t.id
             WHERE cs.class_id=%d
             ORDER BY p.period_number ASC, cs.day_of_week ASC, cs.sort_order ASC",
            (int)$class_id));
    }

    /* Returns array [day][period_id] = [ array of schedule rows (subgroups) ] */
    public static function get_class_schedule_grid($class_id) {
        $rows = self::get_class_schedule_raw($class_id);
        $grid = array();
        foreach ($rows as $r) {
            $grid[$r->day_of_week][$r->period_id][] = $r;
        }
        return $grid;
    }

    public static function save_class_schedule_cells($class_id, $cells) {
        global $wpdb; $class_id = (int)$class_id;
        $wpdb->delete("{$wpdb->prefix}ss_class_schedule", array('class_id'=>$class_id));
        foreach ((array)$cells as $cell) {
            $lid = (int)($cell['lesson_id'] ?? -1);
            $is_free = !empty($cell['is_free']) ? 1 : 0;
            // Allow lesson_id=0 (Nera pamokos) or any positive lesson id
            if ($lid < 0 && !$is_free) continue;
            // Resolve room: prefer explicit room_id; keep `room` text in sync for display.
            $room_id   = $is_free ? 0 : (int)($cell['room_id'] ?? 0);
            $room_name = '';
            if (!$is_free) {
                if ($room_id > 0) {
                    $room = self::get_room($room_id);
                    $room_name = $room ? $room->name : '';
                } else {
                    // Backward compat: a free-text room name may still arrive
                    $room_name = sanitize_text_field($cell['room'] ?? '');
                    if ($room_name !== '') {
                        $room_id = self::find_or_create_room($room_name);
                    }
                }
            }
            $wpdb->insert("{$wpdb->prefix}ss_class_schedule", array(
                'class_id'       => $class_id,
                'day_of_week'    => (int)$cell['day'],
                'period_id'      => (int)$cell['period_id'],
                'lesson_id'      => $is_free ? 0 : $lid,
                'subgroup_label' => sanitize_text_field($cell['subgroup_label'] ?? ''),
                'sort_order'     => (int)($cell['sort_order'] ?? 0),
                'room'           => $room_name,
                'room_id'        => $room_id,
            ));
        }
    }

    /* ── USER ↔ CLASS ─────────────────────── */
    public static function get_user_classes($user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT uc.*, c.name, c.color
             FROM {$wpdb->prefix}ss_user_classes uc
             JOIN {$wpdb->prefix}ss_classes c ON uc.class_id=c.id
             WHERE uc.user_id=%d", (int)$user_id));
    }
    public static function set_user_classes($user_id, $class_ids) {
        global $wpdb; $user_id = (int)$user_id;
        $wpdb->delete("{$wpdb->prefix}ss_user_classes", array('user_id'=>$user_id));
        foreach ((array)$class_ids as $cid) {
            $wpdb->insert("{$wpdb->prefix}ss_user_classes",
                array('user_id'=>$user_id, 'class_id'=>(int)$cid));
        }
    }

    /* ── SUBGROUP PREFS ───────�������───────────── */
    public static function get_user_subgroup_prefs($user_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_user_subgroup_prefs WHERE user_id=%d", (int)$user_id));
        $map = array();
        foreach ($rows as $r) {
            $map["{$r->class_id}_{$r->day_of_week}_{$r->period_id}"] = (int)$r->schedule_id;
        }
        return $map;
    }
    public static function save_user_subgroup_pref($user_id, $class_id, $day, $period_id, $schedule_id) {
        global $wpdb; $t = "{$wpdb->prefix}ss_user_subgroup_prefs";
        $key = array('user_id'=>(int)$user_id,'class_id'=>(int)$class_id,
                     'day_of_week'=>(int)$day,'period_id'=>(int)$period_id);
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $t WHERE user_id=%d AND class_id=%d AND day_of_week=%d AND period_id=%d",
            (int)$user_id,(int)$class_id,(int)$day,(int)$period_id));
        if ($existing) {
            $wpdb->update($t, array('schedule_id'=>(int)$schedule_id), array('id'=>$existing->id));
        } else {
            $wpdb->insert($t, array_merge($key, array('schedule_id'=>(int)$schedule_id)));
        }
    }

    /* ── USER FULL SCHEDULE ───────────────── */
    public static function get_user_full_schedule($user_id) {
        global $wpdb; $user_id = (int)$user_id;
        $class_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT class_id FROM {$wpdb->prefix}ss_user_classes WHERE user_id=%d", $user_id));
        $periods = self::get_periods();
        if (empty($class_ids)) return array('grid'=>array(),'periods'=>$periods);

        $ph   = implode(',', array_fill(0, count($class_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.*,
                    COALESCE(l.subject,'') AS subject,
                    COALESCE(l.color,'#888888') AS lesson_color,
                    l.teacher_id AS lesson_teacher_id,
                    t.name AS teacher_name, t.short AS teacher_short,
                    p.period_number, p.start_time, p.end_time,
                    IF(cs.lesson_id=0,1,0) AS is_free
             FROM {$wpdb->prefix}ss_class_schedule cs
             LEFT JOIN {$wpdb->prefix}ss_lessons l ON cs.lesson_id=l.id AND cs.lesson_id>0
             JOIN {$wpdb->prefix}ss_periods p ON cs.period_id=p.id
             LEFT JOIN {$wpdb->prefix}ss_teachers t ON l.teacher_id=t.id
             WHERE cs.class_id IN ($ph)
             ORDER BY p.period_number ASC, cs.day_of_week ASC, cs.sort_order ASC",
            $class_ids));

        /* grid[period_id][day_of_week] = array of rows (≥1 if subgroups) */
        $grid = array();
        foreach ($rows as $r) {
            $grid[$r->period_id][$r->day_of_week][] = $r;
        }
        return array('grid'=>$grid,'periods'=>$periods);
    }

    /* ── ACTIVE WEEK (16:00 / Friday / weekend rollover) ─────
     * Returns the Monday of the "active" week, computed in
     * Lithuanian time (Europe/Vilnius, DST-aware):
     *   Mon–Thu              → current week's Monday
     *   Fri before 16:00     → current week's Monday
     *   Fri at/after 16:00   → next week's Monday
     *   Sat–Sun              → next week's Monday
     *
     * Note: this MUST stay in sync with SS_Time::active_day() so
     * the displayed day and the displayed week match.
     */
    public static function get_active_monday() {
        return SS_Time::active_monday();
    }

    /* Returns ['Y-m-d', 'Y-m-d', ...] for Mon..Fri of given Monday. */
    public static function get_active_weekdays($monday_date = null) {
        $monday = $monday_date ?: self::get_active_monday();
        $tz     = SS_Time::tz();
        $out = array();
        for ($i = 0; $i < 5; $i++) {
            $dt = new DateTime($monday, $tz);
            $dt->modify('+' . $i . ' days');
            $out[$i + 1] = $dt->format('Y-m-d');
        }
        return $out; // [1=>Mon, 2=>Tue, ..., 5=>Fri]
    }

    /* Returns array(day_of_week => array(teacher_id => from_period)) for a week.
       from_period: 0 = visą dieną; N>0 = nuo N-osios pamokos. */
    public static function get_week_absent_map($monday_date = null, $published_only = false) {
        global $wpdb;
        $monday  = $monday_date ?: self::get_active_monday();
        $tz      = SS_Time::tz();
        $end_dt  = new DateTime($monday, $tz);
        $end_dt->modify('+4 days');
        $end     = $end_dt->format('Y-m-d');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT absent_date, teacher_id, from_period
             FROM {$wpdb->prefix}ss_absent_teachers
             WHERE absent_date BETWEEN %s AND %s",
            $monday, $end
        ));
        $map = array(1=>array(), 2=>array(), 3=>array(), 4=>array(), 5=>array());
        foreach ($rows as $r) {
            if ($published_only && !self::is_changes_published($r->absent_date)) continue;
            $dt  = new DateTime($r->absent_date, $tz);
            $dow = (int)$dt->format('N');
            if ($dow >= 1 && $dow <= 5) {
                $map[$dow][(int)$r->teacher_id] = (int)$r->from_period;
            }
        }
        return $map;
    }

    /* ── PAKEITIMŲ PASKELBIMAS (publish workflow) ─────────────
     * Pamokų pakeitimai, neatvykimai ir komentaras mokiniams/mokytojams
     * matomi TIK kai diena paskelbta. Paskelbtos dienos saugomos viename
     * option masyve: ['Y-m-d' => 1]. */
    public static function get_published_change_dates(): array {
        $v = get_option('ss_changes_published', array());
        return is_array($v) ? $v : array();
    }
    public static function is_changes_published($date): bool {
        // Iki vienkartinės migracijos elgiamės kaip anksčiau (rodome viską),
        // kad atnaujinus įskiepį nepradingtų jau esami pakeitimai.
        if (!get_option('ss_changes_publish_migrated')) return true;
        $v = self::get_published_change_dates();
        return !empty($v[$date]);
    }
    public static function set_changes_published($date, $published): void {
        $v = self::get_published_change_dates();
        if ($published) { $v[$date] = 1; } else { unset($v[$date]); }
        update_option('ss_changes_published', $v, false);
    }
    /** Vienkartinė migracija: pažymi visas jau egzistuojančias pakeitimų dienas kaip paskelbtas. */
    public static function migrate_publish_existing_changes(): void {
        if (get_option('ss_changes_publish_migrated')) return;
        global $wpdb;
        $pub = self::get_published_change_dates();
        foreach ((array)$wpdb->get_col("SELECT DISTINCT change_date FROM {$wpdb->prefix}ss_period_changes") as $d) { if ($d) $pub[$d] = 1; }
        foreach ((array)$wpdb->get_col("SELECT DISTINCT absent_date FROM {$wpdb->prefix}ss_absent_teachers") as $d) { if ($d) $pub[$d] = 1; }
        foreach ((array)$wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'ss_changes_comment_%'") as $o) {
            $d = substr((string)$o, strlen('ss_changes_comment_'));
            if ($d) $pub[$d] = 1;
        }
        update_option('ss_changes_published', $pub, false);
        update_option('ss_changes_publish_migrated', 1, false);
    }

    /**
     * E1: keep cold + accumulating options OUT of the global autoload cache (loaded on
     * every WP request site-wide). Flips named cold options + all per-date change options
     * to autoload=no. Idempotent; safe to run on every upgrade.
     */
    public static function trim_option_autoload(): void {
        global $wpdb;
        $cold = array(
            'ss_google_client_secret', 'ss_google_client_id', 'ss_login_image_id',
            'ss_atspek_game_url', 'ss_atspek_game_description', 'ss_atspek_image_id',
            'ss_school_year', 'ss_admin_email', 'ss_changes_publish_migrated',
        );
        $dyn = (array) $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE autoload IN ('yes','on')
               AND (option_name LIKE 'ss_changes_comment_%' OR option_name LIKE 'ss_changes_edited_%')"
        );
        foreach (array_merge($cold, $dyn) as $opt) {
            if (function_exists('wp_set_option_autoload')) {           // WP 6.4+
                wp_set_option_autoload($opt, false);
            } else {                                                    // older WP fallback
                $val = get_option($opt, null);
                if ($val !== null) { delete_option($opt); add_option($opt, $val, '', 'no'); }
            }
        }
        wp_cache_delete('alloptions', 'options');
    }

    /* ── PERSONAL ITEMS ───────────────────── */
    public static function get_personal_items($user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_personal_items
             WHERE user_id=%d ORDER BY day_of_week ASC, start_time ASC", (int)$user_id));
    }
    public static function save_personal_item($d) {
        global $wpdb; $t = "{$wpdb->prefix}ss_personal_items";
        $color = sanitize_hex_color($d['color'] ?? '') ?: '#4F8952';
        $f = array('user_id'=>(int)($d['user_id']??0),'day_of_week'=>(int)($d['day_of_week']??1),
                   'start_time'=>sanitize_text_field($d['start_time']??''),
                   'end_time'  =>sanitize_text_field($d['end_time']??''),
                   'subject'   =>sanitize_text_field($d['subject']??''),
                   'notes'     =>sanitize_textarea_field($d['notes']??''),'color'=>$color);
        $id = (int)($d['id']??0);
        if ($id) { $wpdb->update($t,$f,array('id'=>$id)); return $id; }
        $f['created_at'] = current_time('mysql');
        $wpdb->insert($t,$f); return $wpdb->insert_id;
    }
    public static function delete_personal_item($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}ss_personal_items", array('id'=>(int)$id));
    }

    /* ── STATS ────────────────────────────── */
    public static function get_stats() {
        global $wpdb;
        return array(
            'classes'  => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ss_classes"),
            'teachers' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ss_teachers"),
            'lessons'  => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ss_lessons"),
            'students' => (int)$wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ss_user_classes"),
        );
    }

    /* ── MOKSLO METŲ PABAIGA: tvarkaraščių valymas ──────────────────
     * Skaičiuojam / trinam visus tvarkaraščių duomenis (mokytojai, kabinetai,
     * pamokos ir asmeniniai įrašai NEtrinami — pamokos vėl panaudojamos importe). */
    public static function count_schedule_data(): array {
        global $wpdb; $p = $wpdb->prefix;
        return array(
            'classes'        => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ss_classes"),
            'cells'          => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ss_class_schedule"),
            'links'          => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ss_user_classes"),
            'email_links'    => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ss_class_email_links"),
            'subgroup_prefs' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ss_user_subgroup_prefs"),
            'changes'        => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ss_period_changes"),
            'absences'       => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ss_absent_teachers"),
        );
    }

    /** Ištrina VISUS tvarkaraščius ir su jais susietus duomenis. Grąžina ištrintų kiekius. */
    public static function wipe_all_schedules(): array {
        global $wpdb; $p = $wpdb->prefix;
        $counts = self::count_schedule_data();
        $wpdb->query("DELETE FROM {$p}ss_class_schedule");
        $wpdb->query("DELETE FROM {$p}ss_user_classes");
        $wpdb->query("DELETE FROM {$p}ss_class_email_links");
        $wpdb->query("DELETE FROM {$p}ss_user_subgroup_prefs");
        $wpdb->query("DELETE FROM {$p}ss_period_changes");
        $wpdb->query("DELETE FROM {$p}ss_absent_teachers");
        $wpdb->query("DELETE FROM {$p}ss_classes");
        // Pakeitimų dienų komentarai (ss_changes_comment_YYYY-MM-DD), redagavimo žymos
        // ir paskelbimo būsena — kad neliktų „pakabintų" senų pakeitimų duomenų.
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ss_changes_comment_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ss_changes_edited_%'");
        delete_option('ss_changes_published');
        return $counts;
    }

    /* ── TEACHER FULL SCHEDULE ─────────────── */
    /**
     * Returns all schedule slots for a given teacher (by DB teacher id).
     * grid[period_id][day_of_week] = array of rows (may have multiple classes in same slot)
     */
    public static function get_teacher_full_schedule($teacher_id) {
        global $wpdb;
        $teacher_id = intval($teacher_id);

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.*, l.subject, l.color AS lesson_color,
                    t.name AS teacher_name, t.short AS teacher_short,
                    p.period_number, p.start_time, p.end_time,
                    c.name AS class_name, c.id AS class_id, c.is_imp AS is_imp,
                    cs.subgroup_label, cs.room,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}ss_user_classes uc WHERE uc.class_id = c.id) AS student_count
             FROM {$wpdb->prefix}ss_class_schedule cs
             JOIN {$wpdb->prefix}ss_lessons l  ON cs.lesson_id  = l.id
             JOIN {$wpdb->prefix}ss_periods p  ON cs.period_id  = p.id
             JOIN {$wpdb->prefix}ss_classes c  ON cs.class_id   = c.id
             JOIN {$wpdb->prefix}ss_teachers t ON l.teacher_id  = t.id
             WHERE l.teacher_id = %d
             ORDER BY p.period_number ASC, cs.day_of_week ASC",
            $teacher_id
        ));

        $grid = array();
        foreach ($rows as $r) {
            if (!isset($grid[$r->period_id]))                    $grid[$r->period_id] = array();
            if (!isset($grid[$r->period_id][$r->day_of_week]))   $grid[$r->period_id][$r->day_of_week] = array();
            $grid[$r->period_id][$r->day_of_week][] = $r;
        }
        return array('grid' => $grid, 'periods' => self::get_periods());
    }

    /* ── TEACHER PDF DATA (with student names per slot) ─────────────── */
    /**
     * Returns teacher's schedule with each slot enriched with the joined list
     * of student names (both IMP and regular classes) — used for PDF export.
     * grid[period_id][day_of_week] = array of:
     *   ['subject','class_name','subgroup_label','room','is_imp','color','students'=>'Name1, Name2']
     */
    public static function get_teacher_pdf_grid($teacher_id) {
        global $wpdb;
        $teacher_id = (int)$teacher_id;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.id AS sched_id, cs.day_of_week, cs.period_id, cs.subgroup_label, cs.room,
                    l.subject, l.color AS lesson_color,
                    c.id AS class_id, c.name AS class_name, c.is_imp
             FROM {$wpdb->prefix}ss_class_schedule cs
             JOIN {$wpdb->prefix}ss_lessons l ON cs.lesson_id = l.id
             JOIN {$wpdb->prefix}ss_classes c ON cs.class_id  = c.id
             WHERE l.teacher_id = %d
             ORDER BY cs.day_of_week ASC, cs.period_id ASC, c.name ASC",
            $teacher_id
        ));

        // Cache class -> students lookup (avoid per-row queries).
        $class_ids = array();
        foreach ($rows as $r) $class_ids[(int)$r->class_id] = true;
        $class_students = array(); // class_id => [ ['id'=>, 'name'=>], … ]
        $all_uids       = array();
        if (!empty($class_ids)) {
            $ids = array_keys($class_ids);
            $ph  = implode(',', array_fill(0, count($ids), '%d'));
            $list = $wpdb->get_results($wpdb->prepare(
                "SELECT uc.class_id, u.ID, u.display_name
                 FROM {$wpdb->prefix}ss_user_classes uc
                 JOIN {$wpdb->users} u ON u.ID = uc.user_id
                 WHERE uc.class_id IN ($ph)
                 ORDER BY u.display_name ASC",
                $ids
            ));
            foreach ($list as $r) {
                $cid = (int)$r->class_id;
                if (!isset($class_students[$cid])) $class_students[$cid] = array();
                $class_students[$cid][] = array('id' => (int)$r->ID, 'name' => $r->display_name);
                $all_uids[(int)$r->ID] = true;
            }
        }

        // IMP tvarkaraščio savininko TIKROJI klasė („IIIf") — tais pačiais principais
        // kaip get_teacher_slot_groups: user_meta (SS_Roles) + atsarginė ne-IMP klasė.
        $uids = array_map('intval', array_keys($all_uids));
        if ($uids) update_meta_cache('user', $uids);
        $fallback_class = array();
        if ($uids) {
            $in = implode(',', $uids);
            $crows = $wpdb->get_results(
                "SELECT uc.user_id, c.name
                 FROM {$wpdb->prefix}ss_user_classes uc
                 JOIN {$wpdb->prefix}ss_classes c ON uc.class_id = c.id
                 WHERE uc.user_id IN ($in) AND c.is_imp = 0
                 ORDER BY c.name ASC"
            );
            foreach ($crows as $cr) {
                if (!isset($fallback_class[(int)$cr->user_id])) $fallback_class[(int)$cr->user_id] = (string)$cr->name;
            }
        }
        $user_label = function(int $uid) use ($fallback_class): string {
            $label = '';
            if (class_exists('SS_Roles')) {
                $lvl   = SS_Roles::get_student_level($uid);
                $label = (string) ($lvl['label'] ?? '');
            }
            return $label !== '' ? $label : ($fallback_class[$uid] ?? '');
        };
        // class_id => savininko klasės etiketė (užpildoma pagrindiniame cikle žemiau,
        // nes reikia IMP tvarkaraščio pavadinimo vardų palyginimui)
        $class_label = array_fill_keys(array_keys($class_students), '');

        $grid = array();
        foreach ($rows as $r) {
            $cid      = (int)$r->class_id;
            $students = isset($class_students[$cid]) ? $class_students[$cid] : array();
            $is_imp   = (int)$r->is_imp === 1;

            // IMP: savininko klasė pagal geriausią vardo atitikmenį pavadinimui.
            if ($is_imp && ($class_label[$cid] ?? '') === '' && $students) {
                $best = null; $best_score = -1.0;
                foreach ($students as $s) {
                    $sc = self::name_similarity($r->class_name, $s['name']);
                    if ($sc > $best_score) { $best_score = $sc; $best = $s; }
                }
                if ($best) $class_label[$cid] = $user_label((int)$best['id']);
            }

            $entry = array(
                'subject'        => $r->subject,
                'color'          => $r->lesson_color ?: '#4F8952',
                'class_name'     => $r->class_name,
                'subgroup_label' => $r->subgroup_label,
                'room'           => $r->room,
                'is_imp'         => $is_imp,
                'class_label'    => $is_imp ? ($class_label[$cid] ?? '') : '',
                'student_names'  => implode(', ', array_column($students, 'name')),
                'student_count'  => count($students),
            );
            if (!isset($grid[$r->period_id]))                  $grid[$r->period_id] = array();
            if (!isset($grid[$r->period_id][$r->day_of_week])) $grid[$r->period_id][$r->day_of_week] = array();
            $grid[$r->period_id][$r->day_of_week][] = $entry;
        }
        return array('grid' => $grid, 'periods' => self::get_periods());
    }

    /* ── TEACHER LESSONS LIST ─────────────── */
    public static function get_teacher_lessons($teacher_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT cs.*, l.subject, l.color AS lesson_color,
                    p.period_number, p.start_time, p.end_time,
                    c.name AS class_name, cs.subgroup_label, cs.room,
                    cs.day_of_week
             FROM {$wpdb->prefix}ss_class_schedule cs
             JOIN {$wpdb->prefix}ss_lessons l ON cs.lesson_id = l.id
             JOIN {$wpdb->prefix}ss_periods p ON cs.period_id = p.id
             JOIN {$wpdb->prefix}ss_classes c ON cs.class_id  = c.id
             WHERE l.teacher_id = %d
             ORDER BY cs.day_of_week ASC, p.period_number ASC",
            intval($teacher_id)
        ));
    }

    /* ── IMP CHECK ─────────────────────────── */
    /**
     * Check if user is assigned to IMP (III-IV grades) class
     */
    public static function user_is_imp($user_id) {
        global $wpdb;
        $user_id = intval($user_id);
        $is_imp = $wpdb->get_var($wpdb->prepare(
            "SELECT c.is_imp FROM {$wpdb->prefix}ss_user_classes uc
             JOIN {$wpdb->prefix}ss_classes c ON uc.class_id = c.id
             WHERE uc.user_id = %d AND c.is_imp = 1
             LIMIT 1",
            $user_id
        ));
        return (bool)$is_imp;
    }

    /**
     * Get all teachers for teacher schedule list
     */
    public static function get_all_teachers_with_schedule() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT DISTINCT t.* FROM {$wpdb->prefix}ss_teachers t
             JOIN {$wpdb->prefix}ss_lessons l ON l.teacher_id = t.id
             JOIN {$wpdb->prefix}ss_class_schedule cs ON cs.lesson_id = l.id
             ORDER BY t.name ASC"
        );
    }

    /* ── ROOMS (KABINETAI) ─────────────────── */
    public static function get_rooms() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ss_rooms ORDER BY name ASC");
    }
    public static function get_room($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_rooms WHERE id=%d", (int)$id));
    }
    public static function find_or_create_room($name) {
        global $wpdb;
        $name = trim((string)$name);
        if ($name === '') return 0;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_rooms WHERE name=%s", $name));
        if ($row) return (int)$row->id;
        $wpdb->insert("{$wpdb->prefix}ss_rooms", array(
            'name'       => sanitize_text_field($name),
            'color'      => '#224768',
            'created_at' => current_time('mysql'),
        ));
        return (int)$wpdb->insert_id;
    }
    public static function save_room($d) {
        global $wpdb; $t = "{$wpdb->prefix}ss_rooms";
        $color = sanitize_hex_color($d['color'] ?? '') ?: '#224768';
        $f = array(
            'name'  => sanitize_text_field($d['name'] ?? ''),
            'color' => $color,
        );
        $id = (int)($d['id'] ?? 0);
        if ($id) {
            $wpdb->update($t, $f, array('id' => $id));
            // Keep denormalised room text in the schedule in sync with the renamed room.
            $wpdb->update("{$wpdb->prefix}ss_class_schedule", array('room' => $f['name']), array('room_id' => $id));
            return $id;
        }
        $f['created_at'] = current_time('mysql');
        $wpdb->insert($t, $f);
        return $wpdb->insert_id;
    }
    public static function delete_room($id) {
        global $wpdb; $id = (int)$id;
        $wpdb->delete("{$wpdb->prefix}ss_rooms", array('id' => $id));
        // Unlink schedule rows (keep the lessons, just drop the room assignment).
        $wpdb->update("{$wpdb->prefix}ss_class_schedule", array('room_id' => 0, 'room' => ''), array('room_id' => $id));
    }

    /**
     * Pamokų skaičius kiekvienam kabinetui (room_id => count) — kabinetų
     * sąrašui admin'e, kad matytųsi, kurie kabinetai realiai naudojami.
     * @return array<int,int>
     */
    public static function get_room_usage_counts() {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT room_id, COUNT(*) AS c
             FROM {$wpdb->prefix}ss_class_schedule
             WHERE room_id > 0 AND lesson_id > 0
             GROUP BY room_id"
        );
        $map = array();
        foreach ($rows as $r) $map[(int)$r->room_id] = (int)$r->c;
        return $map;
    }

    /**
     * Rooms that are actually used in at least one schedule slot — for the
     * room schedule picker (mirrors get_all_teachers_with_schedule()).
     */
    public static function get_all_rooms_with_schedule() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT DISTINCT r.* FROM {$wpdb->prefix}ss_rooms r
             JOIN {$wpdb->prefix}ss_class_schedule cs ON cs.room_id = r.id
             WHERE cs.lesson_id > 0
             ORDER BY r.name ASC"
        );
    }

    /**
     * Full weekly schedule for a room (mirrors get_teacher_full_schedule()).
     * grid[period_id][day_of_week] = array of rows (multiple classes may share a room slot).
     */
    public static function get_room_full_schedule($room_id) {
        global $wpdb;
        $room_id = (int)$room_id;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.*, l.subject, l.color AS lesson_color,
                    t.name AS teacher_name, t.short AS teacher_short,
                    p.period_number, p.start_time, p.end_time,
                    c.name AS class_name, c.id AS class_id, c.is_imp AS is_imp,
                    cs.subgroup_label, cs.room
             FROM {$wpdb->prefix}ss_class_schedule cs
             JOIN {$wpdb->prefix}ss_lessons l  ON cs.lesson_id  = l.id
             JOIN {$wpdb->prefix}ss_periods p  ON cs.period_id  = p.id
             JOIN {$wpdb->prefix}ss_classes c  ON cs.class_id   = c.id
             LEFT JOIN {$wpdb->prefix}ss_teachers t ON l.teacher_id = t.id
             WHERE cs.room_id = %d AND cs.lesson_id > 0
             ORDER BY p.period_number ASC, cs.day_of_week ASC, c.name ASC",
            $room_id
        ));
        $grid = array();
        foreach ($rows as $r) {
            if (!isset($grid[$r->period_id]))                  $grid[$r->period_id] = array();
            if (!isset($grid[$r->period_id][$r->day_of_week])) $grid[$r->period_id][$r->day_of_week] = array();
            $grid[$r->period_id][$r->day_of_week][] = $r;
        }
        return array('grid' => $grid, 'periods' => self::get_periods());
    }

    /* ── CLASS STUDENTS LIST ──────────────── */
    /**
     * Get list of students assigned to a given class.
     */
    public static function get_class_students($class_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT u.ID, u.display_name, u.user_email
             FROM {$wpdb->prefix}ss_user_classes uc
             JOIN {$wpdb->users} u ON u.ID = uc.user_id
             WHERE uc.class_id = %d
             ORDER BY u.display_name ASC",
            (int)$class_id
        ));
        return $rows ?: array();
    }

    /**
     * For a given teacher + day + period, build a structured list of
     * groups with attached student names. Used by teacher dashboard.
     */
    public static function get_teacher_slot_groups($teacher_id, $day_of_week, $period_id) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT cs.id AS sched_id, cs.subgroup_label, cs.room,
                    l.subject,
                    c.id AS class_id, c.name AS class_name, c.is_imp
             FROM {$wpdb->prefix}ss_class_schedule cs
             JOIN {$wpdb->prefix}ss_lessons l ON cs.lesson_id = l.id
             JOIN {$wpdb->prefix}ss_classes c ON cs.class_id  = c.id
             WHERE l.teacher_id = %d AND cs.day_of_week = %d AND cs.period_id = %d
             ORDER BY c.name ASC",
            (int)$teacher_id, (int)$day_of_week, (int)$period_id
        ));

        // 1-as etapas: sudaryti grupes ir surinkti visų IMP mokinių ID.
        $groups          = array();
        $group_students  = array();   // grupės indeksas => mokinių eilutės
        $all_student_ids = array();
        foreach ($rows as $i => $r) {
            $is_imp   = (int)$r->is_imp === 1;
            $students = $is_imp ? self::get_class_students($r->class_id) : array();
            $group_students[$i] = $students;
            foreach ($students as $s) $all_student_ids[(int)$s->ID] = true;
            $groups[$i] = array(
                'class_id'       => (int)$r->class_id,
                'class_name'     => $r->class_name,
                'subject'        => $r->subject,
                'subgroup_label' => $r->subgroup_label,
                'room'           => $r->room,
                'is_imp'         => $is_imp,
                'class_label'    => '',   // mokinio (savininko) tikroji klasė (užpildoma žemiau)
                'students'       => array_map(function($s){
                    return array('id'=>(int)$s->ID, 'name'=>$s->display_name);
                }, $students),
            );
        }

        // 2-as etapas: paruošiam mokinio klasės šaltinius.
        //  a) TIKROJI klasė (pvz. „IIIf") saugoma user_meta (ss_class_level/letter)
        //     per SS_Roles — IMP mokiniai ss_user_classes prisegti tik prie SAVO IMP
        //     klasės, todėl įprastos klasės ten nėra. Tai pagrindinis šaltinis.
        //  b) Atsarginis: ne-IMP ss_classes nuoroda (jei meta tuščia).
        $ids = array_map('intval', array_keys($all_student_ids));
        if ($ids) update_meta_cache('user', $ids); // vienu kartu — be N+1

        $user_class = array(); // user_id => atsarginė (ne-IMP) klasė
        if ($ids) {
            $in = implode(',', $ids);
            $crows = $wpdb->get_results(
                "SELECT uc.user_id, c.name
                 FROM {$wpdb->prefix}ss_user_classes uc
                 JOIN {$wpdb->prefix}ss_classes c ON uc.class_id = c.id
                 WHERE uc.user_id IN ($in) AND c.is_imp = 0
                 ORDER BY c.name ASC"
            );
            foreach ($crows as $cr) {
                if (!isset($user_class[(int)$cr->user_id])) $user_class[(int)$cr->user_id] = (string)$cr->name;
            }
        }

        // 3-as etapas: IMP grupei parenkam mokinį, kurio vardas labiausiai atitinka
        // tvarkaraščio pavadinimą (pavadinimai = „Vardas Pavardė" arba „Pavardė Vardas"),
        // ir imam jo klasę.
        foreach ($groups as $i => &$g) {
            if (!$g['is_imp']) continue;
            $students = $group_students[$i];
            if (!$students) continue;
            $best = null; $best_score = -1.0;
            foreach ($students as $s) {
                $sc = self::name_similarity($g['class_name'], $s->display_name);
                if ($sc > $best_score) { $best_score = $sc; $best = $s; }
            }
            if (!$best) continue;
            $bid   = (int) $best->ID;
            $label = '';
            if (class_exists('SS_Roles')) {
                $lvl   = SS_Roles::get_student_level($bid);
                $label = (string) ($lvl['label'] ?? '');
            }
            if ($label === '') $label = $user_class[$bid] ?? ''; // atsarginis
            $g['class_label'] = $label;
        }
        unset($g);

        // 4-as etapas: rikiuoti pagal klasę, o esant vienodai — pagal tvarkaraščio
        // pavadinimą. Grupės be klasės keliauja į sąrašo galą.
        usort($groups, function($a, $b) {
            $ca = (string)$a['class_label']; $cb = (string)$b['class_label'];
            if ($ca === '' && $cb !== '') return 1;
            if ($ca !== '' && $cb === '') return -1;
            $cmp = strnatcasecmp($ca, $cb);
            return $cmp !== 0 ? $cmp : strnatcasecmp((string)$a['class_name'], (string)$b['class_name']);
        });

        return array_values($groups);
    }

    /**
     * Vardų panašumo įvertis (0–100), nepriklausomas nuo žodžių tvarkos — kad
     * „Vardas Pavardė" atitiktų „Pavardė Vardas". Naudojama parinkti, kuris prie
     * IMP tvarkaraščio prisegtas mokinys yra jo tikrasis savininkas.
     */
    private static function name_similarity($a, $b): float {
        $norm = function($s) {
            $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$s)));
            $t = $s === '' ? array() : explode(' ', $s);
            sort($t);
            return implode(' ', $t);
        };
        $na = $norm($a); $nb = $norm($b);
        if ($na === '' || $nb === '') return 0.0;
        if ($na === $nb) return 100.0;
        $pct = 0.0;
        similar_text($na, $nb, $pct);
        return (float)$pct;
    }

    /* ── PERIOD CHANGES ──────────────────── */

    /**
     * Returns distinct subgroup labels for a class+period+day_of_week cell.
     * Empty string entries are ignored — only named subgroups are returned.
     * Returns [] when the cell has no subgroups (single group).
     */
    public static function get_cell_subgroups($class_id, $period_id, $day_of_week) {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT subgroup_label
             FROM {$wpdb->prefix}ss_class_schedule
             WHERE class_id=%d AND period_id=%d AND day_of_week=%d
               AND subgroup_label <> ''
             ORDER BY sort_order ASC",
            (int)$class_id, (int)$period_id, (int)$day_of_week
        ));
        return array_column($rows, 'subgroup_label');
    }

    /**
     * Returns period changes for the whole week as:
     * $map[day_of_week][class_id][period_id][subgroup_label] = comment
     * subgroup_label '' means the change applies to all subgroups.
     */
    public static function get_week_changes_map($monday_date = null, $published_only = false) {
        global $wpdb;
        $monday = $monday_date ?: self::get_active_monday();
        $tz     = SS_Time::tz();
        $end_dt = new DateTime($monday, $tz);
        $end_dt->modify('+4 days');
        $end    = $end_dt->format('Y-m-d');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT change_date, period_id, class_id, subgroup_label, comment
             FROM {$wpdb->prefix}ss_period_changes
             WHERE change_date BETWEEN %s AND %s",
            $monday, $end
        ));
        $map = array();
        foreach ($rows as $r) {
            if ($published_only && !self::is_changes_published($r->change_date)) continue;
            $dt  = new DateTime($r->change_date, $tz);
            $dow = (int)$dt->format('N');
            if ($dow < 1 || $dow > 5) continue;
            $cid = (int)$r->class_id;
            $pid = (int)$r->period_id;
            $sg  = (string)$r->subgroup_label;
            $map[$dow][$cid][$pid][$sg] = $r->comment;
        }
        return $map;
    }

    public static function get_changes_for_date($date) {
        global $wpdb;
        $date = sanitize_text_field($date);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pc.*, c.name AS class_name, p.period_number
             FROM {$wpdb->prefix}ss_period_changes pc
             LEFT JOIN {$wpdb->prefix}ss_classes c ON pc.class_id  = c.id
             LEFT JOIN {$wpdb->prefix}ss_periods p ON pc.period_id = p.id
             WHERE pc.change_date = %s
             ORDER BY p.period_number ASC, pc.id ASC",
            $date
        ));
        // Praturtiname POGRUPIO pakeitimus dalyku + mokytoju, kad būtų aišku,
        // kuriai grupei skirtas pakeitimas (rodoma tik kai subgroup_label nustatytas).
        if ($rows) {
            try { $dow = (int)(new DateTime($date, SS_Time::tz()))->format('N'); }
            catch (\Exception $e) { $dow = 0; }
            foreach ($rows as $r) {
                $r->subject      = '';
                $r->teacher_name = '';
                if ($dow >= 1 && $dow <= 5 && (string)$r->subgroup_label !== '') {
                    $cell = $wpdb->get_row($wpdb->prepare(
                        "SELECT l.subject AS subject, t.name AS teacher_name
                         FROM {$wpdb->prefix}ss_class_schedule cs
                         LEFT JOIN {$wpdb->prefix}ss_lessons  l ON cs.lesson_id = l.id
                         LEFT JOIN {$wpdb->prefix}ss_teachers t ON l.teacher_id = t.id
                         WHERE cs.class_id=%d AND cs.period_id=%d AND cs.day_of_week=%d AND cs.subgroup_label=%s
                         LIMIT 1",
                        (int)$r->class_id, (int)$r->period_id, $dow, (string)$r->subgroup_label
                    ));
                    if ($cell) {
                        $r->subject      = (string)$cell->subject;
                        $r->teacher_name = (string)$cell->teacher_name;
                    }
                }
            }
        }
        return $rows;
    }

    public static function save_change($d) {
        global $wpdb; $t = "{$wpdb->prefix}ss_period_changes";
        $f = array(
            'change_date'    => sanitize_text_field($d['change_date'] ?? SS_Time::today_ymd()),
            'period_id'      => (int)($d['period_id'] ?? 0),
            'class_id'       => (int)($d['class_id']  ?? 0),
            'subgroup_label' => sanitize_text_field($d['subgroup_label'] ?? ''),
            'comment'        => sanitize_textarea_field($d['comment']  ?? ''),
        );
        $id = (int)($d['id'] ?? 0);
        if ($id) { $wpdb->update($t,$f,array('id'=>$id)); return $id; }
        $f['created_at'] = current_time('mysql');
        $wpdb->insert($t,$f); return $wpdb->insert_id;
    }

    public static function delete_change($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}ss_period_changes", array('id'=>(int)$id));
    }

    /* ── ABSENT TEACHERS ─────────────────── */
    public static function get_absent_for_date($date) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.teacher_id, a.from_period, t.name AS teacher_name
             FROM {$wpdb->prefix}ss_absent_teachers a
             JOIN {$wpdb->prefix}ss_teachers t ON a.teacher_id = t.id
             WHERE a.absent_date = %s
             ORDER BY t.name ASC",
            sanitize_text_field($date)
        ));
    }

    public static function save_absent($date, $teacher_id, $from_period = 0) {
        global $wpdb;
        $from_period = max(0, (int)$from_period);
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ss_absent_teachers WHERE absent_date=%s AND teacher_id=%d",
            sanitize_text_field($date), (int)$teacher_id
        ));
        if ($exists) {
            $wpdb->update(
                "{$wpdb->prefix}ss_absent_teachers",
                array('from_period' => $from_period),
                array('id' => $exists)
            );
            return $exists;
        }
        $wpdb->insert("{$wpdb->prefix}ss_absent_teachers", array(
            'absent_date' => sanitize_text_field($date),
            'teacher_id'  => (int)$teacher_id,
            'from_period' => $from_period,
            'created_at'  => current_time('mysql'),
        ));
        return $wpdb->insert_id;
    }

    public static function delete_absent($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}ss_absent_teachers", array('id'=>(int)$id));
    }

    /* ── REGISTRATIONS ─────────────────── */
    public static function get_registration_by_email($email) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_registrations WHERE email=%s",
            sanitize_email($email)
        ));
    }

    public static function get_registration($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_registrations WHERE id=%d", (int)$id
        ));
    }

    public static function get_pending_registrations() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ss_registrations
             WHERE status='pending' AND verified=1
                   AND first_name <> ''
             ORDER BY created_at DESC"
        );
    }

    public static function get_all_registrations($limit = 100) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_registrations
             ORDER BY created_at DESC LIMIT %d", (int)$limit
        ));
    }

    public static function upsert_registration_code($email, $code, $expires_at) {
        global $wpdb; $t = "{$wpdb->prefix}ss_registrations";
        $email = sanitize_email($email);
        $existing = self::get_registration_by_email($email);
        if ($existing) {
            // Don't allow rewriting an already-verified-and-submitted record.
            if ($existing->status === 'approved') return false;
            $wpdb->update($t, array(
                'code'            => $code,
                'code_expires_at' => $expires_at,
                'code_attempts'   => 0,
                'verified'        => 0,
                'status'          => 'pending',
            ), array('id' => $existing->id));
            return $existing->id;
        }
        $wpdb->insert($t, array(
            'email'           => $email,
            'code'            => $code,
            'code_expires_at' => $expires_at,
            'verified'        => 0,
            'status'          => 'pending',
            'created_at'      => current_time('mysql'),
        ));
        return $wpdb->insert_id;
    }

    public static function mark_registration_verified($email) {
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}ss_registrations",
            array('verified' => 1, 'code' => '', 'code_expires_at' => '0000-00-00 00:00:00'),
            array('email' => sanitize_email($email))
        );
    }

    public static function increment_registration_attempts($email) {
        global $wpdb; $t = "{$wpdb->prefix}ss_registrations";
        $wpdb->query($wpdb->prepare(
            "UPDATE $t SET code_attempts = code_attempts + 1 WHERE email = %s",
            sanitize_email($email)
        ));
    }

    public static function update_registration_details($email, $details) {
        global $wpdb;
        $email  = sanitize_email($email);
        $allowed = array('first_name','last_name','class_level','class_letter','class_label','password_hash','reg_role','matched_teacher_id');
        $fields = array();
        foreach ($allowed as $k) {
            if (array_key_exists($k, $details)) {
                if ($k === 'matched_teacher_id') {
                    $fields[$k] = (int)$details[$k];
                } elseif ($k === 'password_hash') {
                    $fields[$k] = (string)$details[$k];
                } else {
                    $fields[$k] = sanitize_text_field((string)$details[$k]);
                }
            }
        }
        if ($fields) {
            $wpdb->update("{$wpdb->prefix}ss_registrations", $fields, array('email' => $email));
        }
    }

    public static function set_registration_status($id, $status, $extra = array()) {
        global $wpdb;
        $fields = array_merge(array('status' => $status), (array)$extra);
        $wpdb->update("{$wpdb->prefix}ss_registrations", $fields, array('id' => (int)$id));
    }

    public static function delete_registration($id) {
        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}ss_registrations", array('id' => (int)$id));
    }

    /* ── Helper: detect if email belongs to a teacher (no leading digits) ── */
    public static function email_local_part($email) {
        $email = strtolower(trim((string)$email));
        $at = strpos($email, '@');
        return $at === false ? $email : substr($email, 0, $at);
    }
    public static function is_teacher_style_email($email) {
        $local = self::email_local_part($email);
        // Teacher emails do NOT start with digits. Student emails DO.
        return $local !== '' && !preg_match('/^\d/', $local);
    }

    /* ── Helper: try to match a teacher record by name ── */
    public static function match_teacher_by_name($first, $last) {
        global $wpdb;
        $first = trim((string)$first);
        $last  = trim((string)$last);
        if (!$first || !$last) return null;
        $full     = $first . ' ' . $last;
        $reverse  = $last . ' ' . $first;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_teachers
             WHERE LOWER(name)=LOWER(%s) OR LOWER(name)=LOWER(%s) LIMIT 1",
            $full, $reverse
        ));
        if ($row) return $row;
        // Loose match: contains both words
        $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ss_teachers");
        $fl = mb_strtolower($first); $ll = mb_strtolower($last);
        foreach ($rows as $r) {
            $n = mb_strtolower($r->name);
            if (strpos($n, $fl) !== false && strpos($n, $ll) !== false) return $r;
        }
        return null;
    }

    /* ════════════════════════════════════════════
       SURVEYS / QUIZZES
       ════════════════════════════════════════════ */
    public static function get_surveys($filter = 'all') {
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->prefix}ss_surveys";
        if ($filter === 'survey' || $filter === 'quiz') {
            $sql .= $wpdb->prepare(" WHERE type=%s", $filter);
        }
        $sql .= " ORDER BY updated_at DESC, id DESC";
        return $wpdb->get_results($sql);
    }
    public static function get_survey($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ss_surveys WHERE id=%d",(int)$id));
    }
    public static function save_survey($d) {
        global $wpdb; $t="{$wpdb->prefix}ss_surveys";
        $now = current_time('mysql');
        $type   = in_array($d['type']??'survey',['survey','quiz'],true) ? $d['type'] : 'survey';
        $status = in_array($d['status']??'draft',['draft','active','closed'],true) ? $d['status'] : 'draft';
        // Accept JSON-encoded array (new format, e.g. '["teachers"]') or legacy plain string
        $aud_raw     = $d['audience'] ?? 'all';
        $aud_decoded = json_decode($aud_raw, true);
        $audience    = (is_array($aud_decoded) && !empty($aud_decoded))
            ? $aud_raw
            : (in_array($aud_raw, ['all','students','teachers'], true) ? $aud_raw : 'all');
        $f = array(
            'title'              => sanitize_text_field($d['title'] ?? ''),
            'description'        => wp_kses_post($d['description'] ?? ''),
            'type'               => $type,
            'status'             => $status,
            'show_results_admin' => !empty($d['show_results_admin']) ? 1 : 0,
            'show_results_users' => !empty($d['show_results_users']) ? 1 : 0,
            'show_answers_users' => !empty($d['show_answers_users']) ? 1 : 0,
            'results_show_from'  => !empty($d['results_show_from']) ? sanitize_text_field($d['results_show_from']) : null,
            'results_close_at'   => !empty($d['results_close_at'])  ? sanitize_text_field($d['results_close_at'])  : null,
            'open_from'          => !empty($d['open_from']) ? sanitize_text_field($d['open_from']) : null,
            'close_at'           => !empty($d['close_at']) ? sanitize_text_field($d['close_at']) : null,
            'audience'           => $audience,
            'image_id'           => (int)($d['image_id'] ?? 0),
            'questions'          => is_string($d['questions']??null) ? $d['questions'] : wp_json_encode($d['questions']??[]),
            'updated_at'         => $now,
        );
        $id = (int)($d['id'] ?? 0);
        if ($id) { $wpdb->update($t,$f,array('id'=>$id)); return $id; }
        $f['created_at'] = $now;
        $wpdb->insert($t, $f);
        return $wpdb->insert_id;
    }
    public static function delete_survey($id) {
        global $wpdb; $id=(int)$id;
        $wpdb->delete("{$wpdb->prefix}ss_surveys", array('id'=>$id));
        $wpdb->delete("{$wpdb->prefix}ss_survey_responses", array('survey_id'=>$id));
    }
    public static function get_survey_responses($survey_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name, u.user_email
               FROM {$wpdb->prefix}ss_survey_responses r
               LEFT JOIN {$wpdb->users} u ON r.user_id=u.ID
              WHERE r.survey_id=%d ORDER BY r.submitted_at DESC", (int)$survey_id));
    }
    public static function get_user_response($survey_id, $user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_survey_responses
              WHERE survey_id=%d AND user_id=%d", (int)$survey_id,(int)$user_id));
    }
    public static function save_survey_response($survey_id, $user_id, $answers, $score=0, $max=0) {
        global $wpdb; $t="{$wpdb->prefix}ss_survey_responses";
        $existing = self::get_user_response($survey_id, $user_id);
        $f = array(
            'answers'      => is_string($answers) ? $answers : wp_json_encode($answers),
            'score'        => (int)$score,
            'max_score'    => (int)$max,
            'submitted_at' => current_time('mysql'),
        );
        if ($existing) { $wpdb->update($t,$f,array('id'=>$existing->id)); return $existing->id; }
        $f['survey_id']=(int)$survey_id; $f['user_id']=(int)$user_id;
        $wpdb->insert($t,$f);
        return $wpdb->insert_id;
    }

    /**
     * Grade a text answer for a specific response.
     * Stores per-qi text scores and recalculates total score.
     * @param int $response_id
     * @param int $qi  question index
     * @param int $granted  points granted (0 or N)
     * @param int $auto_score  sum of auto-graded (non-text) points for this response
     */
    public static function grade_text_response($response_id, $qi, $granted, $auto_score) {
        global $wpdb; $t = "{$wpdb->prefix}ss_survey_responses";
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $response_id));
        if (!$row) return false;
        $ts = $row->text_scores ? json_decode($row->text_scores, true) : array();
        if (!is_array($ts)) $ts = array();
        $ts[(string)$qi] = (int)$granted;
        $total_text = array_sum($ts);
        $new_score = (int)$auto_score + (int)$total_text;
        $wpdb->update($t, array(
            'score'       => $new_score,
            'text_scores' => json_encode($ts),
        ), array('id' => $response_id));
        return $new_score;
    }
    /**
     * Top N responses for a quiz, ordered by score DESC then submitted_at ASC.
     * Returns rows with: user_id, display_name, score, max_score, submitted_at.
     */
    public static function get_survey_leaderboard($survey_id, $limit = 5) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.user_id, u.display_name, r.score, r.max_score, r.submitted_at
               FROM {$wpdb->prefix}ss_survey_responses r
               LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
              WHERE r.survey_id = %d AND r.max_score > 0
              ORDER BY r.score DESC, r.submitted_at ASC
              LIMIT %d", (int)$survey_id, (int)$limit
        ));
    }

    public static function get_active_surveys_for_dashboard() {
        global $wpdb; $now = current_time('mysql');
        // Ar stulpelis results_close_at jau sukurtas (gali nebūti senesnėse DB versijose)
        static $has_close_col = null;
        if ($has_close_col === null) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM {$wpdb->prefix}ss_surveys LIKE 'results_close_at'");
            $has_close_col = !empty($cols);
        }
        if ($has_close_col) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ss_surveys
                  WHERE status='active'
                    AND (open_from IS NULL OR open_from='' OR open_from<=%s)
                    AND (
                      (close_at IS NULL OR close_at='' OR close_at>=%s)
                      OR (
                        (show_results_users=1 OR (results_show_from IS NOT NULL AND results_show_from<>''))
                        AND (results_close_at  IS NULL OR results_close_at=''  OR results_close_at>=%s)
                      )
                    )
                  ORDER BY updated_at DESC",
                $now, $now, $now
            ));
        }
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ss_surveys
              WHERE status='active'
                AND (open_from IS NULL OR open_from='' OR open_from<=%s)
                AND (
                  (close_at IS NULL OR close_at='' OR close_at>=%s)
                  OR show_results_users=1
                  OR (results_show_from IS NOT NULL AND results_show_from<>'')
                )
              ORDER BY updated_at DESC",
            $now, $now
        ));
    }

    /**
     * Aktyvios apklausos/viktorinos einamajam vartotojui, atfiltruotos
     * pagal auditoriją (all / students / teachers) ir laiko langą.
     */
    public static function get_active_surveys_for_user($user_id) {
        $u = get_user_by('id', (int)$user_id);
        if (!$u) return array();
        $roles = (array)$u->roles;
        $is_student = in_array('ss_student', $roles, true);
        $is_teacher = in_array('ss_teacher', $roles, true) || in_array('administrator', $roles, true);

        $all = self::get_active_surveys_for_dashboard();
        $user_class_ids = null; // lazy-loaded below
        $out = array();
        foreach ($all as $s) {
            $aud_raw   = $s->audience ?? 'all';
            $audiences = json_decode($aud_raw, true);
            if (!is_array($audiences)) $audiences = array($aud_raw);

            $include = false;
            if (in_array('all', $audiences, true)) {
                $include = true;
            } elseif ($is_student && in_array('students', $audiences, true)) {
                $include = true;
            } elseif ($is_teacher && in_array('teachers', $audiences, true)) {
                $include = true;
            } else {
                foreach ($audiences as $aud) {
                    if (strpos((string)$aud, 'class:') === 0) {
                        if ($user_class_ids === null) {
                            $uc = self::get_user_classes($user_id);
                            $user_class_ids = array_map('intval', wp_list_pluck($uc, 'class_id'));
                        }
                        $class_id = (int)substr($aud, 6);
                        if (in_array($class_id, $user_class_ids, true)) {
                            $include = true;
                            break;
                        }
                    }
                }
            }
            if ($include) $out[] = $s;
        }
        return $out;
    }

}
