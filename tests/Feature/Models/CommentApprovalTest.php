<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Events\CommentApproved;
use Kurt\Modules\Blog\Events\CommentRejected;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

it('defaults approval to pending and dispatches events on approve/reject', function () {
    Event::fake([CommentApproved::class, CommentRejected::class]);

    $user = StubUser::create(['email' => 'a@b.c']);
    $post = Post::factory()->create(['user_id' => $user->id]);

    $comment = Comment::create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'body' => 'hi',
    ]);

    expect($comment->approval)->toBe(CommentApproval::Pending);

    $comment->approve($user);
    expect($comment->fresh()->approval)->toBe(CommentApproval::Approved);

    $comment->reject($user);
    expect($comment->fresh()->approval)->toBe(CommentApproval::Rejected);

    Event::assertDispatched(CommentApproved::class);
    Event::assertDispatched(CommentRejected::class);
});

it('ignores mass-assigned moderation fields on create', function () {
    $user = StubUser::create(['email' => 'a@b.c']);
    $moderator = StubUser::create(['email' => 'mod@b.c']);
    $post = Post::factory()->create(['user_id' => $user->id]);

    $comment = Comment::create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'body' => 'sneaky',
        'approval' => 'approved',
        'status' => 'published',
        'moderated_by' => $moderator->id,
        'moderated_at' => now(),
    ]);

    $comment->refresh();

    expect($comment->approval)->toBe(CommentApproval::Pending)
        ->and($comment->isApproved())->toBeFalse()
        ->and($comment->moderated_by)->toBeNull()
        ->and($comment->moderated_at)->toBeNull();
});

it('preapproves comments when config enabled', function () {
    config()->set('blog.preapproved_comments', true);
    Event::fake([CommentApproved::class, CommentRejected::class]);

    $user = StubUser::create(['email' => 'a@b.c']);
    $post = Post::factory()->create(['user_id' => $user->id]);

    $comment = Comment::create([
        'post_id' => $post->id,
        'user_id' => $user->id,
        'body' => 'hi',
    ]);

    expect($comment->approval)->toBe(CommentApproval::Approved);
});
