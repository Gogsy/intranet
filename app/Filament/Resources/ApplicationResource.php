<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use App\Support\NesyVersionFetcher;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ApplicationResource\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\ApplicationResource\Pages\ListApplications;
use App\Filament\Resources\ApplicationResource\Pages\CreateApplication;
use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Filament\Resources\ApplicationResource\Pages;
use App\Models\Application;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Applications';
    protected static ?int $navigationSort = 20;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'App Downloads';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Basic')->schema([
                TextInput::make('name')
                    ->label('App Name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('sort_order')
                    ->label('Sort order')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999)
                    ->default(0)
                    ->helperText('Lower number = earlier in the list.'),

                Toggle::make('is_visible')
                    ->label('Visible')
                    ->default(true)
                    ->inline(false)
                    ->helperText('Shown on the public Apps page.'),
            ])->columns(3),

            Grid::make(3)->schema([
                Section::make('Icon')->schema([
                    FileUpload::make('icon')
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
                ])->columnSpan(1),

                Section::make('Manuals (PDF)')->schema([
                    Grid::make(2)->schema([
                        FileUpload::make('pdf_installation_instructions')
                            ->label('PDF Installation Instructions')
                            ->disk('public')
                            ->directory('apps/manuals')
                            ->preserveFilenames()
                            ->openable()
                            ->downloadable()
                            ->acceptedFileTypes(['application/pdf']),

                        FileUpload::make('pdf_user_manual')
                            ->label('PDF User Manual')
                            ->disk('public')
                            ->directory('apps/manuals')
                            ->preserveFilenames()
                            ->openable()
                            ->downloadable()
                            ->acceptedFileTypes(['application/pdf']),
                    ]),
                ])->columnSpan(2),
            ]),

            Section::make('Version source')
                ->description('How this app’s APK builds get into the Versions panel below.')
                ->schema([
                    Toggle::make('update_provider')
                        ->label('This is a Nesy app — fetch builds from the Nesy API')
                        ->helperText('ON: adds a "Fetch latest from Nesy" button to the Versions panel (manual click — nothing automatic). OFF: you upload APKs yourself in the Versions panel.')
                        ->live()
                        ->dehydrateStateUsing(fn ($state) => $state ? 'nesy' : null)
                        ->columnSpanFull(),

                    TextInput::make('update_app_name')
                        ->label('Nesy app identifier (name)')
                        ->placeholder('Nesy-Mobile-Prod')
                        ->default('Nesy-Mobile-Prod')
                        ->visible(fn (Get $get) => (bool) $get('update_provider'))
                        ->helperText('The name the Nesy build server uses for this app — sent to the API to fetch its builds. Leave as "Nesy-Mobile-Prod" unless you are pulling a different Nesy build (e.g. a test channel).'),

                    TextInput::make('update_endpoint')
                        ->label('Update API endpoint (link)')
                        ->url()
                        ->placeholder(NesyVersionFetcher::ENDPOINT)
                        ->visible(fn (Get $get) => (bool) $get('update_provider'))
                        ->helperText('The API the build name is sent to. Leave blank to use the default Overseas endpoint — set this only when hosting for another company with its own update server.'),

                    Toggle::make('live_download')
                        ->label('Live download (always fetch the latest from the API on click)')
                        ->helperText('ON: the public Download button asks the API for the newest build every time and redirects to it — no APK is stored here. OFF: serve the APK you keep in the Versions panel below.')
                        ->visible(fn (Get $get) => (bool) $get('update_provider'))
                        ->columnSpanFull(),
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
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('is_visible')->label('Visible')->boolean(),
                TextColumn::make('sort_order')->label('Order')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
        return [
            VersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApplications::route('/'),
            'create' => CreateApplication::route('/create'),
            'edit' => EditApplication::route('/{record}/edit'),
        ];
    }
}
