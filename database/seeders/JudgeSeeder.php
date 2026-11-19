<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the five DEMO judge accounts (Judge A - Judge E).
 *
 * The password is a documented, demo-only placeholder shared by all judges
 * (override with JUDGING_JUDGE_PASSWORD). Never reuse it outside a demo.
 */
class JudgeSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = 'Judge@Demo2026';

    public const JUDGES = [
        ['name' => 'Judge A', 'username' => 'judge_a'],
        ['name' => 'Judge B', 'username' => 'judge_b'],
        ['name' => 'Judge C', 'username' => 'judge_c'],
        ['name' => 'Judge D', 'username' => 'judge_d'],
        ['name' => 'Judge E', 'username' => 'judge_e'],
    ];

    public function run(): void
    {
        $password = env('JUDGING_JUDGE_PASSWORD') ?: self::DEFAULT_PASSWORD;

        foreach (self::JUDGES as $judge) {
            $user = User::firstOrNew(['username' => $judge['username']]);

            $user->name = $judge['name'];
            $user->role = User::ROLE_JUDGE;
            $user->is_active = true;

            // Only set the password for a brand new account so that a reset
            // done by the administrator is never overwritten by re-seeding.
            if (! $user->exists) {
                $user->password = Hash::make($password);
            }

            $user->save();
        }

        $this->command?->info('Demo judge accounts are ready (judge_a ... judge_e).');
    }
}
