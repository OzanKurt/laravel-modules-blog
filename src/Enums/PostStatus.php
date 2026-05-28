<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Enums;

enum PostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
