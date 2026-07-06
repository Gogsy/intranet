<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use App\Concerns\CanSkipLockGuard;
use App\Concerns\HasOriginLineage;
use App\Services\BudgetRules;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentItem extends Model
{
    use LogsModelActivity;
    use HasOriginLineage;
    use CanSkipLockGuard;

    protected $fillable = [
        'origin_id', 'budget_version_id', 'month', 'entered_by_id', 'investment_type_id',
        'description', 'proposal_comment', 'quantity', 'unit_net_price', 'classification',
        'link_or_description', 'decision_status', 'purchased', 'realization_comment',
    ];

    protected $casts = [
        'month' => 'integer',
        'quantity' => 'decimal:2',
        'unit_net_price' => 'decimal:2',
        'purchased' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (InvestmentItem $item) {
            if (static::$skipLockGuard) {
                return;
            }

            $dirtyKeys = array_keys(array_diff_key($item->getDirty(), array_flip(['created_at', 'updated_at'])));

            // On CREATE, realization fields carrying their default values are
            // not "changes" — without this, a plain item editor could never
            // add a row (decision_status defaults to Proposed and is owner-tier).
            if (! $item->exists) {
                $dirtyKeys = array_values(array_filter($dirtyKeys, fn ($key) => match ($key) {
                    'decision_status' => $item->decision_status !== 'Proposed',
                    'purchased' => (bool) $item->purchased !== false,
                    'realization_comment' => filled($item->realization_comment),
                    default => true,
                }));
            }

            if (empty($dirtyKeys)) {
                return;
            }

            // Fetch fresh rather than trust a cached relation — a long-lived
            // object in the same process (e.g. a bulk action) must not reuse
            // a pre-lock snapshot of the version's status.
            BudgetVersion::findOrFail($item->budget_version_id)->assertCanEditInvestmentFields($dirtyKeys, $item->month);
        });
    }

    public function budgetVersion(): BelongsTo
    {
        return $this->belongsTo(BudgetVersion::class);
    }

    public function investmentType(): BelongsTo
    {
        return $this->belongsTo(InvestmentType::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_id');
    }

    public function getTotalAttribute(): float
    {
        return BudgetRules::investmentTotal((float) $this->quantity, (float) $this->unit_net_price);
    }
}
