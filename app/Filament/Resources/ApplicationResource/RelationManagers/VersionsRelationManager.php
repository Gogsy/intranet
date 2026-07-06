<?php

namespace App\Filament\Resources\ApplicationResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Models\Application;
use App\Support\NesyVersionFetcher;
use Filament\Forms;
use Filament\Forms\Components\{TextInput, FileUpload, Textarea};
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\{TextColumn, ToggleColumn};

class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';
    protected static ?string $recordTitleAttribute = 'file_name';
    protected static ?string $title = 'Versions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('file_path')
                ->label('APK file')
                ->disk('public')
                ->directory('apps/manual')
                ->preserveFilenames()
                ->required()
                ->acceptedFileTypes(['application/vnd.android.package-archive', 'application/octet-stream'])
                ->maxSize(307200) // 300 MB
                ->downloadable()
                ->openable()
                ->helperText('The uploaded filename becomes the version name.')
                ->columnSpanFull(),

            TextInput::make('version_number')
                ->label('Build number')
                ->required()
                ->maxLength(255)
                ->helperText('e.g. 260 or 1.4.2'),

            Textarea::make('notes')->label('Notes (optional)')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Versions')
            ->description('Each row is a stored copy of an app build. Tick "Active" to choose which one everyone downloads from this app’s permanent link — switching or rolling back is instant, and old builds are kept here. "Fetch latest from Nesy" pulls the newest build from the Nesy server; "Add version" uploads one yourself.')
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No versions yet')
            ->emptyStateDescription('Use "Fetch latest from Nesy" to pull the current build, or "Add version" to upload an APK.')
            ->columns([
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->updateStateUsing(function ($record, bool $state) {
                        // Ticking a version makes it the single active one.
                        // One version must always stay active, so un-ticking the
                        // active row is ignored (it stays on).
                        if ($state) {
                            $record->activate();
                        }
                        return (bool) $record->refresh()->is_active;
                    }),

                TextColumn::make('file_name')->label('Name')->searchable()->wrap(),

                TextColumn::make('version_number')->label('Build')->badge(),

                TextColumn::make('source')->label('Source')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'api' ? 'Nesy' : 'Upload')
                    ->color(fn (string $state) => $state === 'api' ? 'success' : 'gray'),

                TextColumn::make('size_for_humans')->label('Size'),

                TextColumn::make('created_at')->label('Added')->dateTime('d.m.Y H:i')
                    ->sortable()->toggleable(),
            ])
            ->headerActions([
                Action::make('fetchNesy')
                    ->label('Fetch latest from Nesy')
                    ->icon('heroicon-o-cloud-arrow-down')
                    ->color('success')
                    ->visible(fn () => $this->getOwnerRecord()->update_provider === 'nesy')
                    ->requiresConfirmation()
                    ->modalDescription('Calls the Nesy API, downloads the latest APK, and adds it to this list (it does not go live until you tick Active).')
                    ->action(function () {
                        /** @var Application $app */
                        $app = $this->getOwnerRecord();
                        $result = app(NesyVersionFetcher::class)->fetchLatest($app);

                        Notification::make()
                            ->title($result['ok'] ? 'Nesy fetch' : 'Nesy fetch failed')
                            ->body($result['message'])
                            ->status($result['ok'] ? 'success' : 'danger')
                            ->send();
                    }),

                CreateAction::make()->label('Add version'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->file_url, shouldOpenInNewTab: true)
                    ->visible(fn ($record) => filled($record->file_url)),

                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Deletes this version and its APK file. You cannot delete the active version.')
                    ->hidden(fn ($record) => $record->is_active),
            ])
            ->toolbarActions([]);
    }
}
