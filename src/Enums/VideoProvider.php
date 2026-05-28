<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Enums;

enum VideoProvider: string
{
    case YouTube = 'youtube';
    case Vimeo = 'vimeo';
    case DailyMotion = 'dailymotion';
}
