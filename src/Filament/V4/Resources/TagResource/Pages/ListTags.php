<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V4\Resources\TagResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Kurt\Modules\Blog\Filament\V4\Resources\TagResource;

class ListTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
