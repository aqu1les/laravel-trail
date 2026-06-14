<?php

declare(strict_types=1);

namespace Trail\Trail;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Trail\Trail\Contracts\ContextCaptureContract;
use Trail\Trail\Models\TrailEvent;
use Trail\Trail\Support\MorphType;

class PendingEvent
{
    protected ?Model $subject = null;

    protected bool $resolveDefaultSubject = true;

    protected ?string $sessionId = null;

    /** @var array<string, mixed> */
    protected array $context = [];

    protected ?string $recorder = null;

    protected ?DateTimeInterface $occurredAt = null;

    public function __construct(protected RecorderManager $recorders) {}

    public function for(Model $subject): static
    {
        $this->subject = $subject;
        $this->resolveDefaultSubject = false;

        return $this;
    }

    public function anonymous(): static
    {
        $this->subject = null;
        $this->resolveDefaultSubject = false;

        return $this;
    }

    public function withSession(string $sessionId): static
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function at(?DateTimeInterface $time): static
    {
        $this->occurredAt = $time;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    public function sync(): static
    {
        $this->recorder = 'sync';

        return $this;
    }

    public function queue(): static
    {
        $this->recorder = 'queue';

        return $this;
    }

    public function ingest(): static
    {
        $this->recorder = 'ingest';

        return $this;
    }

    public function usingRecorder(?string $recorder): static
    {
        $this->recorder = $recorder;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function track(string $name, array $properties = [], ?float $value = null): ?TrailEvent
    {
        $subject = $this->resolveSubject();

        $context = array_merge($this->captureContext(), $this->context);

        $subjectType = $subject !== null ? MorphType::of($subject) : null;

        return $this->recorders->driver($this->recorder)->record([
            'name' => $name,
            'subject_type' => $subjectType,
            'subject_id' => $subject?->getKey(),
            'session_id' => $this->sessionId,
            'properties' => $properties ?: null,
            'context' => $context !== [] ? $context : null,
            'value' => $value,
            'occurred_at' => $this->occurredAt !== null ? Carbon::instance($this->occurredAt) : Carbon::now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function captureContext(): array
    {
        $capture = app(ContextCaptureContract::class);

        // A real routed HTTP request (including test-simulated ones) takes priority.
        if (app()->bound('request') && request()->route() !== null) {
            return $capture->fromRequest(request());
        }

        if (app()->runningInConsole()) {
            return $capture->fromConsole();
        }

        return [];
    }

    protected function resolveSubject(): ?Model
    {
        if (! $this->resolveDefaultSubject) {
            return $this->subject;
        }

        $resolver = config('trail.subject.resolver');

        $resolved = match (true) {
            is_string($resolver) => app($resolver)(),
            is_callable($resolver) => $resolver(),
            default => auth()->user(),
        };

        return $resolved instanceof Model ? $resolved : null;
    }
}
