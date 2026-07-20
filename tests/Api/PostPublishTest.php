<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kurt\Modules\Blog\Events\PostPublished;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Fixtures\PanelUser;

it('publishes and unpublishes a post', function () {
    Event::fake([PostPublished::class]);

    $author = PanelUser::create(['email' => 'author@example.com']);
    $post = Post::factory()->create(['user_id' => $author->id]);
    $this->actingAs($author);

    $this->postJson('/api/blog/posts/'.$post->id.'/publish')
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($post->fresh()->published_at)->not->toBeNull();
    Event::assertDispatched(PostPublished::class);

    $this->postJson('/api/blog/posts/'.$post->id.'/unpublish')
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');
});
