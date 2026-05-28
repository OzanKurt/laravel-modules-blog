<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\Kurt\Modules\Blog\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Tag extends Model
{
    use HasFactory;
    use HasTranslations;
    use Sluggable;
    use SoftDeletes;

    protected $table = 'blog_tags';

    /** @var array<int, string> */
    public array $translatable = ['name', 'description'];

    /** @var array<int, string> */
    protected $fillable = ['slug', 'name', 'description', 'color'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function sluggable(): array
    {
        return ['slug' => ['source' => 'name', 'onUpdate' => true]];
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'blog_post_tag', 'tag_id', 'post_id')->withTimestamps();
    }

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }
}
