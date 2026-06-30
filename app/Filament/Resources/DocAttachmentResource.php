<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocAttachmentResource\Pages;
use App\Models\DocAttachment;
use App\Models\DocNode;
use Filament\Forms;
use Filament\Forms\Components\{TextInput,Select,Toggle,Grid,FileUpload,Radio,Textarea};
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\{TextColumn,IconColumn};
use Filament\Tables\Table;

class DocAttachmentResource extends Resource
{
    protected static ?string $model = DocAttachment::class;
    protected static ?string $navigationIcon = 'heroicon-o-paper-clip';
    protected static ?string $navigationGroup = 'Documentation';
	protected static ?int $navigationSort = 20;
    protected static ?string $navigationLabel = 'Attachments';

    // Documents are managed per-section (Sections › Documents panel), so this
    // standalone global list is hidden from the sidebar. Set to true to restore
    // a cross-section "all documents" view.
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(12)->schema([
                Select::make('doc_node_id')
                    ->label('Sekcija')
                    ->options(DocNode::query()->pluck('title','id'))
                    ->searchable()->required()->columnSpan(6),

                TextInput::make('label')->label('Naziv')->required()->maxLength(255)->columnSpan(6),

                Radio::make('type')
                    ->options(['file'=>'Datoteka','url'=>'Vanjski link'])
                    ->inline()
                    ->default('file')
                    ->reactive()
                    ->columnSpan(6),

                FileUpload::make('file_path')
                    ->label('File (PDF, image, document…)')
                    ->disk('public')
                    ->directory('docs')
                    ->visibility('public')
                    // Unique stored name so same-named uploads don't overwrite each other.
                    ->getUploadedFileNameForStorageUsing(
                        fn ($file): string => DocNodeResource\RelationManagers\AttachmentsRelationManager::uniqueStoredName($file)
                    )
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/gif',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    ])
                    ->maxSize(20_000) // 20 MB
                    ->openable()
                    ->downloadable()
                    ->columnSpan(12)
                    ->hidden(fn($get) => $get('type') !== 'file'),

                TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->columnSpan(12)
                    ->hidden(fn($get) => $get('type') !== 'url'),

                Textarea::make('notes')->label('Notes (optional)')->rows(2)->columnSpan(12),

                TextInput::make('sort_order')->numeric()->default(0)->columnSpan(3),
                Toggle::make('is_active')->label('Active')->default(true)->columnSpan(3),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->toggleable(),
                TextColumn::make('node.title')->label('Sekcija')->searchable()->sortable(),
                TextColumn::make('label')->searchable(),
                TextColumn::make('kind')->label('Kind')->badge(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('doc_node_id')
                    ->label('Sekcija')
                    ->options(DocNode::pluck('title','id')->toArray()),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDocAttachments::route('/'),
            'create' => Pages\CreateDocAttachment::route('/create'),
            'edit'   => Pages\EditDocAttachment::route('/{record}/edit'),
        ];
    }
}
