<?php

use App\Filament\Resources\BudgetVersionResource\Pages\CreateBudgetVersion;
use App\Filament\Resources\BudgetVersionResource\Pages\EditBudgetVersion;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    assignTestRole($user, 'budget_manager');
    $this->actingAs($user);
});

it('creates a budget with just name/year/type — the year row is auto-created behind the scenes', function () {
    Livewire::test(CreateBudgetVersion::class)
        ->fillForm([
            'name' => 'IT Investments Plan 2030',
            'year' => 2030,
            'type' => 'PLAN',
            'editable_from_month' => 1,
            'editable_to_month' => 12,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $year = BudgetYear::where('year', 2030)->first();
    expect($year)->not->toBeNull();

    $version = BudgetVersion::where('budget_year_id', $year->id)->first();
    expect($version->name)->toBe('IT Investments Plan 2030');
    expect($version->status)->toBe('DRAFT');
});

it('prefills the month window from the type but lets it be overridden manually', function () {
    Livewire::test(CreateBudgetVersion::class)
        ->fillForm(['name' => 'FC1 custom window', 'year' => 2031])
        ->fillForm(['type' => 'FC1']) // live: suggests 3-12
        ->assertFormSet(['editable_from_month' => 3, 'editable_to_month' => 12])
        ->fillForm(['editable_from_month' => 5]) // manual override
        ->call('create')
        ->assertHasNoFormErrors();

    $version = BudgetVersion::whereHas('budgetYear', fn ($q) => $q->where('year', 2031))->first();
    expect($version->editable_from_month)->toBe(5);
    expect($version->editable_to_month)->toBe(12);
});

it('creates a budget from a template directly on the Create page', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $plan = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $type = InvestmentType::firstOrCreate(['name' => 'Hardware']);
    $plan->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 1, 'unit_net_price' => 400, 'classification' => 'Asset',
    ]);

    Livewire::test(CreateBudgetVersion::class)
        ->fillForm([
            'name' => 'FC1 2026',
            'year' => 2026,
            'type' => 'FC1',
            'template_version_id' => $plan->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $fc1 = BudgetVersion::where('type', 'FC1')->where('budget_year_id', $year->id)->first();
    expect($fc1)->not->toBeNull();
    expect($fc1->editable_from_month)->toBe(3);
    expect($fc1->baseline_version_id)->toBe($plan->id);
    expect($fc1->investmentItems()->count())->toBe(1);
});

it('lets year, type, months and status be edited on an existing budget', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);

    Livewire::test(EditBudgetVersion::class, ['record' => $version->getRouteKey()])
        ->callAction('settings', data: [
            'name' => 'FC2 2027 scenario',
            'year' => 2027,
            'type' => 'FC2',
            'editable_from_month' => 6, // manual, different from FC2's suggested 7
            'editable_to_month' => 12,
            'status' => 'DRAFT',
        ])
        ->assertHasNoActionErrors();

    $fresh = $version->fresh();
    expect($fresh->type)->toBe('FC2');
    expect($fresh->budgetYear->year)->toBe(2027);
    expect($fresh->editable_from_month)->toBe(6);
    expect($fresh->name)->toBe('FC2 2027 scenario');
});
