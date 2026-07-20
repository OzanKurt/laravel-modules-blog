<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Fixtures\PanelUser;

it('blocks guests from write endpoints', function () {
    $this->postJson('/api/blog/posts', ['title' => 'Sneaky'])->assertUnauthorized();
});

it('denies a non-owner, non-staff user from updating a post (403)', function () {
    $author = PanelUser::create(['email' => 'author@example.com']);
    $post = Post::factory()->published()->create(['user_id' => $author->id]);

    $other = PanelUser::create(['email' => 'other@example.com']);
    $this->actingAs($other);

    $this->patchJson('/api/blog/posts/'.$post->id, ['title' => 'Hijacked'])->assertForbidden();
});

it('denies a non-staff user from creating a category (403)', function () {
    $user = PanelUser::create(['email' => 'user@example.com']);
    $this->actingAs($user);

    $this->postJson('/api/blog/categories', ['name' => 'Nope'])->assertForbidden();
});
