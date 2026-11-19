<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Criterion;
use App\Models\Finalist;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function runSeeder(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeding_twice_does_not_create_duplicates(): void
    {
        $this->runSeeder();
        $this->runSeeder();

        $this->assertSame(2, Category::count());
        $this->assertSame(12, Criterion::count());
        $this->assertSame(15, Finalist::count());
        $this->assertSame(5, User::where('role', User::ROLE_JUDGE)->count());
        $this->assertSame(1, User::where('role', User::ROLE_ADMIN)->count());
    }

    public function test_both_categories_use_the_same_six_criteria_and_weights(): void
    {
        $this->runSeeder();

        $categories = Category::orderBy('display_order')->get();
        $this->assertCount(2, $categories);

        $rubrics = [];
        foreach ($categories as $category) {
            $rubrics[] = $category->activeCriteria()->get()
                ->map(fn (Criterion $criterion) => [
                    'name' => $criterion->name,
                    'weight' => (float) $criterion->weight,
                    'order' => $criterion->display_order,
                ])->all();
        }

        $this->assertCount(6, $rubrics[0]);
        $this->assertEquals($rubrics[0], $rubrics[1]);

        $this->assertSame(
            ['Background & Method', 'Content & Presentation', 'Collaboration (Scope)', 'Impact', 'Implementation', 'Solution'],
            array_column($rubrics[0], 'name')
        );

        $this->assertSame([10.0, 10.0, 20.0, 20.0, 15.0, 25.0], array_column($rubrics[0], 'weight'));
        $this->assertSame(100.0, array_sum(array_column($rubrics[0], 'weight')));
    }

    public function test_the_demo_finalists_are_seeded(): void
    {
        $this->runSeeder();

        $this->assertSame(10, Finalist::where('code', 'like', 'IG-%')->count());
        $this->assertSame(5, Finalist::where('code', 'like', 'FS-%')->count());

        $this->assertDatabaseHas('finalists', [
            'code' => 'IG-001',
            'name' => 'Participant 001',
            'department' => 'Department A',
            'project_title' => 'Smart Energy Monitoring System',
        ]);

        $this->assertDatabaseHas('finalists', [
            'code' => 'FS-005',
            'name' => 'Participant 015',
            'department' => 'Department C',
            'project_title' => 'Automated Tool Crib Kiosk',
        ]);
    }

    public function test_judges_are_seeded_with_separate_accounts(): void
    {
        $this->runSeeder();

        $judges = User::where('role', User::ROLE_JUDGE)->pluck('name')->sort()->values()->all();

        $this->assertSame(['Judge A', 'Judge B', 'Judge C', 'Judge D', 'Judge E'], $judges);

        $usernames = User::where('role', User::ROLE_JUDGE)->pluck('username');
        $this->assertCount(5, $usernames->unique());
    }
}
