<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

beforeEach(function () {
    $this->user = StubUser::create(['email' => 'author@example.com']);
});

it('scopes published to posts with published status and past published_at', function () {
    Post::factory()->create(['user_id' => $this->user->id]);
    Post::factory()->published()->create(['user_id' => $this->user->id]);
    Post::factory()->scheduled(now()->subDay())->create(['user_id' => $this->user->id]);

    expect(Post::published()->count())->toBe(1);
});

it('scopes scheduled to scheduled status posts', function () {
    Post::factory()->create(['user_id' => $this->user->id]);
    Post::factory()->published()->create(['user_id' => $this->user->id]);
    Post::factory()->scheduled(now()->addDay())->create(['user_id' => $this->user->id]);

    expect(Post::scheduled()->count())->toBe(1);
});

it('scopes drafts to draft status posts', function () {
    Post::factory()->count(2)->create(['user_id' => $this->user->id]);
    Post::factory()->published()->create(['user_id' => $this->user->id]);

    expect(Post::drafts()->count())->toBe(2);
});

it('orders popular posts by view_count desc', function () {
    Post::factory()->create(['user_id' => $this->user->id])->forceFill(['view_count' => 10])->save();
    Post::factory()->create(['user_id' => $this->user->id])->forceFill(['view_count' => 100])->save();
    Post::factory()->create(['user_id' => $this->user->id])->forceFill(['view_count' => 5])->save();

    /** @var array<int, int> $counts */
    $counts = Post::popular()->pluck('view_count')->all();

    expect($counts)->toBe([100, 10, 5]);
});

it('filters posts in a category', function () {
    $category = Category::factory()->create();
    $other = Category::factory()->create();

    Post::factory()->count(2)->create(['user_id' => $this->user->id, 'category_id' => $category->id]);
    Post::factory()->create(['user_id' => $this->user->id, 'category_id' => $other->id]);

    expect(Post::inCategory($category)->count())->toBe(2);
    expect(Post::inCategory($category->id)->count())->toBe(2);
});

it('filters posts having any of given tags', function () {
    $a = Tag::factory()->create();
    $b = Tag::factory()->create();
    $c = Tag::factory()->create();

    $p1 = Post::factory()->create(['user_id' => $this->user->id]);
    $p2 = Post::factory()->create(['user_id' => $this->user->id]);
    $p3 = Post::factory()->create(['user_id' => $this->user->id]);

    $p1->tags()->attach([$a->id, $b->id]);
    $p2->tags()->attach([$b->id]);
    $p3->tags()->attach([$c->id]);

    expect(Post::withTags([$a->id, $b->id])->count())->toBe(2);
});

it('filters posts having all of given tags when matchAll is true', function () {
    $a = Tag::factory()->create();
    $b = Tag::factory()->create();

    $both = Post::factory()->create(['user_id' => $this->user->id]);
    $aOnly = Post::factory()->create(['user_id' => $this->user->id]);

    $both->tags()->attach([$a->id, $b->id]);
    $aOnly->tags()->attach([$a->id]);

    expect(Post::withTags([$a->id, $b->id], matchAll: true)->count())->toBe(1);
});

it('filters posts authored by a user', function () {
    $other = StubUser::create(['email' => 'other@example.com']);

    Post::factory()->count(2)->create(['user_id' => $this->user->id]);
    Post::factory()->create(['user_id' => $other->id]);

    expect(Post::authoredBy($this->user)->count())->toBe(2);
    expect(Post::authoredBy($this->user->id)->count())->toBe(2);
});
