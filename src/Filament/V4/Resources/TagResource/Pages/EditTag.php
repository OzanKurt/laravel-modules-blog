<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V4\Resources\TagResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Kurt\Modules\Blog\Filament\V4\Resources\TagResource;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

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
