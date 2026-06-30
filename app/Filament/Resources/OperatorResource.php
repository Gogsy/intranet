<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\OperatorResource\Pages;
use App\Models\Operator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OperatorResource extends Resource
{
    use \App\Concerns\AuthorizesViaPhoneBookPermission;

    protected static ?string $model = Operator::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationLabel = 'Operators';
    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Operator')->required()->maxLength(255),
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
        return ['index' => Pages\ManageOperators::route('/')];
    }
}
