<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Models;

use Database\Factories\Kurt\Modules\Blog\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Events\CommentApproved;
use Kurt\Modules\Blog\Events\CommentRejected;
use Kurt\Modules\Core\Concerns\ResolvesUser;

/**
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $body
 * @property CommentApproval|null $approval
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property int|null $approved_by
 * @property int|null $rejected_by
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    use ResolvesUser;
    use SoftDeletes;

    protected $table = 'blog_comments';

    /** @var list<string> */
    protected $fillable = [
        'post_id', 'user_id', 'parent_id', 'body', 'approval',
        'approved_at', 'rejected_at', 'approved_by', 'rejected_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'approval' => CommentApproval::class,
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->userBelongsTo();
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function author(): BelongsTo
    {
        return $this->user();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('approval', CommentApproval::Approved->value);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopePending(Builder $q): Builder
    {
        return $q->where('approval', CommentApproval::Pending->value);
    }

    public function isApproved(): bool
    {
        return $this->approval === CommentApproval::Approved;
    }

    public function approve(Model $approver): self
    {
        $this->forceFill([
            'approval' => CommentApproval::Approved,
            'approved_at' => now(),
            'approved_by' => $approver->getKey(),
        ])->save();

        $this->refresh();

        CommentApproved::dispatch($this, $approver);

        return $this;
    }

    public function reject(Model $rejector): self
    {
        $this->forceFill([
            'approval' => CommentApproval::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $rejector->getKey(),
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
