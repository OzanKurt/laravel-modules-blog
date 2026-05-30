<?php

declare(strict_types=1);

use Filament\Tables\Table;
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Filament\V5\Resources\CommentResource;
use Kurt\Modules\Blog\Filament\V5\Resources\CommentResource\Pages\ListComments;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Core\Support\FilamentVersion;

beforeEach(function () {
    if (FilamentVersion::major() !== 5) {
        $this->markTestSkipped('Filament v5 is not installed.');
    }
});

it('targets the Comment model and registers a list + edit page (no create)', function () {
    expect(CommentResource::getModel())->toBe(Comment::class)
        ->and(array_keys(CommentResource::getPages()))->toBe(['index', 'edit']);
});

it('builds a body + approval form', function () {
    $fields = formFieldNames(CommentResource::class, ListComments::class);

    expect($fields)->toContain('body', 'approval');
});

it('defaults the moderation queue to pending comments', function () {
    expect(tableFilterNames(CommentResource::class, ListComments::class))
        ->toContain('approval');

    // The approval filter defaults to the Pending bucket (moderation queue).
    $table = CommentResource::table(
        Table::make(app(ListComments::class))
    );
    $filter = $table->getFilters()['approval'];

    expect($filter->getDefaultState())->toBe(CommentApproval::Pending->value);
});

it('offers approve/reject row actions and approve/reject/delete bulk actions', function () {
    expect(tableActionNames(CommentResource::class, ListComments::class))
        ->toContain('approve', 'reject', 'edit', 'delete');

    expect(tableBulkActionNames(CommentResource::class, ListComments::class))
        ->toContain('approve', 'reject', 'delete');
});
