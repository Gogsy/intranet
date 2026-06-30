<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers\PhoneNumbersRelationManager;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    use \App\Concerns\AuthorizesViaPhoneBookPermission;

    protected static ?string $model = Employee::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Employees';
    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('full_name')->label('Full name')->required()->maxLength(255)
                ->columnSpanFull(),
            Forms\Components\Select::make('department_id')->label('Department')
                ->relationship('department', 'name')->searchable()->preload()
                ->createOptionForm([Forms\Components\TextInput::make('name')->required()]),
            Forms\Components\Select::make('center_id')->label('Center')
                ->relationship('center', 'name')->searchable()->preload()
                ->createOptionForm([Forms\Components\TextInput::make('name')->required()]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('full_name')
            ->columns([
                Tables\Columns\TextColumn::make('full_name')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('department.name')->label('Department')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('center.name')->label('Center')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('phone_numbers_count')->counts('phoneNumbers')->label('Numbers')->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')->label('Department')->relationship('department', 'name'),
                Tables\Filters\SelectFilter::make('center_id')->label('Center')->relationship('center', 'name'),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [PhoneNumbersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
