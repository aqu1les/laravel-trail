<?php

declare(strict_types=1);

namespace Trail\Trail\Queries;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Resolves subjects to the identity the dashboard displays.
 *
 * @internal Not covered by the package's backwards-compatibility promise.
 */
final class SubjectIdentity
{
    /**
     * Batch-resolve identities, one query per distinct subject type (avoids an
     * N+1 find() per actor).
     *
     * @param  list<SubjectKey>  $keys
     * @return array<string, array{name: ?string, email: ?string}> keyed by "type|id"
     */
    public static function resolve(array $keys): array
    {
        $idsByType = [];

        foreach ($keys as $key) {
            $class = Relation::getMorphedModel($key->type) ?? $key->type;
            if (class_exists($class)) {
                $idsByType[$key->type] ??= ['class' => $class, 'ids' => []];
                $idsByType[$key->type]['ids'][] = $key->id;
            }
        }

        $out = [];

        foreach ($idsByType as $type => $entry) {
            $models = $entry['class']::query()
                ->whereKey(array_values(array_unique($entry['ids'])))
                ->get();

            foreach ($models as $model) {
                $out[$type.'|'.$model->getKey()] = [
                    'name' => $model->name ?? null,
                    'email' => $model->email ?? null,
                ];
            }
        }

        return $out;
    }

    /**
     * The display shape every actor list and header uses. Falls back to
     * "User #7" when the subject has no resolvable identity.
     *
     * $emailAsName decides whether a subject with an email but no name is shown
     * by its email or by its id. The screens differ on purpose: the Events
     * actor menu has no other place to show an email, while the timeline's
     * index and switcher deliberately keep addresses off a list view.
     *
     * @param  array<string, array{name: ?string, email: ?string}>  $identities  keyed by "type|id"
     * @return array{key: string, name: string, type: string, id: string, email: ?string}
     */
    public static function display(SubjectKey $key, array $identities, bool $emailAsName = false): array
    {
        $identity = $identities[(string) $key] ?? [];
        $label = $key->label();
        $fallback = $emailAsName ? $identity['email'] ?? null : null;

        return [
            'key' => (string) $key,
            'name' => (string) ($identity['name'] ?? $fallback ?? $label.' #'.$key->id),
            'type' => $label,
            'id' => $key->id,
            'email' => $identity['email'] ?? null,
        ];
    }

    /**
     * The display shape for a raw row, which may carry an id without a type.
     * Such a row is not selectable (there is nothing to filter on), but it still
     * shows up in the lists with its id rather than disappearing from them.
     *
     * @param  array<string, array{name: ?string, email: ?string}>  $identities
     * @return array{key: string, name: string, type: string, id: string, email: ?string}
     */
    public static function displayRow(?string $type, mixed $id, array $identities, bool $emailAsName = false): array
    {
        $key = SubjectKey::of($type, $id);

        if ($key !== null) {
            return self::display($key, $identities, $emailAsName);
        }

        return ['key' => '', 'name' => 'Anônimo #'.$id, 'type' => 'Anônimo', 'id' => (string) $id, 'email' => null];
    }

    /** The display shape for an event with no subject at all. */
    public static function anonymous(): array
    {
        return ['key' => '', 'name' => 'Anônimo', 'type' => 'Anônimo', 'id' => '-', 'email' => null];
    }
}
