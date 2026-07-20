<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\Blog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\Blog\Models\Comment;
use Kurt\Modules\Interactions\Comments\Enums\CommentStatus;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    /** @var class-string<Comment> */
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'body' => $this->faker->paragraph(),
        ];
    }

    /**
     * Moderation status is not mass-assignable, so force it after creation
     * rather than passing it through the guarded attributes.
     */
    public function approved(): static
    {
        return $this->afterCreating(function (Comment $comment) {
            $comment->forceFill(['status' => CommentStatus::Published->value])->save();
        });
    }
}
