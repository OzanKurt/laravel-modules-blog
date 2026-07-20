<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Support;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Post;

/**
 * Builds feed data for the latest published posts. Headless by design: it
 * returns a data structure (toArray) or a ready-to-serve RSS 2.0 XML string
 * (toRss); a consuming app decides where to expose it, e.g.
 *
 *     Route::get('feed', fn () => response(
 *         FeedBuilder::make()->toRss(),
 *         200,
 *         ['Content-Type' => 'application/rss+xml; charset=UTF-8'],
 *     ));
 */
final class FeedBuilder
{
    private int $limit;

    private ?Category $category = null;

    private ?string $title = null;

    private ?string $description = null;

    private ?string $link = null;

    private ?Closure $linkResolver = null;

    public function __construct()
    {
        $this->limit = max(1, (int) config('blog.feed.limit', 20));
    }

    public static function make(): self
    {
        return new self;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    public function forCategory(Category|int $category): self
    {
        $this->category = $category instanceof Category
            ? $category
            : Category::query()->findOrFail($category);

        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function link(string $link): self
    {
        $this->link = $link;

        return $this;
    }

    /**
     * Override how a post's canonical URL is resolved. The callback receives
     * the Post and must return a string URL. Defaults to
     * `url({route_prefix}/{slug})`.
     */
    public function linkUsing(Closure $resolver): self
    {
        $this->linkResolver = $resolver;

        return $this;
    }

    /**
     * The published posts backing the feed, newest first. Category is eager
     * loaded so rendering item `<category>` never triggers an N+1.
     *
     * @return Collection<int, Post>
     */
    public function posts(): Collection
    {
        $query = Post::query()
            ->with('category')
            ->published();

        if ($this->category !== null) {
            $query->inCategory($this->category);
        }

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($this->limit)
            ->get();
    }

    /**
     * The feed as a plain data structure, for consumers that render their own
     * format (JSON Feed, Atom, a Blade view, ...).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $posts = $this->posts();
        $latest = $posts->first();

        return [
            'title' => $this->resolvedTitle(),
            'link' => $this->resolvedLink(),
            'description' => $this->resolvedDescription(),
            'updated' => $latest?->published_at?->toAtomString(),
            'items' => $posts->map(fn (Post $post): array => [
                'title' => (string) $post->title,
                'link' => $this->linkFor($post),
                'guid' => $this->linkFor($post),
                'summary' => $this->summaryFor($post),
                'category' => $post->category?->name,
                'published_at' => $post->published_at?->toAtomString(),
            ])->all(),
        ];
    }

    /**
     * A valid RSS 2.0 document for the resolved posts. All dynamic text is
     * XML-escaped, so the output is always well-formed.
     */
    public function toRss(): string
    {
        $posts = $this->posts();

        $lastBuild = '';
        $latest = $posts->first();
        if ($latest instanceof Post && $latest->published_at instanceof Carbon) {
            $lastBuild = '<lastBuildDate>'.$this->esc($latest->published_at->toRssString()).'</lastBuildDate>';
        }

        $items = $posts->map(fn (Post $post): string => $this->renderItem($post))->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rss version="2.0"><channel>'
            .'<title>'.$this->esc($this->resolvedTitle()).'</title>'
            .'<link>'.$this->esc($this->resolvedLink()).'</link>'
            .'<description>'.$this->esc($this->resolvedDescription()).'</description>'
            .$lastBuild
            .$items
            .'</channel></rss>';
    }

    private function renderItem(Post $post): string
    {
        $link = $this->linkFor($post);

        $parts = [
            '<title>'.$this->esc((string) $post->title).'</title>',
            '<link>'.$this->esc($link).'</link>',
            '<guid isPermaLink="true">'.$this->esc($link).'</guid>',
            '<description>'.$this->esc($this->summaryFor($post)).'</description>',
        ];

        if ($post->category !== null) {
            $parts[] = '<category>'.$this->esc((string) $post->category->name).'</category>';
        }

        if ($post->published_at instanceof Carbon) {
            $parts[] = '<pubDate>'.$this->esc($post->published_at->toRssString()).'</pubDate>';
        }

        return '<item>'.implode('', $parts).'</item>';
    }

    private function summaryFor(Post $post): string
    {
        $excerpt = trim((string) $post->excerpt);

        if ($excerpt !== '') {
            return $excerpt;
        }

        return Str::limit(strip_tags((string) $post->body), 280);
    }

    private function linkFor(Post $post): string
    {
        if ($this->linkResolver !== null) {
            return (string) ($this->linkResolver)($post);
        }

        return url($this->prefix().'/'.$post->slug);
    }

    private function resolvedTitle(): string
    {
        return $this->title
            ?? (string) config('blog.feed.title', config('app.name', 'Blog'));
    }

    private function resolvedDescription(): string
    {
        return $this->description
            ?? (string) config('blog.feed.description', 'Latest posts');
    }

    private function resolvedLink(): string
    {
        return $this->link ?? url($this->prefix());
    }

    private function prefix(): string
    {
        return trim((string) config('blog.route_prefix', 'blog'), '/');
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
