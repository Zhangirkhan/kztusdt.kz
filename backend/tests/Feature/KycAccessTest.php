<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class KycAccessTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_home_redirects_to_kyc_without_approved_kyc(): void
    {
        $user = $this->createUnverifiedClient();

        $this->actingAs($user)->get('/home')->assertRedirect(route('kyc'));
    }

    public function test_home_redirects_to_wallet_with_approved_kyc(): void
    {
        $user = $this->createClient();

        $this->actingAs($user)->get('/home')->assertRedirect(route('wallet'));
    }

    public function test_protected_sections_require_approved_kyc(): void
    {
        $user = $this->createUnverifiedClient();

        foreach (['/wallet', '/wallet/history', '/exchange', '/withdraw', '/profile/bank'] as $uri) {
            $this->actingAs($user)->get($uri)->assertRedirect(route('kyc'));
        }
    }

    public function test_profile_sections_stay_available_without_kyc(): void
    {
        $user = $this->createUnverifiedClient();

        foreach (['/profile', '/profile/personal', '/profile/security', '/profile/language', '/profile/notifications', '/profile/support', '/kyc'] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }

    public function test_pending_kyc_user_gets_pending_message_on_wallet(): void
    {
        $user = $this->createUnverifiedClient(['kyc_status' => 'pending_review']);

        $this->actingAs($user)
            ->get('/wallet')
            ->assertRedirect(route('kyc'))
            ->assertSessionHasErrors(['form' => 'Кошелёк откроется после одобрения KYC. Заявка уже на проверке.']);
    }
}
