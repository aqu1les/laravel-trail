<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

use Illuminate\Http\Request;

class ContextCapture
{
    /**
     * Capture privacy-aware request context.
     *
     * @return array<string, mixed>
     */
    public function fromRequest(Request $request): array
    {
        /** @var array<string, mixed> $privacy */
        $privacy = (array) config('trail.privacy', []);

        $storeIp = (bool) ($privacy['store_ip'] ?? false);
        $anonymizeIp = (bool) ($privacy['anonymize_ip'] ?? true);
        $storeUserAgent = (bool) ($privacy['store_user_agent'] ?? true);

        $context = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'referrer' => $request->headers->get('referer'),
        ];

        if ($storeIp) {
            $ip = $request->ip();

            $context['ip'] = $ip !== null && $anonymizeIp ? $this->anonymizeIp($ip) : $ip;
        }

        if ($storeUserAgent) {
            $context['user_agent'] = $request->userAgent();
        }

        return array_filter($context, static fn ($value): bool => $value !== null);
    }

    protected function anonymizeIp(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $blocks = explode(':', $ip);
            $blocks = array_slice($blocks, 0, 3);

            return implode(':', $blocks).'::';
        }

        $octets = explode('.', $ip);

        if (count($octets) === 4) {
            $octets[3] = '0';

            return implode('.', $octets);
        }

        return $ip;
    }
}
