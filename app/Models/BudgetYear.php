<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetYear extends Model
{
    use LogsModelActivity;

    protected $fillable = ['year', 'name', 'status'];

    protected $casts = [
        'year' => 'integer',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(BudgetVersion::class);
    }
}
