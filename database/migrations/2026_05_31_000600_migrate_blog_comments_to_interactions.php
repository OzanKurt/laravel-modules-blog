<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kurt\Modules\Blog\Models\Post;

/**
 * Moves Blog comments onto the shared Interactions comment store. Each
 * `blog_comments` row becomes an `interactions_comments` row whose commentable
 * is the Post; `approval` maps to `status` (approved→published, rejected→spam,
 * else pending) and the approver/rejecter audit collapses into
 * `moderated_by` / `moderated_at`. Threading is preserved by remapping
 * `parent_id` to the freshly assigned ids, then the legacy table is dropped.
 *
 * Guarded on both tables so the migration is a no-op when either is absent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('blog_comments') || ! Schema::hasTable('interactions_comments')) {
            return;
        }

        $postType = (new Post)->getMorphClass();
        $rows = DB::table('blog_comments')->orderBy('id')->get();

        /** @var array<int, int> $map old blog_comments id => new interactions_comments id */
        $map = [];

        foreach ($rows as $row) {
            $map[$row->id] = DB::table('interactions_comments')->insertGetId([
                'user_id' => $row->user_id,
                'commentable_type' => $postType,
                'commentable_id' => $row->post_id,
                'parent_id' => null,
                'body' => $row->body,
                'status' => match ($row->approval) {
                    'approved' => 'published',
                    'rejected' => 'spam',
                    default => 'pending',
                },
                'moderated_by' => $row->approved_by ?? $row->rejected_by,
                'moderated_at' => $row->approved_at ?? $row->rejected_at,
                'edited_at' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
                'deleted_at' => $row->deleted_at,
            ]);
        }

        foreach ($rows as $row) {
            if ($row->parent_id !== null && isset($map[$row->parent_id])) {
                DB::table('interactions_comments')
                    ->where('id', $map[$row->id])
                    ->update(['parent_id' => $map[$row->parent_id]]);
            }
        }

        Schema::dropIfExists('blog_comments');
    }

    public function down(): void
    {
        // One-way data migration; the legacy blog_comments table is not restored.
    }
};
