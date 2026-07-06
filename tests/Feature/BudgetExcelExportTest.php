<?php

use App\Exports\ExpenseItemsExport;
use App\Exports\InvestmentItemsExport;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentType;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

afterEach(function () {
    @unlink(storage_path('app/test-investments.xlsx'));
    @unlink(storage_path('app/test-expenses.xlsx'));
});

it('exports investments to a real .xlsx file with the expected headers and values', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $type = InvestmentType::create(['name' => 'Hardware']);
    $version->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 2, 'unit_net_price' => 400, 'classification' => 'Asset',
        'decision_status' => 'Approved', 'purchased' => true,
    ]);

    Excel::store(new InvestmentItemsExport($version), 'test-investments.xlsx', 'local', ExcelFormat::XLSX);

    $rows = Excel::toArray([], storage_path('app/test-investments.xlsx'))[0];

    expect($rows[0])->toBe([
        'Mjesec', 'Zadnje uredio', 'Vrsta investicije', 'Opis', 'Komentar / prijedlog',
        'Količina', 'Jedinična neto cijena', 'Ukupno', 'Klasifikacija', 'Link i/ili opis',
        'Status odluke', 'Kupljeno', 'Napomena realizacije',
    ]);
    expect($rows[1][2])->toBe('Hardware');
    expect($rows[1][3])->toBe('Laptop');
    expect((float) $rows[1][7])->toBe(800.0);
    expect($rows[1][11])->toBe('Da');
});

it('exports expenses to a real .xlsx file with 12 month columns and a total', function () {
    $year = BudgetYear::create(['year' => 2026, 'name' => 'IT Budget 2026', 'status' => 'ACTIVE']);
    $version = BudgetVersion::create([
        'budget_year_id' => $year->id, 'type' => 'PLAN', 'name' => 'Plan 2026',
        'editable_from_month' => 1, 'editable_to_month' => 12, 'status' => 'DRAFT',
    ]);
    $expense = $version->expenseItems()->create(['name' => 'Office365', 'vendor' => 'Microsoft', 'expense_type' => 'MONTHLY']);
    foreach (range(1, 12) as $month) {
        $expense->monthValues()->create(['month' => $month, 'amount' => 100]);
    }

    Excel::store(new ExpenseItemsExport($version), 'test-expenses.xlsx', 'local', ExcelFormat::XLSX);

    $rows = Excel::toArray([], storage_path('app/test-expenses.xlsx'))[0];

    expect($rows[0])->toBe(['Naziv', 'Konto', 'Dobavljač', 'Opis', 'Komentar', 'Tip', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 'Ukupno']);
    expect($rows[1][0])->toBe('Office365');
    expect($rows[1][2])->toBe('Microsoft');
    expect((float) $rows[1][6])->toBe(100.0); // month 1
    expect((float) $rows[1][18])->toBe(1200.0); // total
});
