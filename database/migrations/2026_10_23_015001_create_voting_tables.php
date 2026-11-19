<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligible_voters', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 8)->unique();
            $table->string('name')->nullable();
            $table->string('department')->nullable();
            $table->string('pin_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('voting_periods', function (Blueprint $table) {
            $table->id();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->boolean('is_open')->default(false);
            $table->timestamps();
        });

        Schema::create('voting_ballots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eligible_voter_id')->constrained('eligible_voters')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->unique('eligible_voter_id');
        });

        Schema::create('voting_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voting_ballot_id')->constrained('voting_ballots')->cascadeOnDelete();
            $table->foreignId('finalist_id')->constrained('finalists')->cascadeOnDelete();
            $table->unsignedTinyInteger('count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voting_votes');
        Schema::dropIfExists('voting_ballots');
        Schema::dropIfExists('voting_periods');
        Schema::dropIfExists('eligible_voters');
    }
};
