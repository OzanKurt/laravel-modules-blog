<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;
use Kurt\Modules\Interactions\Comments\Enums\CommentStatus;
use Kurt\Modules\Interactions\Comments\Models\Comment as InteractionsComment;

beforeEach(function () {
    $this->user = StubUser::create(['email' => 'author@example.com']);
    $this->post = Post::factory()->create(['user_id' => $this->user->id]);

    // A comment attached to a non-Post commentable (another module sharing the
    // interactions_comments store). It is Published so it would also leak
    // through scopeApproved() if isolation were missing.
    $this->foreign = InteractionsComment::create([
        'user_id' => $this->user->id,
        'commentable_type' => 'App\\Models\\ForumThread',
        'commentable_id' => 999,
        'body' => 'foreign comment',
        'status' => CommentStatus::Published->value,
    ]);
});

it('excludes non-Post comments from the Blog comment query', function () {
    Comment::create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'blog comment',
    ]);

    expect(Comment::count())->toBe(1)
        ->and(Comment::query()->pluck('body')->all())->toBe(['blog comment'])
        ->and(Comment::find($this->foreign->getKey()))->toBeNull();
});

it('excludes non-Post comments from scopeApproved and scopePending', function () {
    $approved = Comment::create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'approved blog comment',
    ]);
    $approved->approve($this->user);

    Comment::create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'pending blog comment',
    ]);

    expect(Comment::approved()->count())->toBe(1)
        ->and(Comment::approved()->first()->body)->toBe('approved blog comment')
        ->and(Comment::pending()->count())->toBe(1)
        ->and(Comment::pending()->first()->body)->toBe('pending blog comment');
});

it('keeps post() resolving to a Post because foreign commentables are scoped out', function () {
    $comment = Comment::create([
        'post_id' => $this->post->id,
        'user_id' => $this->user->id,
        'body' => 'blog comment',
    ]);

    expect($comment->approval)->toBe(CommentApproval::Pending)
        ->and($comment->post)->toBeInstanceOf(Post::class)
        ->and($comment->post->id)->toBe($this->post->id);
});
