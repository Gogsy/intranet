<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Row-level presence for the Budget Planner relation managers.
 *
 * Every open Expenses/Investments tab sends a heartbeat (~5s, from
 * planner-tools.blade.php) carrying the table row the user currently has
 * focused, if any. Presence is kept in the cache per budget version with a
 * short TTL, so closing the tab makes the user disappear within seconds.
 * The heartbeat's return value is everyone ELSE on the same version — the
 * client uses it to outline their rows and list who's online.
 */
trait TracksBudgetPresence
{
    /** Seconds after the last heartbeat before a user is considered gone (heartbeat is every 3s). */
    protected static int $bpPresenceTtl = 10;

    /**
     * Record "I'm here (editing $row)" and return the other active users.
     *
     * @param  string|null  $row  table record key the user has focused, or null
     * @return array<int, array{id: int, name: string, tab: string, row: ?string}>
     */
    public function bpHeartbeat(?string $row = null): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $key = 'bp-presence:' . $this->getOwnerRecord()->getKey();
        $now = time();

        $entries = collect(Cache::get($key, []))
            ->filter(fn (array $entry) => ($entry['ts'] ?? 0) > $now - static::$bpPresenceTtl);

        $entries[$user->id] = [
            'id' => $user->id,
            'name' => $user->name,
            // Which grid the row key belongs to (expenseItems / investmentItems).
            'tab' => static::getRelationshipName(),
            'row' => $row !== null ? substr($row, 0, 64) : null,
            'ts' => $now,
        ];

        Cache::put($key, $entries->all(), static::$bpPresenceTtl * 2);

        return $entries
            ->reject(fn (array $entry) => $entry['id'] === $user->id)
            ->map(fn (array $entry) => [
                'id' => $entry['id'],
                'name' => $entry['name'],
                'tab' => $entry['tab'],
                'row' => $entry['row'],
            ])
            ->values()
            ->all();
    }
}
