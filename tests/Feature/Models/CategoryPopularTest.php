<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

beforeEach(function () {
    $this->user = StubUser::create(['email' => 'author@example.com']);
});

it('counts only published posts in scopePopular', function () {
    $category = Category::factory()->create();

    Post::factory()->count(2)->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);

    // Drafts, scheduled and future-dated posts must not inflate the count.
    Post::factory()->create(['user_id' => $this->user->id, 'category_id' => $category->id]);
    Post::factory()->scheduled(now()->addDay())->create(['user_id' => $this->user->id, 'category_id' => $category->id]);

    $popular = Category::popular()->firstOrFail();

    expect($popular->posts_count)->toBe(2);
});

it('orders categories by their published post count', function () {
    $busy = Category::factory()->create();
    $quiet = Category::factory()->create();

    Post::factory()->count(3)->published()->create(['user_id' => $this->user->id, 'category_id' => $busy->id]);
    Post::factory()->published()->create(['user_id' => $this->user->id, 'category_id' => $quiet->id]);
    // A pile of drafts on the "quiet" category must not push it to the top.
    Post::factory()->count(5)->create(['user_id' => $this->user->id, 'category_id' => $quiet->id]);

    $ordered = Category::popular()->pluck('id')->all();

    expect($ordered)->toBe([$busy->id, $quiet->id]);
});
