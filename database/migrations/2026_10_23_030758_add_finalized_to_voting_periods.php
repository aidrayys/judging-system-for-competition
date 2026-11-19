<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voting_periods', function (Blueprint $table) {
            $table->boolean('is_finalized')->default(false)->after('is_open');
            $table->timestamp('finalized_at')->nullable()->after('is_finalized');
        });
    }

    public function down(): void
    {
        Schema::table('voting_periods', function (Blueprint $table) {
            $table->dropColumn(['is_finalized', 'finalized_at']);
        });
    }
};
