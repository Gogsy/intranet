<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApplicationResource\Pages;
use App\Models\Application;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;
    protected static ?string $navigationGroup = 'Applications';
    protected static ?int $navigationSort = 20;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'App Downloads';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('App Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999)
                    ->default(0)
                    ->helperText('Lower number = earlier in the list.'),
            ])->columns(2),

            Forms\Components\Section::make('Icon')->schema([
                Forms\Components\FileUpload::make('icon')
                    ->label('Icon')
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory('images/icons')
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

            Forms\Components\Section::make('Manuals (PDF)')->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\FileUpload::make('pdf_installation_instructions')
                        ->label('PDF Installation Instructions')
                        ->disk('public')
                        ->directory('apps/manuals')
                        ->preserveFilenames()
                        ->openable()
                        ->downloadable()
                        ->acceptedFileTypes(['application/pdf']),

                    Forms\Components\FileUpload::make('pdf_user_manual')
                        ->label('PDF User Manual')
                        ->disk('public')
                        ->directory('apps/manuals')
                        ->preserveFilenames()
                        ->openable()
                        ->downloadable()
                        ->acceptedFileTypes(['application/pdf']),
                ]),
            ]),

            Forms\Components\Section::make('Version source')
                ->description('How this app’s APK builds get into the Versions panel below.')
                ->schema([
                    Forms\Components\Toggle::make('update_provider')
                        ->label('This is a Nesy app — fetch builds from the Nesy API')
                        ->helperText('ON: adds a "Fetch latest from Nesy" button to the Versions panel (manual click — nothing automatic). OFF: you upload APKs yourself in the Versions panel.')
                        ->live()
                        ->formatStateUsing(fn ($state) => $state === 'nesy')
                        ->dehydrateStateUsing(fn ($state) => $state ? 'nesy' : null)
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('update_app_name')
                        ->label('Nesy app identifier (name)')
                        ->placeholder('Nesy-Mobile-Prod')
                        ->default('Nesy-Mobile-Prod')
                        ->visible(fn (Forms\Get $get) => (bool) $get('update_provider'))
                        ->helperText('The name the Nesy build server uses for this app — sent to the API to fetch its builds. Leave as "Nesy-Mobile-Prod" unless you are pulling a different Nesy build (e.g. a test channel).'),

                    Forms\Components\TextInput::make('update_endpoint')
                        ->label('Update API endpoint (link)')
                        ->url()
                        ->placeholder(\App\Support\NesyVersionFetcher::ENDPOINT)
                        ->visible(fn (Forms\Get $get) => (bool) $get('update_provider'))
                        ->helperText('The API the build name is sent to. Leave blank to use the default Overseas endpoint — set this only when hosting for another company with its own update server.'),

                    Forms\Components\Toggle::make('live_download')
                        ->label('Live download (always fetch the latest from the API on click)')
                        ->helperText('ON: the public Download button asks the API for the newest build every time and redirects to it — no APK is stored here. OFF: serve the APK you keep in the Versions panel below.')
                        ->visible(fn (Forms\Get $get) => (bool) $get('update_provider'))
                        ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('is_visible')->label('Visible')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->label('Order')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
        return [
            \App\Filament\Resources\ApplicationResource\RelationManagers\VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplications::route('/'),
            'create' => Pages\CreateApplication::route('/create'),
            'edit' => Pages\EditApplication::route('/{record}/edit'),
        ];
    }
}
