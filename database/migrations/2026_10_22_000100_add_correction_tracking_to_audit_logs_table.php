<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing audit log with the structured information that is
 * required for evaluation corrections and resets.
 *
 * The historical rows are never touched: the new columns are nullable, so
 * every existing audit entry keeps working exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // The evaluation that was corrected or reset.
            $table->unsignedBigInteger('evaluation_id')->nullable()->after('subject_id')->index();

            // The judge who originally produced the evaluation. An admin
            // correction never changes the evaluator identity.
            $table->foreignId('original_judge_id')->nullable()->after('evaluation_id')->constrained('users')->nullOnDelete();

            // score_correction | reset_scores | reset_submission
            $table->string('action_type')->nullable()->after('action');

            // judge | admin
            $table->string('actor_role')->nullable()->after('action_type');

            // Criterion id => score, before and after the action.
            $table->json('previous_scores')->nullable();
            $table->json('new_scores')->nullable();

            $table->decimal('previous_total', 8, 2)->nullable();
            $table->decimal('new_total', 8, 2)->nullable();

            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();

            $table->text('previous_comment')->nullable();
            $table->text('new_comment')->nullable();

            // Mandatory for admin actions, optional for a judge.
            $table->text('reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['evaluation_id']);
            $table->dropConstrainedForeignId('original_judge_id');
            $table->dropColumn([
                'evaluation_id',
                'action_type',
                'actor_role',
                'previous_scores',
                'new_scores',
                'previous_total',
                'new_total',
                'previous_status',
                'new_status',
                'previous_comment',
                'new_comment',
                'reason',
            ]);
        });
    }
};
