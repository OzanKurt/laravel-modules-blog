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
        if (preg_match('#(?:https?://)?(?:www\.)?(?:player\.)?vimeo\.com/(?:[a-z]+/)*(\d{4,11})#i', $url, $m)) {
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
