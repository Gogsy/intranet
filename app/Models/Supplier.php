<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use LogsModelActivity;

    protected $fillable = [
        'name',
        'oib',
        'iban',
        'email',
        'phone',
        'address',
        'is_active',
        'expected_monthly',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'expected_monthly' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(SupplierCategory::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(SupplierBudget::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeExpectedMonthly(Builder $query): Builder
    {
        return $query->active()->where('expected_monthly', true);
    }
}
