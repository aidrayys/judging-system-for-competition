<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_LOCKED = 'locked';

    protected $fillable = [
        'judge_id',
        'category_id',
        'finalist_id',
        'status',
        'comment',
        'total_score',
        'submitted_at',
        'reopen_count',
    ];

    protected function casts(): array
    {
        return [
            'total_score' => 'float',
            'submitted_at' => 'datetime',
            'reopen_count' => 'integer',
        ];
    }

    public function judge()
    {
        return $this->belongsTo(User::class, 'judge_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function finalist()
    {
        return $this->belongsTo(Finalist::class);
    }

    public function scores()
    {
        return $this->hasMany(EvaluationScore::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * A draft without any score and without feedback, e.g. right after a
     * "Reset My Score" or when a judge only opened the page. It is shown as
     * "Not Started" because there is nothing to continue.
     *
     * Uses the `scores_count` attribute when it was eager-counted
     * (withCount('scores')) to avoid an extra query per row.
     */
    public function isBlankDraft(): bool
    {
        if (! $this->isDraft()) {
            return false;
        }

        if (trim((string) $this->comment) !== '') {
            return false;
        }

        $count = $this->scores_count ?? ($this->relationLoaded('scores')
            ? $this->scores->count()
            : $this->scores()->count());

        return (int) $count === 0;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}
