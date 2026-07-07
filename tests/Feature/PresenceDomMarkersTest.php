<?php

use App\Filament\Resources\BudgetVersionResource\Pages\EditBudgetVersion;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\ExpensesRelationManager;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\InvestmentItemsRelationManager;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    assignTestRole($user, 'super_admin');
    $this->actingAs($user);
});

it('renders budget rows with the DOM markers the presence JS relies on', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $item = $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => InvestmentType::first()->id, 'description' => 'Laptop',
        'quantity' => 1, 'unit_net_price' => 400, 'classification' => 'Asset',
    ]);

    $html = Livewire::test(InvestmentItemsRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ])->html();

    // The presence JS keys off: bp-compact rows carrying a wire:key of the
    // form "<componentId>.table.records.<recordKey>".
    expect($html)->toContain('bp-compact')
        ->and($html)->toContain('.table.records.' . $item->getKey());
});

it('renders the top-bar presence container on admin pages', function () {
    // The presence JS renders pills into #bp-presence-topbar (TOPBAR_END hook).
    $this->get('/admin/user-sessions')
        ->assertOk()
        ->assertSee('bp-presence-topbar', false);
});

it('marks the expenses grid with bp-month-input (the presence tab discriminator)', function () {
    $year = BudgetYear::create(['year' => 2027, 'name' => 'IT 2027', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2027',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    App\Models\ExpenseItem::withoutLockGuard(
        fn () => $version->expenseItems()->create(['name' => 'Licenca', 'expense_type' => 'MONTHLY'])
    );

    $html = Livewire::test(ExpensesRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ])->html();

    // The JS reads this class to tell the expenses grid from investments.
    expect($html)->toContain('bp-month-input');
});
