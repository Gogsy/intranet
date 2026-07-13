<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    /** May mutate categories (the tab itself is reachable only with view_invoices). */
    protected function userCanManage(): bool
    {
        return auth()->user()?->can('manage_invoices') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule
                            ->where('supplier_id', $this->getOwnerRecord()->getKey()),
                    )
                    ->validationMessages([
                        'unique' => 'This supplier already has a category with this name.',
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('invoices_count')
                    ->label('Invoices')
                    ->counts('invoices'),
                TextColumn::make('budgets_count')
                    ->label('Budget rows')
                    ->counts('budgets'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => $this->userCanManage()),
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
