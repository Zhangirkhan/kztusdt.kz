<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReferralBenefitType;
use App\Models\User;
use App\Models\UserReferralBenefit;
use App\Support\RequestLogContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

final class ReferralService
{
    public function captureFromRequest(Request $request): void
    {
        $code = $this->normalizeCode($request->query('ref'));

        if ($code === null) {
            return;
        }

        if ($this->resolveReferrer($code) === null) {
            return;
        }

        Cookie::queue($this->rememberCode($code));
    }

    public function resolveReferrer(?string $code): ?User
    {
        $code = $this->normalizeCode($code);

        if ($code === null) {
            return null;
        }

        return User::query()->where('referral_code', $code)->first();
    }

    public function applyToNewUser(User $user, ?int $referrerUserId = null): void
    {
        if ($user->referred_by_user_id !== null) {
            return;
        }

        $referrerId = $referrerUserId;

        if ($referrerId === null) {
            $code = $this->normalizeCode(request()->cookie($this->cookieName()));

            if ($code === null) {
                return;
            }

            $referrer = $this->resolveReferrer($code);
            $referrerId = $referrer?->id;
        }

        if ($referrerId === null || (int) $referrerId === (int) $user->id) {
            Cookie::queue(Cookie::forget($this->cookieName()));

            return;
        }

        $user->forceFill(['referred_by_user_id' => $referrerId])->save();

        Cookie::queue(Cookie::forget($this->cookieName()));
    }

    public function resolveReferrerIdFromRequest(Request $request): ?int
    {
        $code = $this->normalizeCode($request->cookie($this->cookieName()));

        if ($code === null) {
            $code = $this->normalizeCode($request->query('ref'));
        }

        if ($code === null) {
            $code = $this->normalizeCode($request->input('ref'));
        }

        if ($code === null) {
            return null;
        }

        return $this->resolveReferrer($code)?->id;
    }

    public function referralLink(User $user, string $locale): string
    {
        $code = $user->ensureReferralCode();

        return url('/'.trim($locale, '/').'/auth/phone?ref='.$code);
    }

    public function activeFeeDiscount(User $user): float
    {
        $benefits = $user->referralBenefits()
            ->where('type', ReferralBenefitType::FeeDiscount->value)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->get();

        $discount = 0.0;

        foreach ($benefits as $benefit) {
            if ($benefit->value !== null) {
                $discount += (float) $benefit->value;
            }
        }

        return $discount;
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(User $user, string $locale): array
    {
        $user->ensureReferralCode();

        return [
            'link' => $this->referralLink($user, $locale),
            'code' => $user->referral_code,
            'referrals_count' => $user->referrals()->count(),
            'referrals' => $this->referralsPayload($user),
            'active_benefit' => $this->activeBenefitPayload($user),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function referralsPayload(User $user): array
    {
        return $user->referrals()
            ->withCount(['exchangeOrders', 'deposits'])
            ->latest('id')
            ->get()
            ->map(fn (User $referral): array => [
                'id' => $referral->id,
                'name' => $referral->displayName(),
                'phone_masked' => RequestLogContext::maskPhone($referral->phone),
                'kyc_status' => (string) $referral->kyc_status,
                'registered_at' => $referral->created_at?->toIso8601String(),
                'orders_count' => (int) $referral->exchange_orders_count,
                'deposits_count' => (int) $referral->deposits_count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeBenefitPayload(User $user): ?array
    {
        $benefit = $user->referralBenefits()
            ->where('type', ReferralBenefitType::FeeDiscount->value)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if (! $benefit instanceof UserReferralBenefit) {
            return null;
        }

        return [
            'type' => $benefit->type,
            'value' => $benefit->value,
            'note' => $benefit->note,
            'expires_at' => $benefit->expires_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminPayload(User $user): array
    {
        $user->loadMissing(['referrer:id,name,phone,referral_code', 'referralBenefits.grantedBy:id,name']);

        return [
            'code' => $user->ensureReferralCode(),
            'link' => $this->referralLink($user, $user->locale ?: 'ru'),
            'referrals_count' => $user->referrals()->count(),
            'referrals' => $this->referralsPayload($user),
            'referred_by' => $user->referrer instanceof User ? [
                'id' => $user->referrer->id,
                'name' => $user->referrer->displayName(),
                'referral_code' => $user->referrer->referral_code,
            ] : null,
            'benefits' => $user->referralBenefits
                ->sortByDesc('id')
                ->values()
                ->map(fn (UserReferralBenefit $benefit): array => [
                    'id' => $benefit->id,
                    'type' => $benefit->type,
                    'value' => $benefit->value,
                    'note' => $benefit->note,
                    'is_active' => $benefit->is_active,
                    'expires_at' => $benefit->expires_at?->toIso8601String(),
                    'granted_by' => $benefit->grantedBy?->name,
                    'created_at' => $benefit->created_at?->toIso8601String(),
                ])
                ->all(),
            'active_benefit' => $this->activeBenefitPayload($user),
        ];
    }

    /**
     * @param  array{type: string, value?: float|null, note?: string|null, is_active?: bool, expires_at?: string|null}  $data
     */
    public function upsertBenefit(User $user, array $data, User $grantedBy): UserReferralBenefit
    {
        $type = ReferralBenefitType::tryFrom((string) ($data['type'] ?? '')) ?? ReferralBenefitType::FeeDiscount;

        $benefit = $user->referralBenefits()
            ->where('type', $type->value)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $attributes = [
            'type' => $type->value,
            'value' => array_key_exists('value', $data) ? $data['value'] : null,
            'note' => isset($data['note']) ? trim((string) $data['note']) : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'expires_at' => ! empty($data['expires_at']) ? $data['expires_at'] : null,
            'granted_by_user_id' => $grantedBy->id,
        ];

        if ($benefit instanceof UserReferralBenefit) {
            $benefit->update($attributes);

            return $benefit->fresh(['grantedBy']);
        }

        return $user->referralBenefits()->create($attributes);
    }

    public function deactivateBenefit(UserReferralBenefit $benefit): void
    {
        $benefit->update(['is_active' => false]);
    }

    private function normalizeCode(mixed $code): ?string
    {
        if (! is_string($code)) {
            return null;
        }

        $code = Str::upper(trim($code));

        if ($code === '' || strlen($code) > 16 || ! preg_match('/^[A-Z0-9]+$/', $code)) {
            return null;
        }

        return $code;
    }

    private function rememberCode(string $code): \Symfony\Component\HttpFoundation\Cookie
    {
        $minutes = (int) config('referrals.cookie_ttl_days', 30) * 24 * 60;

        return Cookie::make(
            name: $this->cookieName(),
            value: $code,
            minutes: $minutes,
            path: '/',
            secure: request()->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    private function cookieName(): string
    {
        return (string) config('referrals.cookie', 'referral_code');
    }
}
