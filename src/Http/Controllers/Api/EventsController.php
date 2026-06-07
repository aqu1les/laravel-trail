<?php

declare(strict_types=1);

namespace Trail\Trail\Http\Controllers\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Trail\Trail\Models\TrailEvent;

class EventsController
{
    /**
     * List events, newest first, with optional filters.
     *
     * @return LengthAwarePaginator<int, TrailEvent>
     */
    public function index(Request $request): LengthAwarePaginator
    {
        return TrailEvent::query()
            ->when($request->filled('name'), fn ($query) => $query->where('name', $request->string('name')))
            ->when($request->filled('subject_type'), fn ($query) => $query->where('subject_type', $request->string('subject_type')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('subject_id', $request->string('subject_id')))
            ->when($request->filled('session_id'), fn ($query) => $query->where('session_id', $request->string('session_id')))
            ->when($request->filled('from'), fn ($query) => $query->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('occurred_at', '<=', $request->date('to')))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(perPage: (int) $request->integer('per_page', 50));
    }
}
