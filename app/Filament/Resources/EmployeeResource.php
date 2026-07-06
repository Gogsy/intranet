<?php

namespace App\Filament\Resources;

use App\Concerns\AuthorizesViaPhoneBookPermission;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\EmployeeResource\Pages\EditEmployee;
use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers\PhoneNumbersRelationManager;
use App\Models\Employee;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeResource extends Resource
{
    use AuthorizesViaPhoneBookPermission;

    protected static ?string $model = Employee::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationLabel = 'Employees';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('full_name')->label('Full name')->required()->maxLength(255)
                ->columnSpanFull(),
            Select::make('department_id')->label('Department')
                ->relationship('department', 'name')->searchable()->preload()
                ->createOptionForm([TextInput::make('name')->required()]),
            Select::make('center_id')->label('Center')
                ->relationship('center', 'name')->searchable()->preload()
                ->createOptionForm([TextInput::make('name')->required()]),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('full_name')
            ->paginated([5, 10, 25, 50, 100, 'all'])
            ->columns([
                TextColumn::make('full_name')->label('Name')->searchable()->sortable(),
                TextColumn::make('department.name')->label('Department')->sortable()->placeholder('—'),
                TextColumn::make('center.name')->label('Center')->sortable()->placeholder('—'),
                TextColumn::make('phone_numbers_count')->counts('phoneNumbers')->label('Numbers')->badge(),
            ])
            ->filters([
                SelectFilter::make('department_id')->label('Department')->relationship('department', 'name'),
                SelectFilter::make('center_id')->label('Center')->relationship('center', 'name'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [PhoneNumbersRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmployees::route('/'),
            'create' => CreateEmployee::route('/create'),
            'edit' => EditEmployee::route('/{record}/edit'),
        ];
    }
}
