<?php

namespace App\Concerns;

use App\Support\BudgetPresence;

/**
 * Row-level presence for the Budget Planner relation managers.
 *
 * NOTE: the client no longer calls this method — presence now runs off Livewire
 * entirely, via a plain JSON route (BudgetPresenceController) hit with fetch(),
 * so the heartbeat never re-renders the relation-manager component (which would
 * grey its buttons and twitch its filter dropdown / modals). This method is kept
 * as the server-side entry point that the presence feature test drives directly;
 * both it and the route delegate to the same {@see BudgetPresence} store.
 */
trait TracksBudgetPresence
{
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

        return BudgetPresence::heartbeat(
            $this->getOwnerRecord()->getKey(),
            $user->id,
            $user->name,
            // Which grid the row key belongs to (expenseItems / investmentItems).
            static::getRelationshipName(),
            $row,
        );
    }
}
