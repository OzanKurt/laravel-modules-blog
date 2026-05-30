<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V4\Resources\TagResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Kurt\Modules\Blog\Filament\V4\Resources\TagResource;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;
}
