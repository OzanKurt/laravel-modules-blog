<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Policies\PostPolicy;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

beforeEach(function () {
    $this->user = StubUser::create(['email' => 'author@example.com']);
});

it('backfills published_at when a post is saved Published with none set', function () {
    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'status' => PostStatus::Draft,
    ]);

    expect($post->published_at)->toBeNull();

    $post->status = PostStatus::Published;
    $post->save();

    expect($post->fresh()->published_at)->not->toBeNull();
});

it('makes a manually published post visible via scopePublished and PostPolicy::view', function () {
    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => null,
    ]);

    expect($post->fresh()->published_at)->not->toBeNull()
        ->and(Post::published()->whereKey($post->id)->exists())->toBeTrue()
        ->and((new PostPolicy)->view(null, $post->fresh()))->toBeTrue();
});

it('does not overwrite an explicit published_at', function () {
    $when = now()->subWeek()->startOfSecond();

    $post = Post::factory()->create([
        'user_id' => $this->user->id,
        'status' => PostStatus::Published,
        'published_at' => $when,
    ]);

    expect($post->fresh()->published_at->equalTo($when))->toBeTrue();
});
