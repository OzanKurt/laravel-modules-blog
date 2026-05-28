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
        $locale = app()->getLocale();

        $title = $post->getTranslation('meta_title', $locale, false)
            ?: $post->getTranslation('title', $locale);

        $description = $post->getTranslation('meta_description', $locale, false)
            ?: strip_tags(
                $post->getTranslation('excerpt', $locale, false)
                    ?: substr((string) $post->getTranslation('body', $locale, false), 0, 160)
            );

        $ogImage = $post->meta_og_image
            ?: $post->getFirstMediaUrl('social')
            ?: $post->getFirstMediaUrl('cover')
            ?: null;

        return new self((string) $title, (string) $description, $ogImage ?: null);
    }
}
