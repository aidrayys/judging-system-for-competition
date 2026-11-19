<?php

/**
 * Test fixture for the browser end-to-end test.
 *
 * Creates a self-contained data set: a judge with a known password, a
 * category with the six judging criteria, one finalist and an open judging
 * period. Run against the throw-away database of the browser test.
 *
 * Usage: php artisan tinker --execute="include base_path('tests/Support/browser_fixture.php');"
 */

use App\Models\Category;
use App\Models\CompetitionSetting;
use App\Models\Criterion;
use App\Models\Finalist;
use App\Models\User;
use App\Support\Rubric;

$judge = User::updateOrCreate(
    ['username' => 'browserjudge'],
    [
        'name' => 'Browser Judge',
        'role' => User::ROLE_JUDGE,
        'is_active' => true,
        'password' => 'BrowserTest123!',
    ]
);

$category = Category::updateOrCreate(
    ['slug' => 'idea-generation'],
    [
        'name' => 'Idea Generation',
        'description' => 'Browser test category',
        'is_active' => true,
        'display_order' => 1,
    ]
);

foreach (Rubric::criteria() as $criterion) {
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

$finalist = Finalist::updateOrCreate(
    ['code' => 'IG-001'],
    [
        'category_id' => $category->id,
        'name' => 'Participant 001',
        'department' => 'Department A',
        'project_title' => 'Smart Energy Monitoring System',
        'display_order' => 1,
        'is_active' => true,
    ]
);

CompetitionSetting::put('judging_open', '1');

echo 'fixture ready: judge='.$judge->username.' category='.$category->id.' finalist='.$finalist->id.PHP_EOL;
