<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Kurt\Modules\Blog\Filament\BlogPlugin;
use Kurt\Modules\Blog\Filament\V4\Resources\CategoryResource;
use Kurt\Modules\Blog\Filament\V4\Resources\CommentResource;
use Kurt\Modules\Blog\Filament\V4\Resources\PostResource;
use Kurt\Modules\Blog\Filament\V4\Resources\TagResource;
use Kurt\Modules\Core\Support\FilamentVersion;

beforeEach(function () {
    if (FilamentVersion::major() !== 4) {
        $this->markTestSkipped('Filament v4 is not installed.');
    }
});

it('dispatches the facade to the v4 plugin', function () {
    expect(BlogPlugin::make())->toBeInstanceOf(Kurt\Modules\Blog\Filament\V4\BlogPlugin::class)
        ->and(BlogPlugin::make()->getId())->toBe('kurtmodules-blog');
});

it('registers all four blog resources on the panel', function () {
    $resources = Filament::getPanel('admin')->getResources();

    expect($resources)
        ->toContain(PostResource::class)
        ->toContain(CategoryResource::class)
        ->toContain(TagResource::class)
        ->toContain(CommentResource::class);
});

it('registers routes for every resource', function () {
    $uris = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->all();

    expect($uris)
        ->toContain('admin/posts', 'admin/posts/create', 'admin/posts/{record}/edit')
        ->toContain('admin/categories', 'admin/tags')
        ->toContain('admin/comments', 'admin/comments/{record}/edit');
});
