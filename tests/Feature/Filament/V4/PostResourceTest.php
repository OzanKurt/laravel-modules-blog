<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Filament\V4\Resources\PostResource;
use Kurt\Modules\Blog\Filament\V4\Resources\PostResource\Pages\CreatePost;
use Kurt\Modules\Blog\Filament\V4\Resources\PostResource\Pages\ListPosts;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Core\Support\FilamentVersion;

beforeEach(function () {
    if (FilamentVersion::major() !== 4) {
        $this->markTestSkipped('Filament v4 is not installed.');
    }
});

it('targets the Post model and registers its pages', function () {
    expect(PostResource::getModel())->toBe(Post::class)
        ->and(array_keys(PostResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('builds a translatable form with enum, conditional and media fields', function () {
    $fields = formFieldNames(PostResource::class, CreatePost::class);

    expect($fields)
        ->toContain('title.en', 'title.tr')
        ->toContain('excerpt.en', 'excerpt.tr')
        ->toContain('body.en', 'body.tr')
        ->toContain('meta_title.en', 'meta_title.tr')
        ->toContain('meta_description.en', 'meta_description.tr')
        ->toContain('status', 'type', 'category_id', 'tags')
        ->toContain('scheduled_for', 'video_url')
        ->toContain('cover');
});

it('builds a table with key columns and status/type filters', function () {
    expect(tableColumnNames(PostResource::class, ListPosts::class))
        ->toContain('title', 'status', 'type', 'author.name', 'category.name', 'published_at', 'view_count');

    expect(tableFilterNames(PostResource::class, ListPosts::class))
        ->toContain('status', 'type');
});

it('exposes edit, delete and bulk delete actions', function () {
    expect(tableActionNames(PostResource::class, ListPosts::class))
        ->toContain('edit', 'delete');

    expect(tableBulkActionNames(PostResource::class, ListPosts::class))
        ->toContain('delete');
});
