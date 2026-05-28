<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Contracts;

interface BlogAuthor
{
    public function getKey(): int|string;

    public function getAuthorDisplayName(): string;
}
