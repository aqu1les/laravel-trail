<?php

declare(strict_types=1);

namespace Trail\Trail\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $period
 * @property CarbonInterface $bucket
 * @property string $name
 * @property int $count
 * @property int $unique_subjects
 * @property float|null $sum_value
 */
class TrailAggregate extends Model
{
    protected $guarded = [];

    protected $casts = [
        'bucket' => 'datetime',
        'count' => 'integer',
        'unique_subjects' => 'integer',
        'sum_value' => 'decimal:4',
    ];

    public function getConnectionName(): ?string
    {
        return config('trail.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return 'trail_aggregates';
    }
}
