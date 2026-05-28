<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\Blog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Models\Comment;

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
            'approval' => CommentApproval::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'approval' => CommentApproval::Approved,
            'approved_at' => now(),
        ]);
    }
}
