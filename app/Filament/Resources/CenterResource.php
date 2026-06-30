<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\CenterResource\Pages;
use App\Models\Center;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CenterResource extends Resource
{
    use \App\Concerns\AuthorizesViaPhoneBookPermission;

    protected static ?string $model = Center::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationLabel = 'Centers';
    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Center')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('employees_count')->counts('employees')->label('Employees')->badge(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageCenters::route('/')];
    }
}
