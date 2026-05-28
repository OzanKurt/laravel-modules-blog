# `ozankurt/laravel-modules-blog` v2.0 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `KurtModules-Blog` as `ozankurt/laravel-modules-blog` v2.0.0 — a headless Laravel blog module with scheduled publishing, SEO meta, drafts, translatable content, Spatie medialibrary, and Filament 3/4/5 admin.

**Architecture:** Same scaffold as Core (Spatie's `PackageServiceProvider`, Pest 3, PHPStan 8, Pint, GH Actions matrix). Drops v1's Repository layer + global helper functions + custom Model base. Eloquent + scopes only. Translatable columns are JSON via `spatie/laravel-translatable`. File uploads handled by `spatie/laravel-medialibrary`. Filament resources ship in parallel under `src/Filament/V{3,4,5}` with version-dispatch from the provider base in Core.

**Tech Stack:** PHP 8.4, Laravel 12/13, Filament 3/4/5, Pest 3, Testbench, PHPStan 8, Pint, Rector, spatie/laravel-translatable, spatie/laravel-medialibrary, cviebrock/eloquent-sluggable.

**Spec:** [2026-05-28-kurtmodules-blog-v2-spec.md](../specs/2026-05-28-kurtmodules-blog-v2-spec.md)

**Prerequisite:** `ozankurt/laravel-modules-core` v2.0.0 must be tagged + published (or accessible via a path/VCS composer repository during development).

**Working directory:** `D:\Code\Projects\KurtModules-Blog` for ALL tasks.

**Reference repo:** `D:\Code\Projects\KurtModules-Core` (just-completed v2.0 scaffold). Copy boilerplate (Pint config, PHPStan neon, Rector config, gitattributes, gitignore, GitHub workflow, README structure) from there.

---

## Task 0: Repo prep

- [ ] **Step 1: Create v2 branch from master**

```bash
cd D:/Code/Projects/KurtModules-Blog
git fetch --all --prune
git switch master
git pull
git switch -c v2.0
```

- [ ] **Step 2: Snapshot v1 onto legacy branch and push**

```bash
git switch master
git switch -c v1-legacy
git push -u origin v1-legacy
git switch v2.0
```

- [ ] **Step 3: Remove v1 source (preserve docs/, SECURITY.md, contributors.txt)**

```bash
git rm -r src config database composer.json composer.lock README.md
git commit -m "chore: remove v1 sources ahead of v2 rebuild"
```

(`docs/superpowers/` already lives in master and stays — that's the spec+plan home.)

---

## Task 1: composer.json

**Files:**
- Create: `composer.json`

- [ ] **Step 1: Write composer.json**

```json
{
  "name": "ozankurt/laravel-modules-blog",
  "description": "Headless blog module for Laravel with scheduled publishing, SEO, translations, and Filament admin.",
  "keywords": ["laravel", "filament", "kurtmodules", "blog"],
  "license": "MIT",
  "authors": [{ "name": "Ozan Kurt", "email": "ozankurt2@gmail.com" }],
  "require": {
    "php": "^8.4",
    "illuminate/contracts": "^12.0 || ^13.0",
    "illuminate/database": "^12.0 || ^13.0",
    "illuminate/support": "^12.0 || ^13.0",
    "cviebrock/eloquent-sluggable": "^11.0 || ^12.0",
    "ozankurt/laravel-modules-core": "^2.0",
    "spatie/laravel-medialibrary": "^11.0",
    "spatie/laravel-package-tools": "^1.92",
    "spatie/laravel-translatable": "^6.11"
  },
  "require-dev": {
    "filament/filament": "^3.0 || ^4.0 || ^5.0",
    "filament/spatie-laravel-media-library-plugin": "^3.0 || ^4.0 || ^5.0",
    "larastan/larastan": "^3.0",
    "laravel/pint": "^1.18",
    "league/commonmark": "^2.5",
    "orchestra/testbench": "^9.0 || ^10.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0",
    "rector/rector": "^2.0"
  },
  "suggest": {
    "league/commonmark": "Required if you use Post::renderedBody() for markdown rendering."
  },
  "autoload": {
    "psr-4": { "Kurt\\Modules\\Blog\\": "src/" }
  },
  "autoload-dev": {
    "psr-4": { "Kurt\\Modules\\Blog\\Tests\\": "tests/" }
  },
  "extra": {
    "laravel": {
      "providers": ["Kurt\\Modules\\Blog\\Providers\\BlogServiceProvider"]
    }
  },
  "config": {
    "sort-packages": true,
    "allow-plugins": {
      "pestphp/pest-plugin": true
    }
  },
  "scripts": {
    "test": "vendor/bin/pest",
    "test:coverage": "vendor/bin/pest --coverage --min=80",
    "lint": "vendor/bin/pint --test",
    "format": "vendor/bin/pint",
    "stan": "vendor/bin/phpstan analyse --memory-limit=2G"
  },
  "minimum-stability": "stable",
  "prefer-stable": true
}
```

- [ ] **Step 2: composer validate; if Core isn't published yet, add a path/VCS repo entry**

```bash
composer validate --strict
```

If `composer install` fails on `ozankurt/laravel-modules-core ^2.0` because Core isn't on Packagist yet, add this to `composer.json` AFTER `prefer-stable`:

```json
,"repositories": [
  { "type": "path", "url": "../KurtModules-Core", "options": { "symlink": true } }
]
```

- [ ] **Step 3: composer install**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe composer install --no-interaction
```

Expected: green install.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock
git commit -m "feat: composer scaffold for laravel-modules-blog v2"
```

---

## Task 2: Dev configs (copy from Core)

**Files:**
- Copy from `D:\Code\Projects\KurtModules-Core`: `pint.json`, `phpstan.neon`, `rector.php`, `.gitattributes`, `.gitignore`

- [ ] **Step 1: Copy verbatim, then commit**

```bash
cp ../KurtModules-Core/pint.json .
cp ../KurtModules-Core/phpstan.neon .
cp ../KurtModules-Core/rector.php .
cp ../KurtModules-Core/.gitattributes .
cp ../KurtModules-Core/.gitignore .

git add pint.json phpstan.neon rector.php .gitattributes .gitignore
git commit -m "chore: add pint, phpstan, rector configs"
```

- [ ] **Step 2: Run pint, expect pass (no source yet)**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pint --test
```

---

## Task 3: Config file

**Files:**
- Create: `config/blog.php`

- [ ] **Step 1: Write config/blog.php**

```php
<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Models\Category;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;
use Kurt\Modules\Blog\Models\Tag;

return [
    'preapproved_comments' => false,

    'allow_threaded_comments' => true,
    'comment_max_depth' => 1,

    'scheduler' => [
        'enabled' => true,
        'cron' => '* * * * *',
    ],

    'media' => [
        'disk' => env('BLOG_MEDIA_DISK', 'public'),
        'conversions' => [
            'thumb' => [320, 320],
            'cover' => [1200, 630],
        ],
    ],

    'video' => [
        'thumbnail_quality' => [
            'youtube' => 'maxresdefault',
            'vimeo' => 'thumbnail_large',
        ],
    ],

    'models' => [
        'post' => Post::class,
        'category' => Category::class,
        'tag' => Tag::class,
        'comment' => Comment::class,
    ],

    'route_prefix' => 'blog',
];
```

- [ ] **Step 2: Commit**

```bash
git add config/blog.php
git commit -m "feat(blog): add config file"
```

---

## Task 4: Enums

**Files:**
- Create: `src/Enums/PostStatus.php`
- Create: `src/Enums/PostType.php`
- Create: `src/Enums/CommentApproval.php`
- Create: `src/Enums/VideoProvider.php`
- Create: `tests/Unit/Enums/{PostStatus,PostType,CommentApproval,VideoProvider}Test.php`

For each enum, follow strict TDD (failing test first). Use the same pattern as Core's enums.

- [ ] **Step 1: Tests + impls for each enum**

`src/Enums/PostStatus.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
```

Test (`PostStatusTest.php`):
```php
<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Enums\PostStatus;

it('exposes draft, scheduled, published, archived', function () {
    expect(PostStatus::Draft->value)->toBe('draft');
    expect(PostStatus::Scheduled->value)->toBe('scheduled');
    expect(PostStatus::Published->value)->toBe('published');
    expect(PostStatus::Archived->value)->toBe('archived');
});
```

`src/Enums/PostType.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Enums;

enum PostType: string
{
    case Text = 'text';
    case Image = 'image';
    case Video = 'video';
    case Carousel = 'carousel';
}
```

`src/Enums/CommentApproval.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Enums;

enum CommentApproval: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
```

`src/Enums/VideoProvider.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Enums;

enum VideoProvider: string
{
    case YouTube = 'youtube';
    case Vimeo = 'vimeo';
    case DailyMotion = 'dailymotion';
}
```

Write trivial cases-and-values tests for each, mirroring `PostStatusTest`.

- [ ] **Step 2: Run, all pass**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest tests/Unit/Enums
```

- [ ] **Step 3: Commit**

```bash
git add src/Enums tests/Unit/Enums
git commit -m "feat(blog): add PostStatus, PostType, CommentApproval, VideoProvider enums"
```

---

## Task 5: VideoUrl + VideoSource

**Files:**
- Create: `src/Support/VideoSource.php`
- Create: `src/Support/VideoUrl.php`
- Create: `src/Exceptions/InvalidVideoUrl.php`
- Create: `tests/Unit/Support/VideoUrlTest.php`

The legacy `src/video_functions.php` (with `getYoutubeId`, `getVimeoId`, `getDailyMotionId`) is replaced by an OO `VideoUrl::parse(string): ?VideoSource` plus a value object that knows how to render embed and thumbnail URLs.

- [ ] **Step 1: Failing test**

`tests/Unit/Support/VideoUrlTest.php`:
```php
<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Enums\VideoProvider;
use Kurt\Modules\Blog\Support\VideoSource;
use Kurt\Modules\Blog\Support\VideoUrl;

it('extracts youtube id from watch url', function () {
    $source = VideoUrl::parse('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($source)->toBeInstanceOf(VideoSource::class);
    expect($source->provider)->toBe(VideoProvider::YouTube);
    expect($source->id)->toBe('dQw4w9WgXcQ');
});

it('extracts youtube id from short url', function () {
    expect(VideoUrl::parse('https://youtu.be/dQw4w9WgXcQ')?->id)->toBe('dQw4w9WgXcQ');
});

it('extracts vimeo id', function () {
    $source = VideoUrl::parse('https://vimeo.com/123456789');

    expect($source?->provider)->toBe(VideoProvider::Vimeo);
    expect($source?->id)->toBe('123456789');
});

it('extracts dailymotion id', function () {
    $source = VideoUrl::parse('https://www.dailymotion.com/video/x7tgad0');

    expect($source?->provider)->toBe(VideoProvider::DailyMotion);
    expect($source?->id)->toBe('x7tgad0');
});

it('returns null for unknown URL', function () {
    expect(VideoUrl::parse('https://example.com/foo'))->toBeNull();
});

it('builds embed URL per provider', function () {
    expect(VideoUrl::parse('https://www.youtube.com/watch?v=ABC123XYZ')?->embedUrl())
        ->toBe('https://www.youtube.com/embed/ABC123XYZ');
    expect(VideoUrl::parse('https://vimeo.com/55555')?->embedUrl())
        ->toBe('https://player.vimeo.com/video/55555');
    expect(VideoUrl::parse('https://www.dailymotion.com/video/x7tgad0')?->embedUrl())
        ->toBe('https://www.dailymotion.com/embed/video/x7tgad0');
});

it('builds youtube thumbnail URL with quality', function () {
    expect(VideoUrl::parse('https://youtu.be/ABC123XYZ')?->thumbnailUrl('hqdefault'))
        ->toBe('https://i.ytimg.com/vi/ABC123XYZ/hqdefault.jpg');
});
```

- [ ] **Step 2: Run, expect fail (classes missing)**

- [ ] **Step 3: Implement**

`src/Support/VideoSource.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Support;

use Kurt\Modules\Blog\Enums\VideoProvider;

final readonly class VideoSource
{
    public function __construct(
        public VideoProvider $provider,
        public string $id,
        public string $url,
    ) {}

    public function embedUrl(): string
    {
        return match ($this->provider) {
            VideoProvider::YouTube => "https://www.youtube.com/embed/{$this->id}",
            VideoProvider::Vimeo => "https://player.vimeo.com/video/{$this->id}",
            VideoProvider::DailyMotion => "https://www.dailymotion.com/embed/video/{$this->id}",
        };
    }

    public function thumbnailUrl(string $quality = 'maxresdefault'): string
    {
        return match ($this->provider) {
            VideoProvider::YouTube => "https://i.ytimg.com/vi/{$this->id}/{$quality}.jpg",
            VideoProvider::Vimeo => "https://vumbnail.com/{$this->id}.jpg",
            VideoProvider::DailyMotion => "https://www.dailymotion.com/thumbnail/video/{$this->id}",
        };
    }
}
```

`src/Support/VideoUrl.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Support;

use Kurt\Modules\Blog\Enums\VideoProvider;

final class VideoUrl
{
    public static function parse(string $url): ?VideoSource
    {
        if ($id = self::youtubeId($url)) {
            return new VideoSource(VideoProvider::YouTube, $id, $url);
        }

        if ($id = self::vimeoId($url)) {
            return new VideoSource(VideoProvider::Vimeo, $id, $url);
        }

        if ($id = self::dailymotionId($url)) {
            return new VideoSource(VideoProvider::DailyMotion, $id, $url);
        }

        return null;
    }

    private static function youtubeId(string $url): ?string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';

        if (! str_contains($host, 'youtube.com') && ! str_contains($host, 'youtu.be')) {
            return null;
        }

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            if (! empty($query['v']) && is_string($query['v'])) {
                return $query['v'];
            }
        }

        if (isset($parts['path'])) {
            $segments = array_values(array_filter(explode('/', trim($parts['path'], '/'))));
            if ($segments !== []) {
                return end($segments) ?: null;
            }
        }

        return null;
    }

    private static function vimeoId(string $url): ?string
    {
        if (preg_match('#(?:https?://)?(?:www\.)?(?:player\.)?vimeo\.com/(?:[a-z]+/)*(\d{6,11})#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private static function dailymotionId(string $url): ?string
    {
        if (preg_match('#(?:dailymotion\.com/(?:video|hub)/|dai\.ly/)([a-z0-9]+)#i', $url, $m)) {
            return $m[1];
        }

        return null;
    }
}
```

`src/Exceptions/InvalidVideoUrl.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Exceptions;

use RuntimeException;

final class InvalidVideoUrl extends RuntimeException
{
    public static function for(string $url): self
    {
        return new self("Could not parse video URL: [{$url}].");
    }
}
```

- [ ] **Step 4: Run, all pass**

- [ ] **Step 5: Commit**

```bash
git add src/Support src/Exceptions tests/Unit/Support
git commit -m "feat(blog): replace video_functions.php with VideoUrl + VideoSource"
```

---

## Task 6: Migrations

**Files:**
- Create: `database/migrations/2026_05_28_000001_create_blog_categories_table.php`
- Create: `database/migrations/2026_05_28_000002_create_blog_tags_table.php`
- Create: `database/migrations/2026_05_28_000003_create_blog_posts_table.php`
- Create: `database/migrations/2026_05_28_000004_create_blog_post_tag_table.php`
- Create: `database/migrations/2026_05_28_000005_create_blog_comments_table.php`

All use anonymous class syntax (`return new class extends Migration { … };`).

- [ ] **Step 1: Categories migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('blog_categories')->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
```

- [ ] **Step 2: Tags migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_tags', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_tags');
    }
};
```

- [ ] **Step 3: Posts migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('excerpt')->nullable();
            $table->json('body')->nullable();
            $table->string('status')->default('draft');
            $table->string('type')->default('text');
            $table->string('video_url')->nullable();
            $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->restrictOnDelete();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->string('last_viewer_ip')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('meta_og_image')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
            $table->index('scheduled_for');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
```

- [ ] **Step 4: Pivot blog_post_tag migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_post_tag', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('blog_tags')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['post_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_post_tag');
    }
};
```

- [ ] **Step 5: Comments migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('blog_posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(config('auth.providers.users.table', 'users'))->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('blog_comments')->cascadeOnDelete();
            $table->text('body');
            $table->string('approval')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained(config('auth.providers.users.table', 'users'))->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_comments');
    }
};
```

- [ ] **Step 6: Commit**

```bash
git add database/migrations
git commit -m "feat(blog): add v2 anonymous publishable migrations"
```

---

## Task 7: Models

**Files:**
- Create: `src/Models/Category.php`
- Create: `src/Models/Tag.php`
- Create: `src/Models/Post.php`
- Create: `src/Models/Comment.php`
- Create: `database/factories/{Category,Tag,Post,Comment}Factory.php`

### Step 1 — Category

`src/Models/Category.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Models;

use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\Kurt\Modules\Blog\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Category extends Model
{
    use HasFactory;
    use HasTranslations;
    use Sluggable;
    use SoftDeletes;

    protected $table = 'blog_categories';

    /** @var array<int, string> */
    public array $translatable = ['name', 'description'];

    protected $fillable = ['slug', 'name', 'description', 'parent_id', 'position'];

    public function sluggable(): array
    {
        return ['slug' => ['source' => 'name', 'onUpdate' => true]];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopePopular(Builder $q, bool $desc = true): Builder
    {
        return $q->withCount('posts')->orderBy('posts_count', $desc ? 'desc' : 'asc');
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
```

### Step 2 — Tag

`src/Models/Tag.php`:
```php
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

    protected $fillable = ['slug', 'name', 'description', 'color'];

    public function sluggable(): array
    {
        return ['slug' => ['source' => 'name', 'onUpdate' => true]];
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'blog_post_tag', 'tag_id', 'post_id')->withTimestamps();
    }

    protected static function newFactory(): TagFactory
    {
        return TagFactory::new();
    }
}
```

### Step 3 — Post (largest model)

`src/Models/Post.php`:
```php
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Enums\PostType;
use Kurt\Modules\Blog\Support\SeoMetadata;
use Kurt\Modules\Blog\Support\VideoSource;
use Kurt\Modules\Blog\Support\VideoUrl;
use Kurt\Modules\Core\Concerns\ResolvesUser;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

class Post extends Model implements HasMedia
{
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;
    use ResolvesUser;
    use Sluggable;
    use SoftDeletes;

    protected $table = 'blog_posts';

    /** @var array<int, string> */
    public array $translatable = ['title', 'excerpt', 'body', 'meta_title', 'meta_description'];

    protected $fillable = [
        'slug', 'title', 'excerpt', 'body', 'status', 'type', 'video_url',
        'user_id', 'category_id', 'view_count', 'last_viewer_ip',
        'published_at', 'scheduled_for',
        'meta_title', 'meta_description', 'meta_og_image',
    ];

    protected $casts = [
        'status' => PostStatus::class,
        'type' => PostType::class,
        'published_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'view_count' => 'integer',
    ];

    public function sluggable(): array
    {
        return ['slug' => ['source' => 'title', 'onUpdate' => true]];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function user(): BelongsTo
    {
        return $this->userBelongsTo();
    }

    public function author(): BelongsTo
    {
        return $this->user();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('approval', CommentApproval::Approved->value);
    }

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
        $this->addMediaConversion('thumb')->width(320)->height(320)->nonQueued();
        $this->addMediaConversion('cover')->width(1200)->height(630)->nonQueued();
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', PostStatus::Published->value)->where('published_at', '<=', now());
    }

    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', PostStatus::Scheduled->value);
    }

    public function scopeDrafts(Builder $q): Builder
    {
        return $q->where('status', PostStatus::Draft->value);
    }

    public function scopeArchived(Builder $q): Builder
    {
        return $q->where('status', PostStatus::Archived->value);
    }

    public function scopePopular(Builder $q, bool $desc = true): Builder
    {
        return $q->orderBy('view_count', $desc ? 'desc' : 'asc');
    }

    public function scopeInCategory(Builder $q, Category|int $category): Builder
    {
        return $q->where('category_id', is_int($category) ? $category : $category->getKey());
    }

    public function scopeWithTags(Builder $q, array|int $tagIds, bool $matchAll = false): Builder
    {
        $ids = is_array($tagIds) ? $tagIds : [$tagIds];

        return $matchAll
            ? $q->whereHas('tags', fn ($t) => $t->whereIn('blog_tags.id', $ids), '=', count($ids))
            : $q->whereHas('tags', fn ($t) => $t->whereIn('blog_tags.id', $ids));
    }

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

    public function seo(): SeoMetadata
    {
        return SeoMetadata::forPost($this);
    }
}
```

### Step 4 — Comment

`src/Models/Comment.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Models;

use Database\Factories\Kurt\Modules\Blog\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Events\CommentApproved;
use Kurt\Modules\Blog\Events\CommentRejected;
use Kurt\Modules\Core\Concerns\ResolvesUser;

class Comment extends Model
{
    use HasFactory;
    use ResolvesUser;
    use SoftDeletes;

    protected $table = 'blog_comments';

    protected $fillable = [
        'post_id', 'user_id', 'parent_id', 'body', 'approval',
        'approved_at', 'rejected_at', 'approved_by', 'rejected_by',
    ];

    protected $casts = [
        'approval' => CommentApproval::class,
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->userBelongsTo();
    }

    public function author(): BelongsTo
    {
        return $this->user();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('approval', CommentApproval::Approved->value);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('approval', CommentApproval::Pending->value);
    }

    public function isApproved(): bool
    {
        return $this->approval === CommentApproval::Approved;
    }

    public function approve(Model $approver): self
    {
        $this->forceFill([
            'approval' => CommentApproval::Approved,
            'approved_at' => now(),
            'approved_by' => $approver->getKey(),
        ])->save();

        CommentApproved::dispatch($this->fresh(), $approver);

        return $this;
    }

    public function reject(Model $rejector): self
    {
        $this->forceFill([
            'approval' => CommentApproval::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $rejector->getKey(),
        ])->save();

        CommentRejected::dispatch($this->fresh(), $rejector);

        return $this;
    }
}
```

### Step 5 — Factories

(For brevity, each factory follows Laravel 12 conventions. Use the Database\Factories\Kurt\Modules\Blog namespace.)

`database/factories/CategoryFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\Blog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\Blog\Models\Category;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug' => str($name)->slug(),
            'name' => ['en' => $name],
            'position' => 0,
        ];
    }
}
```

`database/factories/TagFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\Blog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\Blog\Models\Tag;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'slug' => str($name)->slug(),
            'name' => ['en' => $name],
            'color' => $this->faker->hexColor(),
        ];
    }
}
```

`database/factories/PostFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\Blog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Enums\PostType;
use Kurt\Modules\Blog\Models\Post;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'slug' => str($title)->slug(),
            'title' => ['en' => $title],
            'excerpt' => ['en' => $this->faker->sentence(20)],
            'body' => ['en' => $this->faker->paragraphs(3, true)],
            'status' => PostStatus::Draft,
            'type' => PostType::Text,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function scheduled(\DateTimeInterface $at): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Scheduled,
            'scheduled_for' => $at,
        ]);
    }

    public function video(string $url): static
    {
        return $this->state(fn () => [
            'type' => PostType::Video,
            'video_url' => $url,
        ]);
    }
}
```

`database/factories/CommentFactory.php`:
```php
<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\Blog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Models\Comment;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'body' => $this->faker->paragraph(),
            'approval' => CommentApproval::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'approval' => CommentApproval::Approved,
            'approved_at' => now(),
        ]);
    }
}
```

### Step 6 — Commit

```bash
git add src/Models database/factories
git commit -m "feat(blog): add Category, Tag, Post, Comment models with factories"
```

---

## Task 8: Events

**Files:** all under `src/Events/`. One class per file.

- [ ] **Step 1: Write each event class**

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kurt\Modules\Blog\Models\Post;

final class PostCreated
{
    use Dispatchable;

    public function __construct(public readonly Post $post) {}
}
```

Repeat the same skeleton for:
- `PostUpdated($post)`
- `PostPublished($post)`
- `PostArchived($post)`
- `CommentCreated($comment)`
- `CommentApproved($comment, $approver)`
- `CommentRejected($comment, $rejector)`
- `CategoryCreated($category)` / `CategoryUpdated($category)` / `CategoryDeleted($category)`
- `TagCreated($tag)` / `TagUpdated($tag)` / `TagDeleted($tag)`

For events with two args, use:
```php
public function __construct(public readonly Comment $comment, public readonly Model $approver) {}
```

- [ ] **Step 2: Commit**

```bash
git add src/Events
git commit -m "feat(blog): add domain events"
```

---

## Task 9: Observers

**Files:**
- `src/Observers/CategoryObserver.php`
- `src/Observers/TagObserver.php`
- `src/Observers/PostObserver.php`
- `src/Observers/CommentObserver.php`

Each dispatches the corresponding events. Comment observer also sets default approval per config.

`src/Observers/PostObserver.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Observers;

use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Events\PostArchived;
use Kurt\Modules\Blog\Events\PostCreated;
use Kurt\Modules\Blog\Events\PostPublished;
use Kurt\Modules\Blog\Events\PostUpdated;
use Kurt\Modules\Blog\Models\Post;

final class PostObserver
{
    public function created(Post $post): void
    {
        PostCreated::dispatch($post);
    }

    public function updated(Post $post): void
    {
        PostUpdated::dispatch($post);

        if ($post->wasChanged('status')) {
            match ($post->status) {
                PostStatus::Published => PostPublished::dispatch($post),
                PostStatus::Archived => PostArchived::dispatch($post),
                default => null,
            };
        }
    }
}
```

`src/Observers/CommentObserver.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Observers;

use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Events\CommentCreated;
use Kurt\Modules\Blog\Models\Comment;

final class CommentObserver
{
    public function creating(Comment $comment): void
    {
        if ($comment->approval === null) {
            $comment->approval = config('blog.preapproved_comments')
                ? CommentApproval::Approved
                : CommentApproval::Pending;
        }
    }

    public function created(Comment $comment): void
    {
        CommentCreated::dispatch($comment);
    }
}
```

`CategoryObserver` and `TagObserver` dispatch `CategoryCreated/Updated/Deleted` and `TagCreated/Updated/Deleted` respectively.

Commit:
```bash
git add src/Observers
git commit -m "feat(blog): add observers wiring domain events"
```

---

## Task 10: Policies

**Files:**
- `src/Policies/PostPolicy.php`
- `src/Policies/CommentPolicy.php`
- `src/Policies/CategoryPolicy.php`
- `src/Policies/TagPolicy.php`

Each ships sensible defaults (see spec §6 for the matrix). Use `Illuminate\Foundation\Auth\User` or `Illuminate\Contracts\Auth\Authenticatable` for the user param.

`src/Policies/PostPolicy.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Models\Post;

final class PostPolicy
{
    public function view(?Authenticatable $user, Post $post): bool
    {
        if ($post->status === PostStatus::Published && $post->published_at?->isPast()) {
            return true;
        }

        return $user !== null && ($post->user_id === $user->getAuthIdentifier() || $this->isStaff($user));
    }

    public function create(Authenticatable $user): bool
    {
        return true;
    }

    public function update(Authenticatable $user, Post $post): bool
    {
        return $post->user_id === $user->getAuthIdentifier() || $this->isStaff($user);
    }

    public function delete(Authenticatable $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    private function isStaff(Authenticatable $user): bool
    {
        return app('gate')->allows('canManageBlog', $user);
    }
}
```

(Repeat the pattern for `CommentPolicy` — approve/reject require `isStaff`. `CategoryPolicy` and `TagPolicy` gate writes behind `canManageBlog`.)

Commit:
```bash
git add src/Policies
git commit -m "feat(blog): add Post, Comment, Category, Tag policies"
```

---

## Task 11: Author trait + contract

**Files:**
- `src/Contracts/BlogAuthor.php`
- `src/Concerns/IsBlogAuthor.php`

`src/Contracts/BlogAuthor.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Contracts;

interface BlogAuthor
{
    public function getKey(): int|string;

    public function getAuthorDisplayName(): string;
}
```

`src/Concerns/IsBlogAuthor.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;

trait IsBlogAuthor
{
    public function blogPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id', $this->getKeyName());
    }

    public function blogComments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id', $this->getKeyName());
    }

    public function getAuthorDisplayName(): string
    {
        return $this->name ?? $this->email ?? (string) $this->getKey();
    }
}
```

Commit:
```bash
git add src/Contracts src/Concerns
git commit -m "feat(blog): add BlogAuthor contract + IsBlogAuthor trait"
```

---

## Task 12: SEO + BlogModels support

**Files:**
- `src/Support/SeoMetadata.php`
- `src/Support/BlogModels.php`

`src/Support/SeoMetadata.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Support;

use Kurt\Modules\Blog\Models\Post;

final readonly class SeoMetadata
{
    public function __construct(
        public string $title,
        public string $description,
        public ?string $ogImage,
    ) {}

    public static function forPost(Post $post): self
    {
        $title = $post->getTranslation('meta_title', app()->getLocale(), false)
            ?: $post->getTranslation('title', app()->getLocale());

        $description = $post->getTranslation('meta_description', app()->getLocale(), false)
            ?: strip_tags(
                $post->getTranslation('excerpt', app()->getLocale(), false)
                    ?: substr((string) $post->getTranslation('body', app()->getLocale(), false), 0, 160)
            );

        $ogImage = $post->meta_og_image
            ?: $post->getFirstMediaUrl('social')
            ?: $post->getFirstMediaUrl('cover')
            ?: null;

        return new self($title, $description, $ogImage ?: null);
    }
}
```

`src/Support/BlogModels.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Support;

final class BlogModels
{
    /** @return class-string */
    public static function post(): string
    {
        return config('blog.models.post');
    }

    /** @return class-string */
    public static function category(): string
    {
        return config('blog.models.category');
    }

    /** @return class-string */
    public static function tag(): string
    {
        return config('blog.models.tag');
    }

    /** @return class-string */
    public static function comment(): string
    {
        return config('blog.models.comment');
    }
}
```

Commit:
```bash
git add src/Support/SeoMetadata.php src/Support/BlogModels.php
git commit -m "feat(blog): add SeoMetadata and BlogModels helpers"
```

---

## Task 13: Console commands

**Files:**
- `src/Console/Commands/PublishDuePostsCommand.php`
- `src/Console/Commands/UpgradeTranslationsCommand.php`
- `src/Console/Commands/DemoCommand.php`

`src/Console/Commands/PublishDuePostsCommand.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Console\Commands;

use Illuminate\Console\Command;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Models\Post;

final class PublishDuePostsCommand extends Command
{
    protected $signature = 'blog:publish-due';

    protected $description = 'Publish posts whose scheduled_for is now or earlier.';

    public function handle(): int
    {
        $count = Post::query()
            ->where('status', PostStatus::Scheduled->value)
            ->where('scheduled_for', '<=', now())
            ->get()
            ->each(function (Post $post) {
                $post->forceFill([
                    'status' => PostStatus::Published,
                    'published_at' => $post->scheduled_for ?? now(),
                    'scheduled_for' => null,
                ])->save();
            })
            ->count();

        $this->info("Published {$count} post(s).");

        return self::SUCCESS;
    }
}
```

`src/Console/Commands/UpgradeTranslationsCommand.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class UpgradeTranslationsCommand extends Command
{
    protected $signature = 'blog:upgrade-translations {--locale=en}';

    protected $description = 'Wrap legacy scalar string columns into JSON translation arrays.';

    /** @var array<string, array<int, string>> */
    private array $targets = [
        'blog_posts' => ['title', 'excerpt', 'body', 'meta_title', 'meta_description'],
        'blog_categories' => ['name', 'description'],
        'blog_tags' => ['name', 'description'],
    ];

    public function handle(): int
    {
        $locale = (string) $this->option('locale');

        foreach ($this->targets as $table => $columns) {
            foreach ($columns as $column) {
                $this->upgradeColumn($table, $column, $locale);
            }
        }

        return self::SUCCESS;
    }

    private function upgradeColumn(string $table, string $column, string $locale): void
    {
        $rows = DB::table($table)->select(['id', $column])->whereNotNull($column)->get();

        foreach ($rows as $row) {
            $value = $row->{$column};

            if ($value === null || $value === '' || $this->looksLikeJson($value)) {
                continue;
            }

            DB::table($table)
                ->where('id', $row->id)
                ->update([$column => json_encode([$locale => $value], JSON_UNESCAPED_UNICODE)]);
        }

        $this->info("Upgraded {$table}.{$column}");
    }

    private function looksLikeJson(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }
}
```

`src/Console/Commands/DemoCommand.php` — uses factories to seed sample data; opt-in:
```php
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
            ->sequence(fn ($s) => ['category_id' => $categories->random()->id])
            ->create()
            ->each(fn (Post $p) => $p->tags()->sync($tags->random(2)->pluck('id')));

        $this->info('Demo data seeded.');

        return self::SUCCESS;
    }
}
```

Commit:
```bash
git add src/Console
git commit -m "feat(blog): add publish-due, upgrade-translations, demo commands"
```

---

## Task 14: Service provider

**Files:**
- `src/Providers/BlogServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Providers;

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

        $gate = $this->app['Illuminate\Contracts\Auth\Access\Gate'];
        $gate->policy(Post::class, PostPolicy::class);
        $gate->policy(Comment::class, CommentPolicy::class);
        $gate->policy(Category::class, CategoryPolicy::class);
        $gate->policy(Tag::class, TagPolicy::class);

        if (config('blog.scheduler.enabled', true) && $this->app->runningInConsole()) {
            $this->app->booted(function () {
                $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
                $schedule->command(PublishDuePostsCommand::class)->cron(config('blog.scheduler.cron', '* * * * *'));
            });
        }
    }
}
```

Commit:
```bash
git add src/Providers
git commit -m "feat(blog): add BlogServiceProvider with observers, policies, scheduler"
```

---

## Task 15: Filament resources V3/V4/V5

This is the largest task. Each version directory contains four resources: `PostResource`, `CategoryResource`, `TagResource`, `CommentResource`. The resource bodies differ enough between Filament 3/4/5 that they're written separately.

**Strategy:** Use `epic-skills:filament-v5` skill when writing V5 resources. Use the Filament 3 and Filament 4 official docs (via context7) when writing those.

For each version:

1. Create `src/Filament/V{3,4,5}/Resources/PostResource.php` with the appropriate API.
2. Same for Category, Tag, Comment.
3. Wire up registration in `src/Providers/BlogFilamentServiceProvider.php` (one provider, dispatches via `FilamentVersion::major()`).

`src/Providers/BlogFilamentServiceProvider.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Providers;

use Illuminate\Support\ServiceProvider;
use Kurt\Modules\Core\Support\FilamentVersion;

final class BlogFilamentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $major = FilamentVersion::major();

        if ($major === null) {
            return;
        }

        $namespace = match ($major) {
            5 => 'Kurt\\Modules\\Blog\\Filament\\V5\\Resources',
            4 => 'Kurt\\Modules\\Blog\\Filament\\V4\\Resources',
            default => 'Kurt\\Modules\\Blog\\Filament\\V3\\Resources',
        };

        // Filament panels discover resources from their configured directories.
        // Consumers must register this namespace in their Panel; we expose it via
        // a service so consumer code can call BlogPanel::resources() to get the FQCNs.
        $this->app->instance('blog.filament.resources', [
            "{$namespace}\\PostResource",
            "{$namespace}\\CategoryResource",
            "{$namespace}\\TagResource",
            "{$namespace}\\CommentResource",
        ]);
    }
}
```

The actual `PostResource` implementations are version-specific. Because the surface is large (form schema, table columns, relation managers per version), defer the bodies to per-version subagent work guided by the relevant Filament skill (`epic-skills:filament-v5` for V5).

For this plan, each V{N}/Resources/PostResource.php contains, at minimum:
- Slug input (read-only on edit), translatable title/excerpt/body inputs, status select bound to `PostStatus`, type select bound to `PostType`, scheduled-for datetime picker (visible when status=Scheduled), category select, tag multi-select, video URL input (visible when type=Video), SEO fieldset, media uploader.
- Table columns: title, status badge, author, category, published_at, view_count, actions.

Smoke tests live in `tests/Feature/Filament/V{N}/PostResourceTest.php` and use `Livewire::test(PostResource\Pages\ListPosts::class)->assertOk()` (Filament 3/4) or the v5 equivalent.

Commit progressively:
```bash
git add src/Filament/V3 src/Providers/BlogFilamentServiceProvider.php
git commit -m "feat(blog): add Filament v3 resources"

git add src/Filament/V4
git commit -m "feat(blog): add Filament v4 resources"

git add src/Filament/V5
git commit -m "feat(blog): add Filament v5 resources"
```

---

## Task 16: Test base + feature tests

**Files:**
- `tests/Pest.php`
- `tests/TestCase.php`
- `tests/Feature/{Models/PostTest,Models/CommentTest,Console/PublishDueTest,Models/ScopesTest,Models/SeoMetadataTest}.php`

`tests/TestCase.php`:
```php
<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Tests;

use Kurt\Modules\Blog\Providers\BlogServiceProvider;
use Kurt\Modules\Core\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function modulePackageProviders($app): array
    {
        return [BlogServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
```

`tests/Pest.php`:
```php
<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
```

Sample feature test (`tests/Feature/Console/PublishDueTest.php`):
```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Events\PostPublished;
use Kurt\Modules\Blog\Models\Post;

it('publishes scheduled posts whose time has come', function () {
    Event::fake();
    $user = \Kurt\Modules\Core\Tests\Stubs\StubUser::create(['email' => 'a@b.c']);

    $due = Post::factory()->scheduled(now()->subMinute())->create(['user_id' => $user->id]);
    $future = Post::factory()->scheduled(now()->addDay())->create(['user_id' => $user->id]);

    $this->artisan('blog:publish-due')->assertExitCode(0);

    expect($due->refresh()->status)->toBe(PostStatus::Published);
    expect($future->refresh()->status)->toBe(PostStatus::Scheduled);

    Event::assertDispatched(PostPublished::class);
});
```

Cover key behaviours per the spec §10 testing matrix.

Commit:
```bash
git add tests
git commit -m "feat(blog): add test base + feature tests"
```

---

## Task 17: CI matrix + Filament matrix

Copy `D:\Code\Projects\KurtModules-Core\.github` and extend the matrix to also iterate Filament `[3.*, 4.*, 5.*]`.

Modify `.github/workflows/tests.yml`:
```yaml
strategy:
    fail-fast: false
    matrix:
        php: ['8.4']
        laravel: ['12.*', '13.*']
        filament: ['3.*', '4.*', '5.*']
        include:
            - laravel: 12.*
              testbench: 9.*
            - laravel: 13.*
              testbench: 10.*

…

- name: Require Laravel ${{ matrix.laravel }} + Filament ${{ matrix.filament }}
  run: |
      composer require --no-update --no-interaction \
          "illuminate/contracts:${{ matrix.laravel }}" \
          "illuminate/database:${{ matrix.laravel }}" \
          "illuminate/support:${{ matrix.laravel }}" \
          "orchestra/testbench:${{ matrix.testbench }}" \
          "filament/filament:${{ matrix.filament }}"
```

Commit:
```bash
git add .github
git commit -m "ci: add github actions matrix for laravel x filament"
```

---

## Task 18: README + CHANGELOG + UPGRADE-2.0

Adapt the templates from Core. Key sections:
- README: install, what it provides, quick example, link to specs.
- CHANGELOG: 2.0.0 entry listing everything new.
- UPGRADE-2.0.md: v1 → v2 migration matching spec §11 exactly.

Commit:
```bash
git add README.md CHANGELOG.md UPGRADE-2.0.md
git commit -m "docs: add README, CHANGELOG, UPGRADE-2.0"
```

---

## Task 19: Push + PR

```bash
git push -u origin v2.0
gh pr create --title "v2.0: rewrite as ozankurt/laravel-modules-blog" --body "$(cat <<'EOF'
## Summary

- Rebuilds Blog as the v2.0 line of ozankurt/laravel-modules-blog.
- Drops the Repository layer, custom Model base, global helper functions, and the v1 routes/controllers.
- Adds: scheduled publishing, SEO meta, drafts, translatable content (Spatie), Spatie medialibrary, Filament 3/4/5 admin.
- Requires ozankurt/laravel-modules-core ^2.0.

See UPGRADE-2.0.md for migration.
EOF
)"
```

Pause for user review + merge.

After merge:
```bash
git switch master
git pull
git tag -a v2.0.0 -m "v2.0.0"
git push origin v2.0.0
gh release create v2.0.0 --title "v2.0.0" --notes-file CHANGELOG.md
```

---

## Definition of done

- [ ] All migrations run under SQLite in-memory.
- [ ] Pint + PHPStan + Pest all green.
- [ ] CI matrix green for Laravel 12/13 × Filament 3/4/5.
- [ ] `blog:publish-due` flips state and dispatches `PostPublished`.
- [ ] `blog:upgrade-translations` migrates legacy scalar columns.
- [ ] Filament resources smoke-test green per major.
- [ ] README, CHANGELOG, UPGRADE-2.0 in place.
- [ ] Tagged `v2.0.0`.
