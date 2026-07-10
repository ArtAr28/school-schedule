<?php
/**
 * SS_ChangeNotify — pakeitimų (pamokų pakeitimų) pranešimai el. paštu.
 *
 * Vartotojas nustatymuose įsijungia pranešimą apie KITOS dienos pakeitimus.
 * Laiškas: tik jam aktualūs pakeitimai (pamoka kaip dienos rodinyje + pakeitimas)
 * ir bendras dienos komentaras.
 *
 * Siuntimo laikas:
 *   - „immediate" — iškart po paskelbimo;
 *   - „time"      — nurodytu laiku (16:00–21:00). Jei tuo metu dar nepaskelbta —
 *                   išsiunčiama iškart po paskelbimo; jei iki 21:00 vis tiek
 *                   nepaskelbta — siunčiamas pranešimas „dar nepaskelbta".
 *
 * Vartotojo meta:
 *   ss_changes_notify        '1'|'0'
 *   ss_changes_notify_mode   'immediate'|'time'
 *   ss_changes_notify_time   'HH:MM'  (16:00–21:00)
 *   ss_changes_notify_last   'Y-m-d'  (paskutinė diena, dėl kurios išsiųsta — apsauga nuo dubliavimo)
 */
if (!defined('ABSPATH')) exit;

class SS_ChangeNotify {

    const CRON_HOOK     = 'ss_ch_notify_cron';      // pasikartojantis (kas 15 min)
    const RUN_HOOK      = 'ss_ch_notify_run';       // vienkartinis, po paskelbimo
    const CORR_HOOK     = 'ss_ch_notify_corr';      // vienkartinis, po korekcijų (arg: data)
    const SCHEDULE      = 'ss_ch_15min';
    const CUTOFF_HOUR   = 21;                        // vėliausias siuntimo laikas
    const MIN_HOUR      = 16;                        // anksčiausias „time" laikas
    const CORR_DELAY    = 300;                        // korekcijų laiškų debounce (s)
    const BATCH_LIMIT   = 60;                         // daugiausiai laiškų per vieną cron'o paleidimą (anti-DoS, ~700 gavėjų)
    const DRAIN_DELAY   = 60;                         // (s) iki kito „drain" paleidimo, kai liko nepasiųstų

    public static function init(): void {
        add_filter('cron_schedules', [__CLASS__, 'add_schedule']);
        add_action(self::CRON_HOOK, [__CLASS__, 'dispatch']);
        add_action(self::RUN_HOOK,  [__CLASS__, 'dispatch']);
        add_action(self::CORR_HOOK, [__CLASS__, 'dispatch_corrections'], 10, 1);
        add_action('wp_ajax_ss_settings_save_changes_notify', [__CLASS__, 'ajax_save']);
        add_action('wp_ajax_ss_ch_notify_status', [__CLASS__, 'ajax_status']); // admin „Pakeitimai" būsenos juosta
        self::maybe_schedule(); // init() jau vykdoma per „init" kabliuką
    }

    public static function add_schedule($s) {
        if (!isset($s[self::SCHEDULE])) {
            $s[self::SCHEDULE] = ['interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'Kas 15 minučių (pakeitimų pranešimai)'];
        }
        return $s;
    }

    public static function maybe_schedule(): void {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, self::SCHEDULE, self::CRON_HOOK);
        }
    }

    public static function unschedule(): void {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) wp_unschedule_event($ts, self::CRON_HOOK);
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::RUN_HOOK);
        wp_clear_scheduled_hook(self::CORR_HOOK);
    }

    /* ── Vartotojo nustatymai ──────────────────────────────────────── */

    public static function get_settings(int $uid): array {
        $on   = get_user_meta($uid, 'ss_changes_notify', true) === '1';
        $mode = get_user_meta($uid, 'ss_changes_notify_mode', true) ?: 'immediate';
        $time = get_user_meta($uid, 'ss_changes_notify_time', true) ?: '17:00';
        if (!in_array($mode, ['immediate', 'time'], true)) $mode = 'immediate';
        return ['on' => $on, 'mode' => $mode, 'time' => self::clamp_time($time)];
    }

    /** Apriboja laiką iki 16:00–21:00, suapvalina iki 15 min ir grąžina 'HH:MM'. */
    public static function clamp_time(string $t): string {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($t), $m)) return '17:00';
        $min = (int)$m[1] * 60 + (int)$m[2];
        $min = (int) (round($min / 15) * 15);                         // tik 15 min žingsniai
        $min = max(self::MIN_HOUR * 60, min(self::CUTOFF_HOUR * 60, $min));
        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }

    /** Galimi siuntimo laikai (16:00–21:00, kas 15 min) — UI sąrašui. */
    public static function time_options(): array {
        $out = [];
        for ($min = self::MIN_HOUR * 60; $min <= self::CUTOFF_HOUR * 60; $min += 15) {
            $out[] = sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
        }
        return $out;
    }

    public static function ajax_save(): void {
        check_ajax_referer('ss_front_nonce', 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(['message' => 'Nesate prisijungęs.']);
        $uid  = get_current_user_id();
        $on   = !empty($_POST['on']) && $_POST['on'] !== 'false' && $_POST['on'] !== '0';
        $mode = ($_POST['mode'] ?? 'immediate') === 'time' ? 'time' : 'immediate';
        $time = self::clamp_time(sanitize_text_field($_POST['time'] ?? '17:00'));
        update_user_meta($uid, 'ss_changes_notify', $on ? '1' : '0');
        update_user_meta($uid, 'ss_changes_notify_mode', $mode);
        update_user_meta($uid, 'ss_changes_notify_time', $time);
        wp_send_json_success(['on' => $on ? 1 : 0, 'mode' => $mode, 'time' => $time]);
    }

    /* ── Paskelbimo kabliukas (kviečiama iš admin „Paskelbti") ─────── */
    public static function on_publish(string $date): void {
        // Atidedam į vienkartinį cron'ą (kad admin atsakas neužstrigtų siunčiant laiškus).
        // RUN_HOOK — pirmas pranešimas dar negavusiems; CORR_HOOK — korekcijos
        // jau gavusiems (jei turinys keitėsi nuo praėjusio pranešimo, pvz. taisyta neviešai).
        if (!wp_next_scheduled(self::RUN_HOOK)) {
            wp_schedule_single_event(time() + 5, self::RUN_HOOK);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && !wp_next_scheduled(self::CORR_HOOK, [$date])) {
            wp_schedule_single_event(time() + 6, self::CORR_HOOK, [$date]);
        }
    }

    /* ── Redagavimo kabliukas (pakeitimas/komentaras/neatvykimas pakeistas) ─
     * Jei diena jau paskelbta — suplanuojam korekcijų laiškus jau pranešusiems. */
    public static function on_edit(string $date): void {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return;
        update_option('ss_changes_edited_' . $date, time(), false);
        if (!class_exists('SS_Database') || !SS_Database::is_changes_published($date)) return;

        // „Atrišam" tuos, kurie buvo apdoroti BE laiško (neturėjo aktualių pakeitimų),
        // kad pridėjus jiems aktualų pakeitimą gautų PIRMĄ pranešimą (ne korekciją).
        $last = get_users(['meta_key' => 'ss_changes_notify_last', 'meta_value' => $date, 'fields' => 'ID', 'number' => -1]);
        if ($last) {
            $sent = get_users(['meta_key' => 'ss_changes_notify_sent', 'meta_value' => $date, 'fields' => 'ID', 'number' => -1]);
            foreach (array_diff($last, $sent) as $uid) {
                delete_user_meta((int)$uid, 'ss_changes_notify_last');
            }
        }

        if (!wp_next_scheduled(self::CORR_HOOK, [$date])) {
            wp_schedule_single_event(time() + self::CORR_DELAY, self::CORR_HOOK, [$date]); // debounce
        }
        if (!wp_next_scheduled(self::RUN_HOOK)) {
            wp_schedule_single_event(time() + 5, self::RUN_HOOK); // dar negavusiems (jei tai artimiausia diena)
        }
    }

    /* ── Pagrindinė siuntimo logika ────────────────────────────────── */
    public static function dispatch(): void {
        if (!class_exists('SS_Database')) return;

        $now    = SS_Time::now();                 // DateTime vietos laiku
        $nowMin = (int)$now->format('G') * 60 + (int)$now->format('i');
        $hour   = (int)$now->format('G');

        $target = self::next_school_day($now);    // 'Y-m-d' — kitos dienos pamokos
        if (!$target) return;

        $published = SS_Database::is_changes_published($target);

        // Vienu metu gali būti ~700 gavėjų. Užkraunam visų user-meta vienu kvietimu
        // (kitaip — N+1 get_user_meta užklausų) ir siunčiam DALIMIS: ne daugiau
        // BATCH_LIMIT laiškų per paleidimą; likusius tęsiam „drain" cron'e. Per-vartotojo
        // „ss_changes_notify_last" žyma užtikrina, kad kitas paleidimas tęstų be dubliavimo.
        $users = self::eligible_users();
        $ids   = array_map(static fn($u) => (int) $u->ID, $users);
        if ($ids) update_meta_cache('user', $ids);

        $sent_run  = 0;
        $truncated = false;

        foreach ($users as $user) {
            if ($sent_run >= self::BATCH_LIMIT) { $truncated = true; break; }

            $uid = (int)$user->ID;
            if (get_user_meta($uid, 'ss_changes_notify_last', true) === $target) continue; // jau pranešta

            $st = self::get_settings($uid);
            if (!$st['on']) continue;

            if ($st['mode'] === 'immediate') {
                if ($published) {
                    if (self::send_changes($user, $target)) { self::mark_sent($uid, $target); $sent_run++; }
                    else self::mark($uid, $target); // apdorota, bet nieko aktualaus — be laiško
                }
                // nepaskelbta → laukiam paskelbimo įvykio
                continue;
            }

            // mode === 'time'
            list($th, $tm) = array_map('intval', explode(':', $st['time']));
            $userMin = $th * 60 + $tm;
            if ($nowMin < $userMin) continue;     // dar ne laikas

            if ($published) {
                if (self::send_changes($user, $target)) { self::mark_sent($uid, $target); $sent_run++; }
                else self::mark($uid, $target);
            } elseif ($hour >= self::CUTOFF_HOUR) {
                self::send_not_published($user, $target);
                self::mark_sent($uid, $target); // gavo laišką (nors „nėra pakeitimų")
                $sent_run++;
            }
            // kitu atveju (laikas atėjo, bet dar nepaskelbta, ne 21:00) — laukiam
        }

        // Pasiekėm batch'o ribą — dar liko gavėjų; tęsiam netrukus (be dubliavimo).
        if ($truncated && !wp_next_scheduled(self::RUN_HOOK)) {
            wp_schedule_single_event(time() + self::DRAIN_DELAY, self::RUN_HOOK);
        }
    }

    /* Apdorota (be laiško) — kad nekartotume vertinimo tą pačią dieną. */
    private static function mark(int $uid, string $date): void {
        update_user_meta($uid, 'ss_changes_notify_last', $date);
    }

    /* Išsiųstas pirmas laiškas — įsimenam, kad vėliau galėtume siųsti korekcijas. */
    private static function mark_sent(int $uid, string $date): void {
        update_user_meta($uid, 'ss_changes_notify_last', $date);
        update_user_meta($uid, 'ss_changes_notify_sent', $date);
        update_user_meta($uid, 'ss_changes_corr_ts', time());
    }

    /* ── Korekcijų siuntimas (tik jau gavusiems pirmą pranešimą) ───── */
    public static function dispatch_corrections(string $date): void {
        if (!class_exists('SS_Database')) return;
        if (!SS_Database::is_changes_published($date)) return; // korekcijos tik viešai paskelbtoms
        $editedTs = (int) get_option('ss_changes_edited_' . $date, 0);
        if (!$editedTs) return;

        $users = get_users([
            'meta_key'   => 'ss_changes_notify_sent',
            'meta_value' => $date,
            'number'     => -1,
        ]);
        $ids = array_map(static fn($u) => (int) $u->ID, $users);
        if ($ids) update_meta_cache('user', $ids);

        $sent_run  = 0;
        $truncated = false;
        foreach ($users as $user) {
            if ($sent_run >= self::BATCH_LIMIT) { $truncated = true; break; }
            $uid = (int) $user->ID;
            if (!self::get_settings($uid)['on']) continue;            // išjungė — negąsdinam
            $corrTs = (int) get_user_meta($uid, 'ss_changes_corr_ts', true);
            if ($editedTs <= $corrTs) continue;                       // nuo paskutinio karto nepasikeitė
            self::send_changes($user, $date, true);                   // „Koreguoti pakeitimai" (siunčiam visada)
            update_user_meta($uid, 'ss_changes_corr_ts', $editedTs);
            $sent_run++;
        }

        // Liko dar nepasiųstų korekcijų — tęsiam netrukus.
        if ($truncated && !wp_next_scheduled(self::CORR_HOOK, [$date])) {
            wp_schedule_single_event(time() + self::DRAIN_DELAY, self::CORR_HOOK, [$date]);
        }
    }

    /* ── Siuntimo būsena (admin „Pakeitimai" juostai) ──────────────── */

    /** COUNT(user_id) iš usermeta pagal raktą/reikšmę — be 700 vartotojų krovimo. */
    private static function count_user_meta(string $key, string $value): int {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(user_id) FROM {$wpdb->usermeta} WHERE meta_key=%s AND meta_value=%s",
            $key, $value
        ));
    }

    /**
     * Kitos mokslo dienos pranešimų siuntimo būsena (kiek įjungę, išsiųsta, laukia,
     * kada kita siuntimo banga). Skaičiuojama agreguotomis SQL užklausomis.
     */
    public static function sending_status(): array {
        global $wpdb;
        $target    = class_exists('SS_Time') ? self::next_school_day(SS_Time::now()) : null;
        $published = ($target && class_exists('SS_Database')) ? SS_Database::is_changes_published($target) : false;

        $total_on  = self::count_user_meta('ss_changes_notify', '1');
        $sent      = $target ? self::count_user_meta('ss_changes_notify_sent', $target) : 0;
        $processed = $target ? self::count_user_meta('ss_changes_notify_last', $target) : 0;
        $pending   = max(0, $total_on - $processed);

        // „time" režimo gavėjai, dar neapdoroti šiai dienai — sugrupuoti pagal laiką.
        $time_buckets = [];
        if ($target && $pending > 0) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT t.meta_value AS send_time, COUNT(*) AS cnt
                 FROM {$wpdb->usermeta} onm
                 JOIN {$wpdb->usermeta} md ON md.user_id = onm.user_id
                      AND md.meta_key='ss_changes_notify_mode' AND md.meta_value='time'
                 JOIN {$wpdb->usermeta} t  ON t.user_id = onm.user_id
                      AND t.meta_key='ss_changes_notify_time'
                 LEFT JOIN {$wpdb->usermeta} lst ON lst.user_id = onm.user_id
                      AND lst.meta_key='ss_changes_notify_last' AND lst.meta_value=%s
                 WHERE onm.meta_key='ss_changes_notify' AND onm.meta_value='1'
                   AND lst.umeta_id IS NULL
                 GROUP BY t.meta_value
                 ORDER BY t.meta_value ASC",
                $target
            ));
            foreach ((array) $rows as $r) {
                $k = self::clamp_time((string) $r->send_time);
                $time_buckets[$k] = ($time_buckets[$k] ?? 0) + (int) $r->cnt;
            }
        }
        $immediate_pending = max(0, $pending - array_sum($time_buckets));

        $next_ts  = wp_next_scheduled(self::RUN_HOOK) ?: wp_next_scheduled(self::CRON_HOOK);
        $next_run = ($next_ts && class_exists('SS_Time')) ? SS_Time::date('H:i', $next_ts) : '';

        return [
            'target'            => $target,
            'target_label'      => $target ? self::date_label($target) : '',
            'published'         => $published,
            'total_on'          => $total_on,
            'sent'              => $sent,
            'processed'         => $processed,
            'pending'           => $pending,
            'immediate_pending' => $immediate_pending,
            'time_buckets'      => $time_buckets,
            'next_run'          => $next_run,
            'batch_limit'       => self::BATCH_LIMIT,
        ];
    }

    /** Būsenos juostos vidinis HTML (vienas šaltinis – PHP renderiui ir AJAX atnaujinimui). */
    public static function status_html(array $ns): string {
        $html  = '<span class="ss-ns-counts">'
               . 'Įjungę: <strong>' . (int) $ns['total_on'] . '</strong> · '
               . 'Išsiųsta: <strong>' . (int) $ns['sent'] . '</strong> · '
               . 'Laukia: <strong>' . (int) $ns['pending'] . '</strong>'
               . '</span>';

        if (empty($ns['published'])) {
            $html .= '<span class="ss-ns-note">Dar nepaskelbta — siuntimas prasidės paskelbus.</span>';
        } else {
            $note = '';
            if ((int) $ns['immediate_pending'] > 0) {
                $note .= 'Iškart siunčiama: <strong>' . (int) $ns['immediate_pending'] . '</strong> · ';
            }
            $note .= 'dalimis po ' . (int) $ns['batch_limit'];
            if ($ns['next_run'] !== '') {
                $note .= ', kita banga ~' . esc_html($ns['next_run']);
            }
            $html .= '<span class="ss-ns-note">' . $note . '</span>';
        }

        if (!empty($ns['time_buckets'])) {
            $parts = [];
            foreach ($ns['time_buckets'] as $time => $cnt) {
                $parts[] = esc_html($time) . ' — ' . (int) $cnt;
            }
            $html .= '<span class="ss-ns-note">Pagal pasirinktą laiką: ' . implode(' · ', $parts) . '</span>';
        }
        return $html;
    }

    public static function ajax_status(): void {
        if (!check_ajax_referer('ss_admin_nonce', 'nonce', false)) wp_send_json_error(['message' => 'Saugumo klaida.']);
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Nėra teisių.']);
        $ns = self::sending_status();
        wp_send_json_success([
            'published'    => $ns['published'],
            'target_label' => $ns['target_label'],
            'html'         => self::status_html($ns),
        ]);
    }

    /** @return WP_User[] */
    private static function eligible_users(): array {
        return get_users([
            'meta_key'   => 'ss_changes_notify',
            'meta_value' => '1',
            'number'     => -1,
        ]);
    }

    /** Kita mokslo (darbo) diena po nurodytos datos. */
    public static function next_school_day(DateTime $from): ?string {
        $dt = clone $from;
        for ($i = 0; $i < 8; $i++) {
            $dt->modify('+1 day');
            $dow = (int)$dt->format('N');
            if ($dow >= 1 && $dow <= 5) return $dt->format('Y-m-d');
        }
        return null;
    }

    /* ── Asmeninis dienos pakeitimų sąrašas (kaip dienos rodinyje) ─── */
    public static function user_day_items(int $uid, string $date): array {
        $tz  = SS_Time::tz();
        $dt  = new DateTime($date, $tz);
        $dow = (int)$dt->format('N');
        if ($dow < 1 || $dow > 5) return [];

        $monday = (clone $dt)->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');

        $sched    = SS_Database::get_user_full_schedule($uid);
        $grid     = $sched['grid']    ?? [];
        $periods  = $sched['periods'] ?? [];
        $changes  = SS_Database::get_week_changes_map($monday);
        $absent   = SS_Database::get_week_absent_map($monday);
        $sg_prefs = SS_Database::get_user_subgroup_prefs($uid);

        $items = [];
        foreach ($periods as $p) {
            $entries = $grid[$p->id][$dow] ?? [];
            if (empty($entries)) continue;

            $class_id  = (int)($entries[0]->class_id ?? 0);
            $chosen_id = $sg_prefs["{$class_id}_{$dow}_{$p->id}"] ?? null;
            $shown     = $entries[0];
            if ($chosen_id) {
                foreach ($entries as $e) { if ((int)$e->id === (int)$chosen_id) { $shown = $e; break; } }
            }
            $is_free = !empty($shown->is_free) || (int)($shown->lesson_id ?? 1) === 0;

            // Mokytojo neatvykimas
            $cancelled = false;
            $tid = $is_free ? 0 : (int)($shown->lesson_teacher_id ?? 0);
            if (!$is_free && $tid && isset($absent[$dow][$tid])) {
                $from_p = (int)$absent[$dow][$tid];
                if ($from_p === 0 || (int)$p->period_number >= $from_p) $cancelled = true;
            }

            // Pakeitimas (pirmiausia konkrečiam pogrupiui, tada visiems)
            $sg = $shown->subgroup_label ?? '';
            $cell = $is_free ? [] : ($changes[$dow][$class_id][$p->id] ?? []);
            $comment = $cell[$sg] ?? $cell[''] ?? null;
            $has_change = ($comment !== null && $comment !== '');

            if (!$has_change && !$cancelled) continue;

            $teacher = $is_free ? '' : (!empty($shown->teacher_name) ? $shown->teacher_name : ($shown->teacher_short ?? ''));
            $items[] = [
                'period'    => (int)$p->period_number,
                'start'     => substr((string)$p->start_time, 0, 5),
                'end'       => substr((string)$p->end_time,   0, 5),
                'subject'   => $is_free ? 'Nėra pamokos' : (string)$shown->subject,
                'teacher'   => (string)$teacher,
                'room'      => $is_free ? '' : (string)($shown->room ?? ''),
                'change'    => $has_change ? (string)$comment : ($cancelled ? 'Mokytojo nebus' : ''),
            ];
        }
        return $items;
    }

    /* ── Laiškai ──────────────────────────────────────────────────── */

    private static function date_label(string $date): string {
        $w = ['', 'pirmadienis', 'antradienis', 'trečiadienis', 'ketvirtadienis', 'penktadienis', 'šeštadienis', 'sekmadienis'];
        $dt = new DateTime($date, SS_Time::tz());
        return $dt->format('m-d') . ' (' . ($w[(int)$dt->format('N')] ?? '') . ')';
    }

    private static function send_changes(WP_User $user, string $date, bool $corrected = false): bool {
        if (!class_exists('SS_Mail')) return false;
        $items   = self::user_day_items((int)$user->ID, $date);
        $comment = get_option('ss_changes_comment_' . $date, '');

        // Pirmas pranešimas — jei nieko aktualaus, nesiunčiame. Korekcijos — siunčiamos
        // visada (svarbu pranešti ir tada, kai anksčiau skelbtas pakeitimas atšauktas).
        if (!$corrected && empty($items) && trim((string)$comment) === '') return false;

        $school = SS_Mail::school();
        $label  = self::date_label($date);

        $body  = '<p style="margin:0 0 14px;">Sveiki, <strong>' . esc_html($user->display_name) . '</strong>,</p>';
        $body .= $corrected
            ? '<p style="margin:0 0 18px;">Pakoreguoti <strong>' . esc_html($label) . '</strong> pamokų pakeitimai:</p>'
            : '<p style="margin:0 0 18px;">Pamokų pakeitimai <strong>' . esc_html($label) . '</strong>:</p>';

        if (!empty($items)) {
            $body .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin:0 0 8px;">';
            foreach ($items as $it) {
                $meta = trim($it['subject']
                    . ($it['teacher'] ? ' · ' . $it['teacher'] : '')
                    . ($it['room'] ? ' · ' . $it['room'] : ''));
                $body .= '<tr>'
                    . '<td style="padding:10px 12px;border:1px solid #e2e8e2;border-radius:0;background:#f7faf7;width:54px;vertical-align:top;">'
                    . '<div style="font-size:16px;font-weight:800;color:#012A36;line-height:1;">' . (int)$it['period'] . '</div>'
                    . '<div style="font-size:11px;color:#6FA672;margin-top:3px;">' . esc_html($it['start']) . '<br>' . esc_html($it['end']) . '</div>'
                    . '</td>'
                    . '<td style="padding:10px 12px;border:1px solid #e2e8e2;border-left:none;vertical-align:top;">'
                    . '<div style="font-size:13px;color:#012A36;font-weight:600;">' . esc_html($meta) . '</div>'
                    . '<div style="font-size:13px;color:#D05155;font-weight:700;margin-top:4px;">' . esc_html($it['change']) . '</div>'
                    . '</td>'
                    . '</tr>';
            }
            $body .= '</table>';
        } else {
            $body .= $corrected
                ? '<p style="margin:0 0 8px;color:#4a5568;">Šiuo metu Jūsų pamokoms pakeitimų nebėra.</p>'
                : '<p style="margin:0 0 8px;color:#4a5568;">Jūsų pamokoms pakeitimų nėra.</p>';
        }

        $extra = '';
        if (trim((string)$comment) !== '') {
            $extra = SS_Mail::notice('<strong>Bendras komentaras:</strong><br>' . wp_kses_post($comment), 'info');
        }

        $changes_url = ($cid = get_option('ss_changes_page_id')) ? get_permalink($cid) : '';
        $opts = [
            'title'    => $corrected ? 'Koreguoti pakeitimai' : 'Rytojaus pakeitimai',
            'subtitle' => self::date_label($date),
            'accent'   => SS_Mail::NAVY,
            'icon'     => $corrected ? '~' : '!',
            'body'     => $body,
            'extra'    => $extra,
            'note'     => 'Šį pranešimą galite išjungti sistemos nustatymuose.',
            'note_type'=> 'info',
        ];
        if ($changes_url) $opts['button'] = ['text' => 'Peržiūrėti pakeitimus', 'url' => $changes_url];

        $subj = ($corrected ? 'Koreguoti pakeitimai ' : 'Pakeitimai ') . self::date_label($date);
        return (bool) SS_Mail::send($user->user_email, $school . ' — ' . $subj, $opts);
    }

    private static function send_not_published(WP_User $user, string $date): bool {
        if (!class_exists('SS_Mail')) return false;
        $school = SS_Mail::school();
        $body  = '<p style="margin:0 0 14px;">Sveiki, <strong>' . esc_html($user->display_name) . '</strong>,</p>';
        $body .= '<p style="margin:0;">Pamokų pakeitimai <strong>' . esc_html(self::date_label($date)) . '</strong> dar nepaskelbti — '
               . 'greičiausiai jų nėra.</p>';
        return (bool) SS_Mail::send($user->user_email, $school . ' — Pakeitimai ' . self::date_label($date), [
            'title'     => 'Pakeitimų nėra',
            'subtitle'  => $school,
            'icon'      => 'i',
            'body'      => $body,
            'note'      => 'Šį pranešimą galite išjungti sistemos nustatymuose.',
            'note_type' => 'info',
        ]);
    }
}
