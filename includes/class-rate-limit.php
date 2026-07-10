<?php
/**
 * SS_RateLimit — apsauga nuo brutalios jėgos (brute force) atakų.
 *
 * Blokavimas vyksta pagal dviejų dalių raktą: IP + PASKYRA (vartotojo vardas).
 * Per daug nesėkmingų bandymų prisijungti prie KONKREČIOS paskyros iš to paties
 * IP laikinai užblokuoja TIK tą derinį. Todėl už vieno mokyklos NAT IP vieno
 * mokinio klaidos NEUŽRAKINA kitų mokinių paskyrų. User-Agent į raktą NEįeina —
 * priešingu atveju jį keičiant kiekvienai užklausai blokas būtų apeinamas.
 *
 * Eskalacija: kiekvienas paskesnis blokas vis ilgesnis (pvz. 1 → 5 → 30 min).
 * Pasibaigus blokui pradedamas švarus ciklas — vartotojas vėl gauna kelis
 * bandymus (blokas nebepratęsiamas su kiekvienu nauju bandymu).
 */
if (!defined('ABSPATH')) exit;

class SS_RateLimit {

    /** Skaičiavimo lango trukmė (sek.) — be klaidų per tiek laiko skaitiklis išvalomas. */
    private static function window(): int {
        return 15 * MINUTE_IN_SECONDS;
    }

    /**
     * Bloko raktas: IP + paskyra (vartotojo vardas). Paskyros dėmuo užtikrina, kad
     * vieno mokinio nesėkmės neužrakintų kitų mokinių už to paties NAT IP.
     * User-Agent SĄMONINGAI neįtraukiamas — kitaip jį keičiant kiekvienai užklausai
     * atakuotojas gautų naują „bucket" ir apeitų blokavimą.
     */
    private static function device_key(string $ip, string $username = ''): string {
        $acct = mb_strtolower(trim($username));
        return 'ss_rl_dev_' . md5($ip . '|' . $acct);
    }

    /** Eskalacijos trukmės (min.): 1-as, 2-as, 3-ias+ blokai. */
    private static function durations(): array {
        return [
            max(1, (int) get_option('ss_sec_lockout_minutes_1', 1)),
            max(1, (int) get_option('ss_sec_lockout_minutes_2', 5)),
            max(1, (int) get_option('ss_sec_lockout_minutes_3', 30)),
        ];
    }

    /** Kiek nesėkmių per ciklą leidžiama prieš bloką. */
    private static function fail_limit(): int {
        return max(1, (int) get_option('ss_sec_lockout_threshold_1', 5));
    }

    private static function get(string $ip, string $username = ''): array {
        $d = get_transient(self::device_key($ip, $username));
        return is_array($d)
            ? array_merge(['fails' => 0, 'strikes' => 0, 'until' => 0], $d)
            : ['fails' => 0, 'strikes' => 0, 'until' => 0];
    }

    private static function save(string $ip, array $d, string $username = ''): void {
        $remaining = ((int) $d['until'] > time()) ? ((int) $d['until'] - time()) : 0;
        // TTL turi pergyventi patį bloką — kitaip ilgas blokas „dingtų" anksčiau laiko.
        set_transient(self::device_key($ip, $username), $d, max(self::window(), $remaining));
    }

    /**
     * Ar šis ĮRENGINYS šiuo metu užblokuotas (visoms paskyroms)?
     * @return array|null  null jei leidžiama; kitaip ['blocked','retry_after','reason']
     */
    public static function check_login(string $ip, string $username = ''): ?array {
        $d = self::get($ip, $username);
        if (time() < (int) $d['until']) {
            return ['blocked' => true, 'retry_after' => (int) $d['until'] - time(), 'reason' => 'device'];
        }
        return null;
    }

    /** Likęs bloko laikas sekundėmis šiam IP+įrenginiui+paskyrai (0 jei neblokuota). */
    public static function lock_remaining(string $ip, string $username = ''): int {
        $d = self::get($ip, $username);
        return (time() < (int) $d['until']) ? ((int) $d['until'] - time()) : 0;
    }

    /**
     * Užregistruoja nesėkmingą bandymą šiam įrenginiui.
     * @return array ['fails','limit','until','retry_after','locked','remaining_attempts']
     *   - locked  : ar ŠIS bandymas ką tik įjungė bloką
     */
    public static function record_failure(string $ip, string $username = ''): array {
        $d = self::get($ip, $username);

        // Pasibaigus ankstesniam blokui — švarus ciklas (kad iškart vėl neužblokuotų).
        if ((int) $d['until'] > 0 && time() >= (int) $d['until']) {
            $d['fails'] = 0;
            $d['until'] = 0;
        }

        $d['fails']++;
        $limit  = self::fail_limit();
        $locked = false;

        if ($d['fails'] >= $limit) {
            $durs = self::durations();
            $idx  = min((int) $d['strikes'], count($durs) - 1);
            $d['until']   = time() + $durs[$idx] * MINUTE_IN_SECONDS;
            $d['strikes'] = (int) $d['strikes'] + 1;
            $d['fails']   = 0;          // naujas ciklas prasidės po bloko
            $locked       = true;
        }

        self::save($ip, $d, $username);

        $remaining = ((int) $d['until'] > time()) ? ((int) $d['until'] - time()) : 0;
        return [
            'fails'              => (int) $d['fails'],
            'limit'              => $limit,
            'until'              => (int) $d['until'],
            'retry_after'        => $remaining,
            'locked'             => $locked,
            'remaining_attempts' => $locked ? 0 : max(0, $limit - (int) $d['fails']),
        ];
    }

    /** Po sėkmingo prisijungimo — išvalo šio IP+įrenginio+paskyros skaitiklį. */
    public static function clear_on_success(string $ip, string $username = ''): void {
        $key = self::device_key($ip, $username);
        // Trinam tik jei skaitiklis išvis yra — kitaip kiekvienas sėkmingas
        // prisijungimas (800 vienu metu) darytų bereikalingą DB DELETE.
        if (get_transient($key) !== false) delete_transient($key);
    }

    /** Generinė skaitiklių logika (registracijai ir pan.). */
    public static function hit(string $bucket, int $max, int $ttl): bool {
        $key = 'ss_rl_b_' . md5($bucket);
        $count = (int) get_transient($key);
        if ($count >= $max) return false;
        set_transient($key, $count + 1, $ttl);
        return true;
    }

    /** Brute-force apsauga visada įjungta (žr. SS_Security::ALWAYS_ON). */
    public static function enabled(): bool {
        return true;
    }

    /** Patikima kliento IP nustatymo logika. */
    public static function get_client_ip(): string {
        $trust_proxy = (bool) get_option('ss_sec_trust_proxy', 0);
        $candidates  = $trust_proxy
            ? ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR']
            : ['REMOTE_ADDR'];

        foreach ($candidates as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = trim(explode(',', $_SERVER[$k])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
