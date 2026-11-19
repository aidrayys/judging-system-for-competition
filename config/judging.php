<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Designated judges
    |--------------------------------------------------------------------------
    |
    | The five judges designated for the Innovation Challenge 2026. Only evaluations submitted by
    | these accounts are counted for the official ranking. The key is the
    | login username, the value is the display name used when the account
    | cannot be found by username (for example after a rename).
    |
    */

    'designated_judges' => [
        'judge_a' => 'Judge A',
        'judge_b' => 'Judge B',
        'judge_c' => 'Judge C',
        'judge_d' => 'Judge D',
        'judge_e' => 'Judge E',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ranking policy
    |--------------------------------------------------------------------------
    |
    | "competition" - standard competition ranking. Equal averages share a
    | rank and the next rank skips accordingly (1, 1, 3). Ties are flagged
    | for administrator review because no tie-break rule is configured.
    |
    */

    'ranking_method' => 'competition',

    /*
    |--------------------------------------------------------------------------
    | Demo access (login page)
    |--------------------------------------------------------------------------
    |
    | When enabled, the login page lists the seeded DEMO accounts so visitors
    | can explore the app. The values mirror what the seeders create (same
    | env overrides and defaults). Set DEMO_ACCESS=false for any non-demo use.
    |
    */

    'demo_access' => (bool) env('DEMO_ACCESS', true),

    'demo_accounts' => [
        'admin' => [
            'username' => env('APP_ADMIN_USERNAME', 'admin'),
            'password' => env('APP_ADMIN_PASSWORD') ?: \Database\Seeders\AdminSeeder::DEFAULT_PASSWORD,
        ],
        'judges' => [
            'password' => env('JUDGING_JUDGE_PASSWORD') ?: \Database\Seeders\JudgeSeeder::DEFAULT_PASSWORD,
            'accounts' => \Database\Seeders\JudgeSeeder::JUDGES,
        ],
    ],

];
