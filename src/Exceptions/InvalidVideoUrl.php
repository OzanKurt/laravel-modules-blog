<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Exceptions;

use RuntimeException;

final class InvalidVideoUrl extends RuntimeException
{
    public static function for(string $url): self
    {
        return new self("Could not parse video URL: [{$url}].");
    }
}
