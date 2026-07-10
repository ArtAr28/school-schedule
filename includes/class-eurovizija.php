<?php
/**
 * SS_Eurovizija — „Ąžuolyno Eurovizija" balsavimo sistema.
 *
 * Visiškai atskira nuo apklausų/viktorinų (SS_Surveys_Admin) — savo lentelės,
 * AJAX, administravimo puslapis ir balsavimo puslapis. Apklausos naudotos tik
 * kaip architektūros pavyzdys.
 *
 * Principai:
 *   • 1 žmogus = 1 balsas (užtikrinama DB lygmenyje per UNIQUE indeksą).
 *   • Dalyviai: klasės (ss_classes) + atskiri naudotojai (mokytojai/darbuotojai/mokiniai).
 *   • Režimai: single (1 balsas iš viso) | parallel (po 1 iš kiekvienos grupės/paralelės).
 *   • Rezultatus mato TIK administratorius.
 */
if (!defined('ABSPATH')) exit;

class SS_Eurovizija {

    const OPT_ENABLED   = 'ss_euro_enabled';
    const OPT_OPEN      = 'ss_euro_open';
    const OPT_OPEN_FROM = 'ss_euro_open_from';
    const OPT_CLOSE_AT  = 'ss_euro_close_at';
    const OPT_MODE      = 'ss_euro_mode';       // single | parallel
    const OPT_TITLE     = 'ss_euro_title';
    const OPT_DESC      = 'ss_euro_description';
    const OPT_ELIGIBLE  = 'ss_euro_eligible';   // JSON: ['all'] | ['students'] | ['teachers'] | ['class:ID', …]
    const OPT_PAGE      = 'ss_euro_page_id';
    const OPT_LOGO      = 'ss_euro_logo_id';    // logotipas meniu mygtukui (attachment ID)
    const OPT_HERO      = 'ss_euro_hero_id';    // fono paveikslėlis po pavadinimu balsavimo puslapyje

    // Kešas (transient): balsavimo puslapį vienu metu krauna šimtai mokinių, todėl
    // dalyvių sąrašą + nuotraukų URL ir logotipo URL kešuojam, kad nesikartotų
    // brangios wp_get_attachment_image_url() užklausos kiekvienam apsilankymui.
    const CACHE_CANDS   = 'ss_euro_cand_cache';
    const CACHE_LOGO    = 'ss_euro_logo_url';
    const CACHE_HERO    = 'ss_euro_hero_url';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'), 31);

        // Valdymo AJAX (ss_euro_manage nonce + „valdyti balsavimą" teisė) —
        // veikia tiek wp-admin puslapyje, tiek priekiniame valdymo skydelyje.
        foreach (array('save_settings', 'add_candidate', 'update_candidate', 'delete_candidate', 'upload_photo', 'reset') as $a) {
            add_action("wp_ajax_ss_euro_{$a}", array(__CLASS__, "ajax_{$a}"));
        }
        add_action('admin_post_ss_euro_export_csv', array(__CLASS__, 'handle_export_csv'));

        // Priekinės dalies AJAX — balsavimas (prisijungę naudotojai)
        add_action('wp_ajax_ss_euro_vote', array(__CLASS__, 'ajax_vote'));
    }

    /* ════════════════════════════════════════════════════════════════
       Diegimas (dbDelta) — kviečiama iš maybe_upgrade() ir aktyvuojant
       ════════════════════════════════════════════════════════════════ */
    public static function install() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        global $wpdb;
        $p = $wpdb->prefix;
        $c = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$p}ss_euro_candidates (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  kind varchar(10) NOT NULL DEFAULT 'manual',
  ref_id bigint(20) NOT NULL DEFAULT 0,
  name varchar(191) NOT NULL DEFAULT '',
  group_label varchar(64) NOT NULL DEFAULT '',
  photo_id bigint(20) NOT NULL DEFAULT 0,
  sort_order int(11) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (id),
  KEY group_label (group_label)
) $c;");

        // Ankstesnė versija turėjo UNIQUE(kind,ref_id) — rankiniam dalyvių sąrašui jis
        // netinka (visi rankiniai įrašai yra ref_id=0). Pašalinam, jei egzistuoja.
        if ($wpdb->get_var("SHOW INDEX FROM {$p}ss_euro_candidates WHERE Key_name='uq_kind_ref'")) {
            $wpdb->query("ALTER TABLE {$p}ss_euro_candidates DROP INDEX uq_kind_ref");
        }

        dbDelta("CREATE TABLE {$p}ss_euro_votes (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  user_id bigint(20) NOT NULL DEFAULT 0,
  candidate_id bigint(20) NOT NULL DEFAULT 0,
  group_label varchar(64) NOT NULL DEFAULT '',
  voted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  ip varchar(45) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  UNIQUE KEY uq_user_group (user_id, group_label),
  KEY candidate_id (candidate_id)
) $c;");

        // Numatytosios reikšmės — autoload IŠJUNGTAS (reikalingos tik plėtinio puslapiuose).
        $sentinel = "\0__ss_missing__";
        $defaults = array(
            self::OPT_ENABLED   => 0,
            self::OPT_OPEN      => 0,
            self::OPT_OPEN_FROM => '',
            self::OPT_CLOSE_AT  => '',
            self::OPT_MODE      => 'single',
            self::OPT_TITLE     => 'Ąžuolyno Eurovizija',
            self::OPT_DESC      => '',
            self::OPT_ELIGIBLE  => wp_json_encode(array('students')),
            self::OPT_PAGE      => 0,
            self::OPT_LOGO      => 0,
            self::OPT_HERO      => 0,
        );
        foreach ($defaults as $k => $v) {
            if (get_option($k, $sentinel) === $sentinel) add_option($k, $v, '', 'no');
        }
    }

    /* ════════════════════════════════════════════════════════════════
       Pagalbinės (būsena, teisės, dalyviai)
       ════════════════════════════════════════════════════════════════ */
    public static function is_enabled(): bool {
        return (bool) get_option(self::OPT_ENABLED, 0);
    }

    /** Ar šiuo metu priimami balsai (įjungta + atidaryta + laiko langas). */
    public static function is_open(): bool {
        if (!self::is_enabled()) return false;
        if (!get_option(self::OPT_OPEN, 0)) return false;
        $now  = current_time('mysql');
        $from = (string) get_option(self::OPT_OPEN_FROM, '');
        $to   = (string) get_option(self::OPT_CLOSE_AT, '');
        if ($from !== '' && $from > $now) return false;
        if ($to   !== '' && $to   < $now) return false;
        return true;
    }

    public static function mode(): string {
        return get_option(self::OPT_MODE, 'single') === 'parallel' ? 'parallel' : 'single';
    }

    public static function title(): string {
        $t = trim((string) get_option(self::OPT_TITLE, ''));
        return $t !== '' ? $t : 'Ąžuolyno Eurovizija';
    }

    public static function description(): string {
        return (string) get_option(self::OPT_DESC, '');
    }

    public static function page_url(): string {
        $pid = (int) get_option(self::OPT_PAGE, 0);
        return $pid ? (get_permalink($pid) ?: '') : '';
    }

    /** Meniu logotipo URL (jei įkeltas), kitaip ''. Kešuojama (rodoma visuose puslapiuose). */
    public static function logo_url(string $size = 'thumbnail'): string {
        $id = (int) get_option(self::OPT_LOGO, 0);
        if (!$id) return '';
        $ck = self::CACHE_LOGO . '_' . $size;
        $cached = get_transient($ck);
        if (is_string($cached)) return $cached;
        $url = wp_get_attachment_image_url($id, $size) ?: '';
        set_transient($ck, $url, DAY_IN_SECONDS);
        return $url;
    }

    /** Fono paveikslėlio URL po balsavimo pavadinimu (jei įkeltas), kitaip ''. Kešuojama. */
    public static function hero_url(string $size = 'large'): string {
        $id = (int) get_option(self::OPT_HERO, 0);
        if (!$id) return '';
        $ck = self::CACHE_HERO . '_' . $size;
        $cached = get_transient($ck);
        if (is_string($cached)) return $cached;
        $url = wp_get_attachment_image_url($id, $size) ?: '';
        set_transient($ck, $url, DAY_IN_SECONDS);
        return $url;
    }

    public static function eligible(): array {
        $arr = json_decode((string) get_option(self::OPT_ELIGIBLE, ''), true);
        if (!is_array($arr) || empty($arr)) $arr = array('students');
        return $arr;
    }

    /** Ar naudotojas patenka į balsuojančiųjų auditoriją (pritaikyta iš SS_Surveys_Admin::user_in_audience). */
    public static function user_can_vote($user_id): bool {
        $user_id = (int) $user_id;
        if (!$user_id) return false;
        $aud = self::eligible();
        if (in_array('all', $aud, true)) return true;
        $u = get_user_by('id', $user_id);
        if (!$u) return false;
        $roles = (array) $u->roles;
        if (in_array('students', $aud, true) && in_array('ss_student', $roles, true)) return true;
        if (in_array('teachers', $aud, true) && (in_array('ss_teacher', $roles, true) || in_array('ss_employee', $roles, true))) return true;
        global $wpdb;
        foreach ($aud as $a) {
            if (strpos((string) $a, 'class:') === 0) {
                $cid = (int) substr($a, 6);
                if ($cid && $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ss_user_classes WHERE user_id=%d AND class_id=%d",
                    $user_id, $cid
                ))) return true;
            }
        }
        return false;
    }

    /** Ar naudotojas gali valdyti balsavimą (admin arba turi „ss_manage_eurovizija"). */
    public static function user_can_manage($uid = null): bool {
        if ($uid === null) {
            return current_user_can('manage_options') || current_user_can('ss_manage_eurovizija');
        }
        $u = get_user_by('id', (int) $uid);
        if (!$u) return false;
        return user_can($u, 'manage_options') || user_can($u, 'ss_manage_eurovizija');
    }

    public static function get_candidates(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ss_euro_candidates ORDER BY group_label ASC, sort_order ASC, id ASC"
        ) ?: array();
    }

    /**
     * Balsavimo puslapiui: dalyviai su IŠ ANKSTO paskaičiuotu nuotraukos URL
     * (dydis „large", kaip kortelėse). Rezultatas kešuojamas, kad 800 vienu metu
     * krautų puslapį be N×2 wp_get_attachment_image_url() užklausų kiekvienam.
     * @return array stdClass eilutės su papildomu ->photo_large lauku
     */
    public static function voting_candidates(): array {
        $cached = get_transient(self::CACHE_CANDS);
        if (is_array($cached)) return $cached;
        $rows = self::get_candidates();
        foreach ($rows as $c) {
            $c->photo_large = self::candidate_photo_url($c, 'large');
        }
        set_transient(self::CACHE_CANDS, $rows, DAY_IN_SECONDS);
        return $rows;
    }

    /** Išvalo dalyvių/logotipo kešą — kviečiama po bet kokio dalyvių ar nustatymų pakeitimo. */
    public static function clear_cache(): void {
        delete_transient(self::CACHE_CANDS);
        delete_transient(self::CACHE_LOGO . '_thumbnail');
        delete_transient(self::CACHE_LOGO . '_large');
        delete_transient(self::CACHE_HERO . '_large');
        delete_transient(self::CACHE_HERO . '_full');
    }

    /**
     * Vienu užklausos kvietimu įkelia visus balsavimo puslapiui reikalingus
     * ss_euro_* nustatymus į objektų kešą (jie NEautoload), kad vėlesni
     * is_enabled()/is_open()/mode()/title()/… nedarytų po atskirą DB užklausą.
     */
    public static function prime_settings(): void {
        static $done = false;
        if ($done) return;
        $done = true;
        global $wpdb;
        $names = array(
            self::OPT_ENABLED, self::OPT_OPEN, self::OPT_OPEN_FROM, self::OPT_CLOSE_AT,
            self::OPT_MODE, self::OPT_TITLE, self::OPT_DESC, self::OPT_ELIGIBLE, self::OPT_LOGO, self::OPT_HERO,
        );
        $place = implode(',', array_fill(0, count($names), '%s'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN ($place)",
            $names
        ));
        foreach ((array) $rows as $r) {
            // get_option ne-autoload reikšmes ima iš „options" grupės keše.
            wp_cache_set($r->option_name, maybe_unserialize($r->option_value), 'options');
        }
    }

    /** Naudotojo balsai: masyvas eilučių su candidate_id + group_label. */
    public static function get_user_votes($user_id): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT candidate_id, group_label FROM {$wpdb->prefix}ss_euro_votes WHERE user_id=%d",
            (int) $user_id
        )) ?: array();
    }

    /** Kandidato nuotraukos URL (rankiniu būdu įkelta nuotrauka). */
    public static function candidate_photo_url($cand, string $size = 'medium'): string {
        if (!empty($cand->photo_id)) {
            $url = wp_get_attachment_image_url((int) $cand->photo_id, $size);
            if ($url) return $url;
        }
        return '';
    }

    /* ════════════════════════════════════════════════════════════════
       Administravimas
       ════════════════════════════════════════════════════════════════ */
    public static function register_menu() {
        add_submenu_page(
            'school-schedule',
            'Ąžuolyno Eurovizija',
            'Eurovizija',
            'manage_options',
            'ss-eurovizija',
            array(__CLASS__, 'page_admin')
        );
    }

    public static function page_admin() {
        $classes      = SS_Database::get_classes();
        $candidates   = self::candidates_payload();
        $total_voters = self::total_voters();
        include SS_PLUGIN_DIR . 'admin/views/eurovizija.php';
    }

    private static function verify() {
        if (!check_ajax_referer('ss_euro_manage', 'nonce', false))
            wp_send_json_error(array('message' => 'Saugumo klaida.'));
        if (!self::user_can_manage())
            wp_send_json_error(array('message' => 'Neturite teisių.'));
    }

    /** Dalyvių sąrašas su balsų skaičiumi (valdymo JS perpiešimui). */
    public static function candidates_payload(): array {
        global $wpdb;
        $counts = array();
        foreach ($wpdb->get_results("SELECT candidate_id, COUNT(*) c FROM {$wpdb->prefix}ss_euro_votes GROUP BY candidate_id") as $r) {
            $counts[(int) $r->candidate_id] = (int) $r->c;
        }
        $out = array();
        foreach (self::get_candidates() as $c) {
            $out[] = array(
                'id'          => (int) $c->id,
                'kind'        => $c->kind,
                'ref_id'      => (int) $c->ref_id,
                'name'        => $c->name,
                'group_label' => $c->group_label,
                'photo_id'    => (int) $c->photo_id,
                'photo_url'   => self::candidate_photo_url($c, 'thumbnail'),
                'sort_order'  => (int) $c->sort_order,
                'votes'       => $counts[(int) $c->id] ?? 0,
            );
        }
        return $out;
    }

    public static function total_voters(): int {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ss_euro_votes");
    }

    /**
     * Pilna statistika: balsai pagal dalyvį, procentai (grupės viduje) ir grupės.
     * @return array{total_votes:int,total_voters:int,mode:string,groups:array}
     */
    public static function stats(): array {
        global $wpdb;
        $counts = array();
        foreach ($wpdb->get_results("SELECT candidate_id, COUNT(*) c FROM {$wpdb->prefix}ss_euro_votes GROUP BY candidate_id") as $r) {
            $counts[(int) $r->candidate_id] = (int) $r->c;
        }
        $total_votes = array_sum($counts);

        $grouped = array();
        foreach (self::get_candidates() as $c) {
            $g = (string) $c->group_label;
            if (!isset($grouped[$g])) $grouped[$g] = array('label' => $g, 'total' => 0, 'items' => array());
            $v = $counts[(int) $c->id] ?? 0;
            $grouped[$g]['total'] += $v;
            $grouped[$g]['items'][] = array(
                'id'        => (int) $c->id,
                'name'      => $c->name,
                'votes'     => $v,
                'photo_url' => self::candidate_photo_url($c, 'thumbnail'),
            );
        }
        ksort($grouped);

        $groups = array();
        foreach ($grouped as $g) {
            usort($g['items'], function ($a, $b) { return $b['votes'] - $a['votes']; });
            $base = (int) $g['total'];
            foreach ($g['items'] as &$it) {
                $it['pct'] = $base > 0 ? round($it['votes'] * 100 / $base, 1) : 0.0;
            }
            unset($it);
            $groups[] = $g;
        }

        return array(
            'total_votes'  => $total_votes,
            'total_voters' => self::total_voters(),
            'mode'         => self::mode(),
            'groups'       => $groups,
        );
    }

    public static function ajax_save_settings() {
        self::verify();
        $mode    = (sanitize_text_field($_POST['mode'] ?? 'single') === 'parallel') ? 'parallel' : 'single';
        $enabled = !empty($_POST['enabled']) ? 1 : 0;
        $open    = !empty($_POST['open']) ? 1 : 0;
        $title   = sanitize_text_field(wp_unslash($_POST['title'] ?? 'Ąžuolyno Eurovizija'));
        $desc    = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));

        $elig = json_decode(wp_unslash($_POST['eligible'] ?? '["students"]'), true);
        if (!is_array($elig)) $elig = array('students');
        $elig = array_values(array_filter($elig, function ($a) {
            return in_array($a, array('all', 'students', 'teachers'), true) || preg_match('/^class:\d+$/', (string) $a);
        }));
        if (empty($elig)) $elig = array('students');

        // datetime-local → MySQL ('YYYY-MM-DDTHH:MM' → 'YYYY-MM-DD HH:MM:00')
        $norm_dt = function ($v) {
            $v = sanitize_text_field($v);
            if ($v === '') return '';
            $v = str_replace('T', ' ', $v);
            if (strlen($v) === 16) $v .= ':00';
            return $v;
        };
        $from = $norm_dt($_POST['open_from'] ?? '');
        $to   = $norm_dt($_POST['close_at'] ?? '');

        update_option(self::OPT_ENABLED, $enabled, false);
        update_option(self::OPT_OPEN, $open, false);
        update_option(self::OPT_MODE, $mode, false);
        update_option(self::OPT_TITLE, $title, false);
        update_option(self::OPT_DESC, $desc, false);
        update_option(self::OPT_ELIGIBLE, wp_json_encode($elig), false);
        update_option(self::OPT_OPEN_FROM, $from, false);
        update_option(self::OPT_CLOSE_AT, $to, false);
        // Logotipą atnaujinam tik kai laukas atsiųstas (kad kitos formos jo nenuvalytų).
        if (isset($_POST['logo_id'])) {
            update_option(self::OPT_LOGO, (int) $_POST['logo_id'], false);
        }
        if (isset($_POST['hero_id'])) {
            update_option(self::OPT_HERO, (int) $_POST['hero_id'], false);
        }

        self::clear_cache();
        if (class_exists('SS_AuditLog'))
            SS_AuditLog::write('euro_settings_saved', array('enabled' => $enabled, 'open' => $open, 'mode' => $mode));
        wp_send_json_success(array('message' => 'Išsaugota.'));
    }

    /** Rankiniu būdu sukuria dalyvį: pavadinimas + grupė/paralelė + nuotrauka. */
    public static function ajax_add_candidate() {
        self::verify();
        global $wpdb;
        $name  = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $group = sanitize_text_field(wp_unslash($_POST['group_label'] ?? ''));
        $photo = (int) ($_POST['photo_id'] ?? 0);
        if ($name === '') wp_send_json_error(array('message' => 'Įveskite dalyvio pavadinimą.'));
        $wpdb->insert("{$wpdb->prefix}ss_euro_candidates", array(
            'kind'        => 'manual',
            'ref_id'      => 0,
            'name'        => $name,
            'group_label' => $group,
            'photo_id'    => $photo,
            'sort_order'  => 0,
            'created_at'  => current_time('mysql'),
        ));
        self::clear_cache();
        if (class_exists('SS_AuditLog'))
            SS_AuditLog::write('euro_candidate_changed', array('action' => 'add', 'name' => $name, 'group' => $group));
        wp_send_json_success(array('candidates' => self::candidates_payload()));
    }

    /**
     * Nuotraukos įkėlimas iš priekinio valdymo skydelio (managerio, kuris gali
     * neturėti standartinės „upload_files" teisės). Prieiga ribojama user_can_manage().
     */
    public static function ajax_upload_photo() {
        self::verify();
        if (empty($_FILES['photo']) || !is_uploaded_file($_FILES['photo']['tmp_name'] ?? '')) {
            wp_send_json_error(array('message' => 'Nepavyko įkelti failo.'));
        }
        $file  = $_FILES['photo'];
        $tmp   = $file['tmp_name'] ?? '';
        // Tikriname TIKRĄ failo turinį (mime_content_type + getimagesize), o ne
        // naršyklės nurodytą tipą ar plėtinį — juos galima suklastoti.
        $type    = function_exists('mime_content_type') ? mime_content_type($tmp) : '';
        $allowed = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($type, $allowed, true)) {
            wp_send_json_error(array('message' => 'Netinkamas formatas (leidžiama: JPG, PNG, GIF, WEBP).'));
        }
        if (!@getimagesize($tmp)) {
            wp_send_json_error(array('message' => 'Failas neatitinka vaizdo formato.'));
        }
        if ((int) $file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'Failas per didelis (maks. 5 MB).'));
        }
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $attach_id = media_handle_upload('photo', 0);
        if (is_wp_error($attach_id)) {
            wp_send_json_error(array('message' => $attach_id->get_error_message()));
        }
        wp_send_json_success(array(
            'photo_id' => (int) $attach_id,
            'url'      => wp_get_attachment_image_url($attach_id, 'thumbnail') ?: '',
        ));
    }

    public static function ajax_update_candidate() {
        self::verify();
        global $wpdb;
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error(array('message' => 'Neteisingi duomenys.'));
        $data = array();
        if (isset($_POST['group_label'])) $data['group_label'] = sanitize_text_field(wp_unslash($_POST['group_label']));
        if (isset($_POST['name']))        $data['name']        = sanitize_text_field(wp_unslash($_POST['name']));
        if (isset($_POST['photo_id']))    $data['photo_id']    = (int) $_POST['photo_id'];
        if (isset($_POST['sort_order']))  $data['sort_order']  = (int) $_POST['sort_order'];
        if ($data) $wpdb->update("{$wpdb->prefix}ss_euro_candidates", $data, array('id' => $id));
        self::clear_cache();
        if (class_exists('SS_AuditLog'))
            SS_AuditLog::write('euro_candidate_changed', array('action' => 'update', 'id' => $id));
        wp_send_json_success(array('candidates' => self::candidates_payload()));
    }

    public static function ajax_delete_candidate() {
        self::verify();
        global $wpdb;
        $id = (int) ($_POST['id'] ?? 0);
        $wpdb->delete("{$wpdb->prefix}ss_euro_candidates", array('id' => $id));
        $wpdb->delete("{$wpdb->prefix}ss_euro_votes", array('candidate_id' => $id));
        self::clear_cache();
        if (class_exists('SS_AuditLog'))
            SS_AuditLog::write('euro_candidate_changed', array('action' => 'delete', 'id' => $id));
        wp_send_json_success(array('candidates' => self::candidates_payload(), 'total_voters' => self::total_voters()));
    }

    /** Obnulina balsavimą — ištrina VISUS balsus (dalyviai ir nustatymai lieka). */
    public static function ajax_reset() {
        self::verify();
        global $wpdb;
        $deleted = (int) $wpdb->query("DELETE FROM {$wpdb->prefix}ss_euro_votes");
        if (class_exists('SS_AuditLog'))
            SS_AuditLog::write('euro_votes_reset', array('deleted' => $deleted, 'severity' => 'warning'));
        wp_send_json_success(array('deleted' => $deleted));
    }

    /* ════════════════════════════════════════════════════════════════
       Balsavimas (priekinė dalis)
       ════════════════════════════════════════════════════════════════ */
    public static function ajax_vote() {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message' => 'Neprisijungta.'));
        $uid = get_current_user_id();
        if (!self::is_open())          wp_send_json_error(array('message' => 'Balsavimas šiuo metu neaktyvus.'));
        if (!self::user_can_vote($uid)) wp_send_json_error(array('message' => 'Jūs negalite balsuoti šiame balsavime.'));
        if (!empty(self::get_user_votes($uid))) wp_send_json_error(array('message' => 'Jūs jau balsavote.'));

        global $wpdb;
        $tv   = "{$wpdb->prefix}ss_euro_votes";
        $tc   = "{$wpdb->prefix}ss_euro_candidates";
        $ip   = class_exists('SS_RateLimit') ? SS_RateLimit::get_client_ip() : sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        $now  = current_time('mysql');
        $mode = self::mode();

        $ids = json_decode(wp_unslash($_POST['candidate_ids'] ?? '[]'), true);
        if (!is_array($ids)) $ids = array();
        $ids = array_values(array_unique(array_map('intval', array_filter($ids))));
        if (empty($ids)) wp_send_json_error(array('message' => 'Pasirinkite bent vieną dalyvį.'));

        $place = implode(',', array_fill(0, count($ids), '%d'));
        $cand_by_id = array();
        foreach ($wpdb->get_results($wpdb->prepare("SELECT id, group_label FROM {$tc} WHERE id IN ($place)", $ids)) as $c) {
            $cand_by_id[(int) $c->id] = $c;
        }

        $inserted = 0;
        if ($mode === 'single') {
            if (count($ids) !== 1) wp_send_json_error(array('message' => 'Galima balsuoti tik už vieną dalyvį.'));
            $cid = $ids[0];
            if (!isset($cand_by_id[$cid])) wp_send_json_error(array('message' => 'Neteisingas dalyvis.'));
            $ok = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$tv} (user_id, candidate_id, group_label, voted_at, ip) VALUES (%d,%d,'',%s,%s)",
                $uid, $cid, $now, $ip
            ));
            $inserted = $ok ? 1 : 0;
        } else {
            // parallel — po vieną iš kiekvienos grupės
            $seen = array();
            foreach ($ids as $cid) {
                if (!isset($cand_by_id[$cid])) wp_send_json_error(array('message' => 'Neteisingas dalyvis.'));
                $g = (string) $cand_by_id[$cid]->group_label;
                if (isset($seen[$g])) wp_send_json_error(array('message' => 'Galima rinktis tik vieną dalyvį iš kiekvienos grupės.'));
                $seen[$g] = true;
            }
            foreach ($ids as $cid) {
                $g = (string) $cand_by_id[$cid]->group_label;
                $ok = $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$tv} (user_id, candidate_id, group_label, voted_at, ip) VALUES (%d,%d,%s,%s,%s)",
                    $uid, $cid, $g, $now, $ip
                ));
                if ($ok) $inserted++;
            }
        }

        if ($inserted < 1) wp_send_json_error(array('message' => 'Nepavyko įrašyti balso (galbūt jau balsavote).'));
        if (class_exists('SS_AuditLog'))
            SS_AuditLog::write('euro_vote_cast', array('user_id' => $uid, 'mode' => $mode, 'count' => $inserted, 'ip' => $ip));
        wp_send_json_success(array('message' => 'Ačiū! Jūsų balsas priimtas.', 'count' => $inserted));
    }

    /* ════════════════════════════════════════════════════════════════
       Rezultatų CSV (tik administratoriui)
       ════════════════════════════════════════════════════════════════ */
    /** Neutralizuoja CSV formulių injekciją. */
    private static function csv_safe($v) {
        $v = (string) $v;
        if ($v !== '' && in_array($v[0], array('=', '+', '-', '@', "\t", "\r"), true)) $v = "'" . $v;
        return $v;
    }

    public static function handle_export_csv() {
        if (!self::user_can_manage()) wp_die('Neturite teisių');
        check_admin_referer('ss_euro_export');
        if (class_exists('SS_AuditLog')) SS_AuditLog::write('data_export', array('kind' => 'euro_csv'));
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT c.name, c.group_label, c.kind, COUNT(v.id) AS votes
             FROM {$wpdb->prefix}ss_euro_candidates c
             LEFT JOIN {$wpdb->prefix}ss_euro_votes v ON v.candidate_id = c.id
             GROUP BY c.id ORDER BY c.group_label ASC, votes DESC"
        );
        $filename = 'eurovizija-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM
        fputcsv($out, array_map(array(self::class, 'csv_safe'), array('Dalyvis', 'Grupė/paralelė', 'Tipas', 'Balsai')));
        foreach ($rows as $r) {
            $kind = ($r->kind === 'user') ? 'Naudotojas' : 'Klasė';
            fputcsv($out, array_map(array(self::class, 'csv_safe'), array($r->name, $r->group_label, $kind, $r->votes)));
        }
        fclose($out);
        exit;
    }
}
