<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Fixtures\PanelUser;

it('shows a published post by id and by slug', function () {
    $author = PanelUser::create(['email' => 'author@example.com']);
    $post = Post::factory()->published()->create(['user_id' => $author->id, 'title' => ['en' => 'Hello World']]);

    $this->getJson('/api/blog/posts/'.$post->id)
        ->assertOk()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonPath('data.title', 'Hello World');

    $this->getJson('/api/blog/posts/'.$post->slug)
        ->assertOk()
        ->assertJsonPath('data.slug', $post->slug);
});

it('forbids a guest from viewing a draft', function () {
    $author = PanelUser::create(['email' => 'author@example.com']);
    $post = Post::factory()->create(['user_id' => $author->id]);

    $this->getJson('/api/blog/posts/'.$post->id)->assertForbidden();
});

it('returns 404 for an unknown post', function () {
    $this->getJson('/api/blog/posts/does-not-exist')->assertNotFound();
});
