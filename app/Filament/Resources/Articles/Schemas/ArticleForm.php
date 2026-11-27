<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->helperText('Used in the web address. Leave empty to create it from the title.')
                            ->required(fn (string $operation): bool => $operation === 'edit')
                            ->alphaDash()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true),
                        Textarea::make('excerpt')
                            ->helperText('A sentence or two for the blog list and search results.')
                            ->rows(2)
                            ->maxLength(500),
                        MarkdownEditor::make('body')
                            ->required()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('articles'),
                    ]),
                Section::make('Publishing')
                    ->schema([
                        FileUpload::make('cover_image')
                            ->label('Cover image')
                            ->image()
                            ->disk('public')
                            ->directory('articles')
                            ->maxSize(2048),
                        Toggle::make('is_published')
                            ->label('Published'),
                        DateTimePicker::make('published_at')
                            ->label('Publish at')
                            ->helperText('Leave empty to publish now. A later time keeps it hidden until then.'),
                    ]),
            ]);
    }
}
