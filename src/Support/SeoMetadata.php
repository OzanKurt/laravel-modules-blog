<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Support;

use Illuminate\Support\Str;
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
        $locale = app()->getLocale();

        $title = $post->getTranslation('meta_title', $locale, false)
            ?: $post->getTranslation('title', $locale);

        // Fall back to the excerpt, then the body. Strip HTML *before*
        // truncating and use Str::limit so the cut is multibyte-safe and the
        // 160-char budget counts visible text, not tag bytes.
        $rawDescription = $post->getTranslation('excerpt', $locale, false)
            ?: $post->getTranslation('body', $locale, false);

        $description = $post->getTranslation('meta_description', $locale, false)
            ?: Str::limit(strip_tags((string) $rawDescription), 160);

        $ogImage = $post->meta_og_image
            ?: $post->getFirstMediaUrl('social')
            ?: $post->getFirstMediaUrl('cover')
            ?: null;

        return new self((string) $title, (string) $description, $ogImage ?: null);
    }
}
