<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Kurt\Modules\Blog\Models\Post;

/**
 * @mixin Post
 */
final class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'status' => $this->status->value,
            'type' => $this->type->value,
            'video_url' => $this->video_url,
            'view_count' => $this->view_count,
            'author_id' => $this->user_id,
            'category_id' => $this->category_id,
            'published_at' => $this->published_at?->toISOString(),
            'scheduled_for' => $this->scheduled_for?->toISOString(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_og_image' => $this->meta_og_image,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
