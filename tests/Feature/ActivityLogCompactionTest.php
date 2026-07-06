<?php

use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentItem;
use App\Models\InvestmentType;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

function investmentForCompactionTest(): InvestmentItem
{
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $type = InvestmentType::create(['name' => 'Hardware', 'sort_order' => 1]);

    return $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 1, 'unit_net_price' => 400, 'classification' => 'Asset',
        // Explicit, so the first update() doesn't log the DB-default backfill
        // (null → Proposed) as a real change.
        'decision_status' => 'Proposed',
    ]);
}

function updatedActivitiesFor(InvestmentItem $item)
{
    return Activity::where('subject_type', InvestmentItem::class)
        ->where('subject_id', $item->id)
        ->where('event', 'updated')
        ->get();
}

it('folds consecutive edits of the same row by the same user into one activity', function () {
    $this->actingAs(tap(User::factory()->create(), fn ($u) => assignTestRole($u, 'budget_manager')));
    $item = investmentForCompactionTest();

    $item->update(['unit_net_price' => 450]);
    $item->update(['quantity' => 2]);
    $item->update(['unit_net_price' => 500]);

    $activities = updatedActivitiesFor($item);
    expect($activities)->toHaveCount(1);

    $properties = $activities->first()->properties;
    // Net change: original old values, latest new values.
    expect((float) $properties['old']['unit_net_price'])->toBe(400.0);
    expect((float) $properties['attributes']['unit_net_price'])->toBe(500.0);
    expect((int) $properties['old']['quantity'])->toBe(1);
    expect((int) $properties['attributes']['quantity'])->toBe(2);

    // Every individual edit is preserved as a timeline step for the details modal.
    $steps = $properties['steps'];
    expect($steps)->toHaveCount(3);
    expect((float) $steps[0]['attributes']['unit_net_price'])->toBe(450.0);
    expect((int) $steps[1]['attributes']['quantity'])->toBe(2);
    expect((float) $steps[2]['attributes']['unit_net_price'])->toBe(500.0);
});

it('drops the log row entirely when the edits cancel out (A → B → A)', function () {
    $this->actingAs(tap(User::factory()->create(), fn ($u) => assignTestRole($u, 'budget_manager')));
    $item = investmentForCompactionTest();

    $item->update(['unit_net_price' => 450]);
    $item->update(['unit_net_price' => 400]);

    expect(updatedActivitiesFor($item))->toHaveCount(0);
});

it('keeps separate rows for different users', function () {
    $item = investmentForCompactionTest();

    $this->actingAs(tap(User::factory()->create(), fn ($u) => assignTestRole($u, 'budget_manager')));
    $item->update(['unit_net_price' => 450]);

    $this->actingAs(tap(User::factory()->create(), fn ($u) => assignTestRole($u, 'budget_manager')));
    $item->update(['unit_net_price' => 500]);

    expect(updatedActivitiesFor($item))->toHaveCount(2);
});

it('keeps separate rows for edits outside the merge window', function () {
    $this->actingAs(tap(User::factory()->create(), fn ($u) => assignTestRole($u, 'budget_manager')));
    $item = investmentForCompactionTest();

    $item->update(['unit_net_price' => 450]);

    $this->travel(\App\Services\ActivityLogCompactor::MERGE_WINDOW_MINUTES + 1)->minutes();
    $item->refresh()->update(['unit_net_price' => 500]);

    expect(updatedActivitiesFor($item))->toHaveCount(2);
});
