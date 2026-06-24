<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class RequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function logPath(string $channel): string
    {
        return storage_path('logs/'.$channel.'-'.date('Y-m-d').'.log');
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['auth', 'http', 'errors'] as $channel) {
            $path = $this->logPath($channel);

            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    public function test_tracked_auth_route_is_logged_to_http_channel(): void
    {
        $this->get('/auth/phone')->assertOk();

        $log = File::get($this->logPath('http'));

        $this->assertStringContainsString('GET /auth/phone 200', $log);
        $this->assertStringContainsString('request_id', $log);
    }

    public function test_authenticated_home_request_is_logged(): void
    {
        $user = User::factory()->create([
            'phone_verified' => true,
            'kyc_status' => 'approved',
        ]);

        $this->actingAs($user)->get('/home')->assertOk();

        $log = File::get($this->logPath('http'));

        $this->assertStringContainsString('GET /home 200', $log);
        $this->assertStringContainsString('"user_id":'.$user->id, $log);
    }

    public function test_login_event_is_logged_to_auth_channel(): void
    {
        $user = User::factory()->create();

        auth()->login($user);

        $log = File::get($this->logPath('auth'));

        $this->assertStringContainsString('auth.login', $log);
        $this->assertStringContainsString('"user_id":'.$user->id, $log);
    }
}
