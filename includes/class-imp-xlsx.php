<?php
if (!defined('ABSPATH')) exit;

/**
 * SS_IMP_Xlsx — minimalus .xlsx skaitytuvas (be PhpSpreadsheet priklausomybės).
 *
 * Naudoja vien PHP standartines bibliotekas: ZipArchive + SimpleXML.
 * Skaito ląstelių reikšmes, įskaitant formulių rezultatų cache (<v>), todėl
 * tinka gimnazijos IUP formai, kur H/I ir X/Y stulpeliai yra formulės.
 *
 * IUP-2026.xlsx „Planas" lapo struktūra (pagalbiniai stulpeliai Q–Y):
 *   Q — dalykas / modulis (=B arba =C)        S — pasirinkta (A kursas / žyma)
 *   R — B kursas (privalomi)                  W — kursas ("B"/"A")
 *   X — Valandos III kl.                      Y — Valandos IV kl.
 * H ir I stulpeliai (Pamokų skaičius) yra =X / =Y, t. y. tos pačios valandos.
 */
class SS_IMP_Xlsx {

    /** Eilutė → IUP skiltis (atitinka „Planas" lapo išdėstymą). */
    const ROWS = array(
        'privalomi'   => array(12, 13, 14),
        'grupe'       => array(19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37),
        'pasirenkami' => array(42, 43, 44, 45, 46, 47, 48),
        'moduliai'    => array(53, 54, 55, 56, 57, 58, 59),
    );

    /** Privalomi dalykai su kurso pasirinkimu (B/A). Fizinis ugdymas (14) — be kurso. */
    const LEVEL_ROWS = array(12, 13);

    const RELS_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    /** Zip-bomb guard: reject any single uncompressed entry larger than this. */
    const MAX_ENTRY_BYTES = 33554432; // 32 MB

    /* ─────────────────────────── Žemo lygio skaitymas ─────────────────────────── */

    /**
     * Nuskaito .xlsx ir grąžina:
     *   ['error' => '', 'sheets' => ['Planas' => ['C5' => 'reikšmė', ...], ...]]
     */
    public static function read_file($path) {
        if (!class_exists('ZipArchive')) return array('error' => 'Serveryje neįjungtas ZipArchive PHP plėtinys.');
        if (!is_readable($path))         return array('error' => 'Nepavyko atverti įkelto failo.');

        $zip = new ZipArchive();
        if ($zip->open($path) !== true)  return array('error' => 'Netinkamas failas — tai ne .xlsx (nepavyko atidaryti ZIP).');

        $shared = self::read_shared_strings($zip);
        $wb     = self::xml($zip, 'xl/workbook.xml');
        if (!$wb) { $zip->close(); return array('error' => 'Sugadinta .xlsx workbook struktūra.'); }
        $rels = self::read_rels($zip);

        $sheets = array();
        foreach ($wb->xpath('//*[local-name()="sheet"]') as $s) {
            $name = (string) $s['name'];
            $rid  = (string) $s->attributes(self::RELS_NS)->id;
            $target = $rels[$rid] ?? '';
            if ($name === '' || $target === '') continue;
            // Normalizuojame kelią workbook'o atžvilgiu (xl/...)
            if (strpos($target, '/') === 0) $target = ltrim($target, '/');
            else                            $target = 'xl/' . $target;
            $sheets[$name] = self::read_sheet_cells($zip, $target, $shared);
        }
        $zip->close();
        return array('error' => '', 'sheets' => $sheets);
    }

    private static function xml($zip, $name) {
        // Zip-bomb guard: refuse absurdly large uncompressed entries before reading them.
        $st = $zip->statName($name);
        if (is_array($st) && isset($st['size']) && $st['size'] > self::MAX_ENTRY_BYTES) return null;
        $raw = $zip->getFromName($name);
        if ($raw === false || $raw === '') return null;
        // XXE / entity-expansion guard: a valid .xlsx part never declares a DOCTYPE or entities.
        if (stripos($raw, '<!DOCTYPE') !== false || stripos($raw, '<!ENTITY') !== false) return null;
        $prev = libxml_use_internal_errors(true);
        // LIBXML_NONET blocks any network access; we deliberately do NOT pass LIBXML_NOENT.
        $x = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $x ?: null;
    }

    private static function read_shared_strings($zip) {
        $x = self::xml($zip, 'xl/sharedStrings.xml');
        $out = array();
        if (!$x) return $out;
        foreach ($x->xpath('//*[local-name()="si"]') as $si) {
            $out[] = self::node_text($si);
        }
        return $out;
    }

    /** Surenka tekstą iš <si> arba <is> (gali turėti kelis <r><t>…</t></r> arba vieną <t>). */
    private static function node_text($node) {
        $txt = '';
        foreach ($node->xpath('.//*[local-name()="t"]') as $t) {
            $txt .= (string) $t;
        }
        return $txt;
    }

    private static function read_rels($zip) {
        $x = self::xml($zip, 'xl/_rels/workbook.xml.rels');
        $out = array();
        if (!$x) return $out;
        foreach ($x->xpath('//*[local-name()="Relationship"]') as $r) {
            $out[(string) $r['Id']] = (string) $r['Target'];
        }
        return $out;
    }

    /** Nuskaito vieno lapo ląsteles → ['C5' => 'reikšmė', ...]. Tuščios praleidžiamos. */
    private static function read_sheet_cells($zip, $target, $shared) {
        $x = self::xml($zip, $target);
        $cells = array();
        if (!$x) return $cells;
        foreach ($x->xpath('//*[local-name()="c"]') as $c) {
            $ref = (string) $c['r'];
            if ($ref === '') continue;
            $type = (string) $c['t'];   // s | b | str | inlineStr | e | (tuščia = skaičius)
            $val  = null;

            if ($type === 'inlineStr') {
                $is = $c->xpath('*[local-name()="is"]');
                if ($is) $val = self::node_text($is[0]);
            } else {
                $vnode = $c->xpath('*[local-name()="v"]');
                if (!$vnode) continue;
                $v = (string) $vnode[0];
                switch ($type) {
                    case 's': $val = $shared[(int) $v] ?? ''; break; // bendroji eilutė
                    case 'b': $val = ($v === '1');           break; // loginė reikšmė
                    case 'e': $val = null;                   break; // klaida (#REF! ir pan.)
                    case 'str': default: $val = $v;                 // formulės/skaičiaus rezultatas
                }
            }
            if ($val !== null && $val !== '') $cells[$ref] = $val;
        }
        return $cells;
    }

    /* ─────────────────────────── IUP „Planas" parsinimas ─────────────────────────── */

    private static function truthy($v) {
        return $v === true || $v === 1 || $v === '1'
            || (is_string($v) && in_array(strtolower(trim($v)), array('true', 'x', 'taip', '+'), true));
    }

    private static function num($v) {
        return is_numeric($v) ? (int) round((float) $v) : 0;
    }

    /**
     * Iš „Planas" lapo ląstelių ištraukia vieno mokinio planą.
     *
     * Grąžina:
     *   [
     *     'student' => ['vardas','pavarde','full','klase','phone'],
     *     'items'   => [ ['section','subject','module','level','h3','h4'], ... ],
     *   ]
     */
    public static function parse_planas($cells) {
        $g = function ($ref) use ($cells) { return $cells[$ref] ?? null; };

        $vardas  = trim((string) $g('C5'));
        $pavarde = trim((string) $g('H5'));
        $student = array(
            'vardas'  => $vardas,
            'pavarde' => $pavarde,
            'full'    => trim($pavarde . ' ' . $vardas),
            'klase'   => trim((string) $g('C7')),
            'phone'   => trim((string) $g('H7')),
        );

        $items = array();
        foreach (self::ROWS as $section => $rows) {
            foreach ($rows as $r) {
                $name = trim((string) $g("Q{$r}"));
                if ($name === '') continue;

                $rb = self::truthy($g("R{$r}"));   // B kursas (tik privalomi)
                $sb = self::truthy($g("S{$r}"));   // A kursas / pasirinkta
                $h3 = self::num($g("X{$r}"));
                $h4 = self::num($g("Y{$r}"));
                $w  = strtoupper(trim((string) $g("W{$r}")));  // "B" / "A" / ""

                $selected = $rb || $sb || $h3 > 0 || $h4 > 0;
                if (!$selected) continue;

                $level = '';
                if (in_array($r, self::LEVEL_ROWS, true)) {
                    if ($w === 'A' || $w === 'B')      $level = $w;
                    elseif ($sb && !$rb)               $level = 'A';
                    elseif ($rb && !$sb)               $level = 'B';
                }

                $module = '';
                if ($section === 'moduliai') {
                    // Q = modulio pavadinimas; tėvinis dalykas — B stulpelyje.
                    $module = $name;
                    $parent = trim((string) $g("B{$r}"));
                    if ($parent !== '') $name = $parent;
                }

                $items[] = array(
                    'section' => $section,
                    'subject' => $name,
                    'module'  => $module,
                    'level'   => $level,
                    'h3'      => $h3,
                    'h4'      => $h4,
                );
            }
        }

        return array('student' => $student, 'items' => $items);
    }
}
