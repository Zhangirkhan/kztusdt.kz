<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StartPhoneAuthRequest;
use App\Models\User;
use App\Services\PhoneAuthService;
use App\Support\AdminNavPresenter;
use App\Support\CompanyPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class PhoneAuthPageController extends Controller
{
    public function __construct(
        private readonly PhoneAuthService $phoneAuthService,
    ) {}

    public function show(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAfterAuth(Auth::user());
        }

        return Inertia::render('Auth/Phone', [
            'telegramBotUsername' => config('telegram.bot_username'),
            'companyIntro' => CompanyPresenter::intro(),
        ]);
    }

    public function store(StartPhoneAuthRequest $request): RedirectResponse
    {
        try {
            $session = $this->phoneAuthService->start(
                $request->validated('phone'),
                $request->validated('iin'),
            );

            return redirect()->route('auth.telegram.wait', [
                'loginCode' => $session->login_code,
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()]);
        }
    }

    public function wait(string $loginCode): Response|RedirectResponse
    {
        $session = $this->phoneAuthService->getStatus($loginCode);

        if ($session === null) {
            abort(404);
        }

        $pageProps = [
            'loginCode' => $loginCode,
            'phone' => $session->phone,
            'status' => $session->status,
            'expiresAt' => $session->expires_at->toIso8601String(),
            'codeLength' => (int) config('telegram.gateway.code_length'),
        ];

        if (Auth::check()) {
            return $this->renderOnboardingStep(Auth::user(), $pageProps);
        }

        return Inertia::render('Auth/TelegramWait', [
            ...$pageProps,
            'initialStep' => 'telegram',
            'kycStatus' => 'none',
            'kyc' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $pageProps
     */
    private function renderOnboardingStep(User $user, array $pageProps): Response|RedirectResponse
    {
        $kyc = $user->kycMeta();

        if (! $kyc['needs_verification']) {
            return $this->redirectAfterAuth($user);
        }

        if ($kyc['inline_sumsub']) {
            return Inertia::render('Auth/TelegramWait', [
                ...$pageProps,
                'initialStep' => 'kyc',
                'kycStatus' => $user->kyc_status,
                'kyc' => $kyc,
            ]);
        }

        return redirect()->route('kyc');
    }

    private function redirectAfterAuth(User $user): RedirectResponse
    {
        $landing = AdminNavPresenter::landingPath($user);

        if ($landing !== null) {
            return redirect($landing);
        }

        return redirect()->route('home');
    }
}
