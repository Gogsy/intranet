<?php

namespace App\Filament\Resources;

use App\Concerns\AuthorizesViaPhoneBookPermission;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DepartmentResource\Pages\ManageDepartments;
use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    use AuthorizesViaPhoneBookPermission;

    protected static ?string $model = Department::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Departments';
    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Department')->required()->maxLength(255),
            Toggle::make('is_public')->label('Visible in public directory')->default(true)
                ->helperText('Off = hide the WHOLE department from the public imenik: none of its numbers are shown to anonymous visitors (only to logged-in Managers/Finance/admins).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('is_public')->label('Public')->boolean(),
                TextColumn::make('employees_count')->counts('employees')->label('Employees')->badge(),
            ])
            ->filters([
                TernaryFilter::make('is_public')->label('Visibility')
                    ->placeholder('All')->trueLabel('Public')->falseLabel('Hidden'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDepartments::route('/')];
    }
}
