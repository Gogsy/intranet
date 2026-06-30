<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    use \App\Concerns\AuthorizesViaPhoneBookPermission;

    protected static ?string $model = Department::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Departments';
    protected static ?int $navigationSort = 50;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Department')->required()->maxLength(255),
            Forms\Components\Toggle::make('is_public')->label('Visible in public directory')->default(true)
                ->helperText('Off = hide the WHOLE department from the public imenik: none of its numbers are shown to anonymous visitors (only to logged-in Managers/Finance/admins).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_public')->label('Public')->boolean(),
                Tables\Columns\TextColumn::make('employees_count')->counts('employees')->label('Employees')->badge(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_public')->label('Visibility')
                    ->placeholder('All')->trueLabel('Public')->falseLabel('Hidden'),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageDepartments::route('/')];
    }
}
