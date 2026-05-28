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
use Kurt\Modules\Blog\Enums\CommentApproval;
use Kurt\Modules\Blog\Events\CommentApproved;
use Kurt\Modules\Blog\Events\CommentRejected;
use Kurt\Modules\Core\Concerns\ResolvesUser;

class Comment extends Model
{
    use HasFactory;
    use ResolvesUser;
    use SoftDeletes;

    protected $table = 'blog_comments';

    /** @var array<int, string> */
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

    public function user(): BelongsTo
    {
        return $this->userBelongsTo();
    }

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

        CommentApproved::dispatch($this->fresh(), $approver);

        return $this;
    }

    public function reject(Model $rejector): self
    {
        $this->forceFill([
            'approval' => CommentApproval::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $rejector->getKey(),
        ])->save();

        CommentRejected::dispatch($this->fresh(), $rejector);

        return $this;
    }

    protected static function newFactory(): CommentFactory
    {
        return CommentFactory::new();
    }
}
