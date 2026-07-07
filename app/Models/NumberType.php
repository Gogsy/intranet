<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberType extends Model
{
    use LogsModelActivity;

    protected $fillable = ['name', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(PhoneNumber::class);
    }
}
