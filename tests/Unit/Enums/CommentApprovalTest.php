<?php

declare(strict_types=1);

use Kurt\Modules\Blog\Enums\CommentApproval;

it('exposes pending, approved, rejected', function () {
    expect(CommentApproval::Pending->value)->toBe('pending');
    expect(CommentApproval::Approved->value)->toBe('approved');
    expect(CommentApproval::Rejected->value)->toBe('rejected');
});
