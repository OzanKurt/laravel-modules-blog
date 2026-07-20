<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Events\CategoryCreated;
use Kurt\Modules\Blog\Events\CategoryDeleted;
use Kurt\Modules\Blog\Events\CategoryUpdated;
use Kurt\Modules\Blog\Events\CommentCreated;
use Kurt\Modules\Blog\Events\PostArchived;
use Kurt\Modules\Blog\Events\PostCreated;
use Kurt\Modules\Blog\Events\PostUpdated;
use Kurt\Modules\Blog\Events\TagCreated;
use Kurt\Modules\Blog\Events\TagDeleted;
use Kurt\Modules\Blog\Events\TagUpdated;
use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Blog\Tests\Stubs\StubUser;

beforeEach(function () {
    $this->user = StubUser::create(['email' => 'author@example.com']);
});

it('dispatches PostCreated on post creation', function () {
    Event::fake([PostCreated::class]);

    $post = Post::factory()->create(['user_id' => $this->user->id]);

    Event::assertDispatched(PostCreated::class, fn (PostCreated $e) => $e->post->is($post));
});

it('dispatches PostUpdated and PostArchived on archive', function () {
    $post = Post::factory()->published()->create(['user_id' => $this->user->id]);

    Event::fake([PostUpdated::class, PostArchived::class]);

    $post->update(['status' => PostStatus::Archived]);

    Event::assertDispatched(PostUpdated::class);
    Event::assertDispatched(PostArchived::class, fn (PostArchived $e) => $e->post->is($post));
});

it('does not dispatch PostUpdated when only a view is recorded', function () {
    $post = Post::factory()->create(['user_id' => $this->user->id]);

    Event::fake([PostUpdated::class]);

    $post->recordView('203.0.113.7');

    Event::assertNotDispatched(PostUpdated::class);

    expect($post->fresh()->view_count)->toBe(1)
        ->and($post->fresh()->last_viewer_ip)->toBe('203.0.113.7');
});

it('dispatches CommentCreated on comment creation', function () {
    Event::fake([CommentCreated::class]);

    $post = Post::factory()->create(['user_id' => $this->user->id]);

    $comment = Comment::create([
        'post_id' => $post->id,
        'user_id' => $this->user->id,
        'body' => 'hello',
    ]);

    Event::assertDispatched(CommentCreated::class, fn (CommentCreated $e) => $e->comment->is($comment));
});

it('dispatches Category lifecycle events', function () {
    Event::fake([CategoryCreated::class, CategoryUpdated::class, CategoryDeleted::class]);

    $category = Category::factory()->create();
    $category->update(['name' => ['en' => 'Renamed']]);
    $category->delete();

    Event::assertDispatched(CategoryCreated::class);
    Event::assertDispatched(CategoryUpdated::class);
    Event::assertDispatched(CategoryDeleted::class);
});

it('dispatches Tag lifecycle events', function () {
    Event::fake([TagCreated::class, TagUpdated::class, TagDeleted::class]);

    $tag = Tag::factory()->create();
    $tag->update(['name' => ['en' => 'Renamed']]);
    $tag->delete();

    Event::assertDispatched(TagCreated::class);
    Event::assertDispatched(TagUpdated::class);
    Event::assertDispatched(TagDeleted::class);
});
