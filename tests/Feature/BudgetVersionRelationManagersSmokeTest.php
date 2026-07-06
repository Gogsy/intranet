<?php

use App\Filament\Resources\BudgetVersionResource\Pages\EditBudgetVersion;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\ActivitiesRelationManager;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\ExpensesRelationManager;
use App\Filament\Resources\BudgetVersionResource\RelationManagers\InvestmentItemsRelationManager;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

function makeDraftVersion(): BudgetVersion
{
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);

    return BudgetVersion::create([
        'budget_year_id' => $year->id,
        'type' => 'PLAN',
        'name' => 'Plan 2026',
        'editable_from_month' => 1,
        'editable_to_month' => 12,
        'status' => 'DRAFT',
    ]);
}

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->create();
    assignTestRole($this->user, 'budget_manager');
    $this->actingAs($this->user);
});

it('renders the budget version edit page with its relation managers', function () {
    $version = makeDraftVersion();

    $this->get("/admin/budget-planner/budget-versions/{$version->id}/edit")->assertOk();
});

it('creates an investment row and inline-edits it through the relation manager', function () {
    $version = makeDraftVersion();
    $type = InvestmentType::first();

    $component = Livewire::test(InvestmentItemsRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ])
        ->callTableAction('create', data: [
            'month' => 3,
            'investment_type_id' => $type->id,
            'classification' => 'Asset',
            'quantity' => 2,
            'unit_net_price' => 400,
            'description' => 'Laptop',
            'decision_status' => 'Proposed',
        ])
        ->assertHasNoTableActionErrors();

    $investment = $version->investmentItems()->first();

    expect($investment)->not->toBeNull();
    expect($investment->total)->toBe(800.0);
    expect($investment->entered_by_id)->toBe($this->user->id);

    // Inline-edit the decision_status column (realization field — always editable).
    $component->call('updateTableColumnState', 'decision_status', (string) $investment->getKey(), 'Approved');

    expect($investment->fresh()->decision_status)->toBe('Approved');
});

it('creates an expense row with generated months and edits a month cell', function () {
    $version = makeDraftVersion();

    $component = Livewire::test(ExpensesRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ])
        ->callTableAction('create', data: [
            'name' => 'Office365',
            'expense_type' => 'MONTHLY',
            'generation_amount' => 100,
        ])
        ->assertHasNoTableActionErrors();

    $expense = $version->expenseItems()->first();
    expect($expense)->not->toBeNull();
    expect($expense->monthValues()->count())->toBe(12);
    expect($expense->total)->toBe(1200.0);

    $component->call('updateTableColumnState', 'month_3', (string) $expense->getKey(), 500);

    expect((float) $expense->monthValues()->where('month', 3)->first()->amount)->toBe(500.0);
});

it('searches investments across description, comment, link, type and editor name', function () {
    $version = makeDraftVersion();
    $type = InvestmentType::firstOrCreate(['name' => 'Hardware']);

    $laptop = $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop Dell',
        'quantity' => 1, 'unit_net_price' => 400, 'classification' => 'Asset',
        'link_or_description' => 'https://dell.com',
    ]);
    $monitor = $version->investmentItems()->create([
        'month' => 4, 'investment_type_id' => $type->id, 'description' => 'Monitor 27"',
        'quantity' => 2, 'unit_net_price' => 150, 'classification' => 'Asset',
    ]);

    $component = Livewire::test(InvestmentItemsRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ]);

    // By description.
    $component->searchTable('Dell')
        ->assertCanSeeTableRecords([$laptop])
        ->assertCanNotSeeTableRecords([$monitor]);

    // By link.
    $component->searchTable('dell.com')
        ->assertCanSeeTableRecords([$laptop])
        ->assertCanNotSeeTableRecords([$monitor]);

    // By type name — matches both rows.
    $component->searchTable('Hardware')
        ->assertCanSeeTableRecords([$laptop, $monitor]);
});

it('shows item changes in the Change log tab', function () {
    $version = makeDraftVersion();
    $type = InvestmentType::firstOrCreate(['name' => 'Hardware']);

    $investment = $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 1, 'unit_net_price' => 400, 'classification' => 'Asset',
    ]);
    $investment->update(['unit_net_price' => 450]);

    Livewire::test(ActivitiesRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords(
            \Spatie\Activitylog\Models\Activity::where('subject_type', \App\Models\InvestmentItem::class)
                ->where('subject_id', $investment->id)->get()
        )
        ->assertSee('Laptop');
});

it('filters the Change log by Who (causer) and by Event across all activity kinds', function () {
    $version = makeDraftVersion();
    $type = InvestmentType::firstOrCreate(['name' => 'Hardware']);

    // Two different users each touch a different investment row.
    $investment = $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 1, 'unit_net_price' => 400, 'classification' => 'Asset',
        'decision_status' => 'Proposed',
    ]);
    $investment->update(['unit_net_price' => 450]);

    $colleague = User::factory()->create();
    assignTestRole($colleague, 'budget_manager');
    $this->actingAs($colleague);
    $other = $version->investmentItems()->create([
        'month' => 4, 'investment_type_id' => $type->id, 'description' => 'Monitor',
        'quantity' => 1, 'unit_net_price' => 200, 'classification' => 'Asset',
        'decision_status' => 'Proposed',
    ]);
    $other->update(['unit_net_price' => 250]);
    $this->actingAs($this->user);

    $activityQuery = fn () => \Spatie\Activitylog\Models\Activity::where('subject_type', \App\Models\InvestmentItem::class)
        ->whereIn('subject_id', [$investment->id, $other->id]);

    $component = Livewire::test(ActivitiesRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ]);

    // Who: only the colleague's rows remain (their create + update).
    $component
        ->filterTable('causer_id', $colleague->id)
        ->assertCanSeeTableRecords($activityQuery()->where('causer_id', $colleague->id)->get())
        ->assertCanNotSeeTableRecords($activityQuery()->where('causer_id', $this->user->id)->get());

    // Event: the filter must constrain the whole widened query (all subjects),
    // not just the last OR-branch of it.
    $component
        ->resetTableFilters()
        ->filterTable('event', 'created')
        ->assertCanSeeTableRecords($activityQuery()->where('event', 'created')->get())
        ->assertCanNotSeeTableRecords($activityQuery()->where('event', 'updated')->get());
});

it('creates an expense on an FC1 version across all 12 months — the window does not apply to expenses', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id,
        'type' => 'FC1',
        'name' => 'FC1 2026',
        'editable_from_month' => 3,
        'editable_to_month' => 12,
        'status' => 'DRAFT',
    ]);

    Livewire::test(ExpensesRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ])
        ->callTableAction('create', data: [
            'name' => 'Licenses',
            'expense_type' => 'ANNUAL_AVR',
            'generation_amount' => 1000,
        ])
        ->assertHasNoTableActionErrors();

    $expense = $version->expenseItems()->first();
    expect($expense)->not->toBeNull();
    expect($expense->monthValues()->count())->toBe(12);
    expect($expense->total)->toBe(1000.0);
});

it('auto-fills the row when typing a month amount, based on the expense type', function () {
    $version = makeDraftVersion();

    $component = Livewire::test(ExpensesRelationManager::class, [
        'ownerRecord' => $version,
        'pageClass' => EditBudgetVersion::class,
    ]);

    $amounts = fn ($expense) => $expense->monthValues()->orderBy('month')->pluck('amount', 'month')->map(fn ($a) => (float) $a);

    // ANNUAL_AVR: typing the annual amount into April spreads it over the year.
    $avr = $version->expenseItems()->create(['name' => 'Annual license', 'expense_type' => 'ANNUAL_AVR']);
    $component->call('updateTableColumnState', 'month_4', (string) $avr->getKey(), 120);
    expect($amounts($avr)[1])->toBe(10.0);
    expect($amounts($avr)[12])->toBe(10.0);
    expect($avr->fresh()->total)->toBe(120.0);

    // ONE_TIME: the amount lands in that month only, the rest resets to 0.
    $oneTime = $version->expenseItems()->create(['name' => 'Domain renewal', 'expense_type' => 'ONE_TIME']);
    $component->call('updateTableColumnState', 'month_7', (string) $oneTime->getKey(), 17);
    expect($amounts($oneTime)[7])->toBe(17.0);
    expect($oneTime->fresh()->total)->toBe(17.0);

    // MONTHLY: fills from the edited month to December, keeping earlier months —
    // a mid-year price change is two entries, not twelve.
    $monthly = $version->expenseItems()->create(['name' => 'AnyDesk', 'expense_type' => 'MONTHLY']);
    $component->call('updateTableColumnState', 'month_1', (string) $monthly->getKey(), 50);
    $component->call('updateTableColumnState', 'month_4', (string) $monthly->getKey(), 70);
    expect($amounts($monthly)[3])->toBe(50.0);
    expect($amounts($monthly)[4])->toBe(70.0);
    expect($amounts($monthly)[12])->toBe(70.0);

    // VOLUME: manual — only the edited cell changes.
    $volume = $version->expenseItems()->create(['name' => 'SMS', 'expense_type' => 'VOLUME']);
    $component->call('updateTableColumnState', 'month_2', (string) $volume->getKey(), 33);
    expect($amounts($volume))->toHaveCount(1);
    expect($amounts($volume)[2])->toBe(33.0);
});
