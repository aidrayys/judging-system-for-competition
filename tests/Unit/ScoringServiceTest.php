<?php

namespace Tests\Unit;

use App\Models\Criterion;
use App\Services\ScoringService;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    public function test_weighted_total_uses_the_documented_formula(): void
    {
        $service = new ScoringService;

        $criteria = collect([
            $this->criterion(1, 'Background & Method', 10.0),
            $this->criterion(2, 'Content & Presentation', 10.0),
            $this->criterion(3, 'Collaboration (Scope)', 20.0),
            $this->criterion(4, 'Impact', 20.0),
            $this->criterion(5, 'Implementation', 15.0),
            $this->criterion(6, 'Solution', 25.0),
        ]);

        // Scale 1..5: all scores 5 => 100.00
        $this->assertEqualsWithDelta(100.0, $service->weightedTotal($criteria, [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 5]), 0.001);

        // All scores 1 => 20.00
        $this->assertEqualsWithDelta(20.0, $service->weightedTotal($criteria, [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1]), 0.001);

        // All scores 3 => 60.00
        $this->assertEqualsWithDelta(60.0, $service->weightedTotal($criteria, [1 => 3, 2 => 3, 3 => 3, 4 => 3, 5 => 3, 6 => 3]), 0.001);

        // Mixed: (5/5*10)+(4/5*10)+(3/5*20)+(2/5*20)+(1/5*15)+(5/5*25) = 10+8+12+8+3+25 = 66.00
        $this->assertEqualsWithDelta(66.0, $service->weightedTotal($criteria, [1 => 5, 2 => 4, 3 => 3, 4 => 2, 5 => 1, 6 => 5]), 0.001);

        // Values outside 1..5 (e.g. legacy 1..10 values) never contribute.
        $this->assertEqualsWithDelta(0.0, $service->weightedTotal($criteria, [1 => 6, 2 => 10, 3 => 0]), 0.001);
        $this->assertFalse($service->isValidScore(6));
        $this->assertTrue($service->isValidScore(5));
    }

    public function test_missing_criteria_do_not_contribute(): void
    {
        $service = new ScoringService;

        $criteria = collect([
            $this->criterion(1, 'A', 50.0),
            $this->criterion(2, 'B', 50.0),
        ]);

        $this->assertEqualsWithDelta(50.0, $service->weightedTotal($criteria, [1 => 5]), 0.001);
    }

    private function criterion(int $id, string $name, float $weight): Criterion
    {
        $criterion = new Criterion;
        $criterion->id = $id;
        $criterion->name = $name;
        $criterion->weight = $weight;

        return $criterion;
    }
}
