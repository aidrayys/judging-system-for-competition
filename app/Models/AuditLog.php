<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Structured action types for evaluation corrections and resets.
     */
    public const ACTION_SCORE_CORRECTION = 'score_correction';

    public const ACTION_RESET_SCORES = 'reset_scores';

    public const ACTION_RESET_SUBMISSION = 'reset_submission';

    protected $fillable = [
        'user_id',
        'action',
        'action_type',
        'actor_role',
        'evaluation_id',
        'original_judge_id',
        'subject_type',
        'subject_id',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'previous_scores',
        'new_scores',
        'previous_total',
        'new_total',
        'previous_status',
        'new_status',
        'previous_comment',
        'new_comment',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'previous_scores' => 'array',
            'new_scores' => 'array',
            'previous_total' => 'float',
            'new_total' => 'float',
            'evaluation_id' => 'integer',
            'original_judge_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The evaluation that was corrected or reset.
     */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * The judge who originally produced the evaluation.
     */
    public function originalJudge()
    {
        return $this->belongsTo(User::class, 'original_judge_id');
    }

    /**
     * True when the entry belongs to an evaluation correction or reset.
     */
    public function isEvaluationAction(): bool
    {
        return in_array($this->action_type, [
            self::ACTION_SCORE_CORRECTION,
            self::ACTION_RESET_SCORES,
            self::ACTION_RESET_SUBMISSION,
        ], true);
    }

    /**
     * A human readable label of the action type.
     */
    public function actionTypeLabel(): string
    {
        return match ($this->action_type) {
            self::ACTION_SCORE_CORRECTION => 'Score Correction',
            self::ACTION_RESET_SCORES => 'Reset Scores',
            self::ACTION_RESET_SUBMISSION => 'Reset Submission',
            default => $this->action_type ?? $this->action,
        };
    }
}
