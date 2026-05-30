<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Filament\V3;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Kurt\Modules\Blog\Filament\V3\Resources\CategoryResource;
use Kurt\Modules\Blog\Filament\V3\Resources\CommentResource;
use Kurt\Modules\Blog\Filament\V3\Resources\PostResource;
use Kurt\Modules\Blog\Filament\V3\Resources\TagResource;

final class BlogPlugin implements Plugin
{
    public function getId(): string
    {
        return 'kurtmodules-blog';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            PostResource::class,
            CategoryResource::class,
            TagResource::class,
            CommentResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}

    public static function make(): static
    {
        /** @var static */
        return app(self::class);
    }
}
