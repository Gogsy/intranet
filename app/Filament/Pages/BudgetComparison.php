<?php

namespace App\Filament\Pages;

use Filament\Support\Enums\Width;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Clusters\BudgetPlanner;
use App\Models\BudgetVersion;
use App\Services\BudgetComparisonService;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class BudgetComparison extends Page implements HasForms
{
    use InteractsWithForms;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_budget') ?? false;
    }

    protected static ?string $cluster = BudgetPlanner::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $title = 'Budget comparison';
    protected string $view = 'filament.pages.budget-comparison';

    // Reached by selecting two budgets in the Budgets list and clicking
    // "Compare selected" — not a standalone menu destination.
    protected static bool $shouldRegisterNavigation = false;

    protected Width|string|null $maxContentWidth = 'full';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'old_version_id' => request()->integer('old') ?: null,
            'new_version_id' => request()->integer('new') ?: null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('old_version_id')
                    ->label('Old budget')
                    ->options(fn () => $this->versionOptions())
                    ->searchable()
                    ->live(),

                Select::make('new_version_id')
                    ->label('New budget')
                    ->options(fn () => $this->versionOptions())
                    ->searchable()
                    ->live(),
            ]),

            Grid::make(4)->schema([
                Select::make('category_filter')
                    ->label('Category')
                    ->options(fn (Get $get) => $this->optionsFor($get, 'category'))
                    ->live(),

                Select::make('vendor_filter')
                    ->label('Vendor')
                    ->options(fn (Get $get) => $this->optionsFor($get, 'vendor'))
                    ->live(),

                Select::make('account_code_filter')
                    ->label('Account')
                    ->options(fn (Get $get) => $this->optionsFor($get, 'account_code'))
                    ->live(),

                Select::make('month_filter')
                    ->label('Month')
                    ->options(array_combine(range(1, 12), range(1, 12)))
                    ->live(),
            ]),
        ])->statePath('data');
    }

    protected function versionOptions(): array
    {
        return BudgetVersion::with('budgetYear')->get()
            ->mapWithKeys(fn (BudgetVersion $v) => [$v->id => "{$v->budgetYear->year} — {$v->name}"])
            ->all();
    }

    /** Distinct, non-null values for a comparison-row field, scoped to the currently selected versions. */
    protected function optionsFor(Get $get, string $field): array
    {
        $rows = $this->comparisonRowsFor($get('old_version_id'), $get('new_version_id'));

        $values = $rows->pluck($field)->filter()->unique()->sort()->values()->all();

        return array_combine($values, $values);
    }

    protected function comparisonRowsFor(?int $oldId, ?int $newId): Collection
    {
        if (! $oldId || ! $newId) {
            return collect();
        }

        $old = BudgetVersion::find($oldId);
        $new = BudgetVersion::find($newId);

        if (! $old || ! $new) {
            return collect();
        }

        return BudgetComparisonService::compare($old, $new);
    }

    /** The filtered comparison rows for the current form state — called from the Blade view. */
    public function getRows(): Collection
    {
        $state = $this->form->getState();

        return $this->comparisonRowsFor($state['old_version_id'] ?? null, $state['new_version_id'] ?? null)
            ->when($state['category_filter'] ?? null, fn ($rows, $value) => $rows->where('category', $value))
            ->when($state['vendor_filter'] ?? null, fn ($rows, $value) => $rows->where('vendor', $value))
            ->when($state['account_code_filter'] ?? null, fn ($rows, $value) => $rows->where('account_code', $value))
            ->when($state['month_filter'] ?? null, fn ($rows, $value) => $rows->where('month', (int) $value))
            ->values();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'added' => 'Added',
            'removed' => 'Removed',
            'changed' => 'Changed',
            default => 'Unchanged',
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'added' => 'success',
            'removed' => 'danger',
            'changed' => 'warning',
            default => 'gray',
        };
    }
}
