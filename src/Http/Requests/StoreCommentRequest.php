<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
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
            'body' => ['required', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:interactions_comments,id'],
        ];
    }
}
