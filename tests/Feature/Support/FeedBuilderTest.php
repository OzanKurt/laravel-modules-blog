<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Support\FeedBuilder;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

beforeEach(function () {
    $this->user = StubUser::create(['email' => 'author@example.com']);
});

it('builds a well-formed RSS document with the published posts, newest first', function () {
    Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'published_at' => now()->subDays(3),
        'title' => ['en' => 'Old Post'],
    ]);
    Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'published_at' => now()->subDay(),
        'title' => ['en' => 'New Post'],
    ]);

    // A draft and a future-scheduled post must not leak into the feed.
    Post::factory()->create(['user_id' => $this->user->id, 'title' => ['en' => 'Draft Post']]);
    Post::factory()->scheduled(now()->addDay())->create(['user_id' => $this->user->id]);

    $xml = FeedBuilder::make()->toRss();

    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);

    expect($feed)->not->toBeFalse();

    $titles = [];
    foreach ($feed->channel->item as $item) {
        $titles[] = (string) $item->title;
    }

    expect($titles)->toBe(['New Post', 'Old Post']);
});

it('escapes special characters and stays well-formed', function () {
    Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'title' => ['en' => 'Tips & Tricks <alpha>'],
    ]);

    $xml = FeedBuilder::make()->toRss();

    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);

    expect($feed)->not->toBeFalse()
        ->and((string) $feed->channel->item[0]->title)->toBe('Tips & Tricks <alpha>');
});

it('respects the published scope and the configured limit in the data structure', function () {
    Post::factory()->count(3)->published()->create(['user_id' => $this->user->id]);
    Post::factory()->create(['user_id' => $this->user->id]);

    $data = FeedBuilder::make()->limit(2)->toArray();

    expect($data['items'])->toHaveCount(2)
        ->and($data)->toHaveKeys(['title', 'link', 'description', 'items']);
});

it('can restrict the feed to a single category', function () {
    $category = Category::factory()->create();

    Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
        'title' => ['en' => 'In Category'],
    ]);
    Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'title' => ['en' => 'Out of Category'],
    ]);

    $data = FeedBuilder::make()->forCategory($category)->toArray();

    expect(array_column($data['items'], 'title'))->toBe(['In Category']);
});
