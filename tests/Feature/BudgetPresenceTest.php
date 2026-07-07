<?php

use App\Filament\Resources\BudgetVersionResource\RelationManagers\ExpensesRelationManager;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\InvestmentItemsRelationManager;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function presenceTestVersion(int $year = 2026): BudgetVersion
{
    $budgetYear = BudgetYear::create(['year' => $year, 'name' => "IT Budget {$year}", 'status' => 'ACTIVE']);

    return BudgetVersion::create([
        'budget_year_id' => $budgetYear->id, 'type' => 'PLAN', 'name' => "Plan {$year}",
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
}

/** Build a mounted relation-manager instance whose owner is $version. */
function mountRm(string $rmClass, BudgetVersion $version)
{
    $rm = new $rmClass();
    $rm->ownerRecord = $version;

    return $rm;
}

it('reports another user as present on the same budget, with the row they are on', function () {
    $version = presenceTestVersion();
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $rm = mountRm(InvestmentItemsRelationManager::class, $version);

    // Alice checks in (no specific row).
    $this->actingAs($alice);
    expect($rm->bpHeartbeat(null))->toBe([]); // nobody else yet

    // Bob checks in while focused on a specific row.
    $this->actingAs($bob);
    $others = $rm->bpHeartbeat('investment-items-relation-manager.table.records.5');

    // Bob sees Alice present on this budget.
    expect($others)->toHaveCount(1)
        ->and($others[0]['name'])->toBe('Alice')
        ->and($others[0]['tab'])->toBe('investmentItems');

    // And now Alice sees Bob — including the row Bob is editing.
    $this->actingAs($alice);
    $othersForAlice = $rm->bpHeartbeat(null);
    expect($othersForAlice)->toHaveCount(1)
        ->and($othersForAlice[0]['name'])->toBe('Bob')
        ->and($othersForAlice[0]['row'])->toContain('records.5');
});

it('keeps presence separate per budget version', function () {
    $versionA = presenceTestVersion(2026);
    $versionB = presenceTestVersion(2027);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $this->actingAs($alice);
    mountRm(ExpensesRelationManager::class, $versionA)->bpHeartbeat(null);

    // Bob on a DIFFERENT budget sees nobody from budget A.
    $this->actingAs($bob);
    $others = mountRm(ExpensesRelationManager::class, $versionB)->bpHeartbeat(null);
    expect($others)->toBe([]);
});

it('tracks presence across the expenses relation manager too', function () {
    $version = presenceTestVersion();
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $rm = mountRm(ExpensesRelationManager::class, $version);

    $this->actingAs($alice);
    $rm->bpHeartbeat(null);

    $this->actingAs($bob);
    $others = $rm->bpHeartbeat(null);

    expect($others)->toHaveCount(1)
        ->and($others[0]['tab'])->toBe('expenseItems');
});
