<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Fixtures\PanelUser;

it('lists published posts with filter, sort and pagination', function () {
    $author = PanelUser::create(['email' => 'author@example.com']);
    $catA = Category::factory()->create();
    $catB = Category::factory()->create();

    Post::factory()->published()->create(['user_id' => $author->id, 'category_id' => $catA->id, 'title' => ['en' => 'Alpha']]);
    Post::factory()->published()->create(['user_id' => $author->id, 'category_id' => $catA->id, 'title' => ['en' => 'Bravo']]);
    Post::factory()->published()->create(['user_id' => $author->id, 'category_id' => $catB->id, 'title' => ['en' => 'Charlie']]);

    // A draft must never surface for a guest.
    Post::factory()->create(['user_id' => $author->id, 'title' => ['en' => 'Hidden Draft']]);

    // Filter by category (friendly alias -> category_id column).
    $this->getJson('/api/blog/posts?filter[category]='.$catA->id)
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 2);

    // Sort by title ascending.
    $titles = array_column(
        $this->getJson('/api/blog/posts?sort=title')->assertOk()->json('data'),
        'title',
    );
    expect($titles)->toBe(['Alpha', 'Bravo', 'Charlie']);

    // Paginate.
    $this->getJson('/api/blog/posts?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 2)
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonCount(2, 'data');
});

it('excludes drafts from the guest listing', function () {
    $author = PanelUser::create(['email' => 'author@example.com']);
    Post::factory()->create(['user_id' => $author->id]);

    $this->getJson('/api/blog/posts')
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 0);
});
