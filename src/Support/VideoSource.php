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
