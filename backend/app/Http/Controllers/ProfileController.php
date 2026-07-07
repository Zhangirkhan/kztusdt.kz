<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Services\ProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
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
        return Inertia::render('Profile/Bank', [
            'profile' => $this->profileService->profilePayload($request->user()),
        ]);
    }

    public function security(): Response
    {
        return Inertia::render('Profile/Security');
    }

    public function language(Request $request): Response
    {
        return Inertia::render('Profile/Language', [
            'profile' => $this->profileService->profilePayload($request->user()),
        ]);
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

    public function updateBank(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_holder' => ['required', 'string', 'max:255'],
            'bank_account' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($validated);

        return redirect()
            ->route('profile.bank')
            ->with('success', 'Банковские реквизиты сохранены.');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'push' => ['required', 'boolean'],
            'email' => ['required', 'boolean'],
            'sms' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            'notification_preferences' => $validated,
        ]);

        return redirect()
            ->route('profile.notifications')
            ->with('success', 'Настройки уведомлений сохранены.');
    }
}
