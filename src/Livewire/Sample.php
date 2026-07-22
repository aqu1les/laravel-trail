<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire;

use Illuminate\Support\Carbon;

/**
 * Representative sample data for the dashboard screens.
 *
 * This is a placeholder so the Livewire screens render and interact with
 * realistic shapes while the data layer is built. Swap these methods for
 * real queries (Trail::events(), aggregates, …) without touching the views:
 * the array shapes mirror what the real source will return.
 */
final class Sample
{
    /** Known actors (the polymorphic subjects). */
    public static function actors(): array
    {
        return [
            ['name' => 'Marina Rocha', 'type' => 'User', 'id' => 'ator_8821', 'email' => 'marina@acme.app'],
            ['name' => 'João Silva', 'type' => 'User', 'id' => 'ator_3390', 'email' => 'joao@acme.app'],
            ['name' => 'Beatriz Lima', 'type' => 'User', 'id' => 'ator_7745', 'email' => 'bia@studio.co'],
            ['name' => 'Pedro Alves', 'type' => 'User', 'id' => 'ator_1182', 'email' => 'pedro@acme.app'],
            ['name' => 'Carla Dias', 'type' => 'User', 'id' => 'ator_5567', 'email' => 'carla@norte.io'],
            ['name' => 'Acme Team', 'type' => 'Team', 'id' => 'team_204', 'email' => 'ops@acme.app'],
            ['name' => 'Rafael Souza', 'type' => 'User', 'id' => 'ator_9043', 'email' => 'rafa@acme.app'],
            ['name' => 'Studio Norte', 'type' => 'Team', 'id' => 'team_511', 'email' => 'hello@norte.io'],
        ];
    }

    /** Event templates: name → property factory + optional value. */
    private static function templates(): array
    {
        return [
            ['name' => 'order.placed', 'value' => fn () => round(mt_rand(2000, 42000) / 100, 2),
                'props' => fn () => ['amount' => round(mt_rand(2000, 42000) / 100, 2), 'currency' => 'BRL', 'items' => mt_rand(1, 5), 'first_order' => mt_rand(0, 9) < 3]],
            ['name' => 'user.signed_up',
                'props' => fn () => ['plan' => self::pick(['free', 'pro', 'team']), 'method' => self::pick(['email', 'google', 'github']), 'referrer' => self::pick(['organic', 'ads', 'invite'])]],
            ['name' => 'onboarding.step_completed',
                'props' => fn () => ['step' => self::pick(['profile', 'workspace', 'invite', 'first_event']), 'index' => mt_rand(1, 4), 'duration_ms' => mt_rand(800, 9000)]],
            ['name' => 'whatsapp.connected',
                'props' => fn () => ['provider' => 'meta_cloud', 'phone_masked' => '+55 11 9••••-'.mt_rand(1000, 9999), 'verified' => true]],
            ['name' => 'cart.updated',
                'props' => fn () => ['items' => mt_rand(1, 8), 'total' => round(mt_rand(1000, 61000) / 100, 2), 'currency' => 'BRL']],
            ['name' => 'invoice.paid', 'value' => fn () => round(mt_rand(4900, 94900) / 100, 2),
                'props' => fn () => ['amount' => round(mt_rand(4900, 94900) / 100, 2), 'plan' => self::pick(['pro', 'team']), 'period' => 'monthly']],
            ['name' => 'user.logged_in',
                'props' => fn () => ['method' => self::pick(['email', 'google', 'sso']), 'ip_masked' => '187.•••.•••.'.mt_rand(2, 254)]],
            ['name' => 'session.started',
                'props' => fn () => ['device' => self::pick(['desktop', 'mobile', 'tablet']), 'os' => self::pick(['macOS', 'Windows', 'iOS', 'Android']), 'app_version' => '2.'.mt_rand(1, 9).'.'.mt_rand(0, 9)]],
        ];
    }

    /** Derive a stable color from an event name using the chart palette. */
    public static function colorFor(string $name): string
    {
        $index = (crc32($name) % 5 + 5) % 5 + 1;

        return "var(--trail-chart-{$index})";
    }

    /** Build a single event at the given epoch-millis timestamp. */
    public static function makeEvent(int $ts, int $id): array
    {
        $t = self::pick(self::templates());
        $actor = self::pick(self::actors());

        return [
            'id' => $id,
            'name' => $t['name'],
            'color' => self::colorFor($t['name']),
            'actor' => $actor,
            'value' => isset($t['value']) ? ($t['value'])() : null,
            'props' => ($t['props'])(),
            'context' => [
                'ip' => '187.'.mt_rand(0, 255).'.'.mt_rand(0, 255).'.'.mt_rand(0, 255),
                'user_agent' => self::pick(['Chrome/124', 'Safari/17', 'Firefox/126']),
                'session_id' => 'sess_'.mt_rand(10000, 99999),
                'source' => self::pick(['web', 'mobile_sdk', 'api']),
            ],
            'ts' => $ts,
        ];
    }

    /** Seed a descending-by-time stream of events. */
    public static function stream(int $count = 50): array
    {
        $now = self::nowMs();
        $events = [];
        for ($i = 0; $i < $count; $i++) {
            $events[] = self::makeEvent($now - $i * mt_rand(20000, 600000), $i + 1);
        }

        return $events; // already descending (newest first)
    }

    /** A single actor's history, grouped-ready (descending by time). */
    public static function actorHistory(array $actor): array
    {
        $now = self::nowMs();
        $events = [];
        $seq = 1;

        // Signup at the very start.
        $t0 = $now - (6 * 86400000) - mt_rand(2, 8) * 3600000;
        $events[] = self::makeNamed('user.signed_up', $t0, $seq++);

        $cursor = $now;
        $n = mt_rand(26, 40);
        $pool = array_values(array_filter(self::templates(), fn ($t) => $t['name'] !== 'user.signed_up'));
        for ($i = 0; $i < $n; $i++) {
            $cursor -= mt_rand(20 * 60000, 9 * 3600000);
            $events[] = self::makeNamed(self::pick($pool)['name'], $cursor, $seq++);
        }

        usort($events, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return $events;
    }

    private static function makeNamed(string $name, int $ts, int $id): array
    {
        $t = collect(self::templates())->firstWhere('name', $name);

        return [
            'id' => $id,
            'name' => $name,
            'color' => self::colorFor($name),
            'value' => isset($t['value']) ? ($t['value'])() : null,
            'props' => ($t['props'])(),
            'context' => [
                'ip' => '187.'.mt_rand(0, 255).'.'.mt_rand(0, 255).'.'.mt_rand(0, 255),
                'user_agent' => self::pick(['Chrome/124', 'Safari/17']),
                'session_id' => 'sess_'.mt_rand(10000, 99999),
            ],
            'ts' => $ts,
        ];
    }

    /**
     * Representative reconstructed paths for the Paths screen in demo mode.
     * Already in the display shape the screen consumes, newest first.
     *
     * @return list<array{name: string, type: string, id: string, when: string, steps: list<array{name: string, gap: ?string}>}>
     */
    public static function paths(): array
    {
        $templates = [
            [['register', null], ['number_verified', '+38s'], ['whatsapp.connected', '+2min'], ['order.placed', '+1h']],
            [['register', null], ['number_verified', '+1min'], ['order.placed', '+9min'], ['invoice.paid', '+3h']],
            [['register', null], ['number_verified', '+22s'], ['order.placed', '+5min']],
            [['register', null], ['number_verified', '+2min'], ['whatsapp.connected', '+40s'], ['cart.updated', '+6min']],
            [['register', null], ['number_verified', '+1min'], ['invoice.paid', '+2h']],
            [['register', null]],
        ];

        $agoMinutes = [4, 12, 26, 41, 60, 120];

        $now = self::nowMs();
        $rows = [];

        foreach (self::actors() as $index => $actor) {
            $template = $templates[$index % count($templates)];
            $minutesAgo = $agoMinutes[$index % count($agoMinutes)];

            $rows[] = [
                'name' => $actor['name'],
                'type' => $actor['type'],
                'id' => $actor['id'],
                'when' => self::relative($now - $minutesAgo * 60000),
                'steps' => array_map(fn (array $step) => ['name' => $step[0], 'gap' => $step[1]], $template),
            ];
        }

        return $rows;
    }

    /** Relative time label in Portuguese, from an epoch-millis timestamp. */
    public static function relative(int $ts): string
    {
        $s = max(1, intdiv(self::nowMs() - $ts, 1000));
        if ($s < 5) {
            return 'agora';
        }
        if ($s < 60) {
            return "há {$s}s";
        }
        $m = intdiv($s, 60);
        if ($m < 60) {
            return "há {$m} min";
        }
        $h = intdiv($m, 60);
        if ($h < 24) {
            return "há {$h} h";
        }

        return 'há '.intdiv($h, 24).'d';
    }

    /** JSON payload rendered with Trail's .trail-code syntax-highlight spans. */
    public static function highlightJson(mixed $value): string
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $json = htmlspecialchars((string) $json, ENT_QUOTES);

        return (string) preg_replace_callback(
            '/("(?:\\\\.|[^"\\\\])*"(\s*:)?|\b(?:true|false|null)\b|-?\d+(?:\.\d+)?)/',
            function (array $m): string {
                $token = $m[0];
                if (str_starts_with($token, '"')) {
                    $cls = str_ends_with(rtrim($token), ':') ? 'k' : 's';
                } elseif (preg_match('/^(true|false|null)$/', $token)) {
                    $cls = 'b';
                } else {
                    $cls = 'n';
                }

                return '<span class="'.$cls.'">'.$token.'</span>';
            },
            $json
        );
    }

    /** Clock label (HH:MM) from epoch-millis. */
    public static function clock(int $ts): string
    {
        return Carbon::createFromTimestampMs($ts)->format('H:i');
    }

    /** Full date (e.g. "03 jun 2026") from epoch-millis. */
    public static function fullDate(int $ts): string
    {
        return Carbon::createFromTimestampMs($ts)->locale('pt_BR')->isoFormat('DD MMM YYYY');
    }

    /** Day separator label: Hoje / Ontem / "Qua, 03 jun". */
    public static function dayLabel(int $ts): string
    {
        $day = Carbon::createFromTimestampMs($ts)->startOfDay();
        $diff = (int) $day->diffInDays(Carbon::today(), true);

        if ($diff === 0) {
            return 'Hoje';
        }
        if ($diff === 1) {
            return 'Ontem';
        }

        return ucfirst($day->locale('pt_BR')->isoFormat('ddd, DD MMM'));
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($parts, 0, 2))));
    }

    private static function nowMs(): int
    {
        return (int) (microtime(true) * 1000);
    }

    private static function pick(array $a): mixed
    {
        return $a[array_rand($a)];
    }
}
