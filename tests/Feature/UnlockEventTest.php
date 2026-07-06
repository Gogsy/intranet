<?php

use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\User;
use App\Services\BudgetVersionService;
use Spatie\Activitylog\Models\Activity;

function lockedVersionForUnlockTest(): BudgetVersion
{
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);

    return BudgetVersion::create([
        'budget_year_id' => $year->id,
        'type' => 'PLAN',
        'name' => 'Plan 2026',
        'editable_from_month' => 1,
        'editable_to_month' => 12,
        'status' => 'LOCKED',
        'locked_at' => now(),
    ]);
}

it('creates an unlock event with reason and causer, and updates version status', function () {
    $version = lockedVersionForUnlockTest();
    $user = User::factory()->create();

    $unlocked = BudgetVersionService::unlock($version, 'Fix a typo before submission', $user);

    expect($unlocked->status)->toBe('TEMPORARILY_UNLOCKED');
    expect($unlocked->unlocked_at)->not->toBeNull();

    expect($unlocked->unlockEvents)->toHaveCount(1);
    $event = $unlocked->unlockEvents->first();
    expect($event->reason)->toBe('Fix a typo before submission');
    expect($event->unlocked_by_id)->toBe($user->id);
});

it('records a custom budget_planner activity log entry for the unlock', function () {
    $version = lockedVersionForUnlockTest();
    $user = User::factory()->create();

    BudgetVersionService::unlock($version, 'Fix a typo before submission', $user);

    $activity = Activity::where('log_name', 'budget_planner')->where('event', 'unlocked')->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($user->id);
    expect($activity->properties['reason'])->toBe('Fix a typo before submission');
});

it('refuses to unlock without a reason', function () {
    $version = lockedVersionForUnlockTest();
    $user = User::factory()->create();

    expect(fn () => BudgetVersionService::unlock($version, '', $user))
        ->toThrow(RuntimeException::class);
});

it('refuses to unlock a version that is not locked', function () {
    $year = BudgetYear::create(['year' => 2027, 'name' => 'IT Budget 2027', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id,
        'type' => 'PLAN',
        'name' => 'Plan 2027',
        'editable_from_month' => 1,
        'editable_to_month' => 12,
        'status' => 'DRAFT',
    ]);
    $user = User::factory()->create();

    expect(fn () => BudgetVersionService::unlock($version, 'reason', $user))
        ->toThrow(RuntimeException::class);
});
