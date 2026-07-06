<?php

namespace App\Filament\Resources;

use App\Concerns\AuthorizesViaPhoneBookPermission;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\OperatorResource\Pages\ManageOperators;
use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\OperatorResource\Pages;
use App\Models\Operator;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OperatorResource extends Resource
{
    use AuthorizesViaPhoneBookPermission;

    protected static ?string $model = Operator::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationLabel = 'Operators';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Operator')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone_numbers_count')->counts('phoneNumbers')->label('Numbers')->badge(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOperators::route('/')];
    }
}
