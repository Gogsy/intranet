<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use \App\Concerns\LogsModelActivity;

    protected $fillable = ['name', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
