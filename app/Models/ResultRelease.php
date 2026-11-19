<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResultRelease extends Model
{
    protected $fillable = [
        'category_id',
        'released_by',
        'released_at',
        'note',
        'review_required',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'review_required' => 'boolean',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function releasedBy()
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /**
     * True when a correction or reset changed an evaluation of this category
     * after the results were released. The administrator has to review and
     * republish the result.
     */
    public function needsReview(): bool
    {
        return (bool) $this->review_required;
    }
}
