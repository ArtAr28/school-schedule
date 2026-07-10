<?php
if (!defined('ABSPATH')) exit;

/**
 * SS_IMP — individualių ugdymo planų (IMP) pateikimo sistema.
 *
 *  - Katalogas (ss_imp_catalog): administratoriaus apibrėžti dalykai/kursai/moduliai.
 *  - Pateikimai (ss_imp_submissions): mokinio planas (JSON + įkeltas xlsx failas).
 *  - Mokinys pats įkelia užpildytą IUP .xlsx; sistema jį perskaito (Q–Y stulpeliai),
 *    susieja su mokinio paskyra, telefono nr. įrašo į paskyrą. Korekcija — tik
 *    pakartotinis failo įkėlimas (kol neuždarytas priėmimo langas).
 *  - Eksportas: pateikti/patvirtinti planai virsta IMP planais .azuolynotvs faile,
 *    kurį „Tvarkaraščių kūrėjas" (Windows) įkelia į tvarkaraščius.
 */
class SS_IMP {

    const SECTIONS = array(
        'privalomi'   => 'Privalomi dalykai',
        'grupe'       => 'Privalomai pasirenkami dalykai',
        'pasirenkami' => 'Pasirenkamieji dalykai',
        'moduliai'    => 'Pasirenkamieji dalykų moduliai',
    );

    const LEVELS = array('I', 'II', 'III', 'IV');

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'), 31);

        // Admin AJAX
        foreach (array('cat_list','cat_save','cat_delete','seed',
                       'sub_list','sub_get','sub_status','sub_delete','sub_bulk',
                       'settings_get','settings_save','stats') as $a) {
            add_action("wp_ajax_ss_imp_{$a}", array(__CLASS__, "ajax_{$a}"));
        }

        // Frontend AJAX (mokinio įkėlimas)
        add_action('wp_ajax_ss_imp_up_status', array(__CLASS__, 'ajax_up_status'));
        add_action('wp_ajax_ss_imp_up_submit', array(__CLASS__, 'ajax_up_submit'));

        // Atsisiuntimai (admin)
        add_action('admin_post_ss_imp_download_all', array(__CLASS__, 'download_all'));
        add_action('admin_post_ss_imp_download_one', array(__CLASS__, 'download_one'));
        add_action('admin_post_ss_imp_export',       array(__CLASS__, 'export_download'));
        add_action('admin_post_ss_imp_stats_csv',    array(__CLASS__, 'stats_csv'));

        add_shortcode('ss_imp_form', array(__CLASS__, 'shortcode'));
    }

    /* ──────────────────────────── Lentelės ──────────────────────────── */

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $cat = $wpdb->prefix . 'ss_imp_catalog';
        $sub = $wpdb->prefix . 'ss_imp_submissions';

        $wpdb->query("CREATE TABLE IF NOT EXISTS $cat (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            section VARCHAR(20) NOT NULL DEFAULT 'pasirenkami',
            grp VARCHAR(120) NOT NULL DEFAULT '',
            subject VARCHAR(200) NOT NULL DEFAULT '',
            module_name VARCHAR(250) NOT NULL DEFAULT '',
            has_level TINYINT(1) NOT NULL DEFAULT 0,
            h3_b INT NOT NULL DEFAULT 0,
            h4_b INT NOT NULL DEFAULT 0,
            h3_a INT NOT NULL DEFAULT 0,
            h4_a INT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY section (section)
        ) $charset");

        $wpdb->query("CREATE TABLE IF NOT EXISTS $sub (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'submitted',
            data LONGTEXT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset");

        self::seed_default();
        self::backfill_hours();
        self::upload_dir(); // sukuria saugomų failų katalogą su apsauga
    }

    /* ──────────────────────────── Katalogas ──────────────────────────── */

    public static function catalog($active_only = false) {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_catalog';
        $where = $active_only ? 'WHERE active=1' : '';
        return $wpdb->get_results("SELECT * FROM $t $where ORDER BY FIELD(section,'privalomi','grupe','pasirenkami','moduliai'), sort_order, id");
    }

    private static function catalog_by_id() {
        $map = array();
        foreach (self::catalog() as $c) $map[(int)$c->id] = $c;
        return $map;
    }

    /**
     * Numatytasis gimnazijos IUP katalogas su valandomis (pamokų skaičiumi).
     * Valandos paimtos iš IUP-2026.xlsx „Planas" lapo X/Y stulpelių (Valandos 3/4 kl.):
     *   h3_b/h4_b — bendrojo (B) kurso val. III/IV kl. (arba vienintelės val., kai kurso nėra);
     *   h3_a/h4_a — išplėstinio (A) kurso val. III/IV kl.
     */
    public static function default_rows() {
        $rows = array();
        $o = 0;
        // $add(section, grp, subject, module, level, h3, h4=null→h3, h3a, h4a)
        $add = function($section, $grp, $subject, $module = '', $level = 0, $h3 = 0, $h4 = null, $h3a = 0, $h4a = 0) use (&$rows, &$o) {
            if ($h4 === null) $h4 = $h3;
            $rows[] = array(
                'section'=>$section,'grp'=>$grp,'subject'=>$subject,'module_name'=>$module,
                'has_level'=>$level?1:0,
                'h3_b'=>(int)$h3,'h4_b'=>(int)$h4,'h3_a'=>(int)$h3a,'h4_a'=>(int)$h4a,
                'sort_order'=>$o++,
            );
        };

        // Privalomi dalykai (su kurso pasirinkimu B/A)
        $add('privalomi','', 'Lietuvių kalba ir literatūra','',1, 4,4, 6,6);
        $add('privalomi','', 'Matematika','',1, 4,5, 6,7);
        $add('privalomi','', 'Fizinis ugdymas','',0, 3,3);

        // Privalomai pasirenkami (su grupėmis)
        $add('grupe','Dorinis ugdymas','Tikyba','',0, 1,1);
        $add('grupe','Dorinis ugdymas','Etika','',0, 1,1);
        $add('grupe','Kalbinis ugdymas','Užsienio kalba (anglų)','',0, 4,4);
        $add('grupe','Kalbinis ugdymas','Užsienio kalba (vokiečių)','',0, 3,3);
        $add('grupe','Gamtamokslinis ir technologinis ugdymas','Biologija','',0, 3,3);
        $add('grupe','Gamtamokslinis ir technologinis ugdymas','Chemija','',0, 3,3);
        $add('grupe','Gamtamokslinis ir technologinis ugdymas','Fizika','',0, 4,4);
        $add('grupe','Gamtamokslinis ir technologinis ugdymas','Informatika','',0, 4,4);
        $add('grupe','Visuomenės mokslai','Istorija','',0, 3,3);
        $add('grupe','Visuomenės mokslai','Geografija','',0, 3,3);
        $add('grupe','Visuomenės mokslai','Ekonomika ir verslumas','',0, 3,3);
        $add('grupe','Visuomenės mokslai','Filosofija','',0, 3,3);
        $add('grupe','Menai ir technologijos','Dailė','',0, 2,2);
        $add('grupe','Menai ir technologijos','Muzika','',0, 2,2);
        $add('grupe','Menai ir technologijos','Šokis','',0, 2,2);
        $add('grupe','Menai ir technologijos','Teatras','',0, 2,2);
        $add('grupe','Menai ir technologijos','Medijų menas','',0, 2,2);
        $add('grupe','Menai ir technologijos','Taikomosios technologijos (mityba)','',0, 2,2);
        $add('grupe','Menai ir technologijos','Taikomosios technologijos (tekstilė)','',0, 2,2);

        // Pasirenkamieji dalykai
        $add('pasirenkami','','Astronomija','',0, 1,1);
        $add('pasirenkami','','Psichologija','',0, 1,1);
        $add('pasirenkami','','Užsienio kalba (vokiečių)','',0, 1,1);
        $add('pasirenkami','','Užsienio kalba (prancūzų)','',0, 1,1);
        $add('pasirenkami','','Užsienio kalba (vokiečių) pradedantiesiems','',0, 1,1);
        $add('pasirenkami','','Užsienio kalba (prancūzų) pradedantiesiems','',0, 1,1);
        $add('pasirenkami','','Užsienio kalba (ispanų) pradedantiesiems','',0, 1,1);

        // Pasirenkamieji dalykų moduliai
        $add('moduliai','','Lietuvių kalba ir literatūra (A)','Bendravimas rašytine ir sakytine kalba: skaitymo ir rašymo gebėjimų tobulinimas','',0, 1,1);
        $add('moduliai','','Anglų kalba','Debatai anglų kalba','',0, 1,1);
        $add('moduliai','','Biologija','Eksperimento simuliacija, struktūrinių užduočių atlikimas','',0, 1,1);
        $add('moduliai','','Chemija','Praktinė chemija: užduotys ir uždaviniai','',0, 1,1);
        $add('moduliai','','Fizika','Fizikos kokybinių, kiekybinių ir eksperimentinių uždavinių sprendimas','',0, 1,1);
        $add('moduliai','','Istorija','Darbas su istoriniais šaltiniais','',0, 1,1);
        $add('moduliai','','Geografija','Pasiruošk geografijos egzaminui','',0, 1,1);

        return $rows;
    }

    public static function seed_default() {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_catalog';
        if ((int)$wpdb->get_var("SELECT COUNT(*) FROM $t") > 0) return;
        foreach (self::default_rows() as $r) $wpdb->insert($t, $r);
    }

    /**
     * Migracija: užpildo valandas esamuose kataloguose, kur jos dar 0.
     * Neperrašo administratoriaus jau įvestų reikšmių.
     */
    public static function backfill_hours() {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_catalog';
        if ((int)$wpdb->get_var("SELECT COUNT(*) FROM $t") === 0) return;
        foreach (self::default_rows() as $r) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $t SET h3_b=%d, h4_b=%d, h3_a=%d, h4_a=%d, has_level=%d
                 WHERE section=%s AND subject=%s AND module_name=%s
                   AND h3_b=0 AND h4_b=0 AND h3_a=0 AND h4_a=0",
                $r['h3_b'], $r['h4_b'], $r['h3_a'], $r['h4_a'], $r['has_level'],
                $r['section'], $r['subject'], $r['module_name']
            ));
        }
    }

    /* ──────────────────────────── Nustatymai / priėmimo langas ──────────────────────────── */

    public static function settings() {
        $levels = (array) get_option('ss_imp_levels', array());
        return array(
            'open_at'  => (string) get_option('ss_imp_open_at',  ''),  // 'Y-m-d H:i' (svetainės laiku)
            'close_at' => (string) get_option('ss_imp_close_at', ''),
            'levels'   => array_values(array_intersect($levels, self::LEVELS)),
        );
    }

    /** 'before' | 'open' | 'after' — priėmimo lango būsena dabar. */
    public static function window_state() {
        $s   = self::settings();
        $now = current_time('timestamp');
        if ($s['open_at']  !== '' && $now < strtotime($s['open_at']))  return 'before';
        if ($s['close_at'] !== '' && $now > strtotime($s['close_at'])) return 'after';
        return 'open';
    }

    public static function window_open() {
        return self::window_state() === 'open';
    }

    public static function user_level($uid) {
        return (string) get_user_meta($uid, 'ss_class_level', true);
    }

    /** Ar mokinio klasė (I–IV) įtraukta į leidžiamų sąrašą. */
    public static function user_level_allowed($uid) {
        $s = self::settings();
        if (empty($s['levels'])) return false;
        return in_array(self::user_level($uid), $s['levels'], true);
    }

    /** Ar rodyti „Individualus planas" (klasė tinka; langą tikrina puslapis). */
    public static function user_can_see($uid) {
        if (!$uid) return false;
        if (user_can($uid, 'manage_options')) return true;
        return self::user_level_allowed($uid);
    }

    /** Ar mokinys gali pateikti / koreguoti DABAR (klasė tinka IR langas atviras). */
    public static function user_can_submit($uid) {
        if (!$uid) return false;
        if (user_can($uid, 'manage_options')) return true;
        return self::user_level_allowed($uid) && self::window_open();
    }

    public static function ajax_settings_get() {
        self::admin_guard();
        wp_send_json_success(array('settings' => self::settings(), 'levels' => self::LEVELS));
    }

    public static function ajax_settings_save() {
        self::admin_guard();
        $open  = sanitize_text_field($_POST['open_at']  ?? '');
        $close = sanitize_text_field($_POST['close_at'] ?? '');
        // HTML datetime-local atiduoda 'Y-m-dTH:i' — normalizuojame.
        $norm = function($v) {
            $v = trim(str_replace('T', ' ', $v));
            if ($v === '') return '';
            $ts = strtotime($v);
            return $ts ? date('Y-m-d H:i', $ts) : '';
        };
        $levels = array();
        foreach ((array)($_POST['levels'] ?? array()) as $l) {
            $l = sanitize_text_field($l);
            if (in_array($l, self::LEVELS, true)) $levels[] = $l;
        }
        update_option('ss_imp_open_at',  $norm($open));
        update_option('ss_imp_close_at', $norm($close));
        update_option('ss_imp_levels',   array_values(array_unique($levels)));
        wp_send_json_success(self::settings());
    }

    /* ──────────────────────────── Admin AJAX ──────────────────────────── */

    private static function admin_guard() {
        if (!check_ajax_referer('ss_admin_nonce', 'nonce', false))
            wp_send_json_error(array('message' => 'Saugumo klaida.'));
        if (!current_user_can('manage_options'))
            wp_send_json_error(array('message' => 'Neturite teisių.'));
    }

    public static function ajax_cat_list() {
        self::admin_guard();
        wp_send_json_success(array('items' => self::catalog(), 'sections' => self::SECTIONS));
    }

    public static function ajax_cat_save() {
        self::admin_guard();
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_catalog';
        $section = sanitize_text_field($_POST['section'] ?? 'pasirenkami');
        if (!isset(self::SECTIONS[$section])) $section = 'pasirenkami';
        $data = array(
            'section'     => $section,
            'grp'         => sanitize_text_field($_POST['grp'] ?? ''),
            'subject'     => sanitize_text_field($_POST['subject'] ?? ''),
            'module_name' => sanitize_text_field($_POST['module_name'] ?? ''),
            'has_level'   => !empty($_POST['has_level']) ? 1 : 0,
            'h3_b'        => max(0, (int)($_POST['h3_b'] ?? 0)),
            'h4_b'        => max(0, (int)($_POST['h4_b'] ?? 0)),
            'h3_a'        => max(0, (int)($_POST['h3_a'] ?? 0)),
            'h4_a'        => max(0, (int)($_POST['h4_a'] ?? 0)),
            'sort_order'  => (int)($_POST['sort_order'] ?? 0),
            'active'      => isset($_POST['active']) && !$_POST['active'] ? 0 : 1,
        );
        if ($data['subject'] === '') wp_send_json_error(array('message' => 'Įveskite dalyką.'));
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $wpdb->update($t, $data, array('id' => $id));
        else     { $wpdb->insert($t, $data); $id = (int)$wpdb->insert_id; }
        wp_send_json_success(array('id' => $id));
    }

    public static function ajax_cat_delete() {
        self::admin_guard();
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'ss_imp_catalog', array('id' => (int)($_POST['id'] ?? 0)));
        wp_send_json_success();
    }

    public static function ajax_seed() {
        self::admin_guard();
        self::seed_default();
        self::backfill_hours();
        wp_send_json_success();
    }

    /** Papildoma info eilutei: klasė, telefonas, failas. */
    private static function row_meta($r) {
        $lvl = get_user_meta($r->user_id, 'ss_class_level', true);
        $let = get_user_meta($r->user_id, 'ss_class_letter', true);
        $sel = json_decode($r->data ?? '', true);
        $file = (is_array($sel) && !empty($sel['file_rel'])) ? $sel['file_rel'] : '';
        return array(
            'class'    => trim((string)$lvl . (string)$let),
            'phone'    => get_user_meta($r->user_id, 'ss_phone', true) ?: '',
            'source'   => (is_array($sel) && !empty($sel['source'])) ? $sel['source'] : 'online',
            'has_file' => $file !== '',
            'dl_url'   => $file !== '' ? self::dl_url('one', (int)$r->id) : '',
        );
    }

    public static function ajax_sub_list() {
        self::admin_guard();
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_submissions';
        $rows = $wpdb->get_results(
            "SELECT s.*, u.display_name, u.user_email
             FROM $t s LEFT JOIN {$wpdb->users} u ON u.ID = s.user_id
             ORDER BY s.updated_at DESC");
        foreach ($rows as &$r) {
            $r->resolved = self::resolve($r);
            $r->total_subjects = count($r->resolved);
            $r->total_h3 = array_sum(array_column($r->resolved, 'h3'));
            $r->total_h4 = array_sum(array_column($r->resolved, 'h4'));
            $meta = self::row_meta($r);
            $r->class_label = $meta['class'];
            $r->phone       = $meta['phone'];
            $r->source      = $meta['source'];
            $r->has_file    = $meta['has_file'];
            $r->dl_url      = $meta['dl_url'];
            unset($r->data);
        }
        unset($r);
        wp_send_json_success(array('items' => $rows, 'dl_all_url' => self::dl_url('all', 0)));
    }

    public static function ajax_sub_get() {
        self::admin_guard();
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_submissions';
        $row = $wpdb->get_row($wpdb->prepare("SELECT s.*, u.display_name, u.user_email
            FROM $t s LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id WHERE s.id=%d", (int)($_POST['id'] ?? 0)));
        if (!$row) wp_send_json_error(array('message' => 'Nerasta.'));
        $row->resolved = self::resolve($row);
        $meta = self::row_meta($row);
        $row->class_label = $meta['class'];
        $row->phone       = $meta['phone'];
        $row->dl_url      = $meta['dl_url'];
        wp_send_json_success($row);
    }

    public static function ajax_sub_status() {
        self::admin_guard();
        global $wpdb;
        $status = sanitize_text_field($_POST['status'] ?? '');
        if (!in_array($status, array('submitted','approved','rejected'), true))
            wp_send_json_error(array('message' => 'Bloga būsena.'));
        $wpdb->update($wpdb->prefix . 'ss_imp_submissions',
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => (int)($_POST['id'] ?? 0)));
        wp_send_json_success();
    }

    public static function ajax_sub_delete() {
        self::admin_guard();
        self::delete_submission((int)($_POST['id'] ?? 0));
        wp_send_json_success();
    }

    public static function ajax_sub_bulk() {
        self::admin_guard();
        global $wpdb;
        $ids = array_map('intval', (array)($_POST['ids'] ?? array()));
        $ids = array_filter($ids);
        $action = sanitize_text_field($_POST['bulk'] ?? '');
        if (!$ids) wp_send_json_error(array('message' => 'Nepažymėta nė vieno plano.'));
        $n = 0;
        if ($action === 'delete') {
            foreach ($ids as $id) { self::delete_submission($id); $n++; }
        } elseif (in_array($action, array('approve','reject','submitted'), true)) {
            $map = array('approve'=>'approved','reject'=>'rejected','submitted'=>'submitted');
            $st  = $map[$action];
            $in  = implode(',', array_fill(0, count($ids), '%d'));
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ss_imp_submissions SET status=%s, updated_at=%s WHERE id IN ($in)",
                array_merge(array($st, current_time('mysql')), $ids));
            $wpdb->query($sql);
            $n = count($ids);
        } else {
            wp_send_json_error(array('message' => 'Nežinomas veiksmas.'));
        }
        wp_send_json_success(array('count' => $n));
    }

    private static function delete_submission($id) {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_submissions';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", $id));
        if (!$row) return;
        $sel = json_decode($row->data ?? '', true);
        if (is_array($sel) && !empty($sel['file_rel'])) self::delete_file($sel['file_rel']);
        $wpdb->delete($t, array('id' => $id));
    }

    /* ──────────────────────────── Failų saugykla ──────────────────────────── */

    private static function upload_dir() {
        $u   = wp_upload_dir();
        $dir = trailingslashit($u['basedir']) . 'ss-imp';
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            @file_put_contents($dir . '/index.php', "<?php // Silence is golden.\n");
            @file_put_contents($dir . '/.htaccess', "Deny from all\n");
        }
        return $dir;
    }

    private static function store_file($tmp, $uid, $is_upload = true) {
        $rel  = 'ss-imp/user_' . (int)$uid . '.xlsx';
        $dest = trailingslashit(wp_upload_dir()['basedir']) . $rel;
        self::upload_dir();
        $ok = $is_upload ? @move_uploaded_file($tmp, $dest) : false;
        if (!$ok) $ok = @copy($tmp, $dest);
        return $ok ? $rel : '';
    }

    private static function delete_file($rel) {
        $rel = ltrim((string)$rel, '/');
        if (strpos($rel, 'ss-imp/') !== 0) return; // saugumas — tik mūsų katalogas
        $path = trailingslashit(wp_upload_dir()['basedir']) . $rel;
        if (is_file($path)) @unlink($path);
    }

    private static function file_path($rel) {
        $rel = ltrim((string)$rel, '/');
        if (strpos($rel, 'ss-imp/') !== 0) return '';
        return trailingslashit(wp_upload_dir()['basedir']) . $rel;
    }

    private static function dl_url($what, $id) {
        $args = array('action' => 'ss_imp_download_' . ($what === 'all' ? 'all' : 'one'));
        if ($what !== 'all') $args['id'] = (int)$id;
        // Svarbu: grąžinam ŽALIĄ url su tikru „&" (ne wp_nonce_url, kuris jį užkoduoja
        // į „&#038;"). Nuoroda keliauja per JSON ir įrašoma JS'u (.attr/innerHTML),
        // kur HTML-objektas nebūtų dekoduotas → _wpnonce sugestų → „nuoroda nebegalioja".
        $args['_wpnonce'] = wp_create_nonce('ss_imp_dl_' . ($what === 'all' ? 'all' : (int)$id));
        return add_query_arg($args, admin_url('admin-post.php'));
    }

    /* ──────────────────────────── Mokinio įkėlimas (frontend) ──────────────────────────── */

    private static function front_guard() {
        if (!check_ajax_referer('ss_front_nonce', 'nonce', false))
            wp_send_json_error(array('message' => 'Saugumo klaida. Atnaujinkite puslapį.'));
        if (!is_user_logged_in())
            wp_send_json_error(array('message' => 'Neprisijungta.'));
    }

    /** Bendras mokinio būsenos paketas (langas, terminai, esamas planas). */
    private static function up_state_payload($uid) {
        $s   = self::settings();
        $mine = self::get_user_submission($uid);
        $resolved = $mine ? self::resolve($mine) : array();
        $sel = $mine ? json_decode($mine->data ?? '', true) : null;
        return array(
            'window'    => self::window_state(),
            'open_at'   => $s['open_at'],
            'close_at'  => $s['close_at'],
            'can_see'   => self::user_can_see($uid),
            'can_submit'=> self::user_can_submit($uid),
            'level'     => self::user_level($uid),
            'mine'      => array(
                'has'       => $mine ? 1 : 0,
                'status'    => $mine->status ?? '',
                'updated'   => $mine ? (string)$mine->updated_at : '',
                'file_name' => (is_array($sel) && !empty($sel['file_name'])) ? $sel['file_name'] : '',
                'resolved'  => $resolved,
                'total_h3'  => array_sum(array_column($resolved, 'h3')),
                'total_h4'  => array_sum(array_column($resolved, 'h4')),
            ),
        );
    }

    public static function ajax_up_status() {
        self::front_guard();
        $uid = get_current_user_id();
        if (!self::user_can_see($uid))
            wp_send_json_error(array('message' => 'Jūsų klasei individualaus plano pateikimas neįjungtas.'));
        wp_send_json_success(self::up_state_payload($uid));
    }

    public static function ajax_up_submit() {
        self::front_guard();
        $uid = get_current_user_id();

        if (!self::user_can_submit($uid)) {
            $st = self::window_state();
            $msg = ($st === 'before') ? 'Plano priėmimas dar neprasidėjo.'
                 : (($st === 'after') ? 'Plano priėmimas jau pasibaigė.'
                 : 'Jūsų klasei plano pateikimas neįjungtas.');
            wp_send_json_error(array('message' => $msg));
        }
        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            wp_send_json_error(array('message' => 'Pasirinkite užpildytą .xlsx failą.'));
        }
        $orig = sanitize_file_name($_FILES['file']['name'] ?? 'planas.xlsx');
        if (!preg_match('/\.xlsx$/i', $orig)) {
            wp_send_json_error(array('message' => 'Netinkamas formatas — įkelkite .xlsx bylą.'));
        }

        // 1) Parsinimas ir patikra PRIEŠ ką nors įrašant.
        $res = self::import_parse_file($_FILES['file']['tmp_name']);
        if (!empty($res['error'])) wp_send_json_error(array('message' => $res['error']));

        // 2) Failo saugojimas (perrašo ankstesnį — korekcija).
        $rel = self::store_file($_FILES['file']['tmp_name'], $uid, true);

        // 3) Telefonas iš formos → mokinio paskyra.
        $phone = trim((string)($res['student']['phone'] ?? ''));
        if ($phone !== '') update_user_meta($uid, 'ss_phone', sanitize_text_field($phone));

        // 4) Katalogo valandų papildymas (jei dar 0).
        self::import_backfill_catalog($res['resolved']);

        // 5) Pateikimas (perrašo esamą).
        global $wpdb;
        $payload = wp_json_encode(array(
            'source'    => 'xlsx',
            'items'     => $res['items']    ?? array(),
            'resolved'  => $res['resolved'] ?? array(),
            'student'   => $res['student']  ?? array(),
            'file_rel'  => $rel,
            'file_name' => $orig,
        ));
        $t = $wpdb->prefix . 'ss_imp_submissions';
        $now = current_time('mysql');
        $existing = self::get_user_submission($uid);
        if ($existing) {
            $wpdb->update($t, array('data' => $payload, 'status' => 'submitted', 'updated_at' => $now), array('id' => $existing->id));
        } else {
            $wpdb->insert($t, array('user_id' => $uid, 'data' => $payload, 'status' => 'submitted',
                                    'created_at' => $now, 'updated_at' => $now));
        }

        $payload = self::up_state_payload($uid);
        $payload['ok'] = 1;
        $payload['warnings'] = $res['warnings'] ?? array();
        wp_send_json_success($payload);
    }

    public static function get_user_submission($uid) {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_submissions';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id=%d", (int)$uid));
    }

    /* ──────────────────────────── Sprendimas (resolve) ──────────────────────────── */

    /** Suformuoja skaitomą dalyko pavadinimą: „Dalykas — Modulis (A)". */
    public static function display_name($subject, $module = '', $level = '') {
        $name = (string)$subject;
        if ($module !== '' && $module !== null) $name .= ' — ' . $module;
        if ($level === 'A' || $level === 'B')   $name .= ' (' . $level . ')';
        return $name;
    }

    /** Paverčia pateikimo JSON į skaitomą pasirinkimų sąrašą su valandomis. */
    private static function resolve($sub) {
        $sel = json_decode($sub->data ?? '', true);

        // Importuotas (xlsx) planas — valandos jau tikslios iš formos.
        if (is_array($sel) && !empty($sel['resolved']) && is_array($sel['resolved'])) {
            $out = array();
            foreach ($sel['resolved'] as $it) {
                $out[] = array(
                    'name'  => $it['name']  ?? self::display_name($it['subject'] ?? '', $it['module'] ?? '', $it['level'] ?? ''),
                    'group' => $it['group'] ?? '',
                    'level' => $it['level'] ?? '',
                    'h3'    => (int)($it['h3'] ?? 0),
                    'h4'    => (int)($it['h4'] ?? 0),
                );
            }
            return $out;
        }

        // Senesnis internetinės formos pateikimas — valandos iš katalogo.
        $out = array();
        $items = (is_array($sel) && isset($sel['items'])) ? $sel['items'] : array();
        $cat = self::catalog_by_id();
        foreach ($items as $cid => $info) {
            if (empty($info['on'])) continue;
            $c = $cat[(int)$cid] ?? null;
            if (!$c) continue;
            $level = $info['level'] ?? '';
            $name  = self::display_name($c->subject, $c->module_name, $c->has_level ? $level : '');
            $h3 = ($level === 'A') ? (int)$c->h3_a : (int)$c->h3_b;
            $h4 = ($level === 'A') ? (int)$c->h4_a : (int)$c->h4_b;
            $out[] = array('name' => $name, 'group' => $c->grp, 'level' => $level, 'h3' => $h3, 'h4' => $h4);
        }
        return $out;
    }

    /* ──────────────────────────── Statistika ──────────────────────────── */

    /** Normalizuoja pavadinimą sutapatinimui (mažosios, suvienodinti tarpai). */
    private static function norm_name($s) {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$s)), 'UTF-8');
    }

    /**
     * Suskaičiuoja, kiek mokinių pasirinko kiekvieną dalyką/modulį (pagal visus pateikimus).
     * Naudoja resolve(), todėl veikia ir su xlsx, ir su senesne internetine forma.
     * @return array ['items'=>[...], 'totals'=>[...]]
     */
    private static function compute_stats() {
        global $wpdb;
        $t    = $wpdb->prefix . 'ss_imp_submissions';
        $rows = $wpdb->get_results("SELECT * FROM $t");

        // Katalogo indeksas: bazinis pavadinimas (be lygio) → skiltis / grupė / eiliškumas.
        $cat_index = array();
        foreach (self::catalog() as $c) {
            $base = self::display_name($c->subject, $c->module_name, '');
            $cat_index[self::norm_name($base)] = array(
                'section' => $c->section,
                'grp'     => $c->grp,
                'sort'    => (int)$c->sort_order,
            );
        }

        $items  = array();
        $totals = array('submissions'=>0, 'choices'=>0, 'by_class'=>array(), 'by_status'=>array());

        foreach ((array)$rows as $r) {
            $totals['submissions']++;
            $cls    = (string) get_user_meta($r->user_id, 'ss_class_level', true);
            $status = $r->status ?: 'submitted';
            $ck = $cls !== '' ? $cls : '—';
            $totals['by_class'][$ck]      = ($totals['by_class'][$ck] ?? 0) + 1;
            $totals['by_status'][$status] = ($totals['by_status'][$status] ?? 0) + 1;

            foreach (self::resolve($r) as $it) {
                $name  = (string) ($it['name'] ?? '');
                if ($name === '') continue;
                $level = (string) ($it['level'] ?? '');
                $base  = preg_replace('/\s*\((A|B)\)\s*$/u', '', $name); // be „ (A)"/„ (B)"
                $key   = self::norm_name($base);

                if (!isset($items[$key])) {
                    $meta = $cat_index[$key] ?? null;
                    $items[$key] = array(
                        'name'      => $base,
                        'group'     => $meta['grp'] ?? (string)($it['group'] ?? ''),
                        'section'   => $meta['section'] ?? 'kita',
                        'sort'      => $meta['sort'] ?? 9999,
                        'count'     => 0, 'count_b' => 0, 'count_a' => 0,
                        'count_iii' => 0, 'count_iv' => 0, 'hours' => 0,
                    );
                }
                $items[$key]['count']++;
                $totals['choices']++;
                if     ($level === 'A') $items[$key]['count_a']++;
                elseif ($level === 'B') $items[$key]['count_b']++;
                if     ($cls === 'III') { $items[$key]['count_iii']++; $items[$key]['hours'] += (int)($it['h3'] ?? 0); }
                elseif ($cls === 'IV')  { $items[$key]['count_iv']++;  $items[$key]['hours'] += (int)($it['h4'] ?? 0); }
                else                    { $items[$key]['hours'] += max((int)($it['h3'] ?? 0), (int)($it['h4'] ?? 0)); }
            }
        }

        $order = array('privalomi'=>0, 'grupe'=>1, 'pasirenkami'=>2, 'moduliai'=>3, 'kita'=>9);
        $list  = array_values($items);
        usort($list, function($a, $b) use ($order) {
            $sa = $order[$a['section']] ?? 8; $sb = $order[$b['section']] ?? 8;
            if ($sa !== $sb)                 return $sa <=> $sb;
            if ($a['count'] !== $b['count']) return $b['count'] <=> $a['count'];
            return $a['sort'] <=> $b['sort'];
        });

        return array('items' => $list, 'totals' => $totals);
    }

    public static function ajax_stats() {
        self::admin_guard();
        $data = self::compute_stats();
        wp_send_json_success(array(
            'sections' => self::SECTIONS,
            'items'    => $data['items'],
            'totals'   => $data['totals'],
            'csv_url'  => add_query_arg(
                array('action' => 'ss_imp_stats_csv', '_wpnonce' => wp_create_nonce('ss_imp_stats_csv')),
                admin_url('admin-post.php')),
        ));
    }

    /** CSV eksportas (formulių injekcija neutralizuojama SS_AuditLog::csv_cell). */
    public static function stats_csv() {
        if (!current_user_can('manage_options')) wp_die('Nėra teisės.');
        check_admin_referer('ss_imp_stats_csv');
        $data     = self::compute_stats();
        $sections = self::SECTIONS;
        $cell = function($v) {
            return class_exists('SS_AuditLog') ? SS_AuditLog::csv_cell($v) : (string)$v;
        };
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="imp-statistika-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM — kad „Excel" teisingai rodytų lietuviškas raides
        fputcsv($out, array('Skiltis', 'Grupė', 'Dalykas/Modulis', 'Pasirinko', 'B', 'A', 'III', 'IV', 'Viso val.'));
        foreach ($data['items'] as $it) {
            $sec = $sections[$it['section']] ?? 'Kita';
            fputcsv($out, array(
                $cell($sec), $cell($it['group']), $cell($it['name']),
                (int)$it['count'], (int)$it['count_b'], (int)$it['count_a'],
                (int)$it['count_iii'], (int)$it['count_iv'], (int)$it['hours'],
            ));
        }
        fclose($out);
        exit;
    }

    /* ──────────────────────────── Eksportas į Win programą ──────────────────────────── */

    /**
     * Grąžina ['plans'=>[...], 'classes'=>[...]] iš pateiktų/patvirtintų IMP planų,
     * suderinta su .azuolynotvs (Win „Tvarkaraščių kūrėjas") formatu.
     */
    public static function export_for_io() {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_submissions';
        $rows = $wpdb->get_results(
            "SELECT s.*, u.display_name, u.user_email
             FROM $t s LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id");
        $plans = array(); $classes = array();
        foreach ((array)$rows as $r) {
            $name = $r->display_name ?: ($r->user_email ?: ('Mokinys #' . $r->user_id));
            $plan_name = 'IMP — ' . $name;
            $lessons = array();
            foreach (self::resolve($r) as $it) {
                $per = (int)$it['h3'] > 0 ? (int)$it['h3'] : ((int)$it['h4'] > 0 ? (int)$it['h4'] : 1);
                $lessons[] = array(
                    'subject'  => $it['name'],
                    'teacher'  => '',
                    'room'     => '',
                    'subgroup' => '',
                    'per_week' => $per,
                );
            }
            $plans[] = array('name' => $plan_name, 'is_imp' => 1, 'lessons' => $lessons);
            $classes[] = array('name' => $name, 'color' => '#224768', 'is_imp' => 1,
                               'plan' => $plan_name, 'entries' => array());
        }
        return array('plans' => $plans, 'classes' => $classes);
    }

    /** URL „Eksportuoti planus Win programai" mygtukui (žalias — esc_url() echo metu). */
    public static function export_url() {
        return add_query_arg(
            array('action' => 'ss_imp_export', '_wpnonce' => wp_create_nonce('ss_imp_export')),
            admin_url('admin-post.php'));
    }

    /** Atsisiunčia .azuolynotvs su IMP planais (Win „Tvarkaraščių kūrėjui"). */
    public static function export_download() {
        if (!current_user_can('manage_options')) wp_die('Neturite teisių.');
        check_admin_referer('ss_imp_export');
        if (class_exists('SS_AuditLog')) SS_AuditLog::write('data_export', array('kind' => 'imp_plans'));

        $io = self::export_for_io();
        $times = array(
            array('08:00','08:45'), array('08:55','09:40'), array('09:50','10:35'), array('10:50','11:35'),
            array('12:05','12:50'), array('13:00','13:45'), array('13:55','14:40'), array('14:45','15:30'),
        );
        $periods = array();
        foreach ($times as $i => $t) {
            $periods[] = array('period_number' => $i + 1, 'start_time' => $t[0], 'end_time' => $t[1]);
        }

        $pkg = array(
            'format'      => 'azuolynotvs',
            'version'     => '1.0',
            'exported_at' => current_time('c'),
            'school'      => SS_SCHOOL_NAME,
            'includes'    => array('plans', 'schedules'),
            'data'        => array(
                'plans'     => $io['plans'],
                'schedules' => array('periods' => $periods, 'classes' => $io['classes']),
            ),
        );

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="IMP-planai-' . date('Y-m-d') . '.azuolynotvs"');
        echo wp_json_encode($pkg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /* ──────────────────────────── xlsx parsinimas ──────────────────────────── */

    private static function norm($s) {
        $s = (string)$s;
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        return preg_replace('/\s+/u', ' ', trim($s));
    }

    /** Katalogo paieškos žemėlapis: „section|dalykas|modulis" => įrašas. */
    private static function catalog_lookup() {
        $map = array();
        foreach (self::catalog() as $c) {
            $map[$c->section . '|' . self::norm($c->subject) . '|' . self::norm($c->module_name)] = $c;
        }
        return $map;
    }

    private static function match_catalog($lookup, $item) {
        $key = $item['section'] . '|' . self::norm($item['subject']) . '|' . self::norm($item['module']);
        if (isset($lookup[$key])) return $lookup[$key];
        foreach ($lookup as $c) {
            if (self::norm($c->subject) === self::norm($item['subject'])
                && self::norm($c->module_name) === self::norm($item['module'])) return $c;
        }
        return null;
    }

    /**
     * Nuskaito IUP .xlsx (lapas „Planas") ir grąžina:
     *   ['error','student','resolved'[],'items'{cid:{on,level}},'warnings'[]]
     */
    public static function import_parse_file($path) {
        if (!class_exists('SS_IMP_Xlsx')) return array('error' => 'Trūksta xlsx skaitytuvo modulio.');
        $rd = SS_IMP_Xlsx::read_file($path);
        if (!empty($rd['error'])) return array('error' => $rd['error']);

        $sheets = $rd['sheets'] ?? array();
        $cells  = $sheets['Planas'] ?? (count($sheets) ? reset($sheets) : array());
        if (!$cells) return array('error' => 'Faile nerasta „Planas" lapo arba jis tuščias.');

        $parsed = SS_IMP_Xlsx::parse_planas($cells);
        if (empty($parsed['items'])) {
            return array('error' => 'Faile nerasta pažymėtų dalykų. Patikrinkite, ar planas užpildytas (pasirinkimai, valandos).');
        }

        $lookup = self::catalog_lookup();
        $resolved = array(); $items_map = array(); $warnings = array();
        foreach ($parsed['items'] as $it) {
            $c    = self::match_catalog($lookup, $it);
            $name = self::display_name($it['subject'], $it['module'], $it['level']);
            $resolved[] = array(
                'name'    => $name,
                'group'   => $c ? $c->grp : '',
                'level'   => $it['level'],
                'h3'      => (int)$it['h3'],
                'h4'      => (int)$it['h4'],
                'subject' => $it['subject'],
                'module'  => $it['module'],
                'section' => $it['section'],
                'matched' => $c ? 1 : 0,
            );
            if ($c) $items_map[(int)$c->id] = array('on' => 1, 'level' => $it['level']);
            else    $warnings[] = 'Nerasta kataloge (vis tiek įtraukta): ' . $name;
        }

        return array(
            'error'    => '',
            'student'  => $parsed['student'],
            'resolved' => $resolved,
            'items'    => $items_map,
            'warnings' => $warnings,
        );
    }

    /** Užpildo katalogo valandas iš importuoto failo, kur jos dar 0. */
    private static function import_backfill_catalog($resolved) {
        global $wpdb;
        $t = $wpdb->prefix . 'ss_imp_catalog';
        $lookup = self::catalog_lookup();
        foreach ((array)$resolved as $it) {
            if (empty($it['matched'])) continue;
            $c = self::match_catalog($lookup, array(
                'section' => $it['section'], 'subject' => $it['subject'], 'module' => $it['module']));
            if (!$c) continue;
            if ((int)$c->h3_b || (int)$c->h4_b || (int)$c->h3_a || (int)$c->h4_a) continue;
            $data = ($it['level'] === 'A')
                ? array('h3_a' => (int)$it['h3'], 'h4_a' => (int)$it['h4'], 'has_level' => 1)
                : array('h3_b' => (int)$it['h3'], 'h4_b' => (int)$it['h4']);
            $wpdb->update($t, $data, array('id' => (int)$c->id));
        }
    }

    /* ──────────────────────────── Atsisiuntimai (admin) ──────────────────────────── */

    public static function download_one() {
        if (!current_user_can('manage_options')) wp_die('Neturite teisių.');
        $id = (int)($_GET['id'] ?? 0);
        check_admin_referer('ss_imp_dl_' . $id);
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, u.display_name FROM {$wpdb->prefix}ss_imp_submissions s
             LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id WHERE s.id=%d", $id));
        if (!$row) wp_die('Planas nerastas.');
        $sel = json_decode($row->data ?? '', true);
        $path = (is_array($sel) && !empty($sel['file_rel'])) ? self::file_path($sel['file_rel']) : '';
        if (!$path || !is_file($path)) wp_die('Šio plano xlsx failas nerastas.');
        self::stream_file($path, self::nice_name($row, $sel) . '.xlsx');
    }

    public static function download_all() {
        if (!current_user_can('manage_options')) wp_die('Neturite teisių.');
        check_admin_referer('ss_imp_dl_all');
        if (!class_exists('ZipArchive')) wp_die('Serveryje neįjungtas ZipArchive plėtinys.');
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT s.*, u.display_name FROM {$wpdb->prefix}ss_imp_submissions s
             LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id ORDER BY s.updated_at DESC");

        $tmp = wp_tempnam('ss-imp-all');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) wp_die('Nepavyko sukurti ZIP.');
        $used = array(); $count = 0;
        foreach ((array)$rows as $row) {
            $sel = json_decode($row->data ?? '', true);
            $path = (is_array($sel) && !empty($sel['file_rel'])) ? self::file_path($sel['file_rel']) : '';
            if (!$path || !is_file($path)) continue;
            $base = self::nice_name($row, $sel); $nm = $base . '.xlsx'; $i = 2;
            while (isset($used[$nm])) { $nm = $base . '_' . $i++ . '.xlsx'; }
            $used[$nm] = 1;
            $zip->addFile($path, $nm);
            $count++;
        }
        $zip->close();
        if ($count === 0) { @unlink($tmp); wp_die('Nėra įkeltų xlsx failų.'); }
        self::stream_file($tmp, 'IMP-planai-' . date('Y-m-d') . '.zip', 'application/zip', true);
    }

    private static function nice_name($row, $sel) {
        $st = (is_array($sel) && !empty($sel['student'])) ? $sel['student'] : array();
        $name = '';
        if (!empty($st['pavarde']) || !empty($st['vardas'])) $name = trim(($st['pavarde'] ?? '') . '_' . ($st['vardas'] ?? ''), '_');
        if ($name === '') $name = $row->display_name ?: ('mokinys_' . $row->user_id);
        $cls = trim((string)get_user_meta($row->user_id, 'ss_class_level', true) . (string)get_user_meta($row->user_id, 'ss_class_letter', true));
        $full = $name . ($cls ? '_' . $cls : '');
        $full = preg_replace('/[^\p{L}\p{N}_\-]+/u', '_', $full);
        return $full !== '' ? $full : ('mokinys_' . $row->user_id);
    }

    private static function stream_file($path, $download_name, $type = '', $delete_after = false) {
        if (!$type) $type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        nocache_headers();
        header('Content-Type: ' . $type);
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        if ($delete_after) @unlink($path);
        exit;
    }

    /* ──────────────────────────── UI ──────────────────────────── */

    public static function menu() {
        add_submenu_page('school-schedule', 'Individualūs planai (IMP)', 'IMP planai',
            'manage_options', 'ss-imp', array(__CLASS__, 'page'));
    }

    public static function page() {
        include SS_PLUGIN_DIR . 'admin/views/imp.php';
    }

    public static function shortcode($atts = array()) {
        if (!is_user_logged_in()) return '<p>Prisijunkite, kad galėtumėte pateikti individualų planą.</p>';
        ob_start();
        include SS_PLUGIN_DIR . 'public/views/imp-form.php';
        return ob_get_clean();
    }
}
SS_IMP::init();
// IMP v5: mokinio xlsx įkėlimas + priėmimo nustatymai.
