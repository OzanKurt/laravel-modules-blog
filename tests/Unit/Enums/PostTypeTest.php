<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Enums\PostType;

it('exposes text, image, video, carousel', function () {
    expect(PostType::Text->value)->toBe('text');
    expect(PostType::Image->value)->toBe('image');
    expect(PostType::Video->value)->toBe('video');
    expect(PostType::Carousel->value)->toBe('carousel');
});
