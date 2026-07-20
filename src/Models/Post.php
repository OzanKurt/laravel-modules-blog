<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\Kurt\Modules\Blog\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Enums\PostType;
use Kurt\Modules\Blog\Support\SeoMetadata;
use Kurt\Modules\Blog\Support\VideoSource;
use Kurt\Modules\Blog\Support\VideoUrl;
use Kurt\Modules\Core\Concerns\ResolvesUser;
use Kurt\Modules\Interactions\Comments\Enums\CommentStatus;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property string|null $body
 * @property PostStatus $status
 * @property PostType $type
 * @property string|null $video_url
 * @property int $user_id
 * @property int|null $category_id
 * @property int $view_count
 * @property string|null $last_viewer_ip
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_for
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $meta_og_image
 */
class Post extends Model implements HasMedia
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use HasTranslations;
    use InteractsWithMedia;
    use ResolvesUser;
    use Sluggable;
    use SoftDeletes;

    protected $table = 'blog_posts';

    /** @var list<string> */
    public array $translatable = ['title', 'excerpt', 'body', 'meta_title', 'meta_description'];

    /**
     * `view_count` and `last_viewer_ip` are intentionally excluded: they are
     * derived analytics maintained only through recordView(), never through
     * mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug', 'title', 'excerpt', 'body', 'status', 'type', 'video_url',
        'user_id', 'category_id',
        'published_at', 'scheduled_for',
        'meta_title', 'meta_description', 'meta_og_image',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => PostStatus::class,
        'type' => PostType::class,
        'published_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'view_count' => 'integer',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sluggable(): array
    {
        return ['slug' => ['source' => 'title', 'onUpdate' => true]];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->userBelongsTo();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function author(): BelongsTo
    {
        return $this->user();
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * @return MorphMany<Comment, $this>
     */
    public function approvedComments(): MorphMany
    {
        return $this->comments()->where('status', CommentStatus::Published->value);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'blog_post_tag', 'post_id', 'tag_id')->withTimestamps();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
        $this->addMediaCollection('social')->singleFile();
        $this->addMediaCollection('carousel');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = $this->addMediaConversion('thumb');
        $thumb->width(320);
        $thumb->height(320);
        $thumb->nonQueued();

        $cover = $this->addMediaConversion('cover');
        $cover->width(1200);
        $cover->height(630);
        $cover->nonQueued();
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', PostStatus::Published->value)->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', PostStatus::Scheduled->value);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeDrafts(Builder $q): Builder
    {
        return $q->where('status', PostStatus::Draft->value);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeArchived(Builder $q): Builder
    {
        return $q->where('status', PostStatus::Archived->value);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopePopular(Builder $q, bool $desc = true): Builder
    {
        return $q->orderBy('view_count', $desc ? 'desc' : 'asc');
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeInCategory(Builder $q, Category|int $category): Builder
    {
        return $q->where('category_id', is_int($category) ? $category : $category->getKey());
    }

    /**
     * @param  Builder<self>  $q
     * @param  array<int, int>|int  $tagIds
     * @return Builder<self>
     */
    public function scopeWithTags(Builder $q, array|int $tagIds, bool $matchAll = false): Builder
    {
        $ids = is_array($tagIds) ? $tagIds : [$tagIds];

        return $matchAll
            ? $q->whereHas('tags', fn ($t) => $t->whereIn('blog_tags.id', $ids), '=', count($ids))
            : $q->whereHas('tags', fn ($t) => $t->whereIn('blog_tags.id', $ids));
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeAuthoredBy(Builder $q, Model|int $user): Builder
    {
        return $q->where('user_id', $user instanceof Model ? $user->getKey() : $user);
    }

    public function videoSource(): ?VideoSource
    {
        if ($this->type !== PostType::Video || $this->video_url === null) {
            return null;
        }

        return VideoUrl::parse($this->video_url);
    }

    /**
     * Record a view: increment the counter and optionally remember the
     * viewer's IP. This is the only sanctioned way to mutate `view_count`
     * and `last_viewer_ip` (both are excluded from mass assignment).
     */
    public function recordView(?string $ip = null): self
    {
        $this->increment('view_count', 1, $ip === null ? [] : ['last_viewer_ip' => $ip]);

        return $this;
    }

    public function seo(): SeoMetadata
    {
        return SeoMetadata::forPost($this);
    }

    protected static function newFactory(): PostFactory
    {
        return PostFactory::new();
    }
}
