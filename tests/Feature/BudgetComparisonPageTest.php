<?php

use App\Filament\Pages\BudgetComparison;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentType;
use App\Models\User;
use App\Services\BudgetVersionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

it('renders the comparison page and shows filtered rows for the selected versions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    assignTestRole($user, 'budget_manager');
    $this->actingAs($user);

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

    $fc1 = BudgetVersionService::createFromTemplate($year, 'FC1', $plan);
    $fc1->investmentItems()->first()->update(['quantity' => 2]);

    $this->get('/admin/budget-planner/budget-comparison')->assertOk();

    $component = Livewire::test(BudgetComparison::class)
        ->fillForm(['old_version_id' => $plan->id, 'new_version_id' => $fc1->id]);

    $rows = $component->instance()->getRows();

    expect($rows)->toHaveCount(1);
    expect($rows->first()['status'])->toBe('changed');
});
