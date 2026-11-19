<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Finalist;
use Illuminate\Database\Eloquent\Factories\Factory;

class FinalistFactory extends Factory
{
    protected $model = Finalist::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'code' => strtoupper(fake()->unique()->bothify('??-###')),
            'name' => fake()->name(),
            'department' => fake()->randomElement(['Department A', 'Department B', 'Department C', 'Department D']),
            'project_title' => fake()->sentence(6),
            'display_order' => 1,
            'is_active' => true,
        ];
    }
}
