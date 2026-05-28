<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Enums\PostStatus;

it('exposes draft, scheduled, published, archived', function () {
    expect(PostStatus::Draft->value)->toBe('draft');
    expect(PostStatus::Scheduled->value)->toBe('scheduled');
    expect(PostStatus::Published->value)->toBe('published');
    expect(PostStatus::Archived->value)->toBe('archived');
});
