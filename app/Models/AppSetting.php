<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Singleton model holding the application's branding / white-label settings.
 *
 * Use AppSetting::current() (or the global branding() helper) to read settings.
 * The current row is cached per-request to avoid repeated queries.
 */
class AppSetting extends Model
{
    protected $fillable = [
        'app_name',
        'company_name',
        'logo_path',
        'logo_height',
        'admin_logo_height',
        'favicon_path',
        'primary_color',
        'accent_color',
        'setup_completed_at',
    ];

    protected $casts = [
        'logo_height' => 'integer',
        'admin_logo_height' => 'integer',
        'setup_completed_at' => 'datetime',
    ];

    /** Default header logo height (px) when none is configured. */
    public const DEFAULT_LOGO_HEIGHT = 44;

    /** Default admin panel (Filament) logo height (px) when none is configured. */
    public const DEFAULT_ADMIN_LOGO_HEIGHT = 40;

    /** Per-request cache of the singleton row. */
    protected static ?self $cached = null;

    /** The single settings row (creates an in-memory empty one if missing). */
    public static function current(): self
    {
        return static::$cached ??= static::firstOrNew([]);
    }

    /** Forget the cached instance (e.g. after saving). */
    public static function forgetCurrent(): void
    {
        static::$cached = null;
    }

    /* -----------------------------------------------------------------
     | Convenience accessors with sensible fallbacks
     | ----------------------------------------------------------------- */

    /** Display name for the app, falling back to config('app.name'). */
    public function getNameAttribute(): string
    {
        return $this->app_name ?: config('app.name');
    }

    /** Public URL of the uploaded logo, or the neutral default placeholder. */
    public function getLogoUrlAttribute(): string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : asset('images/default-logo.svg');
    }

    /** Header logo height in px, falling back to the default. */
    public function getLogoHeightAttribute(): int
    {
        return $this->attributes['logo_height'] ?? self::DEFAULT_LOGO_HEIGHT;
    }

    /** Admin panel logo height in px, falling back to the default. */
    public function getAdminLogoHeightAttribute(): int
    {
        return $this->attributes['admin_logo_height'] ?? self::DEFAULT_ADMIN_LOGO_HEIGHT;
    }

    /** Public URL of the configured favicon, or the bundled default. */
    public function getFaviconUrlAttribute(): string
    {
        return $this->favicon_path
            ? Storage::disk('public')->url($this->favicon_path)
            : asset('images/favicon-32x32.png');
    }

    /** Primary brand colour (hex), default #F58220. */
    public function getPrimaryAttribute(): string
    {
        return $this->primary_color ?: '#F58220';
    }

    /** Accent brand colour (hex), default #F58220. */
    public function getAccentAttribute(): string
    {
        return $this->accent_color ?: '#F58220';
    }
}
