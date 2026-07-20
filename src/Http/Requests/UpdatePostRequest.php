<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Kurt\Modules\Blog\Enums\PostStatus;
use Kurt\Modules\Blog\Enums\PostType;

class UpdatePostRequest extends FormRequest
{
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'excerpt' => ['sometimes', 'nullable', 'string'],
            'body' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(PostStatus::class)],
            'type' => ['sometimes', Rule::enum(PostType::class)],
            'video_url' => ['sometimes', 'nullable', 'string', 'url'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:blog_categories,id'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_for' => ['sometimes', 'nullable', 'date'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string'],
            'meta_og_image' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['integer', 'exists:blog_tags,id'],
        ];
    }
}
