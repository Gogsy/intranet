<?php

namespace App\Concerns;

use Closure;

/**
 * Lets a system-level bulk operation (template-copy) bypass the per-model
 * lock/editable-window guard, which is otherwise correct for user-initiated
 * edits but wrong here: copying a PLAN's January row into a new FC1 (editable
 * months 3-12) must still succeed — the row exists in the new version, it's
 * just non-editable going forward, not something to reject on copy.
 */
trait CanSkipLockGuard
{
    protected static bool $skipLockGuard = false;

    public static function withoutLockGuard(Closure $callback): mixed
    {
        $previous = static::$skipLockGuard;
        static::$skipLockGuard = true;

        try {
            return $callback();
        } finally {
            static::$skipLockGuard = $previous;
        }
    }
}
