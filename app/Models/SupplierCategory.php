<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierCategory extends Model
{
    use LogsModelActivity;

    protected $fillable = [
        'supplier_id',
        'name',
        'show_in_overview',
    ];

    protected function casts(): array
    {
        return [
            'show_in_overview' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'category_id');
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(SupplierBudget::class, 'category_id');
    }
}
