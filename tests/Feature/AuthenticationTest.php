<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthenticationTest extends CompetitionTestCase
{
    public function test_admin_can_log_in(): void
    {
        $admin = User::factory()->admin()->create([
            'username' => 'admin',
            'password' => Hash::make('SecretPass123'),
        ]);

        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'SecretPass123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_judge_can_log_in(): void
    {
        $response = $this->post('/login', [
            'username' => $this->judge->username,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($this->judge);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->from('/login')->post('/login', [
            'username' => $this->judge->username,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_judge_cannot_log_in(): void
    {
        $this->judge->update(['is_active' => false]);

        $response = $this->from('/login')->post('/login', [
            'username' => $this->judge->username,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/judge')->assertRedirect('/login');
    }

    public function test_judge_cannot_access_admin_routes(): void
    {
        $this->actingAs($this->judge)->get('/admin')->assertForbidden();
        $this->actingAs($this->judge)->get('/admin/results')->assertForbidden();
    }

    public function test_admin_cannot_access_judge_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/judge')->assertForbidden();
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs($this->judge)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
