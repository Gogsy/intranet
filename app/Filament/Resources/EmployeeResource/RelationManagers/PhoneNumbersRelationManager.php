<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PhoneNumbersRelationManager extends RelationManager
{
    protected static string $relationship = 'phoneNumbers';
    protected static ?string $recordTitleAttribute = 'number';
    protected static ?string $title = 'Phone numbers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->label('Phone number')->required()->maxLength(255),
            TextInput::make('sim_card')->label('SIM card')->maxLength(255),
            Select::make('operator_id')->label('Operator')
                ->relationship('operator', 'name')->searchable()->preload()
                ->createOptionForm([TextInput::make('name')->required()]),
            Select::make('number_type_id')->label('Type')
                ->relationship('numberType', 'name')->searchable()->preload()
                ->createOptionForm([TextInput::make('name')->required()]),
            Toggle::make('is_public')->label('Visible to everyone')->default(true)
                ->helperText('Off = hidden from the public directory.'),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('operator.name')->label('Operator'),
                TextColumn::make('numberType.name')->label('Type')->badge(),
                IconColumn::make('is_public')->label('Public')->boolean(),
            ])
            ->headerActions([CreateAction::make()->label('Add number')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }
}
