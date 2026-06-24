<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class ProfileTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_guest_cannot_open_profile(): void
    {
        $this->get('/profile')->assertRedirect('/auth/phone');
    }

    public function test_user_can_view_profile_page(): void
    {
        $user = $this->createClient(['name' => 'Жангир']);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Index')
                ->where('profile.name', 'Жангир')
                ->where('profile.phone_verified', true)
                ->where('profile.current_tariff', 'standard')
                ->where('profile.tariffs.standard.fee_percent', 0.5)
                ->where('profile.tariffs.subscription.fee_percent', 0.05));
    }

    public function test_user_can_update_name_and_email(): void
    {
        $user = $this->createClient(['email' => 'tg_123@exchange.local', 'phone' => '+77071234567']);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Нургалиев Жангир',
                'email' => 'client@example.com',
                'phone' => '+7 (707) 123-45-67',
            ])
            ->assertRedirect(route('profile.show'));

        $user->refresh();

        $this->assertSame('Нургалиев Жангир', $user->name);
        $this->assertSame('client@example.com', $user->email);
        $this->assertTrue($user->phone_verified);
    }

    public function test_changing_phone_resets_verification(): void
    {
        $user = $this->createClient(['phone' => '+77071234567']);
        $this->linkTelegram($user);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => '',
                'phone' => '+7 (747) 999-88-77',
            ])
            ->assertRedirect(route('profile.show'));

        $user->refresh();

        $this->assertSame('+77479998877', $user->phone);
        $this->assertFalse($user->phone_verified);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_phone_must_be_unique(): void
    {
        $this->createClient(['phone' => '+77071234567']);
        $other = $this->createClient(['phone' => '+77079998877']);

        $this->actingAs($other)
            ->patch('/profile', [
                'name' => $other->name,
                'email' => '',
                'phone' => '+7 (707) 123-45-67',
            ])
            ->assertSessionHasErrors('phone');
    }
}
