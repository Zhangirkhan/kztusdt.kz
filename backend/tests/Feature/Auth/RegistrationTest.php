<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_routes_are_disabled(): void
    {
        $get = $this->get('/register');
        $this->assertTrue(
            in_array($get->status(), [404, 405, 301, 302], true),
            'Registration page must not be publicly available',
        );

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
    }
}
