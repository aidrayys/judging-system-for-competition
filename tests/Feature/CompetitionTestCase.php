<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CompetitionSetting;
use App\Models\Criterion;
use App\Models\Evaluation;
use App\Models\Finalist;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class CompetitionTestCase extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected User $judge;

    /**
     * The five designated judges (Judge A to Judge E).
     *
     * @var array<string, User>
     */
    protected array $designatedJudges = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create(['name' => 'Idea Generation', 'slug' => 'idea-generation']);
        $this->seedRubric($this->category);

        $this->judge = $this->makeDesignatedJudge('judge_a', 'Judge A');
        $this->designatedJudges = [
            'judge_a' => $this->judge,
            'judge_b' => $this->makeDesignatedJudge('judge_b', 'Judge B'),
            'judge_c' => $this->makeDesignatedJudge('judge_c', 'Judge C'),
            'judge_d' => $this->makeDesignatedJudge('judge_d', 'Judge D'),
            'judge_e' => $this->makeDesignatedJudge('judge_e', 'Judge E'),
        ];

        $this->openJudging();
    }

    protected function makeDesignatedJudge(string $username, string $name): User
    {
        return User::factory()->judge()->create(['name' => $name, 'username' => $username]);
    }

    protected function seedRubric(Category $category): void
    {
        foreach (CategorySeeder::RUBRIC() as $criterion) {
            Criterion::create([
                'category_id' => $category->id,
                'name' => $criterion['name'],
                'description' => $criterion['description'],
                'weight' => $criterion['weight'],
                'display_order' => $criterion['order'],
                'is_active' => true,
            ]);
        }
    }

    protected function makeFinalist(?Category $category = null, bool $active = true): Finalist
    {
        return Finalist::factory()->create([
            'category_id' => ($category ?? $this->category)->id,
            'is_active' => $active,
        ]);
    }

    protected function openJudging(): void
    {
        CompetitionSetting::put('judging_open', '1');
    }

    /**
     * @return array<int, int> criterion_id => score
     */
    protected function fullScores(int $score = 4): array
    {
        return $this->category->activeCriteria()->pluck('id')->mapWithKeys(fn ($id) => [$id => $score])->all();
    }

    /**
     * Save and submit a complete evaluation for a judge and a finalist.
     */
    protected function submitEvaluation(User $judge, Finalist $finalist, int $score = 4): Evaluation
    {
        $category = $finalist->category;
        $criteria = $category->activeCriteria()->get();
        $scores = $criteria->pluck('id')->mapWithKeys(fn ($id) => [$id => $score])->all();

        $this->actingAs($judge)->postJson("/judge/category/{$category->id}/finalist/{$finalist->id}/autosave", [
            'scores' => $scores,
        ])->assertOk();

        $this->actingAs($judge)
            ->post("/judge/category/{$category->id}/finalist/{$finalist->id}/submit")
            ->assertRedirect();

        return Evaluation::where('judge_id', $judge->id)
            ->where('category_id', $category->id)
            ->where('finalist_id', $finalist->id)
            ->first();
    }
}
