<?php
if (!defined('ABSPATH')) exit;

class SS_Surveys_Admin {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_menu'), 30);
        // AJAX
        $actions = array(
            'survey_save', 'survey_delete', 'survey_get',
            'survey_toggle_status',
            // 'survey_export_csv' – handled via admin_post below
        );
        foreach ($actions as $a) {
            add_action("wp_ajax_ss_{$a}", array(__CLASS__, "ajax_{$a}"));
        }
        // Frontend submission / fetching
        add_action('wp_ajax_ss_survey_submit_response', array(__CLASS__, 'ajax_submit_response'));
        add_action('wp_ajax_ss_survey_front_get',       array(__CLASS__, 'ajax_front_get'));
        add_action('wp_ajax_ss_survey_grade_text',      array(__CLASS__, 'ajax_grade_text'));
        add_action('admin_post_ss_survey_export_csv',   array(__CLASS__, 'handle_export_csv'));
    }

    public static function register_menu() {
        add_submenu_page(
            'school-schedule',
            'Apklausos / Viktorinos',
            'Apklausos',
            'manage_options',
            'ss-surveys',
            array(__CLASS__, 'page_surveys')
        );
    }

    public static function page_surveys() {
        $surveys = SS_Database::get_surveys('all');
        $classes = SS_Database::get_classes();
        include SS_PLUGIN_DIR . 'admin/views/surveys.php';
    }

    private static function verify() {
        if (!check_ajax_referer('ss_admin_nonce','nonce',false))
            wp_send_json_error(array('message'=>'Saugumo klaida.'));
        if (!current_user_can('manage_options'))
            wp_send_json_error(array('message'=>'Neturite teisių.'));
    }

    /* ── Allowed question types ── */
    private static function allowed_types() {
        return array('single','multi','text','rank');
    }

    public static function ajax_survey_save() {
        self::verify();
        $raw_q = wp_unslash($_POST['questions'] ?? '[]');
        $questions = json_decode($raw_q, true);
        if (!is_array($questions)) $questions = array();
        $allowed_types = self::allowed_types();
        $clean_q = array();
        foreach ($questions as $q) {
            if (!is_array($q)) continue;
            $type = in_array(($q['type']??'single'), $allowed_types, true) ? $q['type'] : 'single';
            $opts = array();
            foreach ((array)($q['options'] ?? array()) as $opt) {
                if (!is_array($opt)) continue;
                $opts[] = array(
                    'text'    => sanitize_text_field($opt['text'] ?? ''),
                    'correct' => !empty($opt['correct']) ? 1 : 0,
                );
            }
            $clean_q[] = array(
                'text'    => sanitize_text_field($q['text'] ?? ''),
                'type'    => $type,
                'options' => $opts,
                'points'  => max(1, (int)($q['points'] ?? 1)),
            );
        }
        // Sanitize audience: accept JSON array or legacy string
        $aud_raw  = wp_unslash($_POST['audience'] ?? '["all"]');
        $aud_arr  = json_decode($aud_raw, true);
        if (!is_array($aud_arr) || empty($aud_arr)) {
            $aud_arr = array($aud_raw ?: 'all');
        }
        $aud_arr = array_values(array_filter($aud_arr, function($a) {
            return in_array($a, array('all','students','teachers'), true)
                || preg_match('/^class:\d+$/', (string)$a);
        }));
        if (empty($aud_arr)) $aud_arr = array('all');
        $id = SS_Database::save_survey(array(
            'id'                 => (int)($_POST['id'] ?? 0),
            'title'              => wp_unslash($_POST['title'] ?? ''),
            'description'        => wp_unslash($_POST['description'] ?? ''),
            'type'               => sanitize_text_field($_POST['type'] ?? 'survey'),
            'status'             => sanitize_text_field($_POST['status'] ?? 'draft'),
            'audience'           => json_encode($aud_arr),
            'show_results_admin' => 1,
            'show_results_users' => !empty($_POST['show_results_users']),
            'show_answers_users' => !empty($_POST['show_answers_users']),
            'results_show_from'  => sanitize_text_field($_POST['results_show_from'] ?? ''),
            'results_close_at'   => sanitize_text_field($_POST['results_close_at']  ?? ''),
            'open_from'          => sanitize_text_field($_POST['open_from'] ?? ''),
            'close_at'           => sanitize_text_field($_POST['close_at'] ?? ''),
            'image_id'           => (int)($_POST['image_id'] ?? 0),
            'questions'          => $clean_q,
        ));
        if (!$id) {
            global $wpdb;
            wp_send_json_error(array('message' => 'Duomenų bazės klaida: ' . ($wpdb->last_error ?: 'Patikrinkite ar lentelė egzistuoja.')));
        }
        wp_send_json_success(array('id' => $id));
    }

    public static function ajax_survey_delete() {
        self::verify();
        SS_Database::delete_survey((int)($_POST['id'] ?? 0));
        wp_send_json_success();
    }

    public static function ajax_survey_get() {
        self::verify();
        $s = SS_Database::get_survey((int)($_POST['id'] ?? 0));
        if (!$s) wp_send_json_error(array('message'=>'Nerasta.'));
        $s->questions = json_decode($s->questions, true) ?: array();
        $s->image_url = $s->image_id ? wp_get_attachment_image_url($s->image_id, 'large') : '';
        // Decode audience for frontend
        $aud = json_decode($s->audience, true);
        if (!is_array($aud)) $aud = array($s->audience);
        $s->audience_arr = $aud;
        $responses = SS_Database::get_survey_responses($s->id);
        // Enrich each response with text_scores, computed auto_score and avatar
        foreach ($responses as &$r) {
            $ts = $r->text_scores ? json_decode($r->text_scores, true) : array();
            if (!is_array($ts)) $ts = array();
            $r->text_scores_arr = $ts;
            // auto_score = total score minus sum of text_scores
            $r->auto_score = (int)$r->score - array_sum($ts);
            $r->avatar = $r->user_id ? get_avatar_url((int)$r->user_id, array('size' => 64, 'default' => 'mm')) : '';
        }
        unset($r);
        $s->responses_count = count($responses);
        $s->responses = $responses;
        $s->aggregate = self::build_aggregate($s->questions, $responses);
        wp_send_json_success($s);
    }

    /**
     * Build aggregated results for each question.
     *  - single / multi   → counts per option
     *  - text             → list of text answers
     *  - rank             → average position (1=best) per option + counts
     */
    public static function build_aggregate($questions, $responses) {
        $agg = array();
        foreach ((array)$questions as $qi => $q) {
            $type = $q['type'] ?? 'single';
            $counts = array();
            foreach ((array)($q['options'] ?? array()) as $oi => $opt) {
                $counts[$oi] = 0;
            }
            $text_answers = array();
            $rank_sums    = array();
            $rank_counts  = array();
            foreach ($responses as $r) {
                $ans = json_decode($r->answers, true);
                if (!is_array($ans) || !isset($ans[$qi])) continue;
                $a = $ans[$qi];
                if ($type === 'text') {
                    if (!empty($a)) {
                        $ts  = is_array($r->text_scores_arr ?? null) ? $r->text_scores_arr : array();
                        $granted = isset($ts[(string)$qi]) ? (int)$ts[(string)$qi] : null;
                        $text_answers[] = array(
                            'response_id' => (int)$r->id,
                            'user_name'   => $r->display_name ?: $r->user_email ?: '—',
                            'text'        => (string)$a,
                            'granted'     => $granted,          // null = not graded, N = points granted
                            'auto_score'  => isset($r->auto_score) ? (int)$r->auto_score : 0,
                        );
                    }
                } elseif ($type === 'multi') {
                    foreach ((array)$a as $oi) {
                        $oi = (int)$oi;
                        if (isset($counts[$oi])) $counts[$oi]++;
                    }
                } elseif ($type === 'rank') {
                    foreach ((array)$a as $pos => $oi) {
                        $oi = (int)$oi;
                        if (!isset($counts[$oi])) continue;
                        $position = $pos + 1;
                        $rank_sums[$oi]   = ($rank_sums[$oi] ?? 0) + $position;
                        $rank_counts[$oi] = ($rank_counts[$oi] ?? 0) + 1;
                        $counts[$oi]++;
                    }
                } else { // single
                    $oi = (int)$a;
                    if (isset($counts[$oi])) $counts[$oi]++;
                }
            }
            $rank_avg = array();
            foreach ($rank_counts as $oi => $cnt) {
                $rank_avg[$oi] = $cnt > 0 ? round($rank_sums[$oi] / $cnt, 2) : null;
            }
            $agg[$qi] = array(
                'counts'   => $counts,
                'text'     => $text_answers,
                'rank_avg' => $rank_avg,
            );
        }
        return $agg;
    }

    public static function ajax_survey_toggle_status() {
        self::verify();
        $id = (int)($_POST['id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'draft');
        if (!in_array($status, array('draft','active','closed'), true)) {
            wp_send_json_error(array('message'=>'Neteisinga būsena.'));
        }
        global $wpdb;
        $wpdb->update("{$wpdb->prefix}ss_surveys", array('status'=>$status,'updated_at'=>current_time('mysql')), array('id'=>$id));
        SS_AuditLog::write('survey_status', array('survey_id' => $id, 'status' => $status));
        wp_send_json_success();
    }

    /* ── Public submission ── */
    public static function ajax_submit_response() {
        check_ajax_referer('ss_front_nonce','nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message'=>'Neprisijungta.'));
        $sid = (int)($_POST['survey_id'] ?? 0);
        $survey = SS_Database::get_survey($sid);
        if (!$survey || $survey->status !== 'active') {
            wp_send_json_error(array('message'=>'Apklausa nėra aktyvi.'));
        }
        if (!self::user_in_audience(get_current_user_id(), $survey)) {
            wp_send_json_error(array('message'=>'Ši apklausa skirta kitai auditorijai.'));
        }
        $now = current_time('mysql');
        if (!empty($survey->open_from) && $survey->open_from > $now) wp_send_json_error(array('message'=>'Apklausa dar nepradėta.'));
        if (!empty($survey->close_at)  && $survey->close_at  < $now) wp_send_json_error(array('message'=>'Apklausa baigta.'));
        $questions = json_decode($survey->questions, true) ?: array();
        $raw_a = wp_unslash($_POST['answers'] ?? '{}');
        $answers = json_decode($raw_a, true);
        if (!is_array($answers)) $answers = array();

        // Sanitize answers per question type
        $clean_answers = array();
        foreach ($questions as $qi => $q) {
            $t = $q['type'] ?? 'single';
            $a = $answers[$qi] ?? null;
            if ($t === 'text') {
                $clean_answers[$qi] = sanitize_text_field((string)$a);
            } elseif ($t === 'multi') {
                $clean_answers[$qi] = array_values(array_unique(array_map('intval', (array)$a)));
            } elseif ($t === 'rank') {
                $clean_answers[$qi] = array_values(array_unique(array_map('intval', (array)$a)));
            } else {
                $clean_answers[$qi] = is_array($a) ? (int)reset($a) : (int)$a;
            }
        }

        // Compute score for quizzes
        $score = 0; $max = 0;
        if ($survey->type === 'quiz') {
            foreach ($questions as $qi => $q) {
                $t = $q['type'] ?? 'single';
                $pts = max(1, (int)($q['points'] ?? 1));
                $max += $pts; // all questions count toward max
                if ($t === 'text') continue; // graded manually by admin
                $correct_set = array();
                foreach ((array)($q['options'] ?? []) as $oi => $opt) {
                    if (!empty($opt['correct'])) $correct_set[] = (int)$oi;
                }
                if ($t === 'rank') {
                    $authored_order = array_keys((array)($q['options'] ?? []));
                    if (!$authored_order) { $max -= $pts; continue; }
                    $given = array_map('intval', (array)$clean_answers[$qi]);
                    if ($given === $authored_order) $score += $pts;
                    continue;
                }
                if (!$correct_set) { $max -= $pts; continue; }
                $given = $clean_answers[$qi];
                if ($t === 'multi') {
                    $g = (array)$given; sort($g);
                    $c = $correct_set; sort($c);
                    if ($g === $c) $score += $pts;
                } else {
                    if ((int)$given === (int)$correct_set[0]) $score += $pts;
                }
            }
        }
        SS_Database::save_survey_response($sid, get_current_user_id(), $clean_answers, $score, $max);

        $results_open = self::user_can_see_results($survey);
        $show_answers = $results_open && !empty($survey->show_answers_users);
        $payload = array(
            'score'        => $score,
            'max'          => $max,
            'type'         => $survey->type,
            'show_results' => $results_open,
            'show_answers' => $show_answers,
            'results'      => null,
        );
        if ($show_answers) {
            $responses = SS_Database::get_survey_responses($sid);
            foreach ($responses as &$r) {
                $ts = $r->text_scores ? json_decode($r->text_scores, true) : array();
                if (!is_array($ts)) $ts = array();
                $r->text_scores_arr = $ts;
                $r->auto_score = (int)$r->score - array_sum($ts);
            }
            unset($r);
            $payload['results'] = array(
                'questions'       => $questions,
                'aggregate'       => self::build_aggregate($questions, $responses),
                'responses_count' => count($responses),
            );
        }
        if ($survey->type === 'quiz') {
            $payload['leaderboard'] = self::build_leaderboard($sid, get_current_user_id());
        }
        wp_send_json_success($payload);
    }

    /**
     * AJAX: fetch a survey for the frontend (audience-filtered) +
     * optionally include results if the user has already responded.
     */
    public static function ajax_front_get() {
        check_ajax_referer('ss_front_nonce','nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message'=>'Neprisijungta.'));
        $sid = (int)($_POST['survey_id'] ?? 0);
        $survey = SS_Database::get_survey($sid);
        if (!$survey) wp_send_json_error(array('message'=>'Nerasta.'));
        if (!self::user_in_audience(get_current_user_id(), $survey)) {
            wp_send_json_error(array('message'=>'Ši apklausa skirta kitai auditorijai.'));
        }
        $survey->questions = json_decode($survey->questions, true) ?: array();
        $survey->image_url = $survey->image_id ? wp_get_attachment_image_url($survey->image_id, 'large') : '';
        $existing = SS_Database::get_user_response($sid, get_current_user_id());
        $answered = !empty($existing);

        // Ar šiuo metu galima atsakyti?
        $now2 = current_time('mysql');
        $can_answer = (
            $survey->status === 'active' &&
            (empty($survey->open_from) || $survey->open_from <= $now2) &&
            (empty($survey->close_at)  || $survey->close_at  >= $now2)
        );

        // Rezultatai matomi VISIEMS (ir neatsakiusiems), kai jie įjungti. Nenustačius
        // laiko intervalo — rodoma visada; jei nustatytas — laikomasi intervalo.
        $show_results = self::user_can_see_results($survey);
        // Ar leidžiama peržiūrėti klausimus + suvestinę (ne tik taškus).
        $show_answers = $show_results && !empty($survey->show_answers_users);
        // Teisingus atsakymus atskleidžiam TIK atsakiusiems — kad neatsakę nematytų jų
        // iš anksto (suvestinę/pasiskirstymą mato visi, bet be „teisingo" žymos).
        $reveal_correct = $answered && $show_answers;
        if ($survey->type === 'quiz' && !$reveal_correct) {
            foreach ($survey->questions as &$q) {
                if (!empty($q['options'])) {
                    foreach ($q['options'] as &$o) { unset($o['correct']); }
                }
            }
            unset($q, $o);
        }

        $survey->answered     = $answered;
        $survey->user_answers = ($answered && $show_answers) ? (json_decode($existing->answers, true) ?: array()) : null;
        $survey->user_score   = ($answered && $show_results) ? (int)$existing->score : null;
        $survey->user_max     = ($answered && $show_results) ? (int)$existing->max_score : null;
        $survey->show_results = $show_results;
        $survey->show_answers = $show_answers;
        $survey->can_answer   = $can_answer;

        // Kai rezultatai matomi — visada atiduodam dalyvių skaičių; pilną suvestinę
        // (klausimų pasiskirstymą) — tik kai įjungta peržiūra.
        if ($show_results) {
            $responses = SS_Database::get_survey_responses($sid);
            $survey->responses_count = count($responses);
            if ($show_answers) {
                foreach ($responses as &$r) {
                    $ts = $r->text_scores ? json_decode($r->text_scores, true) : array();
                    if (!is_array($ts)) $ts = array();
                    $r->text_scores_arr = $ts;
                    $r->auto_score = (int)$r->score - array_sum($ts);
                }
                unset($r);
                $survey->aggregate = self::build_aggregate($survey->questions, $responses);
            }
        }
        // Viktorinų rezultatų lentelė — matoma visada, kai rezultatai matomi
        // (ir neatsakiusiems), kad nebūtų tuščio lango.
        if ($survey->type === 'quiz' && $show_results) {
            $survey->leaderboard = self::build_leaderboard($sid, get_current_user_id());
        }
        wp_send_json_success($survey);
    }

    /**
     * Build top-5 leaderboard for a quiz, mark current user's position.
     */
    public static function build_leaderboard($survey_id, $current_user_id) {
        $rows = SS_Database::get_survey_leaderboard($survey_id, 10);
        // Real names are visible only to admins; everyone else sees anonymised
        // labels for users who opted to hide their name in quiz results.
        $is_admin_view = current_user_can('manage_options');
        $board = array();
        $user_rank = null;
        foreach ($rows as $i => $r) {
            $name = $r->display_name ?: 'Vartotojas';
            $hidden = !$is_admin_view && get_user_meta((int)$r->user_id, 'ss_anon_quiz', true);
            if ($hidden) {
                // 8 random alphanumeric chars, regenerated on every render
                $name = 'Anonimas-' . strtoupper(wp_generate_password(8, false, false));
            }
            $is_me = ((int)$r->user_id === (int)$current_user_id);
            // Avatarą rodom tik kai vardas nepaslėptas (kitaip atskleistų anonimą).
            $avatar = ($hidden && !$is_me) ? '' : get_avatar_url((int)$r->user_id, array('size' => 64, 'default' => 'mm'));
            $entry = array(
                'rank'     => $i + 1,
                'name'     => $name,
                'anon'     => $hidden ? 1 : 0,
                'avatar'   => $avatar ?: '',
                'score'    => (int)$r->score,
                'max'      => (int)$r->max_score,
                'time'     => $r->submitted_at,
                'is_me'    => $is_me,
            );
            if ($is_me) $user_rank = $i + 1;
            if ($i < 3) $board[] = $entry; // top 3 only in display
        }
        return array(
            'top'       => $board,
            'user_rank' => $user_rank,
            'total'     => count($rows),
        );
    }

    /** Whether a user belongs to a survey's target audience. */
    public static function user_in_audience($user_id, $survey) {
        $aud_raw = $survey->audience ?? 'all';
        // Support both old string format and new JSON array
        $audiences = json_decode($aud_raw, true);
        if (!is_array($audiences)) $audiences = array($aud_raw);
        if (in_array('all', $audiences, true)) return true;
        $u = get_user_by('id', $user_id);
        if (!$u) return false;
        $roles = (array)$u->roles;
        if (in_array('students', $audiences, true) && in_array('ss_student', $roles, true)) return true;
        if (in_array('teachers', $audiences, true) && (in_array('ss_teacher', $roles, true) || in_array('administrator', $roles, true))) return true;
        // Class-based audience
        global $wpdb;
        foreach ($audiences as $aud) {
            if (strpos((string)$aud, 'class:') === 0) {
                $cid = (int)substr($aud, 6);
                if ($cid && $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}ss_user_classes WHERE user_id=%d AND class_id=%d",
                    $user_id, $cid
                ))) return true;
            }
        }
        return false;
    }

    /** Whether results are currently visible to frontend users. */
    public static function user_can_see_results($survey) {
        $now = current_time('mysql');
        // At least one results-visibility source must be configured:
        //   - show_results_users checkbox, OR
        //   - an explicit results_show_from time window
        $has_results_config = !empty($survey->show_results_users) || !empty($survey->results_show_from);
        if (!$has_results_config) return false;
        // Not yet open
        if (!empty($survey->results_show_from) && $survey->results_show_from > $now) return false;
        // Already closed
        if (!empty($survey->results_close_at)  && $survey->results_close_at  < $now) return false;
        return true;
    }

    /**
     * AJAX: Admin grades a text answer for a quiz response.
     * POST: response_id, qi, granted (points), auto_score
     */
    public static function ajax_grade_text() {
        self::verify();
        $response_id = (int)($_POST['response_id'] ?? 0);
        $qi          = (int)($_POST['qi']          ?? 0);
        $granted     = (int)($_POST['granted']     ?? 0);
        $auto_score  = (int)($_POST['auto_score']  ?? 0);
        if (!$response_id) wp_send_json_error(array('message' => 'Neteisingi duomenys.'));
        $new_score = SS_Database::grade_text_response($response_id, $qi, $granted, $auto_score);
        wp_send_json_success(array('new_score' => $new_score));
    }

    /** Neutralise spreadsheet formula injection: prefix risky leading chars with an apostrophe. */
    private static function csv_safe($v) {
        $v = (string)$v;
        if ($v !== '' && in_array($v[0], array('=', '+', '-', '@', "\t", "\r"), true)) {
            $v = "'" . $v;
        }
        return $v;
    }

    public static function handle_export_csv() {
        if (!current_user_can('manage_options')) wp_die('Neturite teisių');
        check_admin_referer('ss_export_survey');
        $sid = (int)($_GET['id'] ?? 0);
        $survey = SS_Database::get_survey($sid);
        if (!$survey) wp_die('Apklausa nerasta');
        if (class_exists('SS_AuditLog')) SS_AuditLog::write('data_export', array('kind' => 'survey_csv', 'survey_id' => $sid));
        $questions = json_decode($survey->questions, true) ?: array();
        $responses = SS_Database::get_survey_responses($sid);
        $filename = 'apklausa-'.$sid.'-'.date('Ymd-His').'.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out = fopen('php://output','w');
        fputs($out, "\xEF\xBB\xBF"); // BOM
        $head = array('Vartotojas','El. paštas','Pateikta','Taškai','Maksimalu');
        foreach ($questions as $i => $q) $head[] = ($i+1).'. '.$q['text'];
        fputcsv($out, array_map(array(self::class, 'csv_safe'), $head));
        foreach ($responses as $r) {
            $ans = json_decode($r->answers, true) ?: array();
            $row = array($r->display_name, $r->user_email, $r->submitted_at, $r->score, $r->max_score);
            foreach ($questions as $qi => $q) {
                $a = $ans[$qi] ?? '';
                $t = $q['type'] ?? 'single';
                if ($t === 'text') {
                    $row[] = (string)$a;
                } elseif ($t === 'multi') {
                    $opts = array();
                    foreach ((array)$a as $oi) $opts[] = $q['options'][(int)$oi]['text'] ?? '';
                    $row[] = implode('; ', $opts);
                } elseif ($t === 'rank') {
                    $opts = array();
                    foreach ((array)$a as $idx => $oi) {
                        $opts[] = ($idx+1).'. '.($q['options'][(int)$oi]['text'] ?? '');
                    }
                    $row[] = implode(' | ', $opts);
                } else {
                    $row[] = $q['options'][(int)$a]['text'] ?? '';
                }
            }
            fputcsv($out, array_map(array(self::class, 'csv_safe'), $row));
        }
        fclose($out); exit;
    }
}
SS_Surveys_Admin::init();
