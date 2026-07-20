<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

beforeEach(function () {
    $this->user = StubUser::create(['email' => 'author@example.com']);
});

it('ranks related posts by shared tag overlap, then shared category', function () {
    $category = Category::factory()->create();
    $tagA = Tag::factory()->create();
    $tagB = Tag::factory()->create();
    $tagC = Tag::factory()->create();

    $source = Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);
    $source->tags()->attach([$tagA->id, $tagB->id, $tagC->id]);

    // Two shared tags — strongest match.
    $twoTags = Post::factory()->published()->create(['user_id' => $this->user->id]);
    $twoTags->tags()->attach([$tagA->id, $tagB->id]);

    // One shared tag.
    $oneTag = Post::factory()->published()->create(['user_id' => $this->user->id]);
    $oneTag->tags()->attach([$tagA->id]);

    // No shared tags but the same category — the fallback rung.
    $categoryOnly = Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);

    // Neither a shared tag nor the category — must never appear.
    $unrelated = Post::factory()->published()->create(['user_id' => $this->user->id]);

    $related = $source->related();

    expect($related->pluck('id')->all())->toBe([$twoTags->id, $oneTag->id, $categoryOnly->id])
        ->and($related->pluck('id')->all())->not->toContain($unrelated->id);
});

it('falls back to a shared category when no tags overlap', function () {
    $category = Category::factory()->create();
    $other = Category::factory()->create();

    $source = Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);

    $sameCategory = Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);

    Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $other->id,
    ]);

    expect($source->related()->pluck('id')->all())->toBe([$sameCategory->id]);
});

it('excludes the source post and any unpublished posts', function () {
    $tag = Tag::factory()->create();

    $source = Post::factory()->published()->create(['user_id' => $this->user->id]);
    $source->tags()->attach($tag->id);

    // Draft, scheduled and future-dated peers share the tag but are not public.
    $draft = Post::factory()->create(['user_id' => $this->user->id]);
    $draft->tags()->attach($tag->id);

    $future = Post::factory()->scheduled(now()->addDay())->create(['user_id' => $this->user->id]);
    $future->tags()->attach($tag->id);

    $peer = Post::factory()->published()->create(['user_id' => $this->user->id]);
    $peer->tags()->attach($tag->id);

    $ids = $source->related()->pluck('id')->all();

    expect($ids)->toBe([$peer->id])
        ->and($ids)->not->toContain($source->id);
});

it('returns an empty collection for a post with no tags or category', function () {
    $source = Post::factory()->published()->create(['user_id' => $this->user->id]);

    // A published sibling exists but is unrelated; nothing should surface.
    Post::factory()->published()->create(['user_id' => $this->user->id]);

    expect($source->related())->toBeEmpty();
});

it('honours the limit argument', function () {
    $category = Category::factory()->create();

    $source = Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);

    Post::factory()->count(5)->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);

    expect($source->related(2))->toHaveCount(2);
});
