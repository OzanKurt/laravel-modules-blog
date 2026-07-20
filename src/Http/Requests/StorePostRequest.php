<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Enums\PostType;

class StorePostRequest extends FormRequest
{
    /**
     * Authorization is enforced in the controller via the Post policy so the
     * bound model (and its ownership) is available.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'type' => ['nullable', Rule::enum(PostType::class)],
            'video_url' => ['nullable', 'string', 'url'],
            'category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'published_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string'],
            'meta_og_image' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:blog_tags,id'],
        ];
    }
}
