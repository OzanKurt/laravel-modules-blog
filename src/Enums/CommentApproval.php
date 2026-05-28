<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Enums;

enum CommentApproval: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
