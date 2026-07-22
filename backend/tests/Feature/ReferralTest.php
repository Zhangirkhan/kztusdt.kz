<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthSession;
use App\Models\User;
use App\Models\UserReferralBenefit;
use App\Services\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class ReferralTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const VALID_IIN = '900101100014';

    private const OTP_CODE = '123456';

    /** @var array<string, string> */
    private array $otpCodesByPhone = [];

    private function fakeOtp(): void
    {
        config(['otp.token' => 'test-otp-token']);
        $this->otpCodesByPhone = [];

        Http::fake([
            '*/api/otp/send' => function ($request) {
                $phone = (string) $request['phone'];
                $this->otpCodesByPhone[$phone] = self::OTP_CODE;

                return Http::response([
                    'success' => true,
                    'message' => 'OTP отправлен на WhatsApp',
                    'expires_in' => 300,
                ]);
            },
            '*/api/otp/verify' => function ($request) {
                $phone = (string) $request['phone'];
                $code = (string) $request['code'];

                if (($this->otpCodesByPhone[$phone] ?? '') === $code) {
                    return Http::response([
                        'success' => true,
                        'message' => 'Номер подтверждён',
                    ]);
                }

                return Http::response([
                    'success' => false,
                    'message' => 'Неверный или просроченный код',
                ], 422);
            },
        ]);
    }

    private function registerNewUser(string $phone, ?string $referralCookie = null): User
    {
        $this->fakeOtp();

        $start = $this->withCookie('referral_code', $referralCookie ?? '')
            ->postJson('/api/auth/phone/start', array_filter([
                'client_type' => 'individual',
                'iin' => self::VALID_IIN,
                'phone' => $phone,
                'ref' => $referralCookie,
            ], fn ($value) => $value !== null && $value !== ''));

        $loginCode = (string) $start->json('login_code');

        $this->withCookie('referral_code', $referralCookie ?? '')
            ->postJson("/api/auth/phone/verify/{$loginCode}", ['code' => self::OTP_CODE])
            ->assertOk();

        $userId = AuthSession::query()->where('login_code', $loginCode)->value('user_id');

        return User::query()->findOrFail($userId);
    }

    public function test_auth_phone_page_stores_referral_cookie(): void
    {
        $referrer = $this->createClient();
        $referrer->forceFill(['referral_code' => 'REFCODE1'])->save();

        $this->get('/ru/auth/phone?ref=REFCODE1')
            ->assertOk()
            ->assertCookie('referral_code', 'REFCODE1', false);
    }

    public function test_new_user_is_linked_to_referrer(): void
    {
        $referrer = $this->createClient();
        $referrer->forceFill(['referral_code' => 'REFCODE1'])->save();

        $this->fakeOtp();

        $loginCode = $this->withCookie('referral_code', 'REFCODE1')
            ->postJson('/api/auth/phone/start', [
                'client_type' => 'individual',
                'iin' => self::VALID_IIN,
                'phone' => '+77071112233',
                'ref' => 'REFCODE1',
            ])
            ->json('login_code');

        $this->assertDatabaseHas('auth_sessions', [
            'login_code' => $loginCode,
            'referred_by_user_id' => $referrer->id,
        ]);

        $this->withCookie('referral_code', 'REFCODE1')
            ->postJson("/api/auth/phone/verify/{$loginCode}", ['code' => self::OTP_CODE])
            ->assertOk();

        $userId = AuthSession::query()->where('login_code', $loginCode)->value('user_id');
        $referral = User::query()->findOrFail($userId);

        $this->assertSame($referrer->id, $referral->referred_by_user_id);
    }

    public function test_existing_user_login_does_not_change_referrer(): void
    {
        $referrer = $this->createClient();
        $referrer->forceFill(['referral_code' => 'REFCODE1'])->save();

        $otherReferrer = $this->createClient(['phone' => '+77072223344']);
        $otherReferrer->forceFill(['referral_code' => 'REFCODE2'])->save();

        $user = $this->createUnverifiedClient(['phone' => '+77073334455', 'referred_by_user_id' => $referrer->id]);

        $this->registerNewUser('+77073334455', 'REFCODE2');

        $this->assertSame($referrer->id, $user->fresh()->referred_by_user_id);
    }

    public function test_self_referral_is_ignored(): void
    {
        $user = $this->createUnverifiedClient(['phone' => '+77074445566']);
        $user->forceFill(['referral_code' => 'SELFREF1'])->save();

        $this->registerNewUser('+77074445566', 'SELFREF1');

        $this->assertNull($user->fresh()->referred_by_user_id);
    }

    public function test_invalid_referral_code_is_ignored(): void
    {
        $referral = $this->registerNewUser('+77075556677', 'BADCODE9');

        $this->assertNull($referral->referred_by_user_id);
    }

    public function test_profile_referrals_page_returns_link_and_list(): void
    {
        $referrer = $this->createClient();
        $referrer->forceFill(['referral_code' => 'MYREF123'])->save();

        $referral = $this->createClient([
            'phone' => '+77076667788',
            'referred_by_user_id' => $referrer->id,
        ]);

        $this->actingAs($referrer)
            ->get('/ru/profile/referrals')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/Referrals')
                ->where('referral.code', 'MYREF123')
                ->where('referral.referrals_count', 1)
                ->has('referral.referrals', 1)
                ->where('referral.referrals.0.id', $referral->id));
    }

    public function test_admin_can_grant_referral_fee_discount(): void
    {
        $admin = $this->createStaff('super_admin');
        $user = $this->createClient();

        $this->actingAsAdmin($admin)
            ->post('/admin/users/'.$user->id.'/referral-benefits', [
                'type' => 'fee_discount',
                'value' => 0.25,
                'note' => 'Активный реферер',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('user_referral_benefits', [
            'user_id' => $user->id,
            'type' => 'fee_discount',
            'is_active' => true,
        ]);

        $baseFee = app(SubscriptionPlanService::class)->defaultPlan()->fee_percent;
        $this->assertSame($baseFee - 0.25, $user->fresh()->feePercent());
    }

    public function test_admin_can_deactivate_referral_benefit(): void
    {
        $admin = $this->createStaff('super_admin');
        $user = $this->createClient();

        $benefit = UserReferralBenefit::query()->create([
            'user_id' => $user->id,
            'type' => 'fee_discount',
            'value' => 0.5,
            'is_active' => true,
            'granted_by_user_id' => $admin->id,
        ]);

        $baseFee = app(SubscriptionPlanService::class)->defaultPlan()->fee_percent;
        $this->assertSame($baseFee - 0.5, $user->fresh()->feePercent());

        $this->actingAsAdmin($admin)
            ->patch('/admin/users/'.$user->id.'/referral-benefits/'.$benefit->id)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertFalse($benefit->fresh()->is_active);
        $this->assertSame($baseFee, app(SubscriptionPlanService::class)->feePercentFor($user->fresh()));
    }
}
