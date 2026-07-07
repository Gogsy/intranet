<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Row-level presence store for the Budget Planner grids.
 *
 * Presence is a purely visual overlay (who else is on this budget version and
 * which row they're editing), so it deliberately lives OUTSIDE Livewire: the
 * client hits a plain JSON route (BudgetPresenceController) via fetch(), never
 * `wire.$call`. Routing it through a Livewire component would re-render that
 * whole component every few seconds — greying its action buttons, morphing the
 * filter dropdown mid-click and twitching open modals. Keeping it off Livewire
 * is what lets presence run continuously without touching the table's chrome.
 */
class BudgetPresence
{
    /** Seconds after the last heartbeat before a user is considered gone. */
    public const TTL = 10;

    /**
     * Record "$userId is here (editing $row)" on $versionId and return everyone
     * ELSE currently active on the same version.
     *
     * @param  string  $tab  the grid the row belongs to (expenseItems / investmentItems)
     * @param  string|null  $row  table record key the user has focused, or null
     * @return array<int, array{id: int, name: string, tab: string, row: ?string}>
     */
    public static function heartbeat(int|string $versionId, int $userId, string $userName, string $tab, ?string $row): array
    {
        $key = 'bp-presence:' . $versionId;
        $now = time();

        $entries = collect(Cache::get($key, []))
            ->filter(fn (array $entry) => ($entry['ts'] ?? 0) > $now - self::TTL);

        $entries[$userId] = [
            'id' => $userId,
            'name' => $userName,
            'tab' => $tab,
            'row' => $row !== null ? substr($row, 0, 64) : null,
            'ts' => $now,
        ];

        Cache::put($key, $entries->all(), self::TTL * 2);

        return $entries
            ->reject(fn (array $entry) => $entry['id'] === $userId)
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
