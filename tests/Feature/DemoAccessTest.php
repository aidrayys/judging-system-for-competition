<?php

namespace Tests\Feature;

use Database\Seeders\AdminSeeder;
use Database\Seeders\JudgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccessTest extends TestCase
{
    use RefreshDatabase;

    private function seedAccounts(): void
    {
        $this->seed([AdminSeeder::class, JudgeSeeder::class]);
    }

    public function test_login_page_shows_demo_access_with_empty_fields(): void
    {
        $response = $this->get('/login');

        $response->assertOk()
            ->assertSee('Demo Access')
            ->assertSee('Administrator')
            ->assertSee('Demo accounts are provided for exploration purposes.')
            ->assertSee('data-copy-target="demoAdminUser"', false)
            ->assertSee('data-copy-target="demoAdminPass"', false)
            ->assertSee('data-copy-target="demoJudgeUser"', false)
            ->assertSee('data-copy-target="demoJudgePass"', false);

        // No prefilled credentials in the inputs.
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/id="username"[^>]*value=""/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="password"[^>]*value=/', $html);
    }

    public function test_displayed_admin_credentials_log_in_and_keep_admin_permissions(): void
    {
        $this->seedAccounts();
        $admin = config('judging.demo_accounts.admin');

        $this->get('/login')->assertSee($admin['username'])->assertSee($admin['password']);

        $this->post('/login', ['username' => $admin['username'], 'password' => $admin['password']])
            ->assertRedirect();
        $this->get('/admin')->assertOk();
    }

    public function test_displayed_judge_credentials_log_in_and_stay_blocked_from_admin(): void
    {
        $this->seedAccounts();
        $judges = config('judging.demo_accounts.judges');

        foreach ($judges['accounts'] as $judge) {
            $this->get('/login')->assertSee($judge['username']);
        }
        $this->get('/login')->assertSee($judges['password']);

        $first = $judges['accounts'][0];
        $this->post('/login', ['username' => $first['username'], 'password' => $judges['password']])
            ->assertRedirect();
        $this->get('/judge')->assertOk();
        $this->get('/admin')->assertForbidden();
    }

    public function test_demo_access_can_be_switched_off(): void
    {
        config(['judging.demo_access' => false]);

        $this->get('/login')->assertOk()->assertDontSee('Demo Access');
    }
}
