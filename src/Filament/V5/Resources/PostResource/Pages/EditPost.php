<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V5\Resources\PostResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Kurt\Modules\Blog\Filament\V5\Resources\PostResource;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
