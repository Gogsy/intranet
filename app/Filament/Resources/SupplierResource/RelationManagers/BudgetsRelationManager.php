<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Models\SupplierBudget;
use App\Support\InvoiceTracker\Months;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/** Planned amounts per supplier/category month, entered manually here. */
class BudgetsRelationManager extends RelationManager
{
    protected static string $relationship = 'budgets';

    protected function userCanManage(): bool
    {
        return auth()->user()?->can('manage_invoices') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => $this->getOwnerRecord()->categories()->orderBy('name')->pluck('name', 'id')->all())
                    ->placeholder('Uncategorized (ad hoc)')
                    ->live(),
                TextInput::make('year')
                    ->numeric()
                    ->integer()
                    ->default(now()->year)
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->required()
                    ->live(),
                Select::make('month')
                    ->options(Months::options())
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('supplier_id', $this->getOwnerRecord()->getKey())
                            ->where('year', $get('year'))
                            ->where('category_id', $get('category_id')),
                    )
                    ->validationMessages([
                        'unique' => 'A budget for this category, month and year already exists.',
                    ]),
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('EUR')
                    ->required(),
                TextInput::make('note')
                    ->label('Short description')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('month')
            ->columns([
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('month')
                    ->formatStateUsing(fn (int $state): string => Months::name($state))
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->placeholder('Uncategorized')
                    ->badge()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('EUR')
                    ->summarize(Sum::make()->money('EUR')),
                TextColumn::make('note')
                    ->label('Description')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->options(fn (): array => array_combine($years = range(now()->year + 1, 2024), $years))
                    ->default(now()->year),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => $this->getOwnerRecord()->categories()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => $this->userCanManage()),
                Action::make('fillYear')
                    ->label('Fill whole year')
                    ->icon('heroicon-o-calendar')
                    ->visible(fn () => $this->userCanManage())
                    ->schema([
                        Select::make('category_id')
                            ->label('Category')
                            ->options(fn (): array => $this->getOwnerRecord()->categories()->orderBy('name')->pluck('name', 'id')->all())
                            ->placeholder('Uncategorized (ad hoc)'),
                        TextInput::make('year')
                            ->numeric()
                            ->integer()
                            ->default(now()->year)
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->required(),
                        TextInput::make('amount')
                            ->label('Monthly amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('EUR')
                            ->required(),
                        TextInput::make('note')
                            ->label('Short description')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        $supplier = $this->getOwnerRecord();
                        $created = 0;

                        foreach (range(1, 12) as $month) {
                            $budget = SupplierBudget::firstOrCreate(
                                [
                                    'supplier_id' => $supplier->getKey(),
                                    'category_id' => $data['category_id'] ?: null,
                                    'year' => (int) $data['year'],
                                    'month' => $month,
                                ],
                                [
                                    'amount' => $data['amount'],
                                    'note' => $data['note'] ?? null,
                                ],
                            );

                            if ($budget->wasRecentlyCreated) {
                                $created++;
                            }
                        }

                        Notification::make()
                            ->title("Created {$created} monthly budget(s) for {$data['year']}. Existing months were left unchanged.")
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => $this->userCanManage()),
                DeleteAction::make()
                    ->visible(fn () => $this->userCanManage()),
            ])
            ->toolbarActions($this->userCanManage() ? [
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ] : []);
    }
}
