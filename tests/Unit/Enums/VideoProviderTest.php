<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Enums\VideoProvider;

it('exposes youtube, vimeo, dailymotion', function () {
    expect(VideoProvider::YouTube->value)->toBe('youtube');
    expect(VideoProvider::Vimeo->value)->toBe('vimeo');
    expect(VideoProvider::DailyMotion->value)->toBe('dailymotion');
});
