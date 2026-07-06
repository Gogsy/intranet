<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DocNodeResource\Pages\ListDocNodes;
use App\Filament\Resources\DocNodeResource\Pages\CreateDocNode;
use App\Filament\Resources\DocNodeResource\Pages\EditDocNode;
use App\Filament\Resources\DocNodeResource\Pages;
use App\Filament\Resources\DocNodeResource\RelationManagers\AttachmentsRelationManager;
use App\Models\DocNode;
use Filament\Forms\Components\{TextInput, Textarea, Select, Toggle, Grid};
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\{TextColumn, IconColumn};
use Filament\Tables\Table;

class DocNodeResource extends Resource
{
    protected static ?string $model = DocNode::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string | \UnitEnum | null $navigationGroup = 'Applications';
    protected static ?string $navigationLabel = 'Documentation Portal';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Schemas\Components\Grid::make(12)->schema([
                // 1) First, the name of the section.
                TextInput::make('title')
                    ->label('Section name')
                    ->required()
                    ->maxLength(255)
                    ->helperText('The URL is generated automatically from this name.')
                    ->columnSpan(12),

                // 2) Then decide where it sits in the hierarchy.
                Select::make('parent_id')
                    ->label('Sub-section of…')
                    ->placeholder('— None: this is a top-level category —')
                    ->helperText('Leave empty to make a top-level category (e.g. "ISO 27001", "Company assets"). Pick a section to nest this underneath it.')
                    ->options(function (?DocNode $record) {
                        $query = DocNode::query()->orderBy('title');
                        if ($record) {
                            // Prevent choosing self or a descendant (would create a cycle).
                            $query->whereNotIn('id', array_merge([$record->id], $record->descendantIds()));
                        }
                        return $query->pluck('title', 'id');
                    })
                    ->searchable()
                    ->nullable()
                    ->columnSpan(12),

                TextInput::make('summary')
                    ->label('Short description')
                    ->maxLength(255)
                    ->columnSpan(12),

                Textarea::make('description')
                    ->label('Description (optional)')
                    ->rows(4)
                    ->columnSpan(12),

                TextInput::make('sort_order')->numeric()->default(0)->columnSpan(3),
                Toggle::make('is_active')->label('Visible')->default(true)->columnSpan(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('parent.title')->label('Parent')->placeholder('—')->toggleable(),
                TextColumn::make('slug')->searchable()->toggleable(),
                TextColumn::make('children_count')->counts('children')->label('Subsections')->badge(),
                TextColumn::make('attachments_count')->counts('attachments')->label('Documents')->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('sort_order')->label('Order')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Parent')
                    ->options(fn () => DocNode::orderBy('title')->pluck('title', 'id')),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AttachmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocNodes::route('/'),
            'create' => CreateDocNode::route('/create'),
            'edit' => EditDocNode::route('/{record}/edit'),
        ];
    }
}
