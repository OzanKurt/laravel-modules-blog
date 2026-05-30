<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Filament\V4\Resources\CategoryResource;
use Kurt\Modules\Blog\Filament\V4\Resources\CategoryResource\Pages\CreateCategory;
use Kurt\Modules\Blog\Filament\V4\Resources\CategoryResource\Pages\ListCategories;
use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Core\Support\FilamentVersion;

beforeEach(function () {
    if (FilamentVersion::major() !== 4) {
        $this->markTestSkipped('Filament v4 is not installed.');
    }
});

it('targets the Category model and registers its pages', function () {
    expect(CategoryResource::getModel())->toBe(Category::class)
        ->and(array_keys(CategoryResource::getPages()))->toBe(['index', 'create', 'edit']);
});

it('builds a translatable form with parent and slug fields', function () {
    $fields = formFieldNames(CategoryResource::class, CreateCategory::class);

    expect($fields)
        ->toContain('name.en', 'name.tr')
        ->toContain('description.en', 'description.tr')
        ->toContain('parent_id', 'slug', 'position');
});

it('builds a tree-aware table', function () {
    expect(tableColumnNames(CategoryResource::class, ListCategories::class))
        ->toContain('name', 'parent.name', 'posts_count', 'position');
});
