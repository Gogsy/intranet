<?php

namespace App\Filament\Resources\BudgetVersionResource\Pages;

use Filament\Support\Enums\Width;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\CreateAction;
use App\Filament\Resources\BudgetVersionResource;
use App\Models\BudgetVersion;
use App\Models\BudgetYear;
use App\Services\BudgetImportService;
use App\Services\BudgetVersionService;
use App\Support\BudgetPlannerOptions;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListBudgetVersions extends ListRecords
{
    protected static string $resource = BudgetVersionResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importNew')
                ->label('Import from Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                // Creating budgets (incl. via import) is owner-tier.
                ->visible(fn () => auth()->user()?->can('manage_budget') ?? false)
                ->modalDescription('Creates a new budget from an Excel file — it appears in the list below with all its rows.')
                ->schema([
                    TextInput::make('name')
                        ->label('Budget name')
                        ->placeholder('e.g. IT Expenses FC1 2026')
                        ->required()
                        ->maxLength(255),

                    Grid::make(2)->schema([
                        TextInput::make('year')
                            ->label('Year')
                            ->numeric()->minValue(2000)->maxValue(2100)
                            ->default(now()->year)
                            ->required(),

                        Select::make('type')
                            ->label('Type')
                            ->options(BudgetPlannerOptions::VERSION_TYPES)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state) {
                                    $window = BudgetVersion::editableWindowFor($state);
                                    $set('editable_from_month', $window['from']);
                                    $set('editable_to_month', $window['to']);
                                }
                            }),
                    ]),

                    Grid::make(2)->schema([
                        Select::make('editable_from_month')
                            ->label('Editable from month')
                            ->options(array_combine(range(1, 12), range(1, 12)))
                            ->default(1)
                            ->required(),

                        Select::make('editable_to_month')
                            ->label('Editable to month')
                            ->options(array_combine(range(1, 12), range(1, 12)))
                            ->default(12)
                            ->required(),
                    ]),

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
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);

                    // Validate against an in-memory budget first — nothing is
                    // created unless the whole file is valid (all-or-nothing).
                    $probe = new BudgetVersion([
                        'type' => $data['type'],
                        'editable_from_month' => (int) $data['editable_from_month'],
                        'editable_to_month' => (int) $data['editable_to_month'],
                        'status' => 'DRAFT',
                    ]);

                    $result = $data['kind'] === 'expenses'
                        ? BudgetImportService::parseExpenses($path, $probe, $data['sheet_name'])
                        : BudgetImportService::parseInvestments($path, $probe, $data['sheet_name']);

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

                    if (empty($result['rows'])) {
                        Notification::make()->title('The selected sheet has no data rows.')->danger()->send();

                        return;
                    }

                    $year = BudgetYear::firstOrCreate(
                        ['year' => (int) $data['year']],
                        ['name' => "IT Budget {$data['year']}", 'status' => 'ACTIVE'],
                    );

                    $version = BudgetVersionService::createFromTemplate($year, $data['type'], null, [
                        'from' => (int) $data['editable_from_month'],
                        'to' => (int) $data['editable_to_month'],
                    ]);
                    $version->update(['name' => $data['name']]);

                    $count = $data['kind'] === 'expenses'
                        ? BudgetImportService::persistExpenses($result['rows'], $version)
                        : BudgetImportService::persistInvestments($result['rows'], $version, auth()->user());

                    Notification::make()->title("Budget \"{$data['name']}\" created with {$count} rows.")->success()->send();
                }),

            CreateAction::make()->label('New budget'),
        ];
    }
}
