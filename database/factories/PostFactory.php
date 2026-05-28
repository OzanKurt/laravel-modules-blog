<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\Blog;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Enums\PostType;
use Kurt\Modules\Blog\Models\Post;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /** @var class-string<Post> */
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'slug' => str($title)->slug()->toString(),
            'title' => ['en' => $title],
            'excerpt' => ['en' => $this->faker->sentence(20)],
            'body' => ['en' => $this->faker->paragraphs(3, true)],
            'status' => PostStatus::Draft,
            'type' => PostType::Text,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function scheduled(DateTimeInterface $at): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Scheduled,
            'scheduled_for' => $at,
        ]);
    }

    public function video(string $url): static
    {
        return $this->state(fn () => [
            'type' => PostType::Video,
            'video_url' => $url,
        ]);
    }
}
