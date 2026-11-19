<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finalists', function (Blueprint $table) {
            $table->unsignedTinyInteger('winner_rank')->nullable()->after('is_active')->comment('1=Rank 1, 2=Rank 2, 3=Rank 3');
        });
    }

    public function down(): void
    {
        Schema::table('finalists', function (Blueprint $table) {
            $table->dropColumn('winner_rank');
        });
    }
};
