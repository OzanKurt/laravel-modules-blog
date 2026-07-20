<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Models\Post;

final class PublishDuePostsCommand extends Command
{
    protected $signature = 'blog:publish-due';

    protected $description = 'Publish posts whose scheduled_for is now or earlier.';

    public function handle(): int
    {
        $count = 0;

        Post::query()
            ->where('status', PostStatus::Scheduled->value)
            ->where('scheduled_for', '<=', now())
            ->chunkById(100, function (Collection $posts) use (&$count) {
                /** @var Collection<int, Post> $posts */
                foreach ($posts as $post) {
                    $post->forceFill([
                        'status' => PostStatus::Published,
                        'published_at' => $post->scheduled_for ?? now(),
                        'scheduled_for' => null,
                    ])->save();

                    $count++;
                }
            });

        $this->info("Published {$count} post(s).");

        return self::SUCCESS;
    }
}
