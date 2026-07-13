<?php

namespace App\Filament\Resources;

use App\Filament\Clusters\InvoiceTracker;
use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Resources\InvoiceResource\Pages\EditInvoice;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\Invoice;
use App\Models\SupplierCategory;
use App\Support\InvoiceTracker\Months;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;
    protected static ?string $cluster = InvoiceTracker::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $recordTitleAttribute = 'sap_reference';
    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_invoices') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('manage_invoices') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDeleteAny(): bool
    {
        return static::canCreate();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Invoice entry')
                ->description('Record an invoice you approved in SAP.')
                ->columns(2)
                ->components([
                    Select::make('supplier_id')
                        ->relationship('supplier', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),
                    Select::make('category_id')
                        ->label('Category')
                        ->options(fn (Get $get): array => $get('supplier_id')
                            ? SupplierCategory::query()
                                ->where('supplier_id', $get('supplier_id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                            : [])
                        ->placeholder('Uncategorized (ad hoc)')
                        ->disabled(fn (Get $get): bool => blank($get('supplier_id')))
                        ->helperText(fn (Get $get): ?string => blank($get('supplier_id')) ? 'Select a supplier first.' : null)
                        ->createOptionForm([
                            TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                        ])
                        ->createOptionUsing(fn (array $data, Get $get): int => SupplierCategory::firstOrCreate([
                            'supplier_id' => $get('supplier_id'),
                            'name' => $data['name'],
                        ])->getKey()),
                    Select::make('month')
                        ->options(Months::options())
                        ->default(now()->month)
                        ->required(),
                    TextInput::make('year')
                        ->numeric()
                        ->integer()
                        ->default(now()->year)
                        ->minValue(2000)
                        ->maxValue(2100)
                        ->required(),
                    TextInput::make('amount')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('EUR')
                        ->required(),
                    Toggle::make('includes_vat')
                        ->label('Amount includes VAT (25%)')
                        ->helperText('Turn off if you are entering a net amount — VAT will be added on save.')
                        ->default(true)
                        ->visibleOn('create'),
                    TextInput::make('sap_reference')
                        ->label('SAP reference')
                        ->maxLength(255),
                    Textarea::make('note')
                        ->rows(3)
                        ->columnSpanFull(),
                    FileUpload::make('attachments')
                        ->label('Invoice files (PDF/scan)')
                        ->multiple()
                        ->disk(Invoice::ATTACHMENTS_DISK)
                        ->directory('invoices')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(20480) // 20 MB per file
                        ->downloadable()
                        ->openable()
                        ->reorderable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('supplier.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->badge()
                    ->placeholder('Uncategorized')
                    ->sortable(),
                TextColumn::make('year')
                    ->sortable(),
                TextColumn::make('month')
                    ->formatStateUsing(fn (int $state): string => Months::name($state))
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable()
                    ->summarize(Sum::make()->money('EUR')),
                TextColumn::make('attachments')
                    ->label('Files')
                    ->state(fn (Invoice $record) => count($record->attachments ?? []) ?: null)
                    ->icon('heroicon-o-paper-clip')
                    ->placeholder('—')
                    ->tooltip('Attached files — open the invoice to view/download them.'),
                TextColumn::make('sap_reference')
                    ->label('SAP reference')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Entered at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('supplier.name')
                    ->label('Supplier'),
                Group::make('category.name')
                    ->label('Category'),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->options(fn (): array => array_combine($years = range(now()->year, 2024), $years)),
                SelectFilter::make('month')
                    ->options(Months::options()),
                SelectFilter::make('supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->getOptionLabelFromRecordUsing(fn (SupplierCategory $record): string => "{$record->supplier->name} — {$record->name}")
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
