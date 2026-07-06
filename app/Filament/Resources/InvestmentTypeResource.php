<?php

namespace App\Filament\Resources;

use Illuminate\Database\Eloquent\Model;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\InvestmentTypeResource\Pages\ManageInvestmentTypes;
use App\Filament\Clusters\BudgetPlanner;
use App\Filament\Resources\InvestmentTypeResource\Pages;
use App\Models\InvestmentType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InvestmentTypeResource extends Resource
{
    protected static ?string $model = InvestmentType::class;
    protected static ?string $cluster = BudgetPlanner::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Investment Types';
    protected static ?int $navigationSort = 20;

    /** Lookup table maintenance is owner-tier — `manage_budget` only. */
    protected static function userCanManage(): bool
    {
        return auth()->user()?->can('manage_budget') ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::userCanManage();
    }

    public static function canView(Model $record): bool
    {
        return static::userCanManage();
    }

    public static function canCreate(): bool
    {
        return static::userCanManage();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCanManage();
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCanManage();
    }

    public static function canDeleteAny(): bool
    {
        return static::userCanManage();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(255)
                ->helperText('e.g. Hardware, Computer Software, Education'),
            TextInput::make('sort_order')->label('Sort order')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('Sort order')->sortable(),
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('investment_items_count')->counts('investmentItems')->label('Investments')->badge(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInvestmentTypes::route('/')];
    }
}
