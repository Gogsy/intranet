<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolResource\Pages;
use App\Models\Tool;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Applications';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Web Tools Portal';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Tool Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('url')
                    ->label('Tool URL')
                    ->url()
                    ->required()
                    ->placeholder('https://intranet.company.local/my-tool'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999)
                    ->default(0)
                    ->helperText('Lower number = earlier in the list.'),
            ])->columns(3),

            Forms\Components\Section::make('Icon')->schema([
                Forms\Components\FileUpload::make('icon')
                    ->label('Icon')
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
            ]),

            Forms\Components\Section::make('Visibility')->schema([
                Forms\Components\Toggle::make('is_visible')
                    ->label('Is Visible')
                    ->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order', 'asc')
            ->columns([
                Tables\Columns\ImageColumn::make('icon')
                    ->label('Icon')
                    ->getStateUsing(fn ($record) => $record->icon_url)
                    ->circular(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('url')
                    ->label('Link')
                    ->limit(48),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index'  => Pages\ListTools::route('/'),
            'create' => Pages\CreateTool::route('/create'),
            'edit'   => Pages\EditTool::route('/{record}/edit'),
        ];
    }
}
