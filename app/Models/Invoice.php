<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Invoice extends Model
{
    use LogsModelActivity;

    public const float VAT_RATE = 0.25;

    /** Disk holding the uploaded invoice attachments. */
    public const string ATTACHMENTS_DISK = 'public';

    protected $fillable = [
        'supplier_id',
        'category_id',
        'year',
        'month',
        'amount',
        'sap_reference',
        'note',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'amount' => 'decimal:2',
            'attachments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Attachments live on disk, not in the row — remove them with it.
        static::deleting(function (Invoice $invoice) {
            foreach ($invoice->attachments ?? [] as $path) {
                Storage::disk(self::ATTACHMENTS_DISK)->delete($path);
            }
        });

        // Same for files removed from the list while editing.
        static::updating(function (Invoice $invoice) {
            if (! $invoice->isDirty('attachments')) {
                return;
            }

            $removed = array_diff(
                $invoice->getOriginal('attachments') ?? [],
                $invoice->attachments ?? [],
            );

            foreach ($removed as $path) {
                Storage::disk(self::ATTACHMENTS_DISK)->delete($path);
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class, 'category_id');
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('year', $year);
    }

    public function scopeForMonth(Builder $query, int $year, int $month): Builder
    {
        return $query->where('year', $year)->where('month', $month);
    }

    /** Rows shown on the overview/analysis screens: supplier visible AND category visible (or uncategorized). */
    public function scopeVisibleInOverview(Builder $query): Builder
    {
        return $query
            ->whereHas('supplier', fn (Builder $q) => $q->where('show_in_overview', true))
            ->where(fn (Builder $q) => $q
                ->whereNull('category_id')
                ->orWhereHas('category', fn (Builder $q2) => $q2->where('show_in_overview', true)));
    }
}
