<?php

use App\Exports\ExpenseItemsExport;
use App\Filament\Resources\BudgetVersionResource\Pages\EditBudgetVersion;
use App\Filament\Resources\BudgetVersionResource\Pages\ListBudgetVersions;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $user = User::factory()->create();
    assignTestRole($user, 'budget_manager');
    $this->actingAs($user);

    Storage::fake('local');
});

function expensesUploadFor(BudgetVersion $source): UploadedFile
{
    Excel::store(new ExpenseItemsExport($source), 'temp-export.xlsx', 'local', ExcelFormat::XLSX);

    return UploadedFile::fake()->createWithContent('expenses.xlsx', Storage::disk('local')->get('temp-export.xlsx'));
}

function seededSourceBudget(): BudgetVersion
{
    $year = BudgetYear::create(['year' => 2025, 'name' => 'IT Budget 2025', 'status' => 'ACTIVE']);
    $source = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2025',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $expense = $source->expenseItems()->create(['name' => 'Office365', 'vendor' => 'Microsoft', 'expense_type' => 'MONTHLY']);
    foreach (range(1, 12) as $month) {
        $expense->monthValues()->create(['month' => $month, 'amount' => 100]);
    }

    return $source;
}

it('creates a brand-new budget from an Excel file via the list header action', function () {
    $upload = expensesUploadFor(seededSourceBudget());

    Livewire::test(ListBudgetVersions::class)
        ->callAction('importNew', data: [
            'name' => 'IT Expenses FC1 2026',
            'year' => 2026,
            'type' => 'FC1',
            'editable_from_month' => 3,
            'editable_to_month' => 12,
            'kind' => 'expenses',
            'file' => $upload,
            'sheet_name' => 'Worksheet',
        ])
        ->assertHasNoActionErrors();

    $budget = BudgetVersion::where('name', 'IT Expenses FC1 2026')->first();
    expect($budget)->not->toBeNull();
    expect($budget->budgetYear->year)->toBe(2026);
    expect($budget->expenseItems()->count())->toBe(1);
    // All 12 months import (months 1-2 are historical actuals on FC1) → total matches the file.
    expect($budget->expenseItems()->first()->total)->toBe(1200.0);
});

it('imports rows into an existing budget via the edit page action', function () {
    $upload = expensesUploadFor(seededSourceBudget());

    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $target = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);

    Livewire::test(EditBudgetVersion::class, ['record' => $target->getRouteKey()])
        ->callAction('import', data: [
            'kind' => 'expenses',
            'file' => $upload,
            'sheet_name' => 'Worksheet',
        ])
        ->assertHasNoActionErrors();

    expect($target->expenseItems()->count())->toBe(1);
    expect($target->expenseItems()->first()->total)->toBe(1200.0);
});
