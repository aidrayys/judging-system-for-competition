<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Voting rule v2: one voting code may give at most ONE vote to a given idea.
 *
 * Adds a DB-level guarantee that a ballot cannot contain two rows for the
 * same finalist. Purely additive: no existing rows are modified or deleted.
 * The migration aborts (without changing anything) if historical data
 * already violates the constraint, so an admin can decide what to do.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('voting_votes')
            ->select('voting_ballot_id', 'finalist_id')
            ->groupBy('voting_ballot_id', 'finalist_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            throw new RuntimeException(
                "Migration aborted: {$duplicates} ballot/finalist pair(s) already have duplicate rows. "
                .'Historical data was NOT changed. Review voting_votes manually before re-running.'
            );
        }

        Schema::table('voting_votes', function (Blueprint $table) {
            $table->unique(['voting_ballot_id', 'finalist_id'], 'voting_votes_ballot_finalist_unique');
        });
    }

    public function down(): void
    {
        Schema::table('voting_votes', function (Blueprint $table) {
            $table->dropUnique('voting_votes_ballot_finalist_unique');
        });
    }
};
