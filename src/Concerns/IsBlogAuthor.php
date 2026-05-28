<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Blog\Models\Post;

/**
 * @mixin Model
 */
trait IsBlogAuthor
{
    /**
     * @return HasMany<Post, $this>
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id', $this->getKeyName());
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function blogComments(): HasMany
    {
        return $this->hasMany(Comment::class, 'user_id', $this->getKeyName());
    }

    public function getAuthorDisplayName(): string
    {
        $name = $this->getAttribute('name');
        if (is_string($name) && $name !== '') {
            return $name;
        }

        $email = $this->getAttribute('email');
        if (is_string($email) && $email !== '') {
            return $email;
        }

        return (string) $this->getKey();
    }
}
