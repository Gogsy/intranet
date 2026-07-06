<?php

namespace App\Filament\Resources;

use App\Concerns\AuthorizesViaPhoneBookPermission;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\CenterResource\Pages\ManageCenters;
use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\CenterResource\Pages;
use App\Models\Center;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CenterResource extends Resource
{
    use AuthorizesViaPhoneBookPermission;

    protected static ?string $model = Center::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Centers';
    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Center')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('employees_count')->counts('employees')->label('Employees')->badge(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCenters::route('/')];
    }
}
