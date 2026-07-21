<?php

declare(strict_types=1);

namespace Trail\Trail\Livewire\Concerns;

use Trail\Trail\Models\TrailEvent;

/**
 * Maps real TrailEvent records onto the array shape the dashboard views
 * expect, so the same Blade renders both real and demo (Sample) data.
 */
trait ResolvesEvents
{
    /** Derive a stable color from the event name using the dashboard's chart palette. */
    protected function colorFor(string $name): string
    {
        $index = (crc32($name) % 5 + 5) % 5 + 1;

        return "var(--trail-chart-{$index})";
    }

    /** Resolve the actor's display identity from the morphTo subject. */
    protected function subjectLabel(TrailEvent $event): array
    {
        if ($event->subject_type === null && $event->subject_id === null) {
            return ['name' => 'Anônimo', 'type' => 'Anônimo', 'id' => '-'];
        }

        $type = $event->subject_type ? class_basename($event->subject_type) : 'Anônimo';
        $id = $event->subject_id !== null ? (string) $event->subject_id : '-';

        $subject = $event->relationLoaded('subject') ? $event->getRelation('subject') : null;
        $name = $subject->name ?? $subject->email ?? "{$type} #{$id}";

        return ['name' => (string) $name, 'type' => $type, 'id' => $id];
    }

    /** Normalise a TrailEvent into the view's event array shape. */
    protected function normalizeEvent(TrailEvent $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'color' => $this->colorFor($event->name),
            'actor' => $this->subjectLabel($event),
            'subject_key' => $event->subject_type !== null && $event->subject_id !== null
                ? $event->subject_type.'|'.$event->subject_id
                : '',
            'value' => $event->value !== null ? (float) $event->value : null,
            'props' => $event->properties ?? [],
            'context' => $event->context ?? [],
            'ts' => (int) $event->occurred_at->getTimestampMs(),
        ];
    }
}
