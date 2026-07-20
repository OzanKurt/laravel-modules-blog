<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Support\SeoMetadata;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

it('returns SEO metadata using title fallback', function () {
    $user = StubUser::create(['email' => 'a@b.c']);
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'title' => ['en' => 'Hello world'],
        'excerpt' => ['en' => 'Sample excerpt'],
    ]);

    $seo = $post->seo();

    expect($seo)->toBeInstanceOf(SeoMetadata::class);
    expect($seo->title)->toBe('Hello world');
    expect($seo->description)->toBe('Sample excerpt');
});

it('strips HTML and truncates the body multibyte-safely for the description', function () {
    $user = StubUser::create(['email' => 'a@b.c']);
    $body = '<p><strong>'.str_repeat('é', 300).'</strong></p>';
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'title' => ['en' => 'Title'],
        'excerpt' => ['en' => ''],
        'body' => ['en' => $body],
    ]);

    $seo = $post->seo();

    expect($seo->description)
        ->not->toContain('<')
        ->and(mb_strlen($seo->description))->toBeLessThanOrEqual(163) // 160 chars + Str::limit's '...'
        ->and(str_starts_with($seo->description, 'é'))->toBeTrue();
});

it('overrides via meta_title and meta_description', function () {
    $user = StubUser::create(['email' => 'a@b.c']);
    $post = Post::factory()->create([
        'user_id' => $user->id,
        'title' => ['en' => 'Title'],
        'meta_title' => ['en' => 'Meta'],
        'meta_description' => ['en' => 'Desc'],
    ]);

    $seo = $post->seo();

    expect($seo->title)->toBe('Meta');
    expect($seo->description)->toBe('Desc');
});
