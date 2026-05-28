<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Access\Gate;
use Kurt\Modules\Blog\Console\Commands\DemoCommand;
use Kurt\Modules\Blog\Console\Commands\PublishDuePostsCommand;
use Kurt\Modules\Blog\Console\Commands\UpgradeTranslationsCommand;
use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;
use Kurt\Modules\Blog\Observers\CategoryObserver;
use Kurt\Modules\Blog\Observers\CommentObserver;
use Kurt\Modules\Blog\Observers\PostObserver;
use Kurt\Modules\Blog\Observers\TagObserver;
use Kurt\Modules\Blog\Policies\CategoryPolicy;
use Kurt\Modules\Blog\Policies\CommentPolicy;
use Kurt\Modules\Blog\Policies\PostPolicy;
use Kurt\Modules\Blog\Policies\TagPolicy;
use Kurt\Modules\Core\Providers\PackageServiceProvider;
use Spatie\LaravelPackageTools\Package;

final class BlogServiceProvider extends PackageServiceProvider
{
    protected function module(): string
    {
        return 'blog';
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-modules-blog')
            ->hasConfigFile('blog')
            ->hasTranslations()
            ->hasMigrations([
                'create_blog_categories_table',
                'create_blog_tags_table',
                'create_blog_posts_table',
                'create_blog_post_tag_table',
                'create_blog_comments_table',
            ])
            ->hasCommands([
                PublishDuePostsCommand::class,
                UpgradeTranslationsCommand::class,
                DemoCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        Post::observe(PostObserver::class);
        Comment::observe(CommentObserver::class);
        Category::observe(CategoryObserver::class);
        Tag::observe(TagObserver::class);

        /** @var Gate $gate */
        $gate = $this->app->make(Gate::class);
        $gate->policy(Post::class, PostPolicy::class);
        $gate->policy(Comment::class, CommentPolicy::class);
        $gate->policy(Category::class, CategoryPolicy::class);
        $gate->policy(Tag::class, TagPolicy::class);

        if ($this->app->runningInConsole() && (bool) config('blog.scheduler.enabled', true)) {
            $this->app->booted(function () {
                /** @var Schedule $schedule */
                $schedule = $this->app->make(Schedule::class);
                $schedule->command(PublishDuePostsCommand::class)
                    ->cron((string) config('blog.scheduler.cron', '* * * * *'));
            });
        }
    }
}
