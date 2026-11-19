<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags an officially released result as "needs review" when a correction or
 * a reset changed an evaluation of that category. The administrator has to
 * confirm the republication before the official result is valid again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_releases', function (Blueprint $table) {
            $table->boolean('review_required')->default(false)->after('note');
            $table->timestamp('reviewed_at')->nullable()->after('review_required');
        });
    }

    public function down(): void
    {
        Schema::table('result_releases', function (Blueprint $table) {
            $table->dropColumn(['review_required', 'reviewed_at']);
        });
    }
};
