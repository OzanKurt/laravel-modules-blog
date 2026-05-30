<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V3\Resources;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Enums\PostType;
use Kurt\Modules\Blog\Filament\V3\Resources\PostResource\Pages;
use Kurt\Modules\Blog\Models\Post;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $recordTitleAttribute = 'title';

    /** @var array<int, string> */
    protected static array $locales = ['en', 'tr'];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Tabs::make('translations')
                            ->tabs(array_map(
                                fn (string $locale): Tab => Tab::make(strtoupper($locale))
                                    ->schema([
                                        TextInput::make("title.{$locale}")
                                            ->label('Title')
                                            ->required($locale === 'en')
                                            ->maxLength(255),
                                        Textarea::make("excerpt.{$locale}")
                                            ->label('Excerpt')
                                            ->rows(2),
                                        Textarea::make("body.{$locale}")
                                            ->label('Body')
                                            ->rows(10),
                                    ]),
                                static::$locales,
                            ))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Details')
                    ->schema([
                        Select::make('status')
                            ->options(PostStatus::class)
                            ->default(PostStatus::Draft)
                            ->required()
                            ->live(),
                        Select::make('type')
                            ->options(PostType::class)
                            ->default(PostType::Text)
                            ->required()
                            ->live(),
                        DateTimePicker::make('scheduled_for')
                            ->seconds(false)
                            ->visible(fn (Get $get): bool => $get('status') === PostStatus::Scheduled->value),
                        DateTimePicker::make('published_at')
                            ->seconds(false),
                        TextInput::make('video_url')
                            ->url()
                            ->maxLength(2048)
                            ->visible(fn (Get $get): bool => $get('type') === PostType::Video->value),
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->label('Category'),
                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->label('Tags'),
                    ])
                    ->columns(2),

                Section::make('Cover')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->image()
                            ->visibility('public'),
                    ]),

                Fieldset::make('SEO')
                    ->schema([
                        Tabs::make('seo_translations')
                            ->tabs(array_map(
                                fn (string $locale): Tab => Tab::make(strtoupper($locale))
                                    ->schema([
                                        TextInput::make("meta_title.{$locale}")
                                            ->label('Meta title')
                                            ->maxLength(255),
                                        Textarea::make("meta_description.{$locale}")
                                            ->label('Meta description')
                                            ->rows(2),
                                    ]),
                                static::$locales,
                            ))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PostStatus $state): string => match ($state) {
                        PostStatus::Draft => 'gray',
                        PostStatus::Scheduled => 'warning',
                        PostStatus::Published => 'success',
                        PostStatus::Archived => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color('info'),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('view_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PostStatus::class),
                SelectFilter::make('type')
                    ->options(PostType::class),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<class-string, mixed>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
