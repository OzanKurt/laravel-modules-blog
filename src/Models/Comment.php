<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Models;

use Database\Factories\Kurt\Modules\Blog\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Events\CommentApproved;
use Kurt\Modules\Blog\Events\CommentRejected;
use Kurt\Modules\Interactions\Comments\Enums\CommentStatus;
use Kurt\Modules\Interactions\Comments\Models\Comment as InteractionsComment;

/**
 * Blog comments live in the shared Interactions comment store
 * (`interactions_comments`) and inherit threading, revisions, soft-deletes,
 * reactions, mentions, and the moderation audit trail from the Interactions
 * Comment. This subclass keeps the Blog-facing API:
 *
 * - a `post_id` shim onto the polymorphic `commentable` (Blog comments are
 *   always attached to a Post);
 * - an `approval` enum mapped onto the Interactions `status`
 *   (pending↔Pending, approved↔Published, rejected↔Spam);
 * - approve()/reject() verbs that record the moderator (moderated_by/at) and
 *   fire the Blog moderation events.
 *
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $body
 * @property CommentApproval $approval
 * @property CommentStatus $status
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 */
class Comment extends InteractionsComment
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * Moderation fields (`status`, `approval`, `moderated_by`, `moderated_at`)
     * are deliberately NOT mass-assignable: they may only be set through the
     * approve()/reject() verbs and the CommentObserver defaults, so untrusted
     * input to Comment::create() can never self-approve or spoof a moderator.
     *
     * @var list<string>
     */
    protected $fillable = [
        'post_id', 'user_id', 'parent_id', 'body',
        'commentable_type', 'commentable_id', 'edited_at',
    ];

    /**
     * The Blog post behind the polymorphic commentable. Blog comments are
     * always attached to a Post, so `commentable_id` resolves as a Post key.
     *
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'commentable_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->author();
    }

    public function getPostIdAttribute(): ?int
    {
        $id = $this->attributes['commentable_id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    public function setPostIdAttribute(int|string $value): void
    {
        $this->attributes['commentable_type'] = (new Post)->getMorphClass();
        $this->attributes['commentable_id'] = (int) $value;
    }

    public function getApprovalAttribute(): CommentApproval
    {
        return match ($this->status) {
            CommentStatus::Published => CommentApproval::Approved,
            CommentStatus::Spam => CommentApproval::Rejected,
            default => CommentApproval::Pending,
        };
    }

    public function setApprovalAttribute(CommentApproval|string $value): void
    {
        $approval = $value instanceof CommentApproval ? $value : CommentApproval::from($value);

        $this->attributes['status'] = match ($approval) {
            CommentApproval::Approved => CommentStatus::Published->value,
            CommentApproval::Rejected => CommentStatus::Spam->value,
            CommentApproval::Pending => CommentStatus::Pending->value,
        };
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', CommentStatus::Published->value);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', CommentStatus::Pending->value);
    }

    public function isApproved(): bool
    {
        return $this->status === CommentStatus::Published;
    }

    public function approve(Model $approver): self
    {
        $this->forceFill([
            'status' => CommentStatus::Published->value,
            'moderated_by' => $approver->getKey(),
            'moderated_at' => now(),
        ])->save();

        $this->refresh();

        CommentApproved::dispatch($this, $approver);

        return $this;
    }

    public function reject(Model $rejector): self
    {
        $this->forceFill([
            'status' => CommentStatus::Spam->value,
            'moderated_by' => $rejector->getKey(),
            'moderated_at' => now(),
        ])->save();

        $this->refresh();

        CommentRejected::dispatch($this, $rejector);

        return $this;
    }

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }
}
