<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Kurt\Modules\Blog\Models\Comment;

/**
 * @mixin Comment
 */
final class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'user_id' => $this->user_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'approval' => $this->approval->value,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
