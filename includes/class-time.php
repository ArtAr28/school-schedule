<?php
if (!defined('ABSPATH')) exit;

/**
 * Centralized time helper.
 *
 * Always returns/formats times using the WordPress site's
 * configured timezone (Settings → General → Timezone). This
 * automatically handles DST (winter/summer) for named zones
 * such as Europe/Vilnius.
 *
 * The 16:00 cut-off used across the plugin (today's schedule
 * shown until 16:00, then next day; on Friday after 16:00 and
 * the whole weekend — Monday of next week) is implemented in
 * `active_day()` and `active_monday()` here so all views stay
 * consistent.
 */
class SS_Time {

    /** DateTimeZone configured in WordPress settings. */
    public static function tz() {
        if (function_exists('wp_timezone')) {
            return wp_timezone();
        }
        // Fallback for very old WP: build from the string option.
        $tz_string = get_option('timezone_string');
        if (!$tz_string) {
            $offset = (float) get_option('gmt_offset', 0);
            $hours  = (int) $offset;
            $mins   = abs(($offset - $hours) * 60);
            $tz_string = sprintf('%+03d:%02d', $hours, $mins);
        }
        return new DateTimeZone($tz_string);
    }

    /** DateTime "now" in WP timezone. */
    public static function now() {
        return new DateTime('now', self::tz());
    }

    /**
     * Format a timestamp (or now if null) in WP timezone.
     *
     * @param string   $format    PHP date format.
     * @param int|null $timestamp Unix timestamp (UTC seconds) or null for now.
     */
    public static function date($format, $timestamp = null) {
        $dt = new DateTime('@' . ($timestamp === null ? time() : (int)$timestamp));
        $dt->setTimezone(self::tz());
        return $dt->format($format);
    }

    /** Today's date in WP timezone, Y-m-d. */
    public static function today_ymd() {
        return self::now()->format('Y-m-d');
    }

    /** Day of week in WP timezone: 1=Mon … 7=Sun. */
    public static function dow() {
        return (int)self::now()->format('N');
    }

    /** Hour 0-23 in WP timezone. */
    public static function hour() {
        return (int)self::now()->format('G');
    }

    /**
     * "Active" day number that the dashboard should display:
     *   - Mon-Thu before 16:00 → that weekday (1-4)
     *   - Mon-Thu at/after 16:00 → next weekday (2-5)
     *   - Fri before 16:00 → 5
     *   - Fri at/after 16:00 → 1 (Monday of next week)
     *   - Sat/Sun → 1 (Monday of next week)
     *
     * Returns an integer 1-5.
     */
    public static function active_day() {
        $dow  = self::dow();
        $hour = self::hour();
        if ($dow >= 6)                            return 1;            // weekend
        if ($dow === 5 && $hour >= 16)            return 1;            // Friday after 16
        if ($hour >= 16)                          return $dow + 1;     // Mon-Thu after 16
        return $dow;                                                   // Mon-Fri during day
    }

    /**
     * Real today's day-of-week number for "Šiandien" highlighting,
     * returns 1-5 on weekdays or 0 on weekends.
     */
    public static function today_num() {
        $dow = self::dow();
        return ($dow >= 1 && $dow <= 5) ? $dow : 0;
    }

    /**
     * Monday (Y-m-d) of the "active" week. Matches active_day():
     *   - Mon-Thu before 16:00 → this week's Monday
     *   - Mon-Thu at/after 16:00 → this week's Monday
     *     (next-day rollover happens within the same week)
     *   - Fri before 16:00 → this week's Monday
     *   - Fri at/after 16:00 → next week's Monday
     *   - Sat/Sun → next week's Monday
     */
    public static function active_monday() {
        $now  = self::now();
        $dow  = (int)$now->format('N');
        $hour = (int)$now->format('G');

        if ($dow >= 6) {
            // Sat → +2, Sun → +1
            $now->modify('+' . (8 - $dow) . ' days');
        } elseif ($dow === 5 && $hour >= 16) {
            $now->modify('+3 days'); // Friday → Monday
        } else {
            $now->modify('-' . ($dow - 1) . ' days'); // back to Monday
        }
        return $now->format('Y-m-d');
    }

    /**
     * Returns Y-m-d for the active day combined with active_monday().
     * Useful so any caller can compute the actual date being shown
     * without re-implementing the rollover rules.
     */
    public static function active_date() {
        $monday = new DateTime(self::active_monday(), self::tz());
        $monday->modify('+' . (self::active_day() - 1) . ' days');
        return $monday->format('Y-m-d');
    }
}
