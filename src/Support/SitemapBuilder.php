<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Support;

use Closure;
use Illuminate\Support\Collection;
use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;

/**
 * Produces sitemap entries for the blog's public content: every published
 * post, every category that holds at least one published post, and (opt-in)
 * every tag that does. Draft, scheduled and future-dated posts are excluded,
 * as are categories and tags whose only posts are non-public. Headless by
 * design: it returns the entries for a consuming app to merge into its own
 * sitemap.
 */
final class SitemapBuilder
{
    private bool $includeTags = false;

    private ?Closure $postLink = null;

    private ?Closure $categoryLink = null;

    private ?Closure $tagLink = null;

    public static function make(): self
    {
        return new self;
    }

    public function includeTags(bool $include = true): self
    {
        $this->includeTags = $include;

        return $this;
    }

    /**
     * Override how a post's URL is resolved. The callback receives the Post and
     * returns a string URL. Defaults to `url({route_prefix}/{slug})`.
     */
    public function postLinkUsing(Closure $resolver): self
    {
        $this->postLink = $resolver;

        return $this;
    }

    public function categoryLinkUsing(Closure $resolver): self
    {
        $this->categoryLink = $resolver;

        return $this;
    }

    public function tagLinkUsing(Closure $resolver): self
    {
        $this->tagLink = $resolver;

        return $this;
    }

    /**
     * @return Collection<int, SitemapEntry>
     */
    public function entries(): Collection
    {
        return new Collection([
            ...$this->postEntries(),
            ...$this->categoryEntries(),
            ...($this->includeTags ? $this->tagEntries() : []),
        ]);
    }

    /**
     * The entries as plain arrays, ready to serialize.
     *
     * @return array<int, array<string, string>>
     */
    public function toArray(): array
    {
        return $this->entries()
            ->map(static fn (SitemapEntry $entry): array => $entry->toArray())
            ->all();
    }

    /**
     * @return array<int, SitemapEntry>
     */
    private function postEntries(): array
    {
        return Post::query()
            ->published()
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Post $post): SitemapEntry => new SitemapEntry(
                loc: $this->postLoc($post),
                lastmod: $post->updated_at,
                changefreq: (string) config('blog.sitemap.changefreq.posts', 'weekly'),
                priority: 0.7,
            ))
            ->all();
    }

    /**
     * @return array<int, SitemapEntry>
     */
    private function categoryEntries(): array
    {
        // Categories that hold at least one published post. Derived from the
        // Post `published` scope (rather than a whereHas on the Category side)
        // so the definition of "public" stays in one place.
        $categoryIds = Post::query()
            ->published()
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id');

        return Category::query()
            ->whereIn('id', $categoryIds)
            ->get()
            ->map(fn (Category $category): SitemapEntry => new SitemapEntry(
                loc: $this->categoryLoc($category),
                lastmod: $category->updated_at,
                changefreq: (string) config('blog.sitemap.changefreq.categories', 'daily'),
                priority: 0.5,
            ))
            ->all();
    }

    /**
     * @return array<int, SitemapEntry>
     */
    private function tagEntries(): array
    {
        // Tags carried by at least one published post, via the pivot joined to
        // the Post `published` scope so no publish rule is duplicated here.
        $tagIds = Post::query()
            ->published()
            ->join('blog_post_tag', 'blog_post_tag.post_id', '=', 'blog_posts.id')
            ->distinct()
            ->pluck('blog_post_tag.tag_id');

        return Tag::query()
            ->whereIn('id', $tagIds)
            ->get()
            ->map(fn (Tag $tag): SitemapEntry => new SitemapEntry(
                loc: $this->tagLoc($tag),
                lastmod: $tag->updated_at,
                changefreq: (string) config('blog.sitemap.changefreq.tags', 'weekly'),
                priority: 0.3,
            ))
            ->all();
    }

    private function postLoc(Post $post): string
    {
        return $this->postLink !== null
            ? (string) ($this->postLink)($post)
            : url($this->prefix().'/'.$post->slug);
    }

    private function categoryLoc(Category $category): string
    {
        return $this->categoryLink !== null
            ? (string) ($this->categoryLink)($category)
            : url($this->prefix().'/category/'.$category->slug);
    }

    private function tagLoc(Tag $tag): string
    {
        return $this->tagLink !== null
            ? (string) ($this->tagLink)($tag)
            : url($this->prefix().'/tag/'.$tag->slug);
    }

    private function prefix(): string
    {
        return trim((string) config('blog.route_prefix', 'blog'), '/');
    }
}
