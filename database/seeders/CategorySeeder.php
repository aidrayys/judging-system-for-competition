<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Criterion;
use App\Support\Rubric;
use Illuminate\Database\Seeder;

/**
 * The unified rubric used by BOTH categories.
 * Each category receives its own criterion records so rubrics can be managed
 * independently, but the initial names, order and weights are identical.
 */
class CategorySeeder extends Seeder
{
    /**
     * Kept for backwards compatibility - the canonical definition now lives
     * in App\Support\Rubric.
     *
     * @return array<int, array{name: string, description: string, weight: float, order: int}>
     */
    public static function RUBRIC(): array
    {
        return Rubric::criteria();
    }

    public const CATEGORIES = [
        [
            'name' => 'Idea Generation',
            'slug' => 'idea-generation',
            'description' => 'Improvement and innovation ideas presented by the finalists.',
            'order' => 1,
        ],
        [
            'name' => 'Feasibility Study',
            'slug' => 'feasibility-study',
            'description' => 'Feasibility studies presented by the finalists.',
            'order' => 2,
        ],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $data) {
            $category = Category::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'display_order' => $data['order'],
                    'is_active' => true,
                ]
            );

            foreach (self::RUBRIC() as $criterion) {
                Criterion::updateOrCreate(
                    ['category_id' => $category->id, 'name' => $criterion['name']],
                    [
                        'description' => $criterion['description'],
                        'weight' => $criterion['weight'],
                        'display_order' => $criterion['order'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
