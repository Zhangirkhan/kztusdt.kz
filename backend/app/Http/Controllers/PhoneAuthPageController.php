<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StartPhoneAuthRequest;
use App\Models\User;
use App\Services\CaptchaService;
use App\Services\PhoneAuthService;
use App\Support\CompanyPresenter;
use App\Support\RegistrationResume;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class PhoneAuthPageController extends Controller
{
    public function __construct(
        private readonly PhoneAuthService $phoneAuthService,
        private readonly CaptchaService $captchaService,
    ) {}

    public function show(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectAfterAuth(Auth::user());
        }

        return Inertia::render('Auth/Phone', [
            'companyIntro' => CompanyPresenter::intro(),
            'legalEntityEdsRequired' => (bool) config('ncanode.legal_entity_eds_required'),
        ]);
    }

    public function store(StartPhoneAuthRequest $request): RedirectResponse
    {
        try {
            $session = $this->phoneAuthService->start(
                $request->validated('phone'),
                $request->validated('client_type'),
                $request->validated('iin'),
                $request->validated('bin') ?? null,
                $request->validated('company_name') ?? null,
            );

            $this->captchaService->invalidate();

            return redirect()->route('auth.whatsapp.wait', [
                'locale' => $request->route('locale'),
                'loginCode' => $session->login_code,
            ]);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['phone' => $exception->getMessage()]);
        }
    }

    public function wait(string $locale, string $loginCode): Response|RedirectResponse
    {
        $session = $this->phoneAuthService->getStatus($loginCode);

        if ($session === null) {
            abort(404);
        }

        if ($session->isVerified()) {
            // Verified login_code must not authenticate by itself (URL leak = takeover).
            // Session is established only via OTP verify / other auth endpoints.
            if (! Auth::check()) {
                return redirect()->route('auth.phone', ['locale' => $locale]);
            }

            $user = Auth::user();

            if ($user === null || ($session->user_id !== null && (int) $session->user_id !== (int) $user->id)) {
                return redirect()->route('auth.phone', ['locale' => $locale]);
            }

            $pageProps = [
                'loginCode' => $loginCode,
                'phone' => $this->maskedPhone($session->phone),
                'status' => $session->status,
                'expiresAt' => $session->expires_at->toIso8601String(),
                'codeLength' => (int) config('otp.code_length'),
            ];

            return $this->renderOnboardingStep($user, $pageProps);
        }

        if ($session->status === 'failed') {
            return redirect()
                ->route('auth.phone', ['locale' => $locale])
                ->withErrors(['phone' => 'Превышено число попыток. Введите номер и запросите новый код.']);
        }

        $pageProps = [
            'loginCode' => $loginCode,
            'phone' => $this->maskedPhone($session->phone),
            'status' => $session->status,
            'expiresAt' => $session->expires_at->toIso8601String(),
            'codeLength' => (int) config('otp.code_length'),
        ];

        if (Auth::check()) {
            return $this->renderOnboardingStep(Auth::user(), $pageProps);
        }

        return Inertia::render('Auth/WhatsAppWait', [
            ...$pageProps,
            'initialStep' => 'whatsapp',
            'kycStatus' => 'none',
            'kyc' => null,
        ]);
    }

    private function maskedPhone(string $phone): string
    {
        return \App\Support\RequestLogContext::maskPhone($phone) ?? '***';
    }

    /**
     * @param  array<string, mixed>  $pageProps
     */
    private function renderOnboardingStep(User $user, array $pageProps): Response|RedirectResponse
    {
        $kyc = $user->kycMeta();

        if ($kyc['iin_mismatch']) {
            return Inertia::render('Auth/WhatsAppWait', [
                ...$pageProps,
                'initialStep' => 'kyc',
                'kycStatus' => $user->kyc_status,
                'kyc' => $kyc,
            ]);
        }

        if (! $kyc['needs_verification']) {
            return $this->redirectAfterAuth($user);
        }

        if ($kyc['inline_sumsub']) {
            return Inertia::render('Auth/WhatsAppWait', [
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
        return redirect(RegistrationResume::path($user));
    }
}
