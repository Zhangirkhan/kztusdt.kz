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
            'telegramBotUsername' => config('telegram.bot_username'),
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $result = $this->profileService->update($request->user(), $request->validated());

        $message = $result['phone_changed']
            ? __('profile.saved_phone_changed')
            : __('profile.saved');

        return redirect()
            ->route('profile.show')
            ->with('success', $message);
    }
}
