<?php

declare(strict_types=1);

namespace Trail\Trail\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

final class MorphType
{
    /**
     * Return the morph alias when the model is registered in the morph map,
     * or the fully-qualified class name when it is not.
     *
     * This avoids a ClassMorphViolationException from getMorphClass() when
     * the app enforces a morph map but has not registered every model in it.
     */
    public static function of(Model $model): string
    {
        $class = \get_class($model);

        return \in_array($class, Relation::morphMap(), true)
            ? $model->getMorphClass()
            : $class;
    }
}
