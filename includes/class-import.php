<?php
if (!defined('ABSPATH')) exit;

class SS_Import {

    public static function parse_html($html_raw) {
        // 1. Convert encoding to UTF-8
        $html = self::to_utf8($html_raw);

        // 2. Ensure charset=utf-8 meta so DOMDocument doesn't re-interpret encoding
        // Remove any existing charset meta, then inject utf-8 one before </head> or at start
        $html = preg_replace('/<meta[^>]+charset[^>]+>/i', '', $html);
        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', '<meta charset="utf-8"></head>', $html);
        } elseif (stripos($html, '<head>') !== false) {
            $html = str_ireplace('<head>', '<head><meta charset="utf-8">', $html);
        } else {
            $html = '<meta charset="utf-8">' . $html;
        }

        // 3. Parse DOM — use mb_convert_encoding to keep UTF-8 intact in DOMDocument
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        // mb_convert_encoding converts HTML entities and prevents double-encoding
        $html_for_dom = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML($html_for_dom, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        $xp = new DOMXPath($dom);

        // 4. Find main schedule table (bgcolor=#00cccc or has most rows)
        $main_table = null;
        $max_rows   = 0;
        foreach ($dom->getElementsByTagName('table') as $t) {
            $rc = $xp->query('.//tr', $t)->length;
            if ($rc > $max_rows) { $max_rows = $rc; $main_table = $t; }
        }
        if (!$main_table || $max_rows < 3) {
            return array('error' => 'Nerasta tvarkaraščio lentelė. Patikrinkite failą.');
        }

        $all_rows = $xp->query('./tbody/tr | ./tr', $main_table);

        // 5. Extract class name from row 0
        $class_name = '';
        $row0_bolds = $xp->query('.//b', $all_rows->item(0));
        if ($row0_bolds->length) {
            $raw = trim($row0_bolds->item(0)->textContent);
            $class_name = trim(preg_replace('/\s+klas[eė]\s*$/iu', '', $raw));
        }
        if (!$class_name && $all_rows->length > 0) {
            preg_match('/([^\s<>]+\s+klas[eė])/iu', $all_rows->item(0)->textContent, $m);
            if ($m) $class_name = trim(preg_replace('/\s+klas[eė]\s*$/iu', '', $m[1]));
        }

        // 6. Map day columns from header row (row 1)
        $day_map     = array(); // col_index => day_num
        $day_prefixes = array('pirm'=>1,'antr'=>2,'tre'=>3,'ketv'=>4,'penk'=>5);
        $header_row   = $all_rows->item(1);
        $hcells       = $xp->query('./td | ./th', $header_row);
        foreach ($hcells as $ci => $cell) {
            $t = mb_strtolower(trim($cell->textContent), 'UTF-8');
            foreach ($day_prefixes as $prefix => $num) {
                if (mb_substr($t, 0, mb_strlen($prefix)) === $prefix) {
                    $day_map[$ci] = $num; break;
                }
            }
        }

        // 7. Parse data rows with rowspan tracking
        // occupied[row_idx][col_idx] = {period_num, entries[]}
        $occupied     = array();
        $cells_output = array();
        $num_rows     = $all_rows->length;

        for ($ri = 2; $ri < $num_rows; $ri++) {
            $row    = $all_rows->item($ri);
            $phys   = $xp->query('./td | ./th', $row); // physical tds
            if ($phys->length === 0) continue;

            // First cell = period number
            $first_text = trim($phys->item(0)->textContent);
            if (!preg_match('/^\d+$/', $first_text)) continue;
            $period_num = (int)$first_text;

            // Walk logical columns 1..5 (Mon..Fri)
            $phys_idx = 1; // skip period number cell

            for ($col = 1; $col <= 5; $col++) {
                if (!empty($occupied[$ri][$col])) {
                    // This cell is occupied by a rowspan from above
                    // Re-register entries for this period too
                    $occ = $occupied[$ri][$col];
                    if (!empty($occ['entries'])) {
                        $cells_output[] = array(
                            'day'     => $day_map[$col] ?? $col,
                            'period'  => $period_num,
                            'entries' => $occ['entries'],
                        );
                    }
                    continue;
                }

                if ($phys_idx >= $phys->length) break;
                $td      = $phys->item($phys_idx++);
                $rowspan = max(1, (int)($td->getAttribute('rowspan') ?: 1));
                $colspan = max(1, (int)($td->getAttribute('colspan') ?: 1));

                $day = $day_map[$col] ?? null;
                if (!$day) {
                    $col += $colspan - 1;
                    continue;
                }

                $entries = self::parse_cell($td, $xp);

                if (!empty($entries)) {
                    $cells_output[] = array(
                        'day'     => $day,
                        'period'  => $period_num,
                        'entries' => $entries,
                    );
                }

                // Mark future rows+cols occupied
                if ($rowspan > 1) {
                    for ($rs = 1; $rs < $rowspan; $rs++) {
                        for ($cs = 0; $cs < $colspan; $cs++) {
                            $occupied[$ri + $rs][$col + $cs] = array(
                                'entries' => $entries,
                            );
                        }
                    }
                }

                $col += $colspan - 1;
            }
        }

        // 8. Determine new teachers / lessons
        $existing_teachers = array();
        foreach (SS_Database::get_teachers() as $t) $existing_teachers[$t->name] = $t->id;
        $existing_lessons = array();
        foreach (SS_Database::get_lessons() as $l) {
            $existing_lessons[$l->subject . '|||' . ($l->teacher_name ?? '')] = $l->id;
        }

        $new_teachers = array();
        $new_lessons  = array();
        foreach ($cells_output as $cell) {
            foreach ($cell['entries'] as $entry) {
                foreach ((array)($entry['teachers'] ?? []) as $tn) {
                    if ($tn && !isset($existing_teachers[$tn])) $new_teachers[$tn] = true;
                }
                $tn1 = !empty($entry['teachers']) ? $entry['teachers'][0] : '';
                $key = ($entry['subject'] ?? '') . '|||' . $tn1;
                if (($entry['subject'] ?? '') && !isset($existing_lessons[$key])) {
                    $new_lessons[$key] = true;
                }
            }
        }

        // ── Build per-teacher stats (existing vs new, with schedule-entry counts) ──
        $teacher_count = array(); // teacher_name => # of schedule entries
        $teacher_room  = array(); // teacher_name => first room seen
        foreach ($cells_output as $cell) {
            foreach ($cell['entries'] as $entry) {
                foreach ((array)($entry['teachers'] ?? []) as $tn) {
                    if (!$tn) continue;
                    $teacher_count[$tn] = ($teacher_count[$tn] ?? 0) + 1;
                    if (!empty($entry['room']) && empty($teacher_room[$tn])) {
                        $teacher_room[$tn] = $entry['room'];
                    }
                }
            }
        }
        $existing_teacher_stats = array();
        $new_teacher_stats      = array();
        foreach ($teacher_count as $tn => $cnt) {
            $info = array('count' => $cnt, 'room' => $teacher_room[$tn] ?? '');
            if (isset($existing_teachers[$tn])) {
                $existing_teacher_stats[$tn] = $info;
            } else {
                $new_teacher_stats[$tn] = $info;
            }
        }

        return array(
            'class_name'    => $class_name,
            'cells'         => $cells_output,
            'new_teachers'  => array_keys($new_teachers),
            'new_lessons'   => array_keys($new_lessons),
            'teacher_stats' => array(
                'existing' => $existing_teacher_stats,
                'new'      => $new_teacher_stats,
            ),
            'error' => '',
        );
    }

    /**
     * Parse a schedule cell. Handles:
     * - Single lesson
     * - Multiple subgroups separated by " / " in bold text
     * - Multiple bold elements (rare)
     */
    private static function parse_cell($td, $xp) {
        $bg = $td->getAttribute('bgcolor');
        // White / empty cells
        if ($bg === '#ffffff' || $bg === 'white') return array();

        $bolds = $xp->query('.//b', $td);
        if ($bolds->length === 0) return array();

        $entries = array();

        foreach ($bolds as $bold) {
            $raw = trim($bold->textContent);
            if (!$raw) continue;

            // Strip class prefix "1a " / "2a, 2b " etc.
            $subj = preg_replace('/^(\d+[a-zA-ZąčęėįšųūžĄČĘĖĮŠŲŪŽ]+(,\s*\d+[a-zA-ZąčęėįšųūžĄČĘĖĮŠŲŪŽ]+)*\s+)/u', '', $raw);
            $subj = trim($subj);
            if (!$subj) continue;

            // Collect teacher names + room tokens from the text nodes after <b>.
            // Mimosa lists them line-by-line (separated by <br> / newlines), and the
            // order varies — sometimes the room comes before the teachers (e.g. gym
            // lessons), sometimes after. So we classify each line independently
            // instead of assuming a fixed order. Rooms are usually bare numbers
            // ("303", "411"), sometimes "(4) 210" or named ("Sporto salė").
            $teacher_lines = array();
            $room_tokens   = array();
            $node = $bold->nextSibling;
            while ($node) {
                if (strtolower($node->nodeName) === 'b') break; // next subject in same cell
                $nt = $node->textContent ?? $node->nodeValue ?? '';
                $nt = str_replace("\xc2\xa0", ' ', $nt); // &nbsp; → space
                foreach (preg_split('/[\n\r]+/', $nt) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line === '&nbsp;') continue;
                    // Skip class references like "1b klasė", "1a, 1b klasė"
                    if (preg_match('/^\d+[a-zA-ZąčęėįšųūžĄČĘĖĮŠŲŪŽ]*(,\s*\d+[a-zA-ZąčęėįšųūžĄČĘĖĮŠŲŪŽ]*)*\s+klas[eė]/u', $line)) continue;
                    if (self::is_room_token($line)) {
                        $room_tokens[] = $line;
                    } else {
                        $teacher_lines[] = $line;
                    }
                }
                $node = $node->nextSibling;
            }

            // Check for " / " = subgroups
            $parts = preg_split('/\s*\/\s*/u', $subj);
            $n     = count($parts);

            if ($n > 1) {
                foreach ($parts as $idx => $part) {
                    $part = trim($part);
                    // Extract subgroup label: prefer [bracket] syntax, then "N gr..." suffix/prefix
                    $sg = '';
                    if (preg_match('/^(.*?)\s*\[([^\]]+)\]\s*$/u', $part, $m)) {
                        $part = trim($m[1]);
                        $sg   = trim($m[2]);
                    } elseif (preg_match('/^(\d+\s+gr[^\/\s]*)\s+(.+)/iu', $part, $m)) {
                        $sg   = trim($m[1]);
                        $part = trim($m[2]);
                    } elseif (preg_match('/^(.+?)\s+(\d+\s+gr[^\/\s]*)\s*$/iu', $part, $m)) {
                        $part = trim($m[1]);
                        $sg   = trim($m[2]);
                    } else {
                        $sg = ($idx + 1) . ' grupė';
                    }
                    // Assign teacher slice
                    $slice = max(1, intdiv(count($teacher_lines), $n));
                    $tslice = array_slice($teacher_lines, $idx * $slice, $slice);
                    $entries[] = array(
                        'subject'        => $part,
                        'teachers'       => $tslice ?: $teacher_lines,
                        'subgroup_label' => $sg,
                        // One room per subgroup when the counts line up, otherwise
                        // share the single room or join them with spaces.
                        'room'           => self::room_for_index($room_tokens, $idx, $n),
                    );
                }
            } else {
                $sg = '';
                if (preg_match('/^(.*?)\s*\[([^\]]+)\]\s*$/u', $subj, $m)) {
                    // [bracket] subgroup label (e.g. "informatika [1 gr]")
                    $subj = trim($m[1]);
                    $sg   = trim($m[2]);
                } elseif (preg_match('/^(.+?)\s+(\d+\s+grup[eė])\s*$/iu', $subj, $m)) {
                    $subj = trim($m[1]);
                    $sg   = trim($m[2]);
                }
                $entries[] = array(
                    'subject'        => $subj,
                    'teachers'       => $teacher_lines,
                    'subgroup_label' => $sg,
                    // Several rooms on one (non-split) lesson → list them with spaces.
                    'room'           => implode(' ', $room_tokens),
                );
            }
        }

        return $entries;
    }

    /**
     * Is this text line a classroom (cabinet) token rather than a teacher name?
     * Rooms in Mimosa exports are usually bare numbers ("303", "411"), sometimes
     * with a building prefix ("(4) 210") or a name ("Sporto salė", "Aktų salė").
     */
    private static function is_room_token($s) {
        $s = trim($s);
        if ($s === '') return false;
        // Named rooms / explicit markers
        if (preg_match('/sal[ėe]\b|\bkab\.?\b|aukšt/iu', $s)) return true;
        // Numeric rooms, optionally with a "(building)" prefix and trailing letter:
        // "303", "411", "12a", "(4) 210"
        if (preg_match('/^\(?\d+\)?(\s*\d+)?\s*[a-zA-Z]?$/u', $s)) return true;
        return false;
    }

    /**
     * Pick the room for subgroup #$idx out of $n subgroups.
     *  - rooms == subgroups → one room each (per index)
     *  - exactly one room    → shared by all subgroups
     *  - otherwise           → join all rooms with spaces (ambiguous source)
     */
    private static function room_for_index($rooms, $idx, $n) {
        $c = count($rooms);
        if ($c === 0) return '';
        if ($c === $n) return $rooms[$idx];
        if ($c === 1) return $rooms[0];
        return implode(' ', $rooms);
    }

    /**
     * Convert any common encoding to UTF-8.
     * Priority: charset from meta tag → Windows-1257 → ISO-8859-13 → raw.
     */
    private static function to_utf8($raw) {
        // Already valid UTF-8?
        if (mb_check_encoding($raw, 'UTF-8')) {
            // But check for garbled Lithuanian (signs it was mis-encoded)
            if (preg_match('/[ąčęėįšųūžĄČĘĖĮŠŲŪŽ]/u', $raw)) {
                return $raw; // genuine UTF-8 with Lithuanian
            }
        }

        // Read charset from meta tag
        $charset = '';
        if (preg_match('/charset\s*=\s*["\']?\s*([a-zA-Z0-9_-]+)/i', $raw, $m)) {
            $charset = strtolower(trim($m[1]));
        }

        // Try declared charset first
        if ($charset && $charset !== 'utf-8' && $charset !== 'utf8') {
            $try = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $raw);
            if ($try && self::has_lt($try)) return $try;
        }

        // Mimosa most commonly uses Windows-1257
        $try = @iconv('WINDOWS-1257', 'UTF-8//TRANSLIT//IGNORE', $raw);
        if ($try && self::has_lt($try)) return $try;

        // ISO-8859-13 (same codepage, different name)
        $try = @iconv('ISO-8859-13', 'UTF-8//TRANSLIT//IGNORE', $raw);
        if ($try && self::has_lt($try)) return $try;

        // ISO-8859-4
        $try = @iconv('ISO-8859-4', 'UTF-8//TRANSLIT//IGNORE', $raw);
        if ($try && self::has_lt($try)) return $try;

        // Last resort: return as-is
        return $raw;
    }

    private static function has_lt($s) {
        return preg_match('/[ąčęėįšųūžĄČĘĖĮŠŲŪŽ]/u', $s) === 1;
    }

    /**
     * Commit: save all parsed data to DB.
     */
    public static function commit($data) {
        $class_name = sanitize_text_field($data['class_name'] ?? '');
        if (!$class_name) return array('error' => 'Klasės pavadinimas tuščias.');
        $class_id = SS_Database::find_or_create_class($class_name);
        $cells    = (array)($data['cells'] ?? array());

        global $wpdb;
        $wpdb->delete("{$wpdb->prefix}ss_class_schedule", array('class_id' => $class_id));

        $sort = array();
        foreach ($cells as $cell) {
            $day     = (int)$cell['day'];
            $pn      = (int)$cell['period'];
            $period  = SS_Database::get_period_by_number($pn);
            if (!$period) continue;
            $pid     = $period->id;
            $sk      = "{$day}_{$pid}";
            if (!isset($sort[$sk])) $sort[$sk] = 0;

            foreach ((array)($cell['entries'] ?? array()) as $entry) {
                $subject = sanitize_text_field($entry['subject'] ?? '');
                if (!$subject) continue;
                $teachers = (array)($entry['teachers'] ?? array());
                $sg       = sanitize_text_field($entry['subgroup_label'] ?? '');
                $room     = sanitize_text_field($entry['room'] ?? '');
                $tid      = 0;
                if (!empty($teachers[0])) {
                    $tid = SS_Database::find_or_create_teacher(sanitize_text_field($teachers[0]));
                }
                $lid = SS_Database::find_or_create_lesson($subject, $tid);
                $room_id = $room !== '' ? SS_Database::find_or_create_room($room) : 0;
                $wpdb->insert("{$wpdb->prefix}ss_class_schedule", array(
                    'class_id'       => $class_id,
                    'day_of_week'    => $day,
                    'period_id'      => $pid,
                    'lesson_id'      => $lid,
                    'subgroup_label' => $sg,
                    'sort_order'     => $sort[$sk]++,
                    'room'           => $room,
                    'room_id'        => $room_id,
                ));
            }
        }
        return array('class_id' => $class_id, 'class_name' => $class_name, 'error' => '');
    }
}
