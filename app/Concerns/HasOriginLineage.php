<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks a row's lineage across budget versions: origin_id groups the "same"
 * investment/expense row as it gets copied version to version, so comparisons
 * can match them up. Seeded to the row's own id on creation unless the caller
 * already set it (template-copy code sets it explicitly to the source row's
 * origin_id so the whole lineage chain shares one id).
 */
trait HasOriginLineage
{
    protected static function bootHasOriginLineage(): void
    {
        static::created(function ($model) {
            if ($model->origin_id === null) {
                $model->origin_id = $model->id;
                $model->saveQuietly();
            }
        });
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(static::class, 'origin_id');
    }
}
