<?php
if (!defined('ABSPATH')) exit;

/**
 * SS_SystemIO — Export / Import visų sistemos duomenų
 * Formatas: .azuolynotvs (JSON su metadata)
 */
class SS_SystemIO {

    const FORMAT  = 'azuolynotvs';
    const VERSION = '1.0';

    // ══════════════════════════════════════════════════════════════════════════
    // EKSPORTAS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Surinkti visus norimus duomenis ir grąžinti masyvą.
     * $sections = ['teachers','lessons','schedules','students']  (bet koks porinkinys)
     */
    public static function export(array $sections): array {
        global $wpdb; $p = $wpdb->prefix;

        $data = [];

        if (in_array('teachers', $sections, true)) {
            $rows = $wpdb->get_results("SELECT name,short,email,color FROM {$p}ss_teachers ORDER BY name", ARRAY_A);
            $data['teachers'] = $rows ?: [];
        }

        if (in_array('lessons', $sections, true)) {
            $rows = $wpdb->get_results(
                "SELECT l.subject, COALESCE(t.name,'') AS teacher_name, l.color
                 FROM {$p}ss_lessons l
                 LEFT JOIN {$p}ss_teachers t ON t.id = l.teacher_id
                 ORDER BY l.subject", ARRAY_A
            );
            $data['lessons'] = $rows ?: [];
        }

        if (in_array('schedules', $sections, true)) {
            // Periods
            $periods = $wpdb->get_results(
                "SELECT period_number, start_time, end_time
                 FROM {$p}ss_periods ORDER BY period_number", ARRAY_A
            );

            // Classes + their schedule entries
            $classes = $wpdb->get_results(
                "SELECT id, name, color, is_imp FROM {$p}ss_classes ORDER BY name", ARRAY_A
            );

            $class_list = [];
            foreach ($classes as $cl) {
                $cid     = (int)$cl['id'];
                $entries = $wpdb->get_results($wpdb->prepare(
                    "SELECT cs.day_of_week, pe.period_number,
                            l.subject AS lesson_subject,
                            COALESCE(t.name,'') AS teacher_name,
                            cs.subgroup_label, cs.sort_order, cs.room
                     FROM {$p}ss_class_schedule cs
                     JOIN {$p}ss_periods  pe ON pe.id  = cs.period_id
                     JOIN {$p}ss_lessons  l  ON l.id   = cs.lesson_id
                     LEFT JOIN {$p}ss_teachers t ON t.id = l.teacher_id
                     WHERE cs.class_id = %d
                     ORDER BY cs.day_of_week, pe.period_number, cs.sort_order",
                    $cid
                ), ARRAY_A);

                $class_list[] = [
                    'name'    => $cl['name'],
                    'color'   => $cl['color'],
                    'is_imp'  => (int)$cl['is_imp'],
                    'entries' => $entries ?: [],
                ];
            }

            // IMP planai (individualūs ugdymo planai) — pateikti mokinių pasirinkimai.
            // Win „Tvarkaraščių kūrėjas" juos perskaito (data.plans + klasės su „plan"),
            // o plugin'o importas šiuos papildomus raktus ignoruoja.
            if (class_exists('SS_IMP')) {
                $imp = SS_IMP::export_for_io();
                if (!empty($imp['classes'])) $class_list = array_merge($class_list, $imp['classes']);
                if (!empty($imp['plans']))   $data['plans'] = $imp['plans'];
            }

            $data['schedules'] = [
                'periods' => $periods ?: [],
                'classes' => $class_list,
            ];
        }

        if (in_array('students', $sections, true)) {
            // WordPress users with ss_student role + their class meta
            $users = get_users(['role' => 'ss_student', 'number' => -1]);
            $student_list = [];
            foreach ($users as $u) {
                $class_id   = (int) get_user_meta($u->ID, 'ss_class_id', true);
                $class_name = '';
                if ($class_id) {
                    $row = $wpdb->get_row($wpdb->prepare(
                        "SELECT name FROM {$p}ss_classes WHERE id=%d", $class_id
                    ));
                    if ($row) $class_name = $row->name;
                }
                $student_list[] = [
                    'display_name' => $u->display_name,
                    'email'        => $u->user_email,
                    'class_name'   => $class_name,
                ];
            }
            $data['students'] = $student_list;
        }

        return [
            'format'      => self::FORMAT,
            'version'     => self::VERSION,
            'exported_at' => current_time('c'),
            'school'      => get_option('ss_school_name', ''),
            'includes'    => $sections,
            'data'        => $data,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // IMPORTAS — PERŽIŪRA
    // ══════════════════════════════════════════════════════════════════════════

    /** Patikrina failą ir grąžina peržiūros info (nieko nerašo į DB). */
    public static function preview(string $json): array {
        $pkg = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || ($pkg['format'] ?? '') !== self::FORMAT) {
            return ['error' => 'Neteisingas failo formatas. Būtina .azuolynotvs byla.'];
        }

        $summary = [];
        $data    = $pkg['data'] ?? [];

        if (isset($data['teachers'])) {
            $summary['teachers'] = [
                'label' => 'Mokytojai',
                'count' => count($data['teachers']),
            ];
        }
        if (isset($data['lessons'])) {
            $summary['lessons'] = [
                'label' => 'Pamokos',
                'count' => count($data['lessons']),
            ];
        }
        if (isset($data['schedules'])) {
            $cl  = $data['schedules']['classes'] ?? [];
            $ent = array_sum(array_column($cl, 'entries') ? array_map(fn($c) => count($c['entries']), $cl) : [0]);
            $summary['schedules'] = [
                'label'   => 'Tvarkaraščiai',
                'classes' => count($cl),
                'entries' => $ent,
                'count'   => count($cl),
            ];
        }
        if (isset($data['students'])) {
            $summary['students'] = [
                'label' => 'Mokiniai',
                'count' => count($data['students']),
            ];
        }

        return [
            'error'       => '',
            'exported_at' => $pkg['exported_at'] ?? '',
            'school'      => $pkg['school']      ?? '',
            'version'     => $pkg['version']     ?? '',
            'includes'    => $pkg['includes']    ?? [],
            'summary'     => $summary,
            'raw'         => $pkg,  // perduodama commit žingsniui
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // IMPORTAS — ĮRAŠYMAS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * $pkg  — decoded JSON array (iš preview['raw'])
     * $mode — 'merge' | 'replace'
     * $sections — kokius skyrius importuoti
     */
    public static function commit(array $pkg, string $mode, array $sections): array {
        global $wpdb; $p = $wpdb->prefix;
        $results = [];
        $data    = $pkg['data'] ?? [];

        // ── Mokytojai ──────────────────────────────────────────────────────
        if (in_array('teachers', $sections, true) && isset($data['teachers'])) {
            if ($mode === 'replace') {
                $wpdb->query("TRUNCATE TABLE {$p}ss_teachers");
            }
            $ins = $upd = 0;
            foreach ($data['teachers'] as $row) {
                $name  = sanitize_text_field($row['name']  ?? '');
                $short = sanitize_text_field($row['short'] ?? '');
                $email = sanitize_email($row['email']      ?? '');
                $color = sanitize_hex_color($row['color']  ?? '') ?: '#4F8952';
                if (!$name) continue;

                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$p}ss_teachers WHERE name=%s", $name
                ));
                if ($existing) {
                    $wpdb->update("{$p}ss_teachers",
                        compact('short','email','color'), ['id' => $existing]);
                    $upd++;
                } else {
                    $wpdb->insert("{$p}ss_teachers",
                        ['name'=>$name,'short'=>$short,'email'=>$email,'color'=>$color,
                         'created_at'=>current_time('mysql')]);
                    $ins++;
                }
            }
            $results['teachers'] = "Mokytojai: +{$ins} nauji, {$upd} atnaujinti.";
        }

        // ── Pamokos ────────────────────────────────────────────────────────
        if (in_array('lessons', $sections, true) && isset($data['lessons'])) {
            if ($mode === 'replace') {
                $wpdb->query("TRUNCATE TABLE {$p}ss_lessons");
            }
            $ins = $upd = 0;
            foreach ($data['lessons'] as $row) {
                $subject      = sanitize_text_field($row['subject']      ?? '');
                $teacher_name = sanitize_text_field($row['teacher_name'] ?? '');
                $color        = sanitize_hex_color($row['color'] ?? '') ?: '#4F8952';
                if (!$subject) continue;

                // Rask mokytojo ID pagal vardą
                $tid = 0;
                if ($teacher_name) {
                    $tid = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$p}ss_teachers WHERE name=%s", $teacher_name
                    ));
                }

                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$p}ss_lessons WHERE subject=%s AND teacher_id=%d",
                    $subject, $tid
                ));
                if ($existing) {
                    $wpdb->update("{$p}ss_lessons", ['color'=>$color], ['id'=>$existing]);
                    $upd++;
                } else {
                    $wpdb->insert("{$p}ss_lessons",
                        ['subject'=>$subject,'teacher_id'=>$tid,'color'=>$color,
                         'created_at'=>current_time('mysql')]);
                    $ins++;
                }
            }
            $results['lessons'] = "Pamokos: +{$ins} naujos, {$upd} atnaujintos.";
        }

        // ── Tvarkaraščiai ──────────────────────────────────────────────────
        if (in_array('schedules', $sections, true) && isset($data['schedules'])) {
            $sched = $data['schedules'];

            // Periodai
            foreach ($sched['periods'] ?? [] as $pr) {
                $pn = (int)$pr['period_number'];
                $existing_p = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$p}ss_periods WHERE period_number=%d", $pn
                ));
                if ($existing_p) {
                    $wpdb->update("{$p}ss_periods",
                        ['start_time'=>$pr['start_time'],'end_time'=>$pr['end_time']],
                        ['id'=>$existing_p]);
                } else {
                    $wpdb->insert("{$p}ss_periods",
                        ['period_number'=>$pn,'start_time'=>$pr['start_time'],
                         'end_time'=>$pr['end_time']]);
                }
            }

            // Klasės + tvarkaraščio įrašai
            $ins_cl = $upd_cl = $ins_ent = 0;
            foreach ($sched['classes'] ?? [] as $cl) {
                $cname = sanitize_text_field($cl['name'] ?? '');
                $color = sanitize_hex_color($cl['color'] ?? '') ?: '#4F8952';
                if (!$cname) continue;

                $cid = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$p}ss_classes WHERE name=%s", $cname
                ));
                if ($cid) {
                    $wpdb->update("{$p}ss_classes",
                        ['color'=>$color,'is_imp'=>(int)($cl['is_imp']??0)], ['id'=>$cid]);
                    $upd_cl++;
                } else {
                    // Generuojame unikalų kodą
                    $code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/','',$cname),0,6))
                          . wp_generate_password(3,false);
                    $wpdb->insert("{$p}ss_classes",
                        ['name'=>$cname,'color'=>$color,'is_imp'=>(int)($cl['is_imp']??0),
                         'code'=>$code,'created_at'=>current_time('mysql')]);
                    $cid = (int)$wpdb->insert_id;
                    $ins_cl++;
                }

                if ($mode === 'replace') {
                    $wpdb->delete("{$p}ss_class_schedule", ['class_id'=>$cid]);
                }

                foreach ($cl['entries'] ?? [] as $ent) {
                    $subject  = sanitize_text_field($ent['lesson_subject'] ?? '');
                    $tname    = sanitize_text_field($ent['teacher_name']   ?? '');
                    $day      = (int)($ent['day_of_week']  ?? 0);
                    $pnum     = (int)($ent['period_number'] ?? 0);
                    $sg       = sanitize_text_field($ent['subgroup_label'] ?? '');
                    $room     = sanitize_text_field($ent['room']           ?? '');
                    $sort     = (int)($ent['sort_order'] ?? 0);
                    if (!$subject || !$day || !$pnum) continue;

                    $period_id = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$p}ss_periods WHERE period_number=%d", $pnum
                    ));
                    if (!$period_id) continue;

                    $tid = 0;
                    if ($tname) {
                        $tid = (int)$wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$p}ss_teachers WHERE name=%s", $tname
                        ));
                        if (!$tid) {
                            $wpdb->insert("{$p}ss_teachers",
                                ['name'=>$tname,'short'=>'','email'=>'','color'=>'#4F8952',
                                 'created_at'=>current_time('mysql')]);
                            $tid = (int)$wpdb->insert_id;
                        }
                    }

                    $lid = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$p}ss_lessons WHERE subject=%s AND teacher_id=%d",
                        $subject, $tid
                    ));
                    if (!$lid) {
                        $wpdb->insert("{$p}ss_lessons",
                            ['subject'=>$subject,'teacher_id'=>$tid,'color'=>'#4F8952',
                             'created_at'=>current_time('mysql')]);
                        $lid = (int)$wpdb->insert_id;
                    }

                    $room_id = $room !== '' ? SS_Database::find_or_create_room($room) : 0;
                    $wpdb->insert("{$p}ss_class_schedule", [
                        'class_id'       => $cid,
                        'day_of_week'    => $day,
                        'period_id'      => $period_id,
                        'lesson_id'      => $lid,
                        'subgroup_label' => $sg,
                        'sort_order'     => $sort,
                        'room'           => $room,
                        'room_id'        => $room_id,
                    ]);
                    $ins_ent++;
                }
            }
            $results['schedules'] = "Tvarkaraščiai: +{$ins_cl} naujos klasės, {$upd_cl} atnaujintos, {$ins_ent} pamokų įrašų.";
        }

        // ── Mokiniai ───────────────────────────────────────────────────────
        if (in_array('students', $sections, true) && isset($data['students'])) {
            $ins = $upd = $skip = 0;
            foreach ($data['students'] as $row) {
                $email      = sanitize_email($row['email']        ?? '');
                $dname      = sanitize_text_field($row['display_name'] ?? '');
                $class_name = sanitize_text_field($row['class_name']   ?? '');
                if (!$email) { $skip++; continue; }

                $class_id = 0;
                if ($class_name) {
                    $class_id = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$p}ss_classes WHERE name=%s", $class_name
                    ));
                }

                $existing_uid = email_exists($email);
                if ($existing_uid) {
                    if ($class_id) update_user_meta($existing_uid, 'ss_class_id', $class_id);
                    $upd++;
                } else {
                    $uid = wp_insert_user([
                        'user_login'   => sanitize_user($email),
                        'user_email'   => $email,
                        'display_name' => $dname,
                        'role'         => 'ss_student',
                        'user_pass'    => wp_generate_password(16),
                    ]);
                    if (!is_wp_error($uid)) {
                        if ($class_id) update_user_meta($uid, 'ss_class_id', $class_id);
                        $ins++;
                    } else {
                        $skip++;
                    }
                }
            }
            $results['students'] = "Mokiniai: +{$ins} nauji, {$upd} atnaujinti, {$skip} praleisti.";
        }

        return ['error' => '', 'results' => $results];
    }
}
