<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Enums\PostType;
use Kurt\Modules\Blog\Enums\VideoProvider;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

it('returns null videoSource for non-video posts', function () {
    $user = StubUser::create(['email' => 'a@b.c']);
    $post = Post::factory()->create(['user_id' => $user->id]);

    expect($post->videoSource())->toBeNull();
});

it('parses video URL for video posts', function () {
    $user = StubUser::create(['email' => 'a@b.c']);
    $post = Post::factory()->video('https://youtu.be/ABC123XYZ')->create(['user_id' => $user->id]);

    expect($post->videoSource()?->provider)->toBe(VideoProvider::YouTube);
    expect($post->videoSource()?->id)->toBe('ABC123XYZ');
    expect($post->type)->toBe(PostType::Video);
});
