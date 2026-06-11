<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

class ConfigMerge
{
    /**
     * Recursively merge package defaults under the user's published config.
     *
     * User values always win; package defaults only fill keys the user is
     * missing. Lists (sequential integer keys, e.g. the ignore patterns) are
     * replaced wholesale by the user's value rather than concatenated.
     *
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $overrides): array
    {
        $merged = $defaults;

        foreach ($overrides as $key => $value) {
            if (
                is_array($value)
                && isset($merged[$key])
                && is_array($merged[$key])
                && self::isAssoc($value)
                && self::isAssoc($merged[$key])
            ) {
                $merged[$key] = self::merge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @param  array<mixed>  $array
     */
    private static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
