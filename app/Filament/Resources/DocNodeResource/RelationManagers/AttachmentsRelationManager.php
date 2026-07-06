<?php

namespace App\Filament\Resources\DocNodeResource\RelationManagers;

use Illuminate\Http\UploadedFile;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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

    /**
     * Uploads accept ANY file type (fonts, templates, archives, images, docs…)
     * EXCEPT scripts/executables — those could run on the server or a visitor's
     * machine, so they are hard-blocked by extension. EXE/APK installers belong
     * in App Downloads, not here.
     */
    public static array $blockedExtensions = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'exe', 'msi', 'dll', 'com', 'scr', 'pif', 'apk',
        'bat', 'cmd', 'ps1', 'psm1', 'vbs', 'vbe', 'wsf', 'wsh',
        'sh', 'bash', 'cgi', 'pl', 'py', 'rb',
        'js', 'mjs', 'jar', 'htaccess', 'html', 'htm',
    ];

    /** Validation rule blocking script/executable uploads; everything else passes. */
    public static function blockScriptsRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail): void {
            $name = $value instanceof UploadedFile ? $value->getClientOriginalName() : (string) $value;
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if (in_array($ext, self::$blockedExtensions, true)) {
                $fail("Datoteke tipa .{$ext} nisu dozvoljene (skripte i izvršne datoteke su blokirane).");
            }
        };
    }

    public const UPLOAD_HELPER_TEXT = 'Bilo koji tip datoteke — PDF, slike, fontovi (TTF/OTF/WOFF), memorandumi i predlošci (Word/Excel/PowerPoint), arhive (ZIP/RAR)… Skripte i izvršne datoteke (.exe, .bat, .php…) su blokirane. Max 500 MB.';

    /** Readable-but-unique storage name: "<slug>-<6hex>.<ext>" (avoids overwrites). */
    public static function uniqueStoredName(UploadedFile $file): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext  = $file->getClientOriginalExtension();
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base);

        return strtolower($safe) . '-' . substr(md5(uniqid('', true)), 0, 6) . ($ext ? '.' . $ext : '');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
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
                ->rules([self::blockScriptsRule()])
                ->maxSize(512_000) // 500 MB — u skladu s Livewire/PHP/nginx limitima
                ->openable()
                ->downloadable()
                ->helperText(self::UPLOAD_HELPER_TEXT)
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

    public function table(Table $table): Table
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
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
