<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('weight', 6, 2); // percentage, e.g. 25.00
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'name']);
            $table->index(['category_id', 'is_active', 'display_order']);
        });

        Schema::create('finalists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('code')->unique(); // e.g. IG-001
            $table->string('name');
            $table->string('department');
            $table->string('project_title');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);

            // Private presentation file metadata
            $table->string('presentation_disk')->nullable();
            $table->string('presentation_path')->nullable();
            $table->string('presentation_original_name')->nullable();
            $table->string('presentation_mime_type')->nullable();
            $table->unsignedBigInteger('presentation_size')->nullable();
            $table->timestamp('presentation_uploaded_at')->nullable();
            $table->foreignId('presentation_uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['category_id', 'is_active', 'display_order']);
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('judge_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('finalist_id')->constrained('finalists')->cascadeOnDelete();
            $table->string('status')->default('draft'); // draft | submitted | locked
            $table->text('comment')->nullable();
            $table->decimal('total_score', 8, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('reopen_count')->default(0);
            $table->timestamps();

            $table->unique(['judge_id', 'category_id', 'finalist_id'], 'evaluations_unique_judge_category_finalist');
            $table->index(['category_id', 'status']);
            $table->index(['finalist_id', 'status']);
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 1..10
            $table->timestamps();

            $table->unique(['evaluation_id', 'criterion_id']);
        });

        Schema::create('competition_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('result_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique('category_id');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('result_releases');
        Schema::dropIfExists('competition_settings');
        Schema::dropIfExists('evaluation_scores');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('finalists');
        Schema::dropIfExists('criteria');
        Schema::dropIfExists('categories');
    }
};
