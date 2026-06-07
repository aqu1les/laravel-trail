<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

use Illuminate\Http\Request;
use Trail\Trail\Contracts\ContextCaptureContract;

class ContextCapture implements ContextCaptureContract
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

    /**
     * Capture process-level context for console commands and queue workers.
     *
     * @return array<string, mixed>
     */
    public function fromConsole(): array
    {
        /** @var array<string, mixed> $console */
        $console = (array) config('trail.console', []);

        $context = [];

        if ($console['capture_hostname'] ?? true) {
            $hostname = $this->resolveHostname();
            if ($hostname !== null) {
                $context['hostname'] = $hostname;
            }
        }

        if ($console['capture_pid'] ?? true) {
            $pid = $this->resolvePid();
            if ($pid !== null) {
                $context['pid'] = $pid;
            }
        }

        if ($console['capture_command'] ?? true) {
            $command = $this->resolveCommand();
            if ($command !== null) {
                $context['command'] = $command;
            }
        }

        if ($console['capture_command_arguments'] ?? false) {
            $args = $this->resolveCommandArguments();
            if ($args !== null) {
                $context['command_arguments'] = $args;
            }
        }

        if ($console['capture_server_ip'] ?? false) {
            $ip = $this->resolveServerIp();
            if ($ip !== null) {
                $context['server_ip'] = $ip;
            }
        }

        return $context;
    }

    protected function resolveHostname(): ?string
    {
        $hostname = gethostname();

        return $hostname !== false ? $hostname : null;
    }

    protected function resolveServerIp(): ?string
    {
        $hostname = gethostname();
        if ($hostname === false) {
            return null;
        }

        $ip = gethostbyname($hostname);

        // gethostbyname returns the hostname unchanged when resolution fails
        return $ip !== $hostname ? $ip : null;
    }

    protected function resolveCommand(): ?string
    {
        $argv = $_SERVER['argv'] ?? [];

        // argv[0] is the script (artisan), argv[1] is the command name
        return isset($argv[1]) && $argv[1] !== '' ? $argv[1] : null;
    }

    protected function resolveCommandArguments(): ?string
    {
        $argv = $_SERVER['argv'] ?? [];

        $args = \array_slice($argv, 2);

        return $args !== [] ? implode(' ', $args) : null;
    }

    protected function resolvePid(): ?int
    {
        $pid = getmypid();

        return $pid !== false ? $pid : null;
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
