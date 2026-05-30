<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V4\Resources\PostResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Kurt\Modules\Blog\Filament\V4\Resources\PostResource;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;
}
