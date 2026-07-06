<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentType extends Model
{
    use LogsModelActivity;

    protected $fillable = ['name', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function investmentItems(): HasMany
    {
        return $this->hasMany(InvestmentItem::class);
    }
}
