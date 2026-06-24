<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_staff_can_authenticate_using_the_login_screen(): void
    {
        $user = $this->createStaff('super_admin', [
            'email' => 'admin@exchange.local',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/admin');
    }

    public function test_client_cannot_use_staff_login(): void
    {
        $user = $this->createClient([
            'email' => 'client@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = $this->createStaff('super_admin', [
            'email' => 'admin@exchange.local',
            'password' => bcrypt('password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = $this->createStaff('super_admin');

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
