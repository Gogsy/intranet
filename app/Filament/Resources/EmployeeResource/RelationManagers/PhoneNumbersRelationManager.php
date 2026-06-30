<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PhoneNumbersRelationManager extends RelationManager
{
    protected static string $relationship = 'phoneNumbers';
    protected static ?string $recordTitleAttribute = 'number';
    protected static ?string $title = 'Phone numbers';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('number')->label('Phone number')->required()->maxLength(255),
            Forms\Components\TextInput::make('sim_card')->label('SIM card')->maxLength(255),
            Forms\Components\Select::make('operator_id')->label('Operator')
                ->relationship('operator', 'name')->searchable()->preload()
                ->createOptionForm([Forms\Components\TextInput::make('name')->required()]),
            Forms\Components\Select::make('number_type_id')->label('Type')
                ->relationship('numberType', 'name')->searchable()->preload()
                ->createOptionForm([Forms\Components\TextInput::make('name')->required()]),
            Forms\Components\Toggle::make('is_public')->label('Visible to everyone')->default(true)
                ->helperText('Off = hidden from the public directory.'),
            Forms\Components\Textarea::make('notes')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable(),
                Tables\Columns\TextColumn::make('operator.name')->label('Operator'),
                Tables\Columns\TextColumn::make('numberType.name')->label('Type')->badge(),
                Tables\Columns\IconColumn::make('is_public')->label('Public')->boolean(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('Add number')])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }
}
