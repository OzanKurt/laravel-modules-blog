<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V3\Resources;

use Filament\Facades\Filament;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Filament\V3\Resources\CommentResource\Pages;
use Kurt\Modules\Blog\Models\Comment;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $recordTitleAttribute = 'body';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Textarea::make('body')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        Select::make('approval')
                            ->options(CommentApproval::class)
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('body')
                    ->limit(60)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('approval')
                    ->badge()
                    ->color(fn (?CommentApproval $state): string => match ($state) {
                        CommentApproval::Approved => 'success',
                        CommentApproval::Rejected => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->toggleable(),
                TextColumn::make('post.title')
                    ->label('Post')
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('approval')
                    ->options(CommentApproval::class)
                    ->default(CommentApproval::Pending->value),
            ])
            ->actions([
                Action::make('approve')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Comment $record): bool => $record->approval !== CommentApproval::Approved)
                    ->action(fn (Comment $record) => $record->approve(static::moderator())),
                Action::make('reject')
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Comment $record): bool => $record->approval !== CommentApproval::Rejected)
                    ->action(fn (Comment $record) => $record->reject(static::moderator())),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')
                        ->label('Approve selected')
                        ->icon('heroicon-m-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => static::moderateEach($records, 'approve')),
                    BulkAction::make('reject')
                        ->label('Reject selected')
                        ->icon('heroicon-m-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => static::moderateEach($records, 'reject')),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function moderator(): Model
    {
        $user = Filament::auth()->user();

        if (! $user instanceof Model) {
            throw new \RuntimeException('A moderating user must be authenticated to approve or reject comments.');
        }

        return $user;
    }

    /**
     * @param  Collection<int, Model>  $records
     */
    protected static function moderateEach(Collection $records, string $verb): void
    {
        $moderator = static::moderator();

        foreach ($records as $record) {
            if (! $record instanceof Comment) {
                continue;
            }

            $verb === 'approve'
                ? $record->approve($moderator)
                : $record->reject($moderator);
        }
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
            'edit' => Pages\EditComment::route('/{record}/edit'),
        ];
    }
}
