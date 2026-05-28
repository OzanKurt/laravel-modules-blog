<?php

declare(strict_types=1);

namespace Database\Factories\Kurt\Modules\Blog;

use Illuminate\Database\Eloquent\Factories\Factory;
use Kurt\Modules\Blog\Models\Category;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /** @var class-string<Category> */
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'slug' => str($name)->slug()->toString(),
            'name' => ['en' => $name],
            'position' => 0,
        ];
    }
}
