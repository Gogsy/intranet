<?php

namespace App\Filament\Resources\BudgetVersionResource\Pages;

use Filament\Support\Enums\Width;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Throwable;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\DeleteAction;
use App\Exports\ExpenseItemsExport;
use App\Exports\InvestmentItemsExport;
use App\Filament\Resources\BudgetVersionResource;
use App\Filament\Resources\BudgetVersionResource\Widgets\BudgetVersionExpensesChart;
use App\Filament\Resources\BudgetVersionResource\Widgets\BudgetVersionInvestmentsChart;
use App\Filament\Resources\BudgetVersionResource\Widgets\BudgetVersionSummary;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Services\BudgetImportService;
use App\Services\BudgetVersionService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class EditBudgetVersion extends EditRecord
{
    protected static string $resource = BudgetVersionResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    public function getTitle(): string
    {
        /** @var BudgetVersion $record */
        $record = $this->getRecord();

        $year = $record->budgetYear?->year;

        return $year ? "Edit Budget — {$record->name} ({$year})" : "Edit Budget — {$record->name}";
    }

    /**
     * The page itself has no inline form — all settings live in the
     * "Budget settings" header action so the page stays a clean workspace
     * (widgets + investment/expense tables).
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [
            BudgetVersionSummary::class,
            BudgetVersionInvestmentsChart::class,
            BudgetVersionExpensesChart::class,
        ];
    }

    /** Owner-tier budget operations (settings, lock, import, …). */
    protected function userCanManageBudget(): bool
    {
        return auth()->user()?->can('manage_budget') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Users without manage_budget get no lock/unlock buttons — just a
            // read-only badge showing the current status.
            Action::make('statusBadge')
                ->label(fn (BudgetVersion $record) => 'Status: ' . $record->status)
                ->icon(fn (BudgetVersion $record) => $record->canEditBudgetValues()
                    ? 'heroicon-o-lock-open'
                    : 'heroicon-o-lock-closed')
                ->color(fn (BudgetVersion $record) => $record->canEditBudgetValues() ? 'success' : 'danger')
                ->disabled()
                ->visible(fn () => ! $this->userCanManageBudget()),

            Action::make('lock')
                ->label('Lock')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Once locked, investment and expense rows can no longer be edited (except the realization fields: decision status, purchased, note).')
                ->visible(fn (BudgetVersion $record) => $this->userCanManageBudget()
                    && in_array($record->status, ['DRAFT', 'TEMPORARILY_UNLOCKED'], true))
                ->action(function (BudgetVersion $record) {
                    try {
                        BudgetVersionService::lock($record);
                        Notification::make()->title('Budget locked.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Locking failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('unlock')
                ->label('Unlock')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->visible(fn (BudgetVersion $record) => $this->userCanManageBudget() && $record->status === 'LOCKED')
                ->schema([
                    Textarea::make('reason')->label('Unlock reason')->required()->rows(2),
                ])
                ->action(function (BudgetVersion $record, array $data) {
                    try {
                        BudgetVersionService::unlock($record, $data['reason'], auth()->user());
                        Notification::make()->title('Budget temporarily unlocked.')->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Unlocking failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('exportInvestments')
                ->label('Export investments')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('export_budget') ?? false)
                ->action(fn (BudgetVersion $record) => Excel::download(
                    new InvestmentItemsExport($record),
                    "investments-{$record->name}.xlsx",
                    ExcelFormat::XLSX,
                )),

            Action::make('exportExpenses')
                ->label('Export expenses')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                // Export follows visibility: no expenses tab, no expenses export.
                ->visible(fn () => (auth()->user()?->can('export_budget') ?? false)
                    && (auth()->user()?->can('view_budget_expenses') ?? false))
                ->action(fn (BudgetVersion $record) => Excel::download(
                    new ExpenseItemsExport($record),
                    "expenses-{$record->name}.xlsx",
                    ExcelFormat::XLSX,
                )),

            Action::make('import')
                ->label('Import from Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (BudgetVersion $record) => $this->userCanManageBudget() && $record->canEditBudgetValues())
                ->schema([
                    Select::make('kind')
                        ->label('Data type')
                        ->options(['investments' => 'Investments', 'expenses' => 'Expenses'])
                        ->required(),

                    FileUpload::make('file')
                        ->label('Excel file (.xlsx)')
                        ->disk('local')
                        ->directory('budget-planner-imports')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->required()
                        ->live(),

                    Select::make('sheet_name')
                        ->label('Sheet')
                        ->helperText('Workbooks often have many sheets — pick the one with the actual data.')
                        ->options(fn (Get $get) => BudgetImportService::sheetOptionsFromUploadState($get('file')))
                        ->visible(fn (Get $get) => filled($get('file')))
                        ->required(),
                ])
                ->action(function (BudgetVersion $record, array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    $result = $data['kind'] === 'expenses'
                        ? BudgetImportService::parseExpenses($path, $record, $data['sheet_name'])
                        : BudgetImportService::parseInvestments($path, $record, $data['sheet_name']);

                    // Keep the file when the import is blocked so the failure
                    // can be inspected; only successful imports clean it up.
                    if (empty($result['errors'])) {
                        Storage::disk('local')->delete($data['file']);
                    }

                    if (! empty($result['errors'])) {
                        Notification::make()
                            ->title('Import blocked — fix these rows in the file first:')
                            ->body(implode("\n", array_slice($result['errors'], 0, 10)) . (count($result['errors']) > 10 ? "\n…and " . (count($result['errors']) - 10) . ' more.' : ''))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    $count = $data['kind'] === 'expenses'
                        ? BudgetImportService::persistExpenses($result['rows'], $record)
                        : BudgetImportService::persistInvestments($result['rows'], $record, auth()->user());

                    Notification::make()->title("Imported {$count} rows.")->success()->send();
                }),

            Action::make('settings')
                ->label('Budget settings')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->visible(fn () => $this->userCanManageBudget())
                ->modalHeading('Budget settings')
                ->modalWidth('3xl')
                ->fillForm(fn (BudgetVersion $record) => [
                    'name' => $record->name,
                    'year' => $record->budgetYear?->year,
                    'type' => $record->type,
                    'editable_from_month' => $record->editable_from_month,
                    'editable_to_month' => $record->editable_to_month,
                    'status' => $record->status,
                ])
                // Same schema the Create page uses — the template picker hides
                // itself and the status field shows itself on existing records.
                ->schema(fn (Schema $schema) => BudgetVersionResource::form($schema)->columns(2))
                ->action(function (BudgetVersion $record, array $data) {
                    // "Year" is a plain number on the form — translate it back
                    // to the hidden BudgetYear row.
                    $year = BudgetYear::firstOrCreate(
                        ['year' => (int) $data['year']],
                        ['name' => "IT Budget {$data['year']}", 'status' => 'ACTIVE'],
                    );
                    $data['budget_year_id'] = $year->id;
                    unset($data['year'], $data['template_version_id']);

                    $record->update($data);

                    Notification::make()->title('Budget settings saved.')->success()->send();
                }),

            DeleteAction::make(),
        ];
    }
}
