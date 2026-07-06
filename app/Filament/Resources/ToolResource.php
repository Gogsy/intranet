<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ToolResource\Pages\ListTools;
use App\Filament\Resources\ToolResource\Pages\CreateTool;
use App\Filament\Resources\ToolResource\Pages\EditTool;
use App\Filament\Resources\ToolResource\Pages;
use App\Models\Tool;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-link';
    protected static string | \UnitEnum | null $navigationGroup = 'Applications';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Web Tools Portal';

    public static function form(Schema $schema): Schema
    {
        // One component per row — without this the root schema packs the
        // sections into a multi-column grid and the form looks crammed.
        return $schema->columns(1)->components([
            Section::make('Basic')->schema([
                TextInput::make('name')
                    ->label('Tool Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('url')
                    ->label('Tool URL')
                    ->url()
                    ->required()
                    ->placeholder('https://intranet.company.local/my-tool'),

                TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999)
                    ->default(0)
                    ->helperText('Lower number = earlier in the list.'),
            ])->columns(3),

            Grid::make(3)->schema([
                Section::make('Icon')->schema([
                    FileUpload::make('icon')
                        ->hiddenLabel()
                        ->image()
                        ->imageEditor()
                        ->disk('public')
                        ->directory('images/icons/tool_icons')
                        ->visibility('public')
                        ->imagePreviewHeight('90')
                        ->maxSize(2048)
                        ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/jpeg', 'image/webp'])
                        ->getUploadedFileNameForStorageUsing(function ($file): string {
                            $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                            $ext  = $file->getClientOriginalExtension();
                            $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base);
                            return strtolower($safe) . '-' . substr(md5(uniqid('', true)), 0, 6) . '.' . $ext;
                        })
                        ->helperText('Upload a PNG/SVG/JPG/WebP. Preview appears immediately; the remove (×) button deletes it.'),
                ])->columnSpan(2),

                Section::make('Visibility')->schema([
                    Toggle::make('is_visible')
                        ->label('Is Visible')
                        ->default(true)
                        ->helperText('Shown on the public Web Tools page.'),
                ])->columnSpan(1),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                ImageColumn::make('icon')
                    ->label('Icon')
                    ->getStateUsing(fn ($record) => $record->icon_url)
                    ->circular(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('url')
                    ->label('Link')
                    ->limit(48),

                IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTools::route('/'),
            'create' => CreateTool::route('/create'),
            'edit'   => EditTool::route('/{record}/edit'),
        ];
    }
}
