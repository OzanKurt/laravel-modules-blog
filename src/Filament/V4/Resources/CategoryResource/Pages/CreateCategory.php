<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V4\Resources\CategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Kurt\Modules\Blog\Filament\V4\Resources\CategoryResource;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
