<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Fixtures\PanelUser;

it('stores a comment on a post and deletes it', function () {
    $author = PanelUser::create(['email' => 'author@example.com']);
    $post = Post::factory()->published()->create(['user_id' => $author->id]);

    $commenter = PanelUser::create(['email' => 'commenter@example.com']);
    $this->actingAs($commenter);

    $created = $this->postJson('/api/blog/posts/'.$post->id.'/comments', ['body' => 'Nice post'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Nice post')
        ->assertJsonPath('data.post_id', $post->id);

    $commentId = $created->json('data.id');

    // The comment's author may delete it.
    $this->deleteJson('/api/blog/comments/'.$commentId)->assertNoContent();

    expect(Comment::withTrashed()->findOrFail($commentId)->trashed())->toBeTrue();
});

it('lists only approved comments for a post to guests', function () {
    $author = PanelUser::create(['email' => 'author@example.com']);
    $post = Post::factory()->published()->create(['user_id' => $author->id]);

    Comment::factory()->approved()->create(['post_id' => $post->id, 'user_id' => $author->id]);
    Comment::factory()->create(['post_id' => $post->id, 'user_id' => $author->id]); // pending

    $this->getJson('/api/blog/posts/'.$post->id.'/comments')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 1);
});
