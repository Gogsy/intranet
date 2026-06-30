<?php

namespace App\Filament\Resources\DocNodeResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Components\{TextInput, Radio, FileUpload, Toggle, Textarea};
use Filament\Tables\Columns\{TextColumn, ImageColumn, ToggleColumn};

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';
    protected static ?string $recordTitleAttribute = 'label';
    protected static ?string $title = 'Documents';

    /** Allowed upload formats: documents + images (logos). */
    protected static array $acceptedFileTypes = [
        'application/pdf',
        'image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /** Readable-but-unique storage name: "<slug>-<6hex>.<ext>" (avoids overwrites). */
    public static function uniqueStoredName(\Illuminate\Http\UploadedFile $file): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext  = $file->getClientOriginalExtension();
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base);

        return strtolower($safe) . '-' . substr(md5(uniqid('', true)), 0, 6) . ($ext ? '.' . $ext : '');
    }

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            TextInput::make('label')->label('Title')->required()->maxLength(255)->columnSpanFull(),

            Radio::make('type')
                ->label('Type')
                ->options(['file' => 'File (PDF, image, document…)', 'url' => 'External link'])
                ->default('file')
                ->inline()
                ->live(),

            FileUpload::make('file_path')
                ->label('File')
                ->disk('public')
                ->directory('docs')
                ->visibility('public')
                // Unique stored name (keep a readable slug + short hash) so two
                // uploads sharing an original filename don't overwrite each other.
                ->getUploadedFileNameForStorageUsing(fn ($file): string => self::uniqueStoredName($file))
                ->acceptedFileTypes(self::$acceptedFileTypes)
                ->maxSize(20_000) // 20 MB
                ->openable()
                ->downloadable()
                ->helperText('PDF, images (logos), Word/Excel/PowerPoint. Max 20 MB.')
                ->hidden(fn ($get) => $get('type') === 'url')
                ->columnSpanFull(),

            TextInput::make('url')
                ->label('URL')
                ->url()
                ->hidden(fn ($get) => $get('type') !== 'url')
                ->columnSpanFull(),

            Textarea::make('notes')->label('Notes (optional)')->rows(2)->columnSpanFull(),

            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->label('Visible')->default(true),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Preview')
                    ->disk('public')
                    ->visibility('public')
                    ->getStateUsing(fn ($record) => $record->is_image ? $record->file_path : null)
                    ->height(36),
                TextColumn::make('label')->searchable()->sortable(),
                TextColumn::make('kind')->label('Kind')->badge(),
                ToggleColumn::make('is_active')->label('Visible'),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
