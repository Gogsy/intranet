<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\NumberTypeResource\Pages;
use App\Models\NumberType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NumberTypeResource extends Resource
{
    use \App\Concerns\AuthorizesViaPhoneBookPermission;

    protected static ?string $model = NumberType::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Number Types';
    protected static ?int $navigationSort = 40;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Type')->required()->maxLength(255)
                ->helperText('e.g. Mobile, Desk, Fax'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone_numbers_count')->counts('phoneNumbers')->label('Numbers')->badge(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNumberTypes::route('/')];
    }
}
