<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Blog\Tests\Fixtures\PanelUser;

it('returns posts related by shared tags', function () {
    $author = PanelUser::create(['email' => 'author@example.com']);
    $tag = Tag::factory()->create();

    $base = Post::factory()->published()->create(['user_id' => $author->id]);
    $base->tags()->attach($tag->id);

    $related = Post::factory()->published()->create(['user_id' => $author->id]);
    $related->tags()->attach($tag->id);

    $unrelated = Post::factory()->published()->create(['user_id' => $author->id]);

    $ids = array_column(
        $this->getJson('/api/blog/posts/'.$base->id.'/related')->assertOk()->json('data'),
        'id',
    );

    expect($ids)->toContain($related->id)
        ->not->toContain($unrelated->id)
        ->not->toContain($base->id);
});
