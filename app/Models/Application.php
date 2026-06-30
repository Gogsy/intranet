<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Application extends Model
{
    use \App\Concerns\LogsModelActivity;

    protected $fillable = [
        'name', 'link', 'icon', 'is_visible', 'sort_order',
        'pdf_installation_instructions', 'pdf_user_manual',
        'update_provider', 'update_app_name', 'live_download', 'update_endpoint',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
        'live_download' => 'boolean',
    ];

    /**
     * True when this app should resolve its download live from the Nesy API on
     * every click (always-latest, nothing stored) instead of serving a stored APK.
     */
    public function isLiveDownload(): bool
    {
        return $this->live_download && $this->update_provider === 'nesy' && filled($this->update_app_name);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ApplicationVersion::class)->orderByDesc('created_at');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(ApplicationVersion::class)->where('is_active', true);
    }

    /**
     * Stable, permanent download link (route) that always serves the active
     * version (or legacy `link`). Returns null when there is nothing to download.
     */
    public function getDownloadUrlAttribute(): ?string
    {
        // Live apps always expose a download link — the APK is resolved on click.
        if ($this->isLiveDownload()) {
            return route('apps.download', $this);
        }

        $active = $this->relationLoaded('activeVersion') ? $this->activeVersion : $this->activeVersion()->first();

        $hasFile = ($active && $active->file_url)
            || (! empty($this->link) && Storage::disk('public')->exists($this->link));

        return $hasFile ? route('apps.download', $this) : null;
    }

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

        if (is_file(public_path('images/icons/' . $file))) {
            return asset('images/icons/' . $file);
        }
        if (is_file(public_path('storage/images/icons/' . $file))) {
            return asset('storage/images/icons/' . $file);
        }
        return null;
    }
}
