<?php
/**
 * SS_Accounts — paskyrų valdymo logika
 * Blokavimas, sesijos, įrenginiai, 2FA admin valdymas, rolės / teisės
 */
if (!defined('ABSPATH')) exit;

class SS_Accounts {

    /* ═══════════════════════════════════════════════════════
       INICIALIZACIJA
    ═══════════════════════════════════════════════════════ */

    public static function init(): void {
        $actions = [
            'admin_get_accounts',
            'admin_get_account_detail',
            'admin_update_account',
            'admin_toggle_2fa',
            'admin_destroy_session',
            'admin_destroy_all_sessions',
            'admin_remove_device',
            'admin_clear_devices',
            'admin_block_account',
            'admin_unblock_account',
            'admin_change_user_role',
            'admin_set_user_caps',
            'admin_save_role_caps',
            'admin_get_role_caps',
            'admin_save_user_notify',
        ];
        foreach ($actions as $a) {
            add_action("wp_ajax_ss_{$a}", [__CLASS__, "ajax_{$a}"]);
        }

        // Blokuojame prisijungimą jei paskyra užblokuota
        add_filter('wp_authenticate_user', [__CLASS__, 'check_account_block'], 15, 2);

        // Automatinis atblokavimas po sesijos (jei laikas pasibaigė)
        add_action('wp_login', [__CLASS__, 'maybe_auto_unblock'], 5, 2);
    }

    /* ═══════════════════════════════════════════════════════
       PRISIJUNGIMO KABLIAI
    ═══════════════════════════════════════════════════════ */

    public static function check_account_block($user, $password) {
        if (is_wp_error($user)) return $user;
        $block = self::get_block_info($user->ID);
        if (!$block) return $user;

        // Laikinas blokas — patikriname ar pasibaigė
        if ($block['type'] === 'timed' && $block['until'] > 0 && $block['until'] <= time()) {
            self::unblock_user($user->ID);
            return $user;
        }

        $msg = 'Ši paskyra yra sustabdyta.';
        if (!empty($block['reason'])) {
            $msg .= ' Priežastis: ' . esc_html($block['reason']);
        }
        if ($block['type'] === 'timed' && !empty($block['until'])) {
            $until = wp_date('Y-m-d H:i', $block['until']);
            $msg  .= " Blokuota iki: {$until}.";
        }

        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('login_blocked', [
                'user_id' => $user->ID,
                'reason'  => 'account_blocked',
            ]);
        }

        return new WP_Error('account_blocked', $msg);
    }

    public static function maybe_auto_unblock(string $user_login, WP_User $user): void {
        $block = self::get_block_info($user->ID);
        if ($block && $block['type'] === 'timed' && !empty($block['until']) && $block['until'] <= time()) {
            self::unblock_user($user->ID);
        }
    }

    /* ═══════════════════════════════════════════════════════
       BLOKAVIMAS
    ═══════════════════════════════════════════════════════ */

    /** @return array|false */
    public static function get_block_info(int $user_id) {
        $raw = get_user_meta($user_id, 'ss_account_blocked', true);
        if (!$raw || !is_array($raw)) return false;
        return $raw;
    }

    public static function block_user(int $user_id, string $type, int $until = 0,
                                      string $reason = '', bool $notify = true): bool {
        if (!$user_id || !get_userdata($user_id)) return false;

        $block = [
            'type'       => $type === 'timed' ? 'timed' : 'indefinite',
            'until'      => $type === 'timed' ? $until : 0,
            'reason'     => sanitize_textarea_field($reason),
            'blocked_at' => time(),
            'blocked_by' => get_current_user_id(),
        ];
        update_user_meta($user_id, 'ss_account_blocked', $block);

        // Sunaikinti visas aktyvias sesijas
        $manager = WP_Session_Tokens::get_instance($user_id);
        $manager->destroy_all();

        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('account_blocked', [
                'user_id'    => $user_id,
                'type'       => $block['type'],
                'until'      => $block['until'],
                'blocked_by' => $block['blocked_by'],
            ]);
        }

        if ($notify) {
            self::send_block_email($user_id, $block);
        }
        return true;
    }

    public static function unblock_user(int $user_id): bool {
        delete_user_meta($user_id, 'ss_account_blocked');
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('account_unblocked', [
                'user_id'      => $user_id,
                'unblocked_by' => get_current_user_id(),
            ]);
        }
        return true;
    }

    private static function send_block_email(int $user_id, array $block): void {
        if (!class_exists('SS_Mail')) return;
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) return;

        $school   = SS_Mail::school();
        $reason   = !empty($block['reason']) ? $block['reason'] : '—';
        $type_txt = ($block['type'] === 'timed' && $block['until'])
            ? 'Iki: ' . wp_date('Y-m-d H:i', $block['until'])
            : 'Neribotam laikui';

        // Sukuriame info lentelę be info_list() – universalu
        $info_html = '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0;border-collapse:collapse">'
            . '<tr><td style="padding:8px 14px;font-size:13px;color:#555;border-bottom:1px solid #eee;font-weight:700;width:120px">Trukmė</td>'
            . '<td style="padding:8px 14px;font-size:13px;color:#333;border-bottom:1px solid #eee">' . esc_html($type_txt) . '</td></tr>'
            . '<tr><td style="padding:8px 14px;font-size:13px;color:#555;font-weight:700">Priežastis</td>'
            . '<td style="padding:8px 14px;font-size:13px;color:#333">' . esc_html($reason) . '</td></tr>'
            . '</table>';

        SS_Mail::send($user->user_email, "{$school} — Paskyra sustabdyta", [
            'title'     => 'Paskyra sustabdyta',
            'accent'    => SS_Mail::RED,
            'icon'      => '🚫',
            'body'      => '<p style="margin:0 0 14px;">Sveiki, <strong>' . esc_html($user->display_name) . '</strong>,</p>'
                         . '<p style="margin:0;">jūsų paskyra sistemoje <strong>' . esc_html($school) . '</strong> buvo sustabdyta.</p>',
            'extra'     => $info_html,
            'note'      => 'Jei manote, kad tai klaida — kreipkitės į mokyklos administratorių.',
            'note_type' => 'warn',
        ]);
    }

    /* ═══════════════════════════════════════════════════════
       SESIJŲ VALDYMAS
    ═══════════════════════════════════════════════════════ */

    public static function get_sessions(int $user_id): array {
        // SVARBU: WP_Session_Tokens::get_all() viduje kviečia array_values(), todėl
        // praranda sha256 hash raktus, kurių reikia konkrečios sesijos panaikinimui.
        // Todėl skaitome user meta TIESIOGIAI ir išsaugome hash raktą kaip „verifier".
        $sessions = get_user_meta($user_id, 'session_tokens', true);
        if (!is_array($sessions)) $sessions = [];
        $now    = time();
        $result = [];
        foreach ($sessions as $hash => $session) {
            if (!is_array($session)) continue;
            if (!empty($session['expiration']) && $session['expiration'] < $now) continue;
            $ua    = $session['ua'] ?? ($session['user-agent'] ?? '');
            $ip    = $session['ip'] ?? '';
            $since = $session['login'] ?? 0;
            $result[] = [
                'verifier'   => (string) $hash,
                'ip'         => $ip,
                'geo'        => class_exists('SS_AuditLog') ? SS_AuditLog::geo_label($ip) : '',
                'ua'         => mb_substr($ua, 0, 160),
                'device'     => class_exists('SS_AuditLog') ? SS_AuditLog::device_label($ua) : mb_substr($ua, 0, 60),
                'login'      => $since,
                'login_fmt'  => $since ? wp_date('Y-m-d H:i', $since) : '—',
                'expiration' => $session['expiration'] ?? 0,
                'exp_fmt'    => !empty($session['expiration']) ? wp_date('Y-m-d H:i', $session['expiration']) : '—',
            ];
        }
        usort($result, fn($a, $b) => $b['login'] <=> $a['login']);
        return $result;
    }

    public static function destroy_session(int $user_id, string $verifier): void {
        // Triname tiesiai iš meta pagal hash raktą (žr. get_sessions paaiškinimą).
        $sessions = get_user_meta($user_id, 'session_tokens', true);
        if (is_array($sessions) && array_key_exists($verifier, $sessions)) {
            unset($sessions[$verifier]);
            update_user_meta($user_id, 'session_tokens', $sessions);
            return;
        }
        // Atsarginis variantas (jei perduotas tikras tokenas, o ne hash)
        WP_Session_Tokens::get_instance($user_id)->destroy($verifier);
    }

    public static function destroy_all_sessions(int $user_id): void {
        WP_Session_Tokens::get_instance($user_id)->destroy_all();
    }

    /* ═══════════════════════════════════════════════════════
       ĮRENGINIŲ VALDYMAS
    ═══════════════════════════════════════════════════════ */

    public static function get_devices(int $user_id): array {
        $history = (array) get_user_meta($user_id, 'ss_login_history', true);
        $blocked = (array) get_user_meta($user_id, 'ss_blocked_devices', true);
        $seen    = [];
        $result  = [];
        foreach ($history as $entry) {
            $fp = $entry['fp'] ?? '';
            if (!$fp || isset($seen[$fp])) continue;
            $seen[$fp] = true;
            $ua  = $entry['ua'] ?? '';
            $ip  = $entry['ip'] ?? '';
            $geo = sanitize_text_field($entry['geo'] ?? '');
            if ($geo === '' && $ip !== '' && class_exists('SS_AuditLog')) {
                $geo = SS_AuditLog::geo_label($ip);
            }
            $result[] = [
                'fp'        => $fp,
                'ip'        => $ip,
                'geo'       => $geo,
                'ua'        => mb_substr($ua, 0, 160),
                'device'    => class_exists('SS_AuditLog') ? SS_AuditLog::device_label($ua) : mb_substr($ua, 0, 60),
                'last_seen' => $entry['ts'] ?? 0,
                'last_fmt'  => !empty($entry['ts']) ? wp_date('Y-m-d H:i', $entry['ts']) : '—',
                'method'    => $entry['method'] ?? 'password',
                'blocked'   => in_array($fp, $blocked, true),
            ];
        }
        usort($result, fn($a, $b) => $b['last_seen'] <=> $a['last_seen']);
        return $result;
    }

    public static function toggle_device_block(int $user_id, string $fp, bool $block): void {
        $blocked = (array) get_user_meta($user_id, 'ss_blocked_devices', true);
        if ($block) {
            if (!in_array($fp, $blocked, true)) $blocked[] = $fp;
        } else {
            $blocked = array_values(array_filter($blocked, fn($b) => $b !== $fp));
        }
        update_user_meta($user_id, 'ss_blocked_devices', $blocked);
    }

    public static function remove_device(int $user_id, string $fp): void {
        $history = (array) get_user_meta($user_id, 'ss_login_history', true);
        $history = array_values(array_filter($history, fn($e) => ($e['fp'] ?? '') !== $fp));
        update_user_meta($user_id, 'ss_login_history', $history);

        $known = (array) get_user_meta($user_id, 'ss_known_devices', true);
        $known = array_values(array_filter($known, fn($k) => $k !== $fp));
        update_user_meta($user_id, 'ss_known_devices', $known);

        self::toggle_device_block($user_id, $fp, false);
    }

    public static function clear_all_devices(int $user_id): void {
        delete_user_meta($user_id, 'ss_login_history');
        delete_user_meta($user_id, 'ss_known_devices');
        delete_user_meta($user_id, 'ss_blocked_devices');
    }

    /* ═══════════════════════════════════════════════════════
       2FA ADMIN VALDYMAS
    ═══════════════════════════════════════════════════════ */

    public static function admin_disable_2fa(int $user_id): void {
        delete_user_meta($user_id, 'ss_2fa_enabled');
        delete_user_meta($user_id, 'ss_2fa_secret');
        delete_user_meta($user_id, 'ss_2fa_last_counter');
        delete_user_meta($user_id, 'ss_2fa_backup_codes');
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('2fa_admin_disabled', [
                'user_id'  => $user_id,
                'by_admin' => get_current_user_id(),
            ]);
        }
    }

    public static function admin_enable_2fa(int $user_id): array {
        if (!class_exists('SS_2FA')) return ['error' => 'SS_2FA klasė nerasta.'];
        $secret = SS_2FA::generate_secret();
        update_user_meta($user_id, 'ss_2fa_secret',  $secret);
        update_user_meta($user_id, 'ss_2fa_enabled', 1);
        delete_user_meta($user_id, 'ss_2fa_last_counter');
        delete_user_meta($user_id, 'ss_2fa_backup_codes');
        if (class_exists('SS_AuditLog')) {
            SS_AuditLog::write('2fa_admin_enabled', [
                'user_id'  => $user_id,
                'by_admin' => get_current_user_id(),
            ]);
        }
        return ['secret' => $secret];
    }

    /* ═══════════════════════════════════════════════════════
       ROLIŲ / TEISIŲ APIBRĖŽIMAI
    ═══════════════════════════════════════════════════════ */

    public static function managed_roles(): array {
        return [
            'ss_student'    => ['label' => 'Mokinys',          'no_admin' => true,  'icon' => 'dashicons-welcome-learn-more', 'color' => '#1565C0', 'bg' => '#E3F2FD'],
            'ss_teacher'    => ['label' => 'Mokytojas',        'no_admin' => true,  'icon' => 'dashicons-admin-users', 'color' => '#6A1B9A', 'bg' => '#F3E5F5'],
            'ss_employee'   => ['label' => 'Darbuotojas',      'no_admin' => true,  'icon' => 'dashicons-id-alt', 'color' => '#E65100', 'bg' => '#FFF3E0'],
            'administrator' => ['label' => 'Administratorius', 'no_admin' => false, 'icon' => 'dashicons-shield', 'color' => '#880E4F', 'bg' => '#FCE4EC'],
        ];
    }

    public static function managed_caps(): array {
        return [
            'Plugin teisės' => [
                'ss_imp_access'           => 'IMP prieiga (Mokinys+IMP)',
                'ss_view_all_schedules'   => 'Matyti visų tvarkaraščius',
                'ss_view_imp_students'    => 'Matyti IMP mokinių sąrašus mokytojų tvarkaraščiuose',
                'ss_employee_access'      => 'Darbuotojo prieiga',
                'ss_manage_eurovizija'    => 'Valdyti „Ąžuolyno Eurovizija" balsavimą',
                'ss_manage_all'           => 'Valdyti visą programą (admin)',
            ],
            'WordPress teisės' => [
                'read'                    => 'Skaityti turinį',
                'edit_posts'              => 'Rašyti įrašus',
                'publish_posts'           => 'Skelbti įrašus',
                'edit_published_posts'    => 'Redaguoti paskelbtus įrašus',
                'upload_files'            => 'Įkelti failus į biblioteką',
                'edit_pages'              => 'Redaguoti puslapius',
                'manage_categories'       => 'Tvarkyti kategorijas',
                'edit_users'              => 'Redaguoti vartotojus',
                'create_users'            => 'Kurti vartotojus',
                'delete_users'            => 'Šalinti vartotojus',
                'manage_options'          => 'WP nustatymai (suteikia admin prieigą)',
            ],
        ];
    }

    public static function all_managed_cap_keys(): array {
        $all = [];
        foreach (self::managed_caps() as $caps) {
            foreach (array_keys($caps) as $key) $all[] = $key;
        }
        return $all;
    }

    /* ═══════════════════════════════════════════════════════
       VARTOTOJO TEISIŲ VALDYMAS
    ═══════════════════════════════════════════════════════ */

    /**
     * Grąžina tik papildomas vartotojo teises (ne iš rolės).
     */
    public static function get_user_extra_caps(int $user_id): array {
        $user = get_userdata($user_id);
        if (!$user) return [];

        // Surinkti visas rolės teises
        $role_caps = [];
        foreach ((array)$user->roles as $role_slug) {
            $role = get_role($role_slug);
            if ($role) {
                foreach ($role->capabilities as $cap => $grant) {
                    if ($grant) $role_caps[$cap] = true;
                }
            }
        }

        // Grąžinti tik valdomas teises, kurios nėra iš rolės
        $all_managed = self::all_managed_cap_keys();
        $extra = [];
        foreach ($all_managed as $cap) {
            $user_has = isset($user->caps[$cap]) && $user->caps[$cap];
            $from_role = isset($role_caps[$cap]) && $role_caps[$cap];
            // Pažymėti jei vartotojas turi individualiai (nepriklausomai nuo rolės)
            if (isset($user->caps[$cap])) {
                $extra[$cap] = (bool)$user->caps[$cap];
            } elseif ($from_role) {
                $extra[$cap] = true; // paveldėta iš rolės
            } else {
                $extra[$cap] = false;
            }
        }
        return $extra;
    }

    /**
     * Išsaugo papildomas vartotojo teises.
     * $caps_on — masyvas cap_key => true/false (tik valdomi caps)
     */
    public static function save_user_extra_caps(int $user_id, array $caps_on): void {
        $user = get_userdata($user_id);
        if (!$user) return;
        $all = self::all_managed_cap_keys();
        foreach ($all as $cap) {
            if (!empty($caps_on[$cap])) {
                $user->add_cap($cap, true);
            } else {
                $user->remove_cap($cap);
            }
        }
    }

    /* ═══════════════════════════════════════════════════════
       PASKYRŲ SĄRAŠAS / DETALĖS
    ═══════════════════════════════════════════════════════ */

    public static function get_all_accounts(string $role_filter = '', string $search = ''): array {
        $allowed_roles = ['ss_student', 'ss_teacher', 'ss_employee', 'administrator'];
        $args = [
            'number'  => -1,
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ];
        if ($role_filter && in_array($role_filter, $allowed_roles, true)) {
            $args['role'] = $role_filter;
        } else {
            $args['role__in'] = $allowed_roles;
        }
        if ($search) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }
        return get_users($args);
    }

    public static function get_account_detail(int $user_id): array {
        $user = get_userdata($user_id);
        if (!$user) return [];

        $classes   = SS_Database::get_user_classes($user_id);
        $class_ids = array_map(fn($r) => (int)$r->class_id, $classes);
        $block     = self::get_block_info($user_id);
        $is_2fa    = (bool) get_user_meta($user_id, 'ss_2fa_enabled', true);

        // Pridedame until_fmt jei blokas laikinis
        if ($block && $block['type'] === 'timed' && !empty($block['until'])) {
            $block['until_fmt'] = wp_date('Y-m-d H:i', $block['until']);
        }

        return [
            'id'           => $user_id,
            'login'        => $user->user_login,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
            'avatar'       => get_avatar_url($user_id, ['size' => 96, 'default' => 'mm']),
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'phone'        => get_user_meta($user_id, 'ss_phone', true) ?: '',
            'registered'   => $user->user_registered,
            'roles'        => (array)$user->roles,
            'class_ids'    => $class_ids,
            'block'        => $block ?: null,
            '2fa_enabled'  => $is_2fa,
            '2fa_backup'   => (int) count((array) get_user_meta($user_id, 'ss_2fa_backup_codes', true)),
            'sessions'     => self::get_sessions($user_id),
            'devices'      => self::get_devices($user_id),
            'extra_caps'   => self::get_user_extra_caps($user_id),
            'notify'       => self::get_user_notify($user_id),
        ];
    }

    /** Vartotojo pranešimų / privatumo nustatymai (rodomi „Paskyros" modale). */
    public static function get_user_notify(int $user_id): array {
        $cn = class_exists('SS_ChangeNotify')
            ? SS_ChangeNotify::get_settings($user_id)
            : ['on' => false, 'mode' => 'immediate', 'time' => '17:00'];
        return [
            'changes_on'   => (bool) $cn['on'],
            'changes_mode' => $cn['mode'],
            'changes_time' => $cn['time'],
            'new_device'   => (get_user_meta($user_id, 'ss_user_new_device_notify', true) !== '0'), // default on
            'anon_quiz'    => (bool) get_user_meta($user_id, 'ss_anon_quiz', true),
        ];
    }

    public static function get_role_label(WP_User $user): string {
        $labels = [
            'administrator' => 'Administratorius',
            'ss_teacher'    => 'Mokytojas',
            'ss_employee'   => 'Darbuotojas',
            'ss_student'    => 'Mokinys',
        ];
        foreach ($labels as $role => $label) {
            if (in_array($role, (array)$user->roles, true)) return $label;
        }
        return implode(', ', (array)$user->roles) ?: '—';
    }

    /* ═══════════════════════════════════════════════════════
       AJAX PAGALBINIS
    ═══════════════════════════════════════════════════════ */

    private static function verify(): void {
        if (!check_ajax_referer('ss_admin_nonce', 'nonce', false))
            wp_send_json_error(['message' => 'Saugumo klaida. Atnaujinkite puslapį.']);
        if (!current_user_can('manage_options'))
            wp_send_json_error(['message' => 'Neturite teisių.']);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX — PASKYRŲ SĄRAŠAS
    ═══════════════════════════════════════════════════════ */

    public static function ajax_admin_get_accounts(): void {
        self::verify();
        $role   = sanitize_text_field($_POST['role']   ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');
        $users  = self::get_all_accounts($role, $search);

        $all_classes = SS_Database::get_classes();
        $cls_map = []; $imp_class = [];
        foreach ($all_classes as $c) {
            $cls_map[(int)$c->id] = $c->name;
            if (!empty($c->is_imp)) $imp_class[(int)$c->id] = true;
        }

        // Batch to avoid N+1: prime the user-meta cache once, fetch all class
        // memberships in a single query (was 2+ queries per user before).
        $ids = array_map(static fn($u) => (int)$u->ID, $users);
        if ($ids) update_meta_cache('user', $ids);
        $classes_by_user = [];
        if ($ids) {
            global $wpdb;
            $in = implode(',', $ids); // all ints — safe
            foreach ($wpdb->get_results("SELECT user_id, class_id FROM {$wpdb->prefix}ss_user_classes WHERE user_id IN ($in)") as $r) {
                $classes_by_user[(int)$r->user_id][] = (int)$r->class_id;
            }
        }

        $out = [];
        foreach ($users as $u) {
            $uid         = (int)$u->ID;
            $block       = self::get_block_info($uid);          // served from primed meta cache
            $my_cids     = $classes_by_user[$uid] ?? [];
            $class_names = array_filter(array_map(static fn($cid) => $cls_map[$cid] ?? null, $my_cids));
            $imp = (bool) get_user_meta($uid, 'ss_imp_access', true);
            if (!$imp) { foreach ($my_cids as $cid) { if (isset($imp_class[$cid])) { $imp = true; break; } } }
            $out[] = [
                'id'           => $uid,
                'display_name' => $u->display_name,
                'login'        => $u->user_login,
                'email'        => $u->user_email,
                'role'         => self::get_role_label($u),
                'role_slug'    => $u->roles[0] ?? '',
                'avatar'       => get_avatar_url($uid, ['size' => 40, 'default' => 'mm']),
                'blocked'      => (bool)$block,
                'block_type'   => $block ? $block['type'] : null,
                'block_until'  => ($block && $block['type'] === 'timed') ? wp_date('Y-m-d H:i', $block['until']) : null,
                'classes'      => array_values($class_names),
                '2fa'          => (bool) get_user_meta($uid, 'ss_2fa_enabled', true),
                'imp'          => $imp,
            ];
        }
        wp_send_json_success(['accounts' => $out]);
    }

    public static function ajax_admin_get_account_detail(): void {
        self::verify();
        $uid = (int)($_POST['user_id'] ?? 0);
        if (!$uid) wp_send_json_error(['message' => 'Trūksta vartotojo ID.']);
        $detail = self::get_account_detail($uid);
        if (!$detail) wp_send_json_error(['message' => 'Vartotojas nerastas.']);
        $all_classes = SS_Database::get_classes();
        wp_send_json_success(['detail' => $detail, 'all_classes' => $all_classes]);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX — DUOMENŲ ATNAUJINIMAS
    ═══════════════════════════════════════════════════════ */

    public static function ajax_admin_update_account(): void {
        self::verify();
        $uid = (int)($_POST['user_id'] ?? 0);
        if (!$uid) wp_send_json_error(['message' => 'Trūksta vartotojo ID.']);

        $data = ['ID' => $uid];
        if (isset($_POST['first_name']))   $data['first_name']   = sanitize_text_field(wp_unslash($_POST['first_name']));
        if (isset($_POST['last_name']))    $data['last_name']     = sanitize_text_field(wp_unslash($_POST['last_name']));
        if (isset($_POST['display_name'])) $data['display_name'] = sanitize_text_field(wp_unslash($_POST['display_name']));

        if (isset($_POST['email'])) {
            $email = sanitize_email(wp_unslash($_POST['email']));
            if (!is_email($email)) wp_send_json_error(['message' => 'Neteisingas el. pašto adresas.']);
            $existing = email_exists($email);
            if ($existing && (int)$existing !== $uid) wp_send_json_error(['message' => 'Šis el. paštas jau naudojamas.']);
            $data['user_email'] = $email;
        }

        if (!empty($_POST['password'])) {
            $pw = wp_unslash($_POST['password']);
            if (class_exists('SS_Security')) {
                $err = SS_Security::validate_password($pw, $data['user_email'] ?? '');
                if ($err) wp_send_json_error(['message' => $err]);
            }
            $data['user_pass'] = $pw;
        }

        $res = wp_update_user($data);
        if (is_wp_error($res)) wp_send_json_error(['message' => $res->get_error_message()]);

        if (isset($_POST['phone'])) {
            update_user_meta($uid, 'ss_phone', sanitize_text_field(wp_unslash($_POST['phone'])));
        }

        // Tvarkaraščiai. „classes_provided“ vėliavėlė leidžia ir IŠVALYTI visus
        // tvarkaraščius (kai nepažymėta nieko — jQuery tuščio masyvo nesiunčia).
        if (isset($_POST['classes_provided']) || isset($_POST['class_ids'])) {
            $class_ids = array_map('intval', (array)($_POST['class_ids'] ?? []));
            SS_Database::set_user_classes($uid, $class_ids);
        }

        SS_AuditLog::write('account_updated', ['target_id' => $uid]);
        wp_send_json_success(['message' => 'Paskyra atnaujinta.']);
    }

    public static function ajax_admin_change_user_role(): void {
        self::verify();
        $uid  = (int)($_POST['user_id'] ?? 0);
        $role = sanitize_key($_POST['role'] ?? '');
        if (!in_array($role, ['ss_student', 'ss_teacher', 'ss_employee', 'administrator'], true))
            wp_send_json_error(['message' => 'Neleistina rolė.']);
        if ($uid === get_current_user_id() && $role !== 'administrator')
            wp_send_json_error(['message' => 'Negalite pakeisti savo rolės.']);
        (new WP_User($uid))->set_role($role);
        SS_AuditLog::write('role_changed', ['target_id' => $uid, 'role' => $role]);
        wp_send_json_success(['message' => 'Rolė pakeista į: ' . esc_html($role) . '.']);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX — 2FA
    ═══════════════════════════════════════════════════════ */

    public static function ajax_admin_toggle_2fa(): void {
        self::verify();
        $uid    = (int)($_POST['user_id'] ?? 0);
        $enable = (int)($_POST['enable']  ?? 0);
        if (!$uid) wp_send_json_error(['message' => 'Trūksta vartotojo ID.']);
        if ($enable) {
            $res = self::admin_enable_2fa($uid);
            if (!empty($res['error'])) wp_send_json_error(['message' => $res['error']]);
            SS_AuditLog::write('account_updated', ['target_id' => $uid, 'action' => '2fa_on']);
            wp_send_json_success(['message' => '2FA įjungta. Vartotojas turės sukonfigūruoti autentifikatorių.', 'secret' => $res['secret']]);
        } else {
            self::admin_disable_2fa($uid);
            SS_AuditLog::write('account_updated', ['target_id' => $uid, 'action' => '2fa_off']);
            wp_send_json_success(['message' => '2FA išjungta ir duomenys ištrinti.']);
        }
    }

    /* ═══════════════════════════════════════════════════════
       AJAX — SESIJOS
    ═══════════════════════════════════════════════════════ */

    public static function ajax_admin_destroy_session(): void {
        self::verify();
        $uid      = (int)($_POST['user_id']  ?? 0);
        $verifier = sanitize_text_field($_POST['verifier'] ?? '');
        if (!$uid || !$verifier) wp_send_json_error(['message' => 'Trūksta duomenų.']);
        self::destroy_session($uid, $verifier);
        wp_send_json_success(['message' => 'Sesija baigta.', 'sessions' => self::get_sessions($uid)]);
    }

    public static function ajax_admin_destroy_all_sessions(): void {
        self::verify();
        $uid = (int)($_POST['user_id'] ?? 0);
        if (!$uid) wp_send_json_error(['message' => 'Trūksta vartotojo ID.']);
        self::destroy_all_sessions($uid);
        wp_send_json_success(['message' => 'Visos sesijos baigtos.', 'sessions' => []]);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX — ĮRENGINIAI
    ═══════════════════════════════════════════════════════ */

    public static function ajax_admin_remove_device(): void {
        self::verify();
        $uid   = (int)($_POST['user_id'] ?? 0);
        $fp    = sanitize_text_field($_POST['fp'] ?? '');
        $block = isset($_POST['block']) ? (bool)$_POST['block'] : null;
        if (!$uid || !$fp) wp_send_json_error(['message' => 'Trūksta duomenų.']);

        if ($block !== null) {
            // Blokuoti / atblokuoti įrenginį
            self::toggle_device_block($uid, $fp, $block);
        } else {
            // Pašalinti iš sąrašo
            self::remove_device($uid, $fp);
        }
        wp_send_json_success(['message' => $block ? 'Įrenginys blokuotas.' : 'Įrenginys pašalintas.', 'devices' => self::get_devices($uid)]);
    }

    public static function ajax_admin_clear_devices(): void {
        self::verify();
        $uid = (int)($_POST['user_id'] ?? 0);
        if (!$uid) wp_send_json_error(['message' => 'Trūksta vartotojo ID.']);
        self::clear_all_devices($uid);
        wp_send_json_success(['message' => 'Visi įrenginiai išvalyti.', 'devices' => []]);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX — BLOKAVIMAS
    ═══════════════════════════════════════════════════════ */

    public static function ajax_admin_block_account(): void {
        self::verify();
        $uid    = (int)($_POST['user_id'] ?? 0);
        $type   = sanitize_text_field($_POST['type'] ?? 'indefinite');
        $until  = (int)($_POST['until'] ?? 0);
        $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));
        $notify = (bool)($_POST['notify'] ?? true);

        if (!$uid) wp_send_json_error(['message' => 'Trūksta vartotojo ID.']);
        if ($uid === get_current_user_id()) wp_send_json_error(['message' => 'Negalite blokuoti savo paskyros.']);
        if ($type === 'timed' && (!$until || $until <= time()))
            wp_send_json_error(['message' => 'Bloko pabaigos laikas turi būti ateityje.']);

        self::block_user($uid, $type, $until, $reason, $notify);
        $block = self::get_block_info($uid);
        if ($block && $block['type'] === 'timed' && !empty($block['until'])) {
            $block['until_fmt'] = wp_date('Y-m-d H:i', $block['until']);
        }
        wp_send_json_success(['message' => 'Paskyra užblokuota.', 'block' => $block]);
    }

    public static function ajax_admin_unblock_account(): void {
        self::verify();
        $uid = (int)($_POST['user_id'] ?? 0);
        if (!$uid) wp_send_json_error(['message' => 'Trūksta vartotojo ID.']);
        self::unblock_user($uid);
        wp_send_json_success(['message' => 'Paskyra atblokuota.']);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX — TEISĖS
    ═══════════════════════════════════════════════════════ */

    public static function ajax_admin_set_user_caps(): void {
        self::verify();
        $uid      = (int)($_POST['user_id'] ?? 0);
        $caps_arr = (array)($_POST['caps'] ?? []);   // aktyvių caps sąrašas
        if (!$uid) wp_send_json_error(['message' => 'Trūksta vartotojo ID.']);

        $all = self::all_managed_cap_keys();
        $caps_on = [];
        foreach ($all as $cap) {
            $caps_on[$cap] = in_array($cap, $caps_arr, true);
        }
        self::save_user_extra_caps($uid, $caps_on);
        SS_AuditLog::write('caps_changed', ['target_id' => $uid, 'scope' => 'user']);
        wp_send_json_success(['message' => 'Teisės išsaugotos.']);
    }

    public static function ajax_admin_save_role_caps(): void {
        self::verify();
        $role_slug = sanitize_key($_POST['role'] ?? '');
        $caps_arr  = (array)($_POST['caps'] ?? []);
        $editable  = ['ss_student', 'ss_teacher', 'ss_employee'];
        if (!in_array($role_slug, $editable, true))
            wp_send_json_error(['message' => 'Šios rolės redaguoti negalima.']);

        $role = get_role($role_slug);
        if (!$role) wp_send_json_error(['message' => 'Rolė nerasta.']);

        $all = self::all_managed_cap_keys();
        // Visada palikti 'read' mokiniams, mokytojams, darbuotojams
        foreach ($all as $cap) {
            if (in_array($cap, $caps_arr, true)) {
                $role->add_cap($cap, true);
            } else {
                $role->remove_cap($cap);
            }
        }
        // Užtikrinti 'read' visada lieka
        $role->add_cap('read', true);
        SS_AuditLog::write('caps_changed', ['scope' => 'role', 'role' => $role_slug]);
        wp_send_json_success(['message' => 'Teisės išsaugotos.']);
    }

    public static function ajax_admin_get_role_caps(): void {
        self::verify();
        $role_slug = sanitize_key($_POST['role'] ?? '');
        $role = get_role($role_slug);
        if (!$role) wp_send_json_error(['message' => 'Rolė nerasta.']);
        wp_send_json_success([
            'caps'     => $role->capabilities,
            'all_caps' => self::managed_caps(),
        ]);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX — VARTOTOJO PRANEŠIMAI (iš naudotojo nustatymų)
    ═══════════════════════════════════════════════════════ */
    public static function ajax_admin_save_user_notify(): void {
        self::verify();
        $uid = (int)($_POST['user_id'] ?? 0);
        if (!$uid || !get_userdata($uid)) wp_send_json_error(['message' => 'Vartotojas nerastas.']);

        // Pakeitimų pranešimai (su režimu / laiku)
        $changes_on   = !empty($_POST['changes_on'])   && $_POST['changes_on']   !== '0';
        $changes_mode = ($_POST['changes_mode'] ?? 'immediate') === 'time' ? 'time' : 'immediate';
        $changes_time = class_exists('SS_ChangeNotify')
            ? SS_ChangeNotify::clamp_time(sanitize_text_field($_POST['changes_time'] ?? '17:00'))
            : '17:00';
        update_user_meta($uid, 'ss_changes_notify',      $changes_on ? '1' : '0');
        update_user_meta($uid, 'ss_changes_notify_mode', $changes_mode);
        update_user_meta($uid, 'ss_changes_notify_time', $changes_time);

        // Naujo įrenginio prisijungimo pranešimai
        $new_device = !empty($_POST['new_device']) && $_POST['new_device'] !== '0';
        update_user_meta($uid, 'ss_user_new_device_notify', $new_device ? '1' : '0');

        // Privatumas viktorinose (slėpti vardą)
        $anon_quiz = !empty($_POST['anon_quiz']) && $_POST['anon_quiz'] !== '0';
        update_user_meta($uid, 'ss_anon_quiz', $anon_quiz ? '1' : '');

        wp_send_json_success([
            'message' => 'Pranešimų nustatymai išsaugoti.',
            'notify'  => self::get_user_notify($uid),
        ]);
    }
}
