<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

beforeEach(function () {
    $this->user = StubUser::create(['email' => 'author@example.com']);
});

it('increments view_count without bumping updated_at', function () {
    $post = Post::factory()->create(['user_id' => $this->user->id]);

    // Pin updated_at firmly in the past so any bump would be unmistakable.
    $past = now()->subDay()->startOfSecond();
    DB::table('blog_posts')->where('id', $post->id)->update(['updated_at' => $past]);
    $post->refresh();

    $post->recordView('203.0.113.7');

    $fresh = $post->fresh();

    expect($fresh->view_count)->toBe(1)
        ->and($fresh->last_viewer_ip)->toBe('203.0.113.7')
        ->and($fresh->updated_at->equalTo($past))->toBeTrue();
});
