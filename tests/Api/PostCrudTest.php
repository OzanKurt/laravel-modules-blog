<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Blog\Tests\Fixtures\PanelUser;

it('creates, updates and deletes a post as the author', function () {
    $author = PanelUser::create(['email' => 'author@example.com']);
    $tag = Tag::factory()->create();
    $this->actingAs($author);

    $created = $this->postJson('/api/blog/posts', [
        'title' => 'My First Post',
        'body' => 'Hello body',
        'tags' => [$tag->id],
    ])->assertCreated();

    $id = $created->json('data.id');

    expect($created->json('data.status'))->toBe('draft')
        ->and($created->json('data.author_id'))->toBe($author->id)
        ->and($created->json('data.tags'))->toHaveCount(1);

    $this->patchJson('/api/blog/posts/'.$id, ['title' => 'Updated Title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Title');

    $this->deleteJson('/api/blog/posts/'.$id)->assertNoContent();

    expect(Post::withTrashed()->findOrFail($id)->trashed())->toBeTrue();
});
