<?php

namespace App\Support;

/**
 * Canonical definition of the Judging System for Competition judging rubric.
 *
 * This is the single, central source for the six criteria, their weights and
 * their descriptions. It is used by:
 *  - Database\Seeders\CategorySeeder (initial data for both categories)
 *  - The judge evaluation screen (interpretation guide)
 *
 * Both categories always receive the same six criteria with the same weights.
 * The description text is stored per criterion row so that an administrator can
 * refine the wording per category from Admin > Rubric.
 */
class Rubric
{
    /**
     * @return array<int, array{name: string, description: string, weight: float, order: int}>
     */
    public static function criteria(): array
    {
        return [
            [
                'name' => 'Background & Method',
                'description' => 'Clarity of the problem or opportunity, supporting baseline data, and appropriateness of the analysis method.',
                'weight' => 10.0,
                'order' => 1,
            ],
            [
                'name' => 'Content & Presentation',
                'description' => 'Clarity, structure, completeness, and quality of the presentation and supporting evidence.',
                'weight' => 10.0,
                'order' => 2,
            ],
            [
                'name' => 'Collaboration (Scope)',
                'description' => 'Cross-functional collaboration, involvement of relevant stakeholders, and clarity of project scope.',
                'weight' => 20.0,
                'order' => 3,
            ],
            [
                'name' => 'Impact',
                'description' => 'Measurable benefits, supported by data, including cost savings, productivity, quality, safety, or service improvements where relevant.',
                'weight' => 20.0,
                'order' => 4,
            ],
            [
                'name' => 'Implementation',
                'description' => 'Practical implementation, evidence of execution, sustainability, and readiness for wider adoption.',
                'weight' => 15.0,
                'order' => 5,
            ],
            [
                'name' => 'Solution',
                'description' => 'Relevance, effectiveness, feasibility, and suitability of the proposed solution to the identified problem.',
                'weight' => 25.0,
                'order' => 6,
            ],
        ];
    }

    /**
     * General scoring guide shown to the judges. It is guidance only and is
     * deliberately NOT a set of additional scoring criteria.
     *
     * @return array<int, array{range: string, label: string, min: int, max: int}>
     */
    public static function interpretationGuide(): array
    {
        return [
            ['range' => '1', 'label' => 'Very Poor', 'min' => 1, 'max' => 1],
            ['range' => '2', 'label' => 'Poor', 'min' => 2, 'max' => 2],
            ['range' => '3', 'label' => 'Fair', 'min' => 3, 'max' => 3],
            ['range' => '4', 'label' => 'Good', 'min' => 4, 'max' => 4],
            ['range' => '5', 'label' => 'Very Good', 'min' => 5, 'max' => 5],
        ];
    }

    public static function labelForScore(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        foreach (self::interpretationGuide() as $band) {
            if ($score >= $band['min'] && $score <= $band['max']) {
                return $band['label'];
            }
        }

        return null;
    }
}
