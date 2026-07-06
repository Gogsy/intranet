<?php

namespace App\Services;

use App\Models\ExpenseItem;
use App\Models\ExpenseMonthValue;
use App\Models\InvestmentItem;
use Spatie\Activitylog\Models\Activity;

/**
 * Compacts the Budget Planner change log: the inline grid saves on every
 * single click/keystroke-blur, which would otherwise flood the log with
 * one activity row per field change. When the same user updates the same
 * row again within the merge window, the new activity is folded into the
 * previous one — the "old" values stay from the first edit, the "new"
 * values come from the last, so the row always shows the net change.
 */
class ActivityLogCompactor
{
    /** Minutes since the previous activity within which edits are considered one editing session. */
    public const MERGE_WINDOW_MINUTES = 10;

    /** Only the noisy inline-edited row models are compacted. */
    protected const MERGEABLE_SUBJECTS = [
        InvestmentItem::class,
        ExpenseItem::class,
        ExpenseMonthValue::class,
    ];

    /** Hooked to Activity::created (see AppServiceProvider). */
    public static function compact(Activity $activity): void
    {
        if ($activity->event !== 'updated') {
            return;
        }

        if (! in_array($activity->subject_type, self::MERGEABLE_SUBJECTS, true)) {
            return;
        }

        $previous = Activity::query()
            ->whereKeyNot($activity->getKey())
            ->where('subject_type', $activity->subject_type)
            ->where('subject_id', $activity->subject_id)
            ->where('event', 'updated')
            ->where('causer_type', $activity->causer_type)
            ->where('causer_id', $activity->causer_id)
            ->where('created_at', '>=', now()->subMinutes(self::MERGE_WINDOW_MINUTES))
            ->latest('created_at')
            ->first();

        if (! $previous) {
            return;
        }

        $previousProperties = $previous->properties;
        $newProperties = $activity->properties;

        // Every individual edit survives as a "step", so the grouped row can
        // be opened in the Change log to see the full timeline of the session.
        $steps = $previousProperties['steps'] ?? [self::step($previousProperties, $previous->created_at)];
        $steps[] = self::step($newProperties, $activity->created_at);

        // Latest value per field wins; the original "old" per field is kept
        // (merge order makes the earlier activity's old take precedence).
        $attributes = collect($previousProperties['attributes'] ?? [])
            ->merge($newProperties['attributes'] ?? []);
        $old = collect($newProperties['old'] ?? [])
            ->merge($previousProperties['old'] ?? [])
            ->only($attributes->keys());

        // Fields that net out to no change (A → B → A) drop out entirely.
        $attributes = $attributes->reject(fn ($value, $field) => $old->has($field) && $old[$field] == $value);
        $old = $old->only($attributes->keys());

        if ($attributes->isEmpty()) {
            // The whole editing session cancelled itself out — no log row at all.
            $previous->delete();
            $activity->delete();

            return;
        }

        $previous->properties = collect([
            'attributes' => $attributes->all(),
            'old' => $old->all(),
            'steps' => $steps,
        ]);
        // "When" should reflect the latest edit (also keeps the row on top).
        $previous->created_at = $activity->created_at;
        $previous->save();

        $activity->delete();
    }

    /** One timeline entry for the grouped row's detail view. */
    protected static function step($properties, $at): array
    {
        return [
            'at' => optional($at)->format('Y-m-d H:i:s'),
            'attributes' => collect($properties['attributes'] ?? [])->all(),
            'old' => collect($properties['old'] ?? [])->all(),
        ];
    }
}
