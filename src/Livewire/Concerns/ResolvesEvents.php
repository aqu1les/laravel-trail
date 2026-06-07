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
    /** Derive a display category from the event name's domain prefix. */
    protected function categoryFor(string $name): string
    {
        $prefix = strtok($name, '.') ?: $name;

        return match (true) {
            in_array($prefix, ['order', 'invoice', 'cart', 'subscription', 'checkout', 'payment'], true) => 'commerce',
            in_array($prefix, ['user', 'auth', 'login', 'account'], true) => 'auth',
            $prefix === 'onboarding' => 'onboarding',
            in_array($prefix, ['whatsapp', 'integration', 'webhook', 'sync'], true) => 'integration',
            default => 'system',
        };
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
            'cat' => $this->categoryFor($event->name),
            'actor' => $this->subjectLabel($event),
            'value' => $event->value !== null ? (float) $event->value : null,
            'props' => $event->properties ?? [],
            'context' => $event->context ?? [],
            'ts' => (int) $event->occurred_at->getTimestampMs(),
        ];
    }

    /**
     * Batch-resolve subject identities, one query per distinct subject type
     * (avoids an N+1 find() per actor).
     *
     * @param  list<array{0: ?string, 1: mixed}>  $pairs  [subject_type, subject_id]
     * @return array<string, array{name: ?string, email: ?string}> keyed by "type|id"
     */
    protected function resolveIdentities(array $pairs): array
    {
        $idsByType = [];
        foreach ($pairs as [$type, $id]) {
            if ($type !== null && class_exists($type)) {
                $idsByType[$type][] = $id;
            }
        }

        $out = [];
        foreach ($idsByType as $type => $ids) {
            foreach ($type::query()->whereKey(array_values(array_unique($ids)))->get() as $model) {
                $out[$type.'|'.$model->getKey()] = [
                    'name' => $model->name ?? null,
                    'email' => $model->email ?? null,
                ];
            }
        }

        return $out;
    }
}
