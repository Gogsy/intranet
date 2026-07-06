<?php

namespace App\Filament\Resources;

use App\Concerns\AuthorizesViaPhoneBookPermission;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\NumberTypeResource\Pages\ManageNumberTypes;
use App\Filament\Clusters\PhoneBook;
use App\Filament\Resources\NumberTypeResource\Pages;
use App\Models\NumberType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NumberTypeResource extends Resource
{
    use AuthorizesViaPhoneBookPermission;

    protected static ?string $model = NumberType::class;
    protected static ?string $cluster = PhoneBook::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Number Types';
    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Type')->required()->maxLength(255)
                ->helperText('e.g. Mobile, Desk, Fax'),
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
        return ['index' => ManageNumberTypes::route('/')];
    }
}
