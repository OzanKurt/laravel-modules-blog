<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Filament\V4\Resources\TagResource;
use Kurt\Modules\Blog\Filament\V4\Resources\TagResource\Pages\CreateTag;
use Kurt\Modules\Blog\Filament\V4\Resources\TagResource\Pages\ListTags;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Core\Support\FilamentVersion;

beforeEach(function () {
    if (FilamentVersion::major() !== 4) {
        $this->markTestSkipped('Filament v4 is not installed.');
    }
});

it('targets the Tag model and registers its pages', function () {
    expect(TagResource::getModel())->toBe(Tag::class)
        ->and(array_keys(TagResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('builds a translatable form with a color picker', function () {
    $fields = formFieldNames(TagResource::class, CreateTag::class);

    expect($fields)
        ->toContain('name.en', 'name.tr')
        ->toContain('description.en', 'description.tr')
        ->toContain('color');
});

it('builds a table with a color swatch column', function () {
    expect(tableColumnNames(TagResource::class, ListTags::class))
        ->toContain('color', 'name', 'posts_count');
});
