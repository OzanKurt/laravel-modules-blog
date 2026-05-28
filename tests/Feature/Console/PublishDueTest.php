<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Events\PostPublished;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

it('publishes scheduled posts whose time has come', function () {
    Event::fake([PostPublished::class]);

    $user = StubUser::create(['email' => 'a@b.c']);

    $due = Post::factory()->scheduled(now()->subMinute())->create(['user_id' => $user->id]);
    $future = Post::factory()->scheduled(now()->addDay())->create(['user_id' => $user->id]);

    $this->artisan('blog:publish-due')->assertExitCode(0);

    expect($due->refresh()->status)->toBe(PostStatus::Published);
    expect($future->refresh()->status)->toBe(PostStatus::Scheduled);

    Event::assertDispatched(PostPublished::class);
});
