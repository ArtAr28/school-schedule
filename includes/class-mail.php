<?php
if (!defined('ABSPATH')) exit;

/**
 * Centralised HTML email builder + sender.
 *
 * All plugin emails go through SS_Mail::send() so they share one
 * consistent, branded template. The look is the "typographic" system
 * (kryptis C): a white card with a coloured 3px top border + soft
 * shadow, an in-card header with a hairline rule, large 800-weight
 * headings, generous body text and flat (no-radius) CTA buttons.
 *
 * Any time shown inside an email MUST be formatted with SS_Time
 * (WordPress timezone) — never with raw date()/gmdate(), which would
 * otherwise render UTC/London time instead of the school's local
 * (Europe/Vilnius) time.
 */
class SS_Mail {

    /* Brand palette (matches the typographic email system) */
    const DARK   = '#012A36';  // headings / strong text
    const NAVY   = '#224768';  // navy accent + CTA
    const GREEN  = '#4F8952';  // primary green accent (top border)
    const GREEN2 = '#6FA672';  // secondary green text
    const BG     = '#F3F7F3';  // page background
    const RED    = '#D05155';  // error / danger accent
    const FOREST = '#14452F';  // deep-green CTA button
    const BODY   = '#56707A';  // body copy
    const MUTE   = '#6EAF93';  // muted labels / footer
    const LINE   = '#EBEBEB';  // hairline dividers

    public static function school() {
        return stripslashes(get_option('ss_school_name', SS_SCHOOL_NAME));
    }

    public static function content_type() {
        return 'text/html; charset=UTF-8';
    }

    /**
     * Send a branded HTML email.
     *
     * @param string $to      Recipient email.
     * @param string $subject Subject line.
     * @param array  $opts {
     *     @type string $title    Big heading inside the card.
     *     @type string $subtitle Small muted line under the title.
     *     @type string $accent   Accent hex colour (top border + CTA). Default brand green.
     *     @type string $icon     Ignored (kept for back-compat — the typographic style drops icons).
     *     @type string $body     Main HTML body (paragraphs etc.).
     *     @type array  $button   ['text' => ..., 'url' => ...] optional CTA.
     *     @type string $note     Optional highlighted note box (HTML).
     *     @type string $note_type 'info' | 'warn' | 'error' (styles the note box).
     *     @type string $extra    Raw HTML inserted before the CTA (e.g. code box, info list).
     * }
     * @return bool
     */
    public static function send($to, $subject, $opts = array()) {
        $html = self::layout($opts);
        add_filter('wp_mail_content_type', array(__CLASS__, 'content_type'));
        $sent = wp_mail($to, $subject, $html);
        remove_filter('wp_mail_content_type', array(__CLASS__, 'content_type'));
        return $sent;
    }

    /** Build the full HTML document. */
    public static function layout($opts) {
        $school    = self::school();
        $accent    = $opts['accent']    ?? self::GREEN;
        $title     = $opts['title']     ?? $school;
        $subtitle  = $opts['subtitle']  ?? '';
        $body      = $opts['body']      ?? '';
        $extra     = $opts['extra']     ?? '';
        $note      = $opts['note']      ?? '';
        $note_type = $opts['note_type'] ?? 'info';
        $year      = SS_Time::date('Y');

        // Hosted logo (never a data: URI — Gmail strips those). Sharp fallback box.
        $logo_html = (class_exists('SS_Admin') && method_exists('SS_Admin', 'logo_html_for_email'))
            ? SS_Admin::logo_html_for_email()
            : '<div style="background:' . self::GREEN . ';width:36px;height:36px;text-align:center;font-size:19px;font-weight:900;color:#fff;font-family:Georgia,serif;line-height:36px;">Ą</div>';

        $btn_html = !empty($opts['button']['url'])
            ? self::button($opts['button']['text'] ?? 'Atidaryti', $opts['button']['url'], self::button_color($accent))
            : '';

        $note_html = $note !== '' ? self::notice($note, $note_type) : '';

        $subtitle_html = $subtitle !== ''
            ? '<p style="margin:0 0 26px;font-size:15px;line-height:1.55;color:' . self::MUTE . ';">' . esc_html($subtitle) . '</p>'
            : '';

        $h1_mb = $subtitle !== '' ? '14px' : '22px';
        $font  = "'Google Sans','DM Sans',Helvetica,Arial,sans-serif";

        return '<!DOCTYPE html>
<html lang="lt"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . esc_html($title) . '</title>
<style>' . self::font_face_css() . '</style>
</head>
<body style="margin:0;padding:0;background:' . self::BG . ';font-family:' . $font . ';">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . self::BG . ';padding:48px 16px 72px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border-top:3px solid ' . $accent . ';box-shadow:0 2px 12px rgba(1,42,54,0.06);">

      <!-- HEADER -->
      <tr><td style="padding:30px 44px 0;">
        <table cellpadding="0" cellspacing="0" border="0"><tr>
          <td style="vertical-align:middle;">' . $logo_html . '</td>
          <td style="padding-left:12px;vertical-align:middle;">
            <div style="font-size:14px;font-weight:700;color:' . self::DARK . ';line-height:1.2;">' . esc_html($school) . '</div>
            <div style="font-size:10px;font-weight:600;letter-spacing:0.5px;text-transform:uppercase;color:' . self::MUTE . ';margin-top:3px;">Tvarkaraščių valdymo sistema</div>
          </td>
        </tr></table>
        <div style="height:1px;background:' . self::LINE . ';margin:24px 0 0;font-size:0;line-height:0;">&nbsp;</div>
      </td></tr>

      <!-- MAIN -->
      <tr><td style="padding:36px 44px 40px;">
        <h1 style="margin:0 0 ' . $h1_mb . ';font-size:30px;font-weight:800;letter-spacing:-0.7px;line-height:1.12;color:' . self::DARK . ';">' . esc_html($title) . '</h1>
        ' . $subtitle_html . '
        <div style="font-size:16px;line-height:1.7;color:' . self::BODY . ';">' . $body . '</div>
        ' . $extra . '
        ' . $btn_html . '
        ' . $note_html . '
        <div style="height:1px;background:' . self::LINE . ';margin:34px 0 0;font-size:0;line-height:0;">&nbsp;</div>
        <p style="margin:18px 0 0;font-size:12px;line-height:1.6;color:' . self::MUTE . ';">© ' . esc_html($year) . ' ' . esc_html($school) . ' · Tvarkaraščių valdymo sistema.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body></html>';
    }

    /**
     * @font-face for the brand body face (DM Sans, the free substitute for
     * Google Sans). Self-hosted woff2 in public/fonts/ — clients that support
     * web fonts in email (Apple Mail, iOS, Samsung Mail…) render DM Sans; the
     * rest fall back gracefully through the Helvetica/Arial stack. Two unicode
     * ranges so Lithuanian latin-ext glyphs (ąčęėįšųūž) resolve correctly.
     */
    private static function font_face_css() {
        $ext = esc_url(SS_PLUGIN_URL . 'public/fonts/dm-sans-latin-ext.woff2');
        $lat = esc_url(SS_PLUGIN_URL . 'public/fonts/dm-sans-latin.woff2');
        $range_ext = 'U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF';
        $range_lat = 'U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD';
        $css = '';
        foreach (array(400, 500, 600, 700) as $w) {
            $css .= "@font-face{font-family:'DM Sans';font-style:normal;font-weight:{$w};font-display:swap;src:url('{$ext}') format('woff2');unicode-range:{$range_ext};}";
            $css .= "@font-face{font-family:'DM Sans';font-style:normal;font-weight:{$w};font-display:swap;src:url('{$lat}') format('woff2');unicode-range:{$range_lat};}";
        }
        return $css;
    }

    /** Flat (no-radius) call-to-action button, left-aligned. */
    public static function button($text, $url, $color = self::FOREST) {
        return '
        <table cellpadding="0" cellspacing="0" border="0" style="margin:30px 0 0;"><tr><td>
          <a href="' . esc_url($url) . '" style="display:inline-block;background:' . $color . ';color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 30px;">' . esc_html($text) . '</a>
        </td></tr></table>';
    }

    /** Green-family accents get the deep forest CTA; everything else keeps its accent. */
    private static function button_color($accent) {
        return strtoupper((string)$accent) === strtoupper(self::GREEN) ? self::FOREST : $accent;
    }

    /**
     * Verification-code block — underline typographic style (no box).
     * $expires already WP-formatted. (Third arg kept for back-compat.)
     */
    public static function code_box($code, $expires_human = '', $accent = self::GREEN) {
        $exp = $expires_human !== ''
            ? '<p style="margin:18px 0 0;font-size:13px;color:' . self::GREEN2 . ';">Kodas galioja iki <strong style="color:' . self::DARK . ';">' . esc_html($expires_human) . '</strong>.</p>'
            : '';
        return '
        <div style="margin:30px 0 4px;">
          <div style="font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:' . self::MUTE . ';margin-bottom:12px;">Patvirtinimo kodas</div>
          <div style="display:inline-block;font-size:44px;font-weight:800;letter-spacing:0.18em;color:' . self::DARK . ';line-height:1;padding-bottom:20px;border-bottom:2px solid ' . self::DARK . ';">' . esc_html($code) . '</div>
          ' . $exp . '
        </div>';
    }

    /** A two-column info list — hairline-ruled rows, no background box. */
    public static function info_list($rows) {
        $cells = '';
        foreach ((array)$rows as $r) {
            $cells .= '<tr>
              <td style="padding:12px 0;border-bottom:1px solid ' . self::LINE . ';font-size:13px;color:' . self::MUTE . ';width:40%;vertical-align:top;">' . esc_html($r['label']) . '</td>
              <td style="padding:12px 0;border-bottom:1px solid ' . self::LINE . ';font-size:14px;color:' . self::DARK . ';font-weight:600;text-align:right;vertical-align:top;">' . ($r['value'] ?? '') . '</td>
            </tr>';
        }
        return '
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 4px;border-top:1px solid ' . self::LINE . ';">
          ' . $cells . '
        </table>';
    }

    /** Coloured note/callout box — flat, accent left rule. */
    public static function notice($html, $type = 'info') {
        $styles = array(
            'info'  => array('bg' => '#EEF3F7', 'border' => self::NAVY, 'text' => self::BODY),
            'warn'  => array('bg' => '#FBF6EC', 'border' => '#7A5B14',  'text' => self::BODY),
            'error' => array('bg' => '#FCEEEE', 'border' => self::RED,  'text' => self::DARK),
        );
        $s = $styles[$type] ?? $styles['info'];
        return '
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 4px;">
          <tr><td style="background:' . $s['bg'] . ';border-left:3px solid ' . $s['border'] . ';padding:14px 18px;">
            <div style="font-size:13px;line-height:1.6;color:' . $s['text'] . ';">' . $html . '</div>
          </td></tr>
        </table>';
    }
}
