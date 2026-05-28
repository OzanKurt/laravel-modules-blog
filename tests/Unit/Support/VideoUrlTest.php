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
