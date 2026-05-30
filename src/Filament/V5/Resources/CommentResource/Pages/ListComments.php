<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V5\Resources\CommentResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Kurt\Modules\Blog\Filament\V5\Resources\CommentResource;

class ListComments extends ListRecords
{
    protected static string $resource = CommentResource::class;
}
