<?php

namespace Database\Seeders;

use App\Models\CompetitionSetting;
use App\Models\EligibleVoter;
use App\Models\Evaluation;
use App\Models\EvaluationScore;
use App\Models\Finalist;
use App\Models\User;
use App\Models\VotingBallot;
use App\Models\VotingPeriod;
use App\Models\VotingVote;
use App\Services\ScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fully synthetic demo records: evaluations (submitted, draft and not started),
 * comments, voting codes, a live voting period and ballots.
 *
 * Deterministic (fixed seed) and idempotent: it does nothing when evaluations
 * already exist, so re-running `db:seed` never duplicates data. Nothing here
 * is derived from real people, real scores or real voting codes.
 */
class DemoDataSeeder extends Seeder
{
    /** Unused voting codes people can try in the demo (documented in README). */
    public const SAMPLE_UNUSED_CODES = ['10061', '10062', '10063', '10064', '10065'];

    /** Base quality per finalist (average judge score, 1..5 scale). */
    private const QUALITY = [
        'IG-001' => 4.4, 'IG-002' => 4.1, 'IG-003' => 3.7, 'IG-004' => 4.3, 'IG-005' => 3.4,
        'IG-006' => 3.9, 'IG-007' => 3.1, 'IG-008' => 3.5, 'IG-009' => 3.6, 'IG-010' => 2.9,
        'FS-001' => 4.2, 'FS-002' => 3.8, 'FS-003' => 4.0, 'FS-004' => 3.3, 'FS-005' => 3.6,
    ];

    private const COMMENTS = [
        'high' => [
            'Clear problem statement and a convincing, data-backed business case. Strong cross-functional ownership.',
            'Excellent presentation. The pilot results make the benefits tangible and the rollout plan is realistic.',
            'Very practical solution with measurable impact. Ready to scale with minimal additional investment.',
        ],
        'mid' => [
            'Good idea with a sensible approach. Quantifying the expected savings more precisely would strengthen it.',
            'Solid solution, but the implementation plan needs more detail on ownership and timeline.',
            'Relevant problem and reasonable method. Collaboration with other teams could be clearer.',
        ],
        'low' => [
            'Interesting direction, but the baseline data is thin and the impact is hard to verify.',
            'The concept needs a clearer scope and stronger evidence before it can be recommended for rollout.',
            'Presentation was unclear in places; the solution is not yet proven against alternatives.',
        ],
    ];

    public function run(ScoringService $scoring): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoDataSeeder refuses to run in production.');

            return;
        }

        if (Evaluation::query()->exists()) {
            $this->command?->info('Demo evaluations already exist - skipping demo data.');

            return;
        }

        mt_srand(20260101);

        DB::transaction(function () use ($scoring) {
            $this->seedEvaluations($scoring);
            $this->seedVoting();
            CompetitionSetting::put('judging_open', '1');
            CompetitionSetting::put('judging_opened_at', now()->subDays(2)->toDateTimeString());
        });

        $this->command?->info('Synthetic demo data created.');
    }

    private function seedEvaluations(ScoringService $scoring): void
    {
        $judges = User::where('role', User::ROLE_JUDGE)->orderBy('username')->get()->values();
        $finalists = Finalist::with('category.activeCriteria')->orderBy('id')->get();

        foreach ($judges as $judgeIndex => $judge) {
            foreach ($finalists as $finalistIndex => $finalist) {
                $criteria = $finalist->category->activeCriteria;

                // Judge E (index 4) leaves some work in progress to showcase status tracking.
                $mode = 'submitted';
                if ($judgeIndex === 4) {
                    $mode = $finalistIndex >= 13 ? 'none' : ($finalistIndex >= 10 ? 'draft' : 'submitted');
                }
                if ($judgeIndex === 3 && $finalistIndex === 14) {
                    $mode = 'draft';
                }

                if ($mode === 'none') {
                    continue;
                }

                $base = self::QUALITY[$finalist->code] ?? 3.5;
                $scores = [];
                foreach ($criteria as $criterion) {
                    $noise = (mt_rand(-100, 100) / 100) * 0.9;
                    $scores[$criterion->id] = max(1, min(5, (int) round($base + $noise)));
                }

                $evaluation = new Evaluation([
                    'judge_id' => $judge->id,
                    'category_id' => $finalist->category_id,
                    'finalist_id' => $finalist->id,
                    'status' => $mode === 'submitted' ? Evaluation::STATUS_SUBMITTED : Evaluation::STATUS_DRAFT,
                    'reopen_count' => 0,
                ]);

                if ($mode === 'submitted') {
                    $evaluation->total_score = $scoring->weightedTotal($criteria, $scores);
                    $evaluation->submitted_at = Carbon::now('UTC')->subDays(1)->subMinutes(mt_rand(5, 900));
                    $tier = $evaluation->total_score >= 80 ? 'high' : ($evaluation->total_score >= 62 ? 'mid' : 'low');
                    $pool = self::COMMENTS[$tier];
                    $evaluation->comment = $pool[mt_rand(0, count($pool) - 1)];
                } else {
                    // A draft keeps only some criteria scored and has no total yet.
                    $scores = array_slice($scores, 0, 3, true);
                    $evaluation->total_score = null;
                    $evaluation->comment = null;
                }

                $evaluation->save();

                foreach ($scores as $criterionId => $score) {
                    EvaluationScore::create([
                        'evaluation_id' => $evaluation->id,
                        'criterion_id' => $criterionId,
                        'score' => $score,
                    ]);
                }
            }
        }
    }

    private function seedVoting(): void
    {
        $departments = ['Department A', 'Department B', 'Department C', 'Department D'];

        // 65 synthetic voting codes: 10001..10065 (5 digits, never starting with 0).
        $voters = [];
        for ($i = 1; $i <= 65; $i++) {
            $voters[] = EligibleVoter::updateOrCreate(
                ['kode_unik' => (string) (10000 + $i)],
                ['name' => sprintf('Voter %03d', $i), 'department' => $departments[($i - 1) % 4]]
            );
        }

        VotingPeriod::create([
            'opens_at' => Carbon::now('UTC')->subDays(1),
            'closes_at' => Carbon::now('UTC')->addDays(30),
            'is_open' => true,
        ]);

        $finalists = Finalist::orderBy('id')->get();
        // Popularity weights per finalist (index aligned with id order).
        $weights = [9, 7, 4, 8, 3, 5, 2, 3, 4, 1, 6, 4, 5, 2, 3];
        $pool = [];
        foreach ($finalists as $index => $finalist) {
            $pool = array_merge($pool, array_fill(0, $weights[$index] ?? 2, $finalist->id));
        }

        // The first 60 voters have voted; codes 10061..10065 stay unused for the live demo.
        foreach (array_slice($voters, 0, 60) as $voter) {
            $picks = [$pool[mt_rand(0, count($pool) - 1)]];
            if (mt_rand(1, 100) <= 65) {
                $second = $pool[mt_rand(0, count($pool) - 1)];
                if ($second !== $picks[0]) {
                    $picks[] = $second;
                }
            }

            $ballot = VotingBallot::create([
                'eligible_voter_id' => $voter->id,
                'submitted_at' => Carbon::now('UTC')->subHours(mt_rand(1, 20))->subMinutes(mt_rand(0, 59)),
                'ip_address' => '203.0.113.'.mt_rand(1, 254), // TEST-NET-3 documentation range
            ]);

            foreach ($picks as $finalistId) {
                VotingVote::create(['voting_ballot_id' => $ballot->id, 'finalist_id' => $finalistId, 'count' => 1]);
            }
        }
    }
}
