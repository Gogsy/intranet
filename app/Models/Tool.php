<?php

namespace App\Models;

use App\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tool extends Model
{
    use LogsModelActivity;

    protected $fillable = ['name', 'url', 'icon', 'is_visible', 'sort_order',];

    public function clicks(): HasMany
    {
        return $this->hasMany(ToolClick::class);
    }

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Resolve the icon to a public URL. Handles both new uploads (full storage
     * path stored in `icon`) and legacy bare filenames (committed library or
     * storage sub-folder).
     */
    public function getIconUrlAttribute(): ?string
    {
        $file = $this->icon;
        if (! $file) {
            return null;
        }

        if (str_contains($file, '/')) {
            $rel = ltrim($file, '/');
            return is_file(public_path('storage/' . $rel)) ? asset('storage/' . $rel) : null;
        }

        if (is_file(public_path('images/icons/tool_icons/' . $file))) {
            return asset('images/icons/tool_icons/' . $file);
        }
        if (is_file(public_path('storage/images/icons/tool_icons/' . $file))) {
            return asset('storage/images/icons/tool_icons/' . $file);
        }
        return null;
    }
}
