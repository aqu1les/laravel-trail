<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Trail\Trail\Facades\Trail;

class IngestController
{
    /** Clamp timestamps further than this into the future back to "now" (seconds). */
    private const FUTURE_SKEW_SECONDS = 300;

    /** Clamp timestamps older than this back to "now" (seconds, ~30 days). */
    private const MAX_AGE_SECONDS = 2592000;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:'.(int) config('trail.browser.max_batch', 50)],
        ]);

        $key = $this->throttleKey($request);
        [$maxAttempts, $minutes] = $this->rateLimit();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json(['message' => 'Too Many Requests.'], 429);
        }

        RateLimiter::hit($key, $minutes * 60);

        $allowed = config('trail.browser.allowed_events');
        $recorder = config('trail.browser.recorder');

        $accepted = 0;

        /** @var array<int, mixed> $events */
        $events = (array) $request->input('events', []);

        foreach ($events as $event) {
            if (! $this->isValidEvent($event)) {
                continue;
            }

            $name = $event['name'];

            if (is_array($allowed) && ! in_array($name, $allowed, true)) {
                continue;
            }

            $properties = isset($event['properties']) && is_array($event['properties']) ? $event['properties'] : [];
            $value = isset($event['value']) && is_numeric($event['value']) ? (float) $event['value'] : null;

            Trail::withSession($this->sessionId($request, $event))
                ->usingRecorder(is_string($recorder) ? $recorder : null)
                ->at($this->occurredAt($event))
                ->track($name, $properties, $value);

            $accepted++;
        }

        return response()->json(['accepted' => $accepted], 202);
    }

    /**
     * @param  mixed  $event
     */
    private function isValidEvent($event): bool
    {
        if (! is_array($event)) {
            return false;
        }

        $name = $event['name'] ?? null;

        if (! is_string($name) || $name === '' || mb_strlen($name) > 255) {
            return false;
        }

        if (isset($event['properties']) && ! is_array($event['properties'])) {
            return false;
        }

        if (isset($event['value']) && ! is_numeric($event['value'])) {
            return false;
        }

        if (isset($event['session_id']) && ! is_string($event['session_id'])) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function occurredAt(array $event): Carbon
    {
        $now = Carbon::now();
        $raw = $event['occurred_at'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return $now;
        }

        try {
            $parsed = Carbon::parse($raw);
        } catch (\Throwable) {
            return $now;
        }

        if ($parsed->greaterThan($now->copy()->addSeconds(self::FUTURE_SKEW_SECONDS))) {
            return $now;
        }

        if ($parsed->lessThan($now->copy()->subSeconds(self::MAX_AGE_SECONDS))) {
            return $now;
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function sessionId(Request $request, array $event): string
    {
        $fromClient = $event['session_id'] ?? null;

        if (is_string($fromClient) && $fromClient !== '') {
            return $fromClient;
        }

        return $request->hasSession() ? (string) $request->session()->getId() : '';
    }

    private function throttleKey(Request $request): string
    {
        $identity = $request->user()?->getAuthIdentifier()
            ?? ($request->hasSession() ? $request->session()->getId() : null)
            ?? $request->ip()
            ?? 'unknown';

        return 'trail:ingest:'.$identity;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function rateLimit(): array
    {
        $raw = (string) config('trail.browser.rate_limit', '120,1');
        $parts = explode(',', $raw);

        $attempts = (int) $parts[0];
        $minutes = (int) ($parts[1] ?? 1);

        return [max(1, $attempts), max(1, $minutes)];
    }
}
