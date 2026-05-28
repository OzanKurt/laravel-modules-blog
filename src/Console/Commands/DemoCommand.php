<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;

final class DemoCommand extends Command
{
    protected $signature = 'blog:demo';

    protected $description = 'Seed demo categories, tags and posts.';

    public function handle(): int
    {
        $categories = Category::factory()->count(3)->create();
        $tags = Tag::factory()->count(5)->create();

        Post::factory()
            ->count(10)
            ->published()
            ->sequence(fn () => ['category_id' => $categories->random()->id])
            ->create()
            ->each(fn (Post $p) => $p->tags()->sync($tags->random(2)->pluck('id')));

        $this->info('Demo data seeded.');

        return self::SUCCESS;
    }
}
