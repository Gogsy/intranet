<?php

use App\Filament\Resources\BudgetVersionResource\Pages\EditBudgetVersion;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\ActivitiesRelationManager;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentItem;
use App\Models\InvestmentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    assignTestRole($this->user, 'budget_manager');
    $this->actingAs($this->user);
});

function versionWithLoggedChanges(): BudgetVersion
{
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $type = InvestmentType::first();

    $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 1, 'unit_net_price' => 400, 'classification' => 'Asset',
    ]);

    return $version;
}

function versionActivityCount(BudgetVersion $version): int
{
    $itemIds = $version->investmentItems()->pluck('id');

    return Activity::query()
        ->where(fn ($q) => $q->where('subject_type', BudgetVersion::class)->where('subject_id', $version->id))
        ->orWhere(fn ($q) => $q->where('subject_type', InvestmentItem::class)->whereIn('subject_id', $itemIds))
        ->count();
}

it('clears all change-log entries for the budget via the header action', function () {
    $version = versionWithLoggedChanges();
    expect(versionActivityCount($version))->toBeGreaterThan(0);

    Livewire::test(ActivitiesRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ])
        ->callTableAction('clearLog', data: ['scope' => 'all'])
        ->assertHasNoTableActionErrors();

    expect(versionActivityCount($version))->toBe(0);
});

it('clears only entries older than the selected age, keeping recent ones', function () {
    $version = versionWithLoggedChanges();

    // Age the existing entries past the 30-day cutoff, then make a fresh one.
    Activity::query()->update(['created_at' => now()->subDays(45)]);
    $version->investmentItems->first()->update(['unit_net_price' => 450]);

    $before = versionActivityCount($version);

    Livewire::test(ActivitiesRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ])
        ->callTableAction('clearLog', data: ['scope' => '30'])
        ->assertHasNoTableActionErrors();

    // Only the fresh update entry survives.
    expect($before)->toBeGreaterThan(1);
    expect(versionActivityCount($version))->toBe(1);
});

it('does not touch activities belonging to other budgets', function () {
    $version = versionWithLoggedChanges();

    $otherYear = BudgetYear::create(['year' => 2027, 'name' => 'IT Budget 2027', 'status' => 'ACTIVE']);
    $other = BudgetVersion::create([
        'budget_year_id' => $otherYear->id, 'type' => 'PLAN', 'name' => 'Plan 2027',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $other->investmentItems()->create([
        'month' => 3, 'investment_type_id' => InvestmentType::first()->id, 'description' => 'Server',
        'quantity' => 1, 'unit_net_price' => 900, 'classification' => 'Asset',
    ]);

    $otherCountBefore = versionActivityCount($other);

    Livewire::test(ActivitiesRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ])
        ->callTableAction('clearLog', data: ['scope' => 'all'])
        ->assertHasNoTableActionErrors();

    expect(versionActivityCount($version))->toBe(0);
    expect(versionActivityCount($other))->toBe($otherCountBefore);
});
