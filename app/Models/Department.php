<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use LogsModelActivity;

    protected $fillable = ['name', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
