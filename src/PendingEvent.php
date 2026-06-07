<?php

declare(strict_types=1);

namespace Trail\Trail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Trail\Trail\Models\TrailEvent;

class PendingEvent
{
    protected ?Model $subject = null;

    protected bool $resolveDefaultSubject = true;

    protected ?string $sessionId = null;

    /** @var array<string, mixed> */
    protected array $context = [];

    protected ?string $recorder = null;

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

    /**
     * @param  array<string, mixed>  $properties
     */
    public function track(string $name, array $properties = [], ?float $value = null): ?TrailEvent
    {
        $subject = $this->resolveSubject();

        return $this->recorders->driver($this->recorder)->record([
            'name' => $name,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'session_id' => $this->sessionId,
            'properties' => $properties ?: null,
            'context' => $this->context ?: null,
            'value' => $value,
            'occurred_at' => Carbon::now(),
        ]);
    }

    protected function resolveSubject(): ?Model
    {
        if (! $this->resolveDefaultSubject) {
            return $this->subject;
        }

        $resolver = config('trail.subject.resolver');

        $resolved = is_callable($resolver) ? $resolver() : null;

        return $resolved instanceof Model ? $resolved : null;
    }
}
