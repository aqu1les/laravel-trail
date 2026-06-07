<?php

declare(strict_types=1);

namespace Trail\Trail\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Trail\Trail\Models\TrailEvent;

class EventQuery
{
    /** @var Builder<TrailEvent> */
    protected Builder $query;

    public function __construct()
    {
        $this->query = TrailEvent::query()->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function for(Model $subject): static
    {
        $this->query
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());

        return $this;
    }

    public function anonymous(): static
    {
        $this->query->whereNull('subject_id');

        return $this;
    }

    public function named(string $name): static
    {
        $this->query->where('name', $name);

        return $this;
    }

    public function between(Carbon $from, Carbon $to): static
    {
        $this->query->whereBetween('occurred_at', [$from, $to]);

        return $this;
    }

    /**
     * @return Collection<int, TrailEvent>
     */
    public function get(): Collection
    {
        return $this->query->get();
    }

    /**
     * @return LengthAwarePaginator<int, TrailEvent>
     */
    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        return $this->query->paginate($perPage);
    }

    public function count(): int
    {
        return $this->query->count();
    }

    public function today(): int
    {
        return $this->between(now()->startOfDay(), now()->endOfDay())->count();
    }

    /**
     * @return Builder<TrailEvent>
     */
    public function toBuilder(): Builder
    {
        return $this->query;
    }
}
