<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-model over Laravel's `sessions` table (SESSION_DRIVER=database).
 * Deleting a row revokes that session — the owner is logged out on their
 * next request. Rows are created/updated by the framework, never by us.
 */
class UserSession extends Model
{
    protected $table = 'sessions';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    /** Minutes without a request before a session no longer counts as "online". */
    public const ONLINE_MINUTES = 5;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOnline(): bool
    {
        return $this->last_activity >= now()->subMinutes(self::ONLINE_MINUTES)->getTimestamp();
    }

    /** Short human label parsed from the raw user-agent string. */
    public function deviceLabel(): string
    {
        $ua = (string) $this->user_agent;

        $platform = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => null,
        };

        return collect([$browser, $platform])->filter()->implode(' · ') ?: ($ua ?: '—');
    }
}
