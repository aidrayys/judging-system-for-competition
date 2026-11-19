<?php
namespace Database\Factories;
use App\Models\EligibleVoter;
use Illuminate\Database\Eloquent\Factories\Factory;
class EligibleVoterFactory extends Factory {
    protected $model = EligibleVoter::class;
    public function definition(): array {
        return [
            'nik' => null,
            'kode_unik' => (string) fake()->unique()->numberBetween(10000, 99999),
            'name' => fake()->name(),
            'department' => fake()->randomElement(['Department A','Department B','Department C','Department D']),
        ];
    }
}
