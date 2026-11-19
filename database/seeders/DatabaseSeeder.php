<?php

namespace Database\Seeders;

use App\Models\CompetitionSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * All seeders are idempotent: running `db:seed` repeatedly will not
     * create duplicate categories, criteria, judges, finalists or demo records.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            FinalistSeeder::class,
            JudgeSeeder::class,
            AdminSeeder::class,
            DemoDataSeeder::class,
        ]);

        if (CompetitionSetting::get('judging_open') === null) {
            CompetitionSetting::put('judging_open', '0');
        }
    }
}
