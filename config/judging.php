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

];
