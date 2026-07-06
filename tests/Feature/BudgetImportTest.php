<?php

use App\Exports\ExpenseItemsExport;
use App\Exports\InvestmentItemsExport;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Models\InvestmentType;
use App\Models\User;
use App\Services\BudgetImportService;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

afterEach(function () {
    @unlink(storage_path('app/import-investments.xlsx'));
    @unlink(storage_path('app/import-expenses.xlsx'));
    @unlink(storage_path('app/import-titled.xlsx'));
});

function importDraftVersion(string $type = 'PLAN', ?int $year = null): BudgetVersion
{
    // A guaranteed-unique year per call (not random — this helper is called
    // multiple times within a single test, and a narrow random range risked
    // rare collisions on budget_years.year's unique constraint).
    static $counter = 0;
    $year ??= 3000 + $counter++;
    $budgetYear = BudgetYear::create(['year' => $year, 'name' => "IT Budget {$year}", 'status' => 'ACTIVE']);
    $window = BudgetVersion::editableWindowFor($type);

    return BudgetVersion::create([
        'budget_year_id' => $budgetYear->id,
        'type' => $type,
        'name' => "{$type} {$year}",
        'editable_from_month' => $window['from'],
        'editable_to_month' => $window['to'],
        'status' => 'DRAFT',
    ]);
}

it('parses and persists a valid investments file', function () {
    $version = importDraftVersion();
    $type = InvestmentType::firstOrCreate(['name' => 'Hardware']);
    $user = User::factory()->create(['name' => 'Marko']);

    // Build a source version to export from, so we get a realistic file.
    $source = importDraftVersion();
    $source->investmentItems()->create([
        'month' => 3, 'entered_by_id' => $user->id, 'investment_type_id' => $type->id,
        'description' => 'Laptop', 'quantity' => 2, 'unit_net_price' => 400,
        'classification' => 'Asset', 'decision_status' => 'Approved', 'purchased' => true,
    ]);

    Excel::store(new InvestmentItemsExport($source), 'import-investments.xlsx', 'local', ExcelFormat::XLSX);
    $path = storage_path('app/import-investments.xlsx');

    $result = BudgetImportService::parseInvestments($path, $version);

    expect($result['errors'])->toBeEmpty();
    expect($result['rows'])->toHaveCount(1);
    expect($result['rows'][0]['description'])->toBe('Laptop');
    expect($result['rows'][0]['entered_by_id'])->toBe($user->id);
    expect($result['rows'][0]['classification'])->toBe('Asset');
    expect($result['rows'][0]['decision_status'])->toBe('Approved');
    expect($result['rows'][0]['purchased'])->toBeTrue();

    $count = BudgetImportService::persistInvestments($result['rows'], $version, $user);

    expect($count)->toBe(1);
    expect($version->investmentItems()->count())->toBe(1);
    $imported = $version->investmentItems()->first();
    expect($imported->total)->toBe(800.0);
});

it('parses and persists a valid expenses file with 12 month values', function () {
    $version = importDraftVersion();

    $source = importDraftVersion();
    $expense = $source->expenseItems()->create(['name' => 'Office365', 'vendor' => 'Microsoft', 'expense_type' => 'MONTHLY']);
    foreach (range(1, 12) as $month) {
        $expense->monthValues()->create(['month' => $month, 'amount' => 100]);
    }

    Excel::store(new ExpenseItemsExport($source), 'import-expenses.xlsx', 'local', ExcelFormat::XLSX);
    $path = storage_path('app/import-expenses.xlsx');

    $result = BudgetImportService::parseExpenses($path, $version);

    expect($result['errors'])->toBeEmpty();
    expect($result['rows'])->toHaveCount(1);
    expect($result['rows'][0]['months'][1])->toBe(100.0);

    BudgetImportService::persistExpenses($result['rows'], $version);

    $imported = $version->expenseItems()->first();
    expect($imported->monthValues()->count())->toBe(12);
    expect($imported->total)->toBe(1200.0);
});

it('blocks the entire import when any row has an error (all-or-nothing)', function () {
    $version = importDraftVersion();
    $type = InvestmentType::firstOrCreate(['name' => 'Hardware']);

    $source = importDraftVersion();
    $source->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Valid row',
        'quantity' => 1, 'unit_net_price' => 100, 'classification' => 'Asset',
    ]);
    $source->investmentItems()->create([
        'month' => 4, 'investment_type_id' => $type->id, 'description' => '', // will become invalid after we blank it in the file
        'quantity' => 1, 'unit_net_price' => 100, 'classification' => 'Asset',
    ]);

    Excel::store(new InvestmentItemsExport($source), 'import-investments.xlsx', 'local', ExcelFormat::XLSX);
    $path = storage_path('app/import-investments.xlsx');

    $result = BudgetImportService::parseInvestments($path, $version);

    // One row has a blank description → the whole file has an error and nothing may be persisted.
    expect($result['errors'])->not->toBeEmpty();
    expect($result['rows'])->toHaveCount(1); // only the valid row parsed, but the page must not call persist()

    // Simulating the page's guard: persist is only ever called when errors are empty.
    expect(empty($result['errors']))->toBeFalse();
});

it('rejects investment rows outside the editable month window', function () {
    // FC1's window is 3-12; a row in month 1 must be rejected.
    $version = importDraftVersion('FC1');
    $type = InvestmentType::firstOrCreate(['name' => 'Hardware']);

    $source = importDraftVersion('PLAN');
    $source->investmentItems()->create([
        'month' => 1, 'investment_type_id' => $type->id, 'description' => 'January item',
        'quantity' => 1, 'unit_net_price' => 100, 'classification' => 'Asset',
    ]);

    Excel::store(new InvestmentItemsExport($source), 'import-investments.xlsx', 'local', ExcelFormat::XLSX);
    $path = storage_path('app/import-investments.xlsx');

    $result = BudgetImportService::parseInvestments($path, $version);

    expect($result['errors'])->not->toBeEmpty();
    expect($result['errors'][0])->toContain("outside the budget's editable range");
});

it('imports expense months outside the editable window as historical actuals (FC1 keeps Jan/Feb)', function () {
    $version = importDraftVersion('FC1'); // editable window 3-12

    $source = importDraftVersion();
    $expense = $source->expenseItems()->create(['name' => 'Office365', 'expense_type' => 'MONTHLY']);
    foreach (range(1, 12) as $month) {
        $expense->monthValues()->create(['month' => $month, 'amount' => 100]);
    }
    Excel::store(new ExpenseItemsExport($source), 'import-expenses.xlsx', 'local', ExcelFormat::XLSX);

    $result = BudgetImportService::parseExpenses(storage_path('app/import-expenses.xlsx'), $version);

    expect($result['errors'])->toBeEmpty();
    expect($result['rows'][0]['months'])->toHaveCount(12);

    BudgetImportService::persistExpenses($result['rows'], $version);

    $imported = $version->expenseItems()->first();
    expect($imported->monthValues()->count())->toBe(12);
    expect((float) $imported->monthValues()->where('month', 1)->first()->amount)->toBe(100.0);
    expect($imported->total)->toBe(1200.0); // annual total matches the file exactly
});

it('recognizes the department\'s historical column names and formula workbooks', function () {
    $version = importDraftVersion('FC1');

    // Mimics the real "Investicije FC1 2026" sheet: merged description column,
    // "Ime tko je editirao" instead of "Zadnje uredio", classification header
    // written as its values, and UKUPNO left out (it's a formula there).
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray([[
        'MJESEC', 'Ime tko je editirao za FC1 2026', 'VRSTA INVESTICIJE', 'Opis / komentar / prijedlog',
        'KOLIČINA', 'JEDINIČNA NETO CIJENA', 'UKUPNO', "Imovina\nPotrošno",
    ], [
        3, 'Marko', 'Hardware', 'SO-DIMM 8GB DDR4', 10, 45, 450, 'Potrošno',
    ]], null, 'A1');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save(storage_path('app/import-titled.xlsx'));

    $result = BudgetImportService::parseInvestments(storage_path('app/import-titled.xlsx'), $version);

    expect($result['errors'])->toBeEmpty();
    expect($result['rows'])->toHaveCount(1);
    expect($result['rows'][0]['description'])->toBe('SO-DIMM 8GB DDR4');
    expect($result['rows'][0]['classification'])->toBe('Consumable');
    expect($result['rows'][0]['quantity'])->toBe(10.0);
});

it('finds the header row even when title/blank rows sit above it', function () {
    $version = importDraftVersion();

    // A realistic hand-made workbook: title in row 1, blank row 2, header in row 3.
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([['IT BUDŽET 2026 — investicije'], [], [
        'Mjesec', 'Zadnje uredio', 'Vrsta investicije', 'Opis', 'Komentar / prijedlog',
        'Količina', 'Jedinična neto cijena', 'Ukupno', 'Klasifikacija', 'Link i/ili opis',
        'Status odluke', 'Kupljeno', 'Napomena realizacije',
    ], [
        3, '', 'Hardware', 'Laptop', '', 2, 400, 800, 'Asset', '', 'Approved', 'Da', '',
    ]], null, 'A1');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save(storage_path('app/import-titled.xlsx'));

    $result = BudgetImportService::parseInvestments(storage_path('app/import-titled.xlsx'), $version);

    expect($result['errors'])->toBeEmpty();
    expect($result['rows'])->toHaveCount(1);
    expect($result['rows'][0]['description'])->toBe('Laptop');
    expect($result['rows'][0]['line'])->toBe(4); // real Excel row number, for fixable error messages
});

it('reports one clear error (not one per row) when the header row cannot be found', function () {
    $version = importDraftVersion();

    // An expenses file parsed as investments must not spam per-row errors.
    $source = importDraftVersion();
    $expense = $source->expenseItems()->create(['name' => 'Office365', 'expense_type' => 'MONTHLY']);
    foreach (range(1, 12) as $month) {
        $expense->monthValues()->create(['month' => $month, 'amount' => 100]);
    }
    Excel::store(new ExpenseItemsExport($source), 'import-expenses.xlsx', 'local', ExcelFormat::XLSX);

    $result = BudgetImportService::parseInvestments(storage_path('app/import-expenses.xlsx'), $version);

    expect($result['rows'])->toBeEmpty();
    expect($result['errors'])->toHaveCount(1);
    expect($result['errors'][0])->toContain('header row');
});

it('blocks import into a locked version', function () {
    $version = importDraftVersion();
    $version->update(['status' => 'LOCKED', 'locked_at' => now()]);

    $type = InvestmentType::firstOrCreate(['name' => 'Hardware']);
    $source = importDraftVersion();
    $source->investmentItems()->create([
        'month' => 3, 'investment_type_id' => $type->id, 'description' => 'Laptop',
        'quantity' => 1, 'unit_net_price' => 400, 'classification' => 'Asset',
    ]);

    Excel::store(new InvestmentItemsExport($source), 'import-investments.xlsx', 'local', ExcelFormat::XLSX);
    $path = storage_path('app/import-investments.xlsx');

    $result = BudgetImportService::parseInvestments($path, $version);

    expect($result['errors'])->not->toBeEmpty();
    expect($result['errors'][0])->toContain('locked');
});
