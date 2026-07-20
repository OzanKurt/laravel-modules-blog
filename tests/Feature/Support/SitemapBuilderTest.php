<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Blog\Support\SitemapBuilder;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

beforeEach(function () {
    $this->user = StubUser::create(['email' => 'author@example.com']);
});

it('emits entries only for published posts and categories that hold public content', function () {
    $category = Category::factory()->create();
    $post = Post::factory()->published()->create([
        'user_id' => $this->user->id,
        'category_id' => $category->id,
    ]);

    // A category whose only post is a draft is not public content.
    $draftCategory = Category::factory()->create();
    Post::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $draftCategory->id,
    ]);

    // A category with no posts at all.
    $emptyCategory = Category::factory()->create();

    // A draft post with no category — excluded entirely.
    Post::factory()->create(['user_id' => $this->user->id]);

    $entries = SitemapBuilder::make()->entries();
    $locs = $entries->pluck('loc')->all();

    expect($locs)->toContain(url('blog/'.$post->slug))
        ->and($locs)->toContain(url('blog/category/'.$category->slug))
        ->and($locs)->not->toContain(url('blog/category/'.$draftCategory->slug))
        ->and($locs)->not->toContain(url('blog/category/'.$emptyCategory->slug))
        ->and($entries)->toHaveCount(2);
});

it('includes tags only when opted in, and only tags with published posts', function () {
    $usedTag = Tag::factory()->create();
    $unusedTag = Tag::factory()->create();
    $draftTag = Tag::factory()->create();

    $post = Post::factory()->published()->create(['user_id' => $this->user->id]);
    $post->tags()->attach($usedTag->id);

    $draft = Post::factory()->create(['user_id' => $this->user->id]);
    $draft->tags()->attach($draftTag->id);

    $without = SitemapBuilder::make()->entries()->pluck('loc')->all();
    expect($without)->not->toContain(url('blog/tag/'.$usedTag->slug));

    $with = SitemapBuilder::make()->includeTags()->entries()->pluck('loc')->all();

    expect($with)->toContain(url('blog/tag/'.$usedTag->slug))
        ->and($with)->not->toContain(url('blog/tag/'.$unusedTag->slug))
        ->and($with)->not->toContain(url('blog/tag/'.$draftTag->slug));
});

it('exposes loc, lastmod and changefreq for each entry', function () {
    Post::factory()->published()->create(['user_id' => $this->user->id]);

    $array = SitemapBuilder::make()->toArray();

    expect($array[0])->toHaveKeys(['loc', 'lastmod', 'changefreq'])
        ->and($array[0]['changefreq'])->toBe('weekly');
});
