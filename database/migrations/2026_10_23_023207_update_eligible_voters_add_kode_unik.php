<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eligible_voters', function (Blueprint $table) {
            $table->string('nik', 8)->nullable()->change();
            $table->dropUnique(['nik']);
            $table->string('kode_unik', 5)->nullable()->unique()->after('nik');
        });
    }

    public function down(): void
    {
        Schema::table('eligible_voters', function (Blueprint $table) {
            $table->dropColumn('kode_unik');
            $table->string('nik', 8)->unique(false)->change();
            $table->string('nik', 8)->unique()->change();
        });
    }
};
