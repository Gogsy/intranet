<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetYear extends Model
{
    use LogsModelActivity;

    protected $fillable = ['year', 'name', 'status', 'tracker_source_version_id'];

    protected $casts = [
        'year' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(BudgetVersion::class);
    }

    /** The version whose expenses mirror into the Invoice Tracker. */
    public function trackerSourceVersion(): BelongsTo
    {
        return $this->belongsTo(BudgetVersion::class, 'tracker_source_version_id');
    }
}
