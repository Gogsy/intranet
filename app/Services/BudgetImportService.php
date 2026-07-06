<?php

namespace App\Services;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;
use App\Models\ExpenseItem;
use App\Models\ExpenseMonthValue;
use App\Models\BudgetVersion;
use App\Models\InvestmentType;
use App\Models\User;
use App\Support\BudgetPlannerOptions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads the same Croatian column headers this module's own Excel export
 * produces (round-trip: export, edit in Excel, re-import) and — unlike the
 * original MVP, where import only ever produced a preview and never actually
 * saved anything — actually persists validated rows. All-or-nothing: persist
 * only runs once every row in the file is valid (see BudgetImport page).
 *
 * Header matching is case/whitespace-insensitive (real workbooks are
 * hand-edited over years and drift in capitalization), and the caller picks
 * which SHEET to read — real multi-sheet workbooks (e.g. this department's
 * historical FC1/Plan templates) rarely have the data on the first sheet.
 */
class BudgetImportService
{
    /** Columns that identify an investments header row (as produced by our own export). */
    private const INVESTMENT_HEADER_KEYS = ['opis', 'mjesec', 'količina', 'jedinična neto cijena'];

    /** Columns that identify an expenses header row. */
    private const EXPENSE_HEADER_KEYS = ['naziv', '1', '12'];

    /** Sheet names in a workbook, in order — lets the import form offer a picker instead of blindly reading sheet 0. */
    public static function listSheetNames(string $filePath): array
    {
        return IOFactory::load($filePath)->getSheetNames();
    }

    /**
     * Sheet options for a Filament FileUpload's live state: while the form is
     * still live the value is a Livewire TemporaryUploadedFile (not yet moved
     * to the configured disk), after submit it's the stored relative path.
     *
     * @return array<string, string>
     */
    public static function sheetOptionsFromUploadState(mixed $state): array
    {
        if (blank($state)) {
            return [];
        }

        $file = is_array($state) ? reset($state) : $state;

        $fullPath = $file instanceof TemporaryUploadedFile
            ? $file->getRealPath()
            : Storage::disk('local')->path((string) $file);

        if (! file_exists($fullPath)) {
            return [];
        }

        try {
            $names = self::listSheetNames($fullPath);

            return array_combine($names, $names);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array{rows: array<int, array>, errors: array<int, string>} */
    public static function parseInvestments(string $filePath, BudgetVersion $version, ?string $sheetName = null): array
    {
        $sheet = self::resolveSheetRows($filePath, $sheetName);
        $headerRowIndex = self::findHeaderRowIndex($sheet, self::INVESTMENT_HEADER_KEYS);

        if ($headerRowIndex === null) {
            return ['rows' => [], 'errors' => [
                'Could not find the investments header row on this sheet (expected columns: Opis, Mjesec, Količina, Jedinična neto cijena). '
                . 'Check that you picked the right sheet and that the data type is Investments, not Expenses. '
                . self::describeSheetStart($sheet),
            ]];
        }

        $header = self::normalizeHeader($sheet[$headerRowIndex]);
        $dataRows = array_slice($sheet, $headerRowIndex + 1, null, preserve_keys: true);

        $rows = [];
        $errors = [];

        foreach ($dataRows as $index => $row) {
            if (self::isBlankRow($row)) {
                continue;
            }

            $lineNumber = $index + 1; // 0-based sheet index → 1-based Excel row
            $assoc = self::combine($header, $row);
            $rowErrors = [];

            $description = trim((string) ($assoc['opis'] ?? ''));
            if ($description === '') {
                $rowErrors[] = 'Description (Opis) is required.';
            }

            $month = (int) ($assoc['mjesec'] ?? 0);
            if ($month < 1 || $month > 12) {
                $rowErrors[] = 'Month (Mjesec) must be a number from 1 to 12.';
            } elseif (! $version->canEditBudgetValues()) {
                $rowErrors[] = 'The budget is locked.';
            } elseif (! $version->isMonthEditable($month)) {
                $rowErrors[] = "Month {$month} is outside the budget's editable range ({$version->editable_from_month}-{$version->editable_to_month}).";
            }

            $quantity = is_numeric($assoc['količina'] ?? null) ? (float) $assoc['količina'] : null;
            if ($quantity === null) {
                $rowErrors[] = 'Quantity (Količina) must be a number.';
            }

            $unitPrice = is_numeric($assoc['jedinična neto cijena'] ?? null) ? (float) $assoc['jedinična neto cijena'] : null;
            if ($unitPrice === null) {
                $rowErrors[] = 'Unit net price (Jedinična neto cijena) must be a number.';
            }

            $classificationRaw = trim((string) ($assoc['klasifikacija'] ?? ''));
            $classification = self::resolveClassification($classificationRaw);
            if ($classificationRaw !== '' && $classification === null) {
                $rowErrors[] = "Unknown classification \"{$classificationRaw}\".";
            }

            $statusRaw = trim((string) ($assoc['status odluke'] ?? ''));
            $decisionStatus = $statusRaw === '' ? 'Proposed' : self::resolveLabel($statusRaw, BudgetPlannerOptions::INVESTMENT_DECISION_STATUSES);
            if ($statusRaw !== '' && $decisionStatus === null) {
                $rowErrors[] = "Unknown decision status \"{$statusRaw}\".";
            }

            if ($rowErrors) {
                $errors[] = "Row {$lineNumber}: " . implode(' ', $rowErrors);

                continue;
            }

            $enteredByName = trim((string) ($assoc['zadnje uredio'] ?? ''));
            $enteredBy = $enteredByName !== ''
                ? User::whereRaw('LOWER(name) = ?', [mb_strtolower($enteredByName)])->first()
                : null;

            $rows[] = [
                'line' => $lineNumber,
                'month' => $month,
                'entered_by_id' => $enteredBy?->id,
                'investment_type_name' => trim((string) ($assoc['vrsta investicije'] ?? '')) ?: 'Ostalo',
                'description' => $description,
                'proposal_comment' => (string) ($assoc['komentar / prijedlog'] ?? ''),
                'quantity' => $quantity,
                'unit_net_price' => $unitPrice,
                'classification' => $classification ?? 'Consumable',
                'link_or_description' => (string) ($assoc['link i/ili opis'] ?? ''),
                'decision_status' => $decisionStatus,
                'purchased' => self::parseBool($assoc['kupljeno'] ?? ''),
                'realization_comment' => (string) ($assoc['napomena realizacije'] ?? ''),
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    public static function persistInvestments(array $rows, BudgetVersion $version, User $importedBy): int
    {
        return DB::transaction(function () use ($rows, $version, $importedBy) {
            foreach ($rows as $row) {
                $type = InvestmentType::firstOrCreate(['name' => $row['investment_type_name']]);

                $version->investmentItems()->create([
                    'month' => $row['month'],
                    'entered_by_id' => $row['entered_by_id'] ?? $importedBy->id,
                    'investment_type_id' => $type->id,
                    'description' => $row['description'],
                    'proposal_comment' => $row['proposal_comment'],
                    'quantity' => $row['quantity'],
                    'unit_net_price' => $row['unit_net_price'],
                    'classification' => $row['classification'],
                    'link_or_description' => $row['link_or_description'],
                    'decision_status' => $row['decision_status'],
                    'purchased' => $row['purchased'],
                    'realization_comment' => $row['realization_comment'],
                ]);
            }

            return count($rows);
        });
    }

    /** @return array{rows: array<int, array>, errors: array<int, string>} */
    public static function parseExpenses(string $filePath, BudgetVersion $version, ?string $sheetName = null): array
    {
        $sheet = self::resolveSheetRows($filePath, $sheetName);
        $headerRowIndex = self::findHeaderRowIndex($sheet, self::EXPENSE_HEADER_KEYS);

        if ($headerRowIndex === null) {
            return ['rows' => [], 'errors' => [
                'Could not find the expenses header row on this sheet (expected columns: Naziv plus month columns 1-12). '
                . 'Check that you picked the right sheet and that the data type is Expenses, not Investments. '
                . self::describeSheetStart($sheet),
            ]];
        }

        $header = self::normalizeHeader($sheet[$headerRowIndex]);
        $dataRows = array_slice($sheet, $headerRowIndex + 1, null, preserve_keys: true);

        $rows = [];
        $errors = [];

        foreach ($dataRows as $index => $row) {
            if (self::isBlankRow($row)) {
                continue;
            }

            $lineNumber = $index + 1;
            $assoc = self::combine($header, $row);
            $rowErrors = [];

            $name = trim((string) ($assoc['naziv'] ?? ''));
            if ($name === '') {
                $rowErrors[] = 'Name (Naziv) is required.';
            }

            if (! $version->canEditBudgetValues()) {
                $rowErrors[] = 'The budget is locked.';
            }

            $months = [];
            foreach (range(1, 12) as $month) {
                $raw = $assoc[(string) $month] ?? 0;

                if ($raw !== '' && $raw !== null && ! is_numeric($raw)) {
                    $rowErrors[] = "The value for month {$month} must be a number.";

                    continue;
                }

                // Months outside this version's editable window (e.g. FC1
                // doesn't touch Jan/Feb) ARE imported — a real FC1 workbook
                // carries the year's actuals in those cells and the annual
                // total must match the file. The window only locks *editing*
                // afterwards; persistExpenses() bypasses the guard for this
                // initial, fully validated load.
                $months[$month] = (float) ($raw ?: 0);
            }

            $expenseType = strtoupper(trim((string) ($assoc['tip'] ?? '')));
            if (! array_key_exists($expenseType, BudgetPlannerOptions::EXPENSE_TYPES)) {
                $expenseType = 'VOLUME';
            }

            if ($rowErrors) {
                $errors[] = "Row {$lineNumber}: " . implode(' ', $rowErrors);

                continue;
            }

            $rows[] = [
                'line' => $lineNumber,
                'name' => $name,
                'account_code' => (string) ($assoc['konto'] ?? ''),
                'vendor' => (string) ($assoc['dobavljač'] ?? ''),
                'description' => (string) ($assoc['opis'] ?? ''),
                'comment' => (string) ($assoc['komentar'] ?? ''),
                'expense_type' => $expenseType,
                'months' => $months,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    public static function persistExpenses(array $rows, BudgetVersion $version): int
    {
        // Bypass the per-model lock guard: this is a fully validated initial
        // load (parse already rejected locked versions), and month values
        // outside the editable window are legitimate historical actuals here
        // — see the comment in parseExpenses().
        return DB::transaction(function () use ($rows, $version) {
            return ExpenseItem::withoutLockGuard(function () use ($rows, $version) {
                return ExpenseMonthValue::withoutLockGuard(function () use ($rows, $version) {
                    foreach ($rows as $row) {
                        $expense = $version->expenseItems()->create([
                            'name' => $row['name'],
                            'account_code' => $row['account_code'],
                            'vendor' => $row['vendor'],
                            'description' => $row['description'],
                            'comment' => $row['comment'],
                            'expense_type' => $row['expense_type'],
                        ]);

                        foreach ($row['months'] as $month => $amount) {
                            $expense->monthValues()->create(['month' => $month, 'amount' => $amount]);
                        }
                    }

                    return count($rows);
                });
            });
        });
    }

    /** Reads the requested sheet by name (case-insensitive); falls back to the first sheet if not found/not specified. */
    private static function resolveSheetRows(string $filePath, ?string $sheetName): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        $sheet = $spreadsheet->getSheet(0);

        if ($sheetName !== null && trim($sheetName) !== '') {
            $needle = mb_strtolower(trim($sheetName));

            foreach ($spreadsheet->getAllSheets() as $candidate) {
                if (mb_strtolower(trim($candidate->getTitle())) === $needle) {
                    $sheet = $candidate;
                    break;
                }
            }
        }

        try {
            return $sheet->toArray(null, true, false);
        } catch (Throwable) {
            // Formula evaluation can fail on real workbooks (e.g. structured
            // references to tables on other sheets). Fall back to raw values —
            // the columns we import (quantities, prices, month amounts) are
            // plain numbers; formulas typically only compute the UKUPNO
            // column, which we recompute ourselves anyway.
            return $sheet->toArray(null, false, false);
        }
    }

    /**
     * Finds the header row anywhere in the first 15 rows — real workbooks
     * routinely have a title, a logo row or blank rows above the actual
     * header, so assuming row 1 rejected every data row with confusing
     * per-row errors. A row qualifies when it contains ALL the identifying
     * column names (case/whitespace-insensitive).
     *
     * @param  array<int, string>  $requiredKeys  normalized column names
     */
    private static function findHeaderRowIndex(array $sheet, array $requiredKeys): ?int
    {
        foreach (array_slice($sheet, 0, 15, preserve_keys: true) as $index => $row) {
            $cells = self::normalizeHeader($row);

            if (count(array_intersect($requiredKeys, $cells)) === count($requiredKeys)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * A short preview of the sheet's first non-blank rows for the
     * header-not-found error, so the user (or we) can see the actual column
     * names in the file without reopening it.
     */
    private static function describeSheetStart(array $sheet): string
    {
        $preview = [];

        foreach (array_slice($sheet, 0, 15, preserve_keys: true) as $index => $row) {
            if (self::isBlankRow($row)) {
                continue;
            }

            $cells = collect($row)
                ->map(fn ($cell) => trim((string) $cell))
                ->filter(fn ($cell) => $cell !== '')
                ->take(8)
                ->map(fn ($cell) => mb_strimwidth($cell, 0, 25, '…'))
                ->implode(' | ');

            $preview[] = 'row ' . ($index + 1) . ': ' . $cells;

            if (count($preview) === 3) {
                break;
            }
        }

        return $preview === []
            ? 'The sheet appears to be empty.'
            : 'The sheet starts with — ' . implode('; ', $preview);
    }

    private static function isBlankRow(array $row): bool
    {
        return collect($row)->filter(fn ($cell) => trim((string) $cell) !== '')->isEmpty();
    }

    /** Lowercases + collapses whitespace (handles newlines inside merged header cells) so matching is forgiving. */
    private static function normalizeHeader(array $header): array
    {
        return array_map(
            fn ($cell) => self::aliasHeader(mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $cell)))),
            $header,
        );
    }

    /**
     * Maps column-name variants seen in the department's real historical
     * workbooks onto the canonical names our export produces, so those files
     * import without manual renaming. Input is already normalized (lowercase,
     * single spaces).
     */
    private static function aliasHeader(string $cell): string
    {
        return match (true) {
            // "Opis / komentar / prijedlog" — description and comment merged into one column.
            str_starts_with($cell, 'opis') => 'opis',
            // "Ime tko je editirao za FC1 2026", "Unio" — who last touched the row.
            str_contains($cell, 'editirao'), $cell === 'unio' => 'zadnje uredio',
            // Classification header written as its possible values ("Imovina / Potrošno").
            str_contains($cell, 'imovina') && str_contains($cell, 'potrošno') => 'klasifikacija',
            default => $cell,
        };
    }

    private static function combine(array $normalizedHeader, array $row): array
    {
        $assoc = [];

        foreach ($normalizedHeader as $i => $key) {
            $assoc[$key] = $row[$i] ?? null;
        }

        return $assoc;
    }

    /**
     * Maps a classification cell to its stored value, accepting both the
     * English values and the Croatian terms used in the department's real
     * historical Excel templates (Imovina/Potrošno).
     */
    private static function resolveClassification(string $input): ?string
    {
        if ($input === '') {
            return null;
        }

        $synonyms = [
            'asset' => 'Asset',
            'imovina' => 'Asset',
            'consumable' => 'Consumable',
            'potrošno' => 'Consumable',
            'rent' => 'Rent',
        ];

        return $synonyms[mb_strtolower($input)] ?? null;
    }

    private static function resolveLabel(string $input, array $labelMap): ?string
    {
        if ($input === '') {
            return null;
        }

        foreach ($labelMap as $key => $label) {
            if (mb_strtolower($key) === mb_strtolower($input) || mb_strtolower($label) === mb_strtolower($input)) {
                return $key;
            }
        }

        return null;
    }

    private static function parseBool(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['da', 'yes', '1', 'true'], true);
    }
}
