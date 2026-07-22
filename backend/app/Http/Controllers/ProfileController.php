<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RenameUserBankCardRequest;
use App\Http\Requests\StoreUserBankCardRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateUserBankCardRequest;
use App\Models\UserBankCard;
use App\Services\ProfileService;
use App\Services\ReferralService;
use App\Services\UserBankCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly UserBankCardService $bankCardService,
        private readonly ReferralService $referralService,
    ) {}

    public function show(Request $request): Response
    {
        return Inertia::render('Profile/Index', [
            'profile' => $this->profileService->profilePayload($request->user()),
        ]);
    }

    public function personal(Request $request): Response
    {
        return Inertia::render('Profile/Personal', [
            'profile' => $this->profileService->profilePayload($request->user()),
        ]);
    }

    public function bank(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Bank', [
            'profile' => $this->profileService->profilePayload($user),
            'banks' => $this->bankCardService->bankCatalog(),
            'cards' => $this->bankCardService->cardsPayload($user),
        ]);
    }

    public function storeBankCard(StoreUserBankCardRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $iin = preg_replace('/\D+/', '', (string) ($data['iin'] ?? '')) ?? '';
        if ($iin !== '' && $user->iin !== $iin) {
            $user->update(['iin' => $iin]);
        }

        unset($data['iin']);
        $this->bankCardService->create($user, $data);

        return redirect()
            ->route('profile.bank')
            ->with('success', 'Карта добавлена.');
    }

    public function updateBankCard(UpdateUserBankCardRequest $request, string $locale, UserBankCard $card): RedirectResponse
    {
        abort_unless((int) $card->user_id === (int) $request->user()->id, 403);

        $this->bankCardService->update($card, $request->validated());

        return redirect()
            ->route('profile.bank')
            ->with('success', 'Карта обновлена.');
    }

    public function renameBankCard(RenameUserBankCardRequest $request, string $locale, UserBankCard $card): RedirectResponse
    {
        abort_unless((int) $card->user_id === (int) $request->user()->id, 403);

        $this->bankCardService->rename($card, $request->validated('label'));

        return redirect()
            ->route('profile.bank')
            ->with('success', 'Название карты обновлено.');
    }

    public function destroyBankCard(Request $request, string $locale, UserBankCard $card): RedirectResponse
    {
        abort_unless((int) $card->user_id === (int) $request->user()->id, 403);
        abort_unless($request->user()->canUseWallet(), 403);

        $this->bankCardService->delete($card);

        return redirect()
            ->route('profile.bank')
            ->with('success', 'Карта удалена.');
    }

    public function security(): Response
    {
        return Inertia::render('Profile/Security');
    }

    public function notifications(Request $request): Response
    {
        return Inertia::render('Profile/Notifications', [
            'profile' => $this->profileService->profilePayload($request->user()),
        ]);
    }

    public function support(Request $request): Response
    {
        return Inertia::render('Profile/Support', [
            'supportEmail' => config('company.support_email'),
            'supportPhone' => config('company.support_phone'),
            'companyName' => config('company.name'),
        ]);
    }

    public function referrals(Request $request): Response
    {
        $locale = (string) $request->route('locale', 'ru');

        return Inertia::render('Profile/Referrals', [
            'referral' => $this->referralService->profilePayload($request->user(), $locale),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $result = $this->profileService->update($request->user(), $request->validated());

        $message = $result['phone_changed']
            ? __('profile.saved_phone_changed')
            : __('profile.saved');

        return redirect()
            ->route('profile.personal')
            ->with('success', $message);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'push' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            'notification_preferences' => [
                'push' => $validated['push'],
                'email' => false,
                'sms' => false,
            ],
        ]);

        return redirect()
            ->route('profile.notifications')
            ->with('success', __('profile.notifications_saved'));
    }

    public function appearance(Request $request): Response
    {
        return Inertia::render('Profile/Appearance');
    }
}
