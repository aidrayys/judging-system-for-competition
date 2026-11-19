<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Criterion;
use Illuminate\Database\Eloquent\Factories\Factory;

class CriterionFactory extends Factory
{
    protected $model = Criterion::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->words(2, true),
            'weight' => 10,
            'display_order' => 1,
            'is_active' => true,
        ];
    }
}
