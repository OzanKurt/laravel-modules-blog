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
