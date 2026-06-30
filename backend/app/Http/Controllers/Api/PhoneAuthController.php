<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartPhoneAuthRequest;
use App\Http\Requests\VerifyPhoneAuthRequest;
use App\Services\PhoneAuthService;
use App\Support\AppLog;
use App\Support\RequestLogContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class PhoneAuthController extends Controller
{
    public function __construct(
        private readonly PhoneAuthService $phoneAuthService,
    ) {}

    public function start(StartPhoneAuthRequest $request): JsonResponse
    {
        try {
            $session = $this->phoneAuthService->start(
                $request->validated('phone'),
                $request->validated('iin'),
            );

            return response()->json([
                'login_code' => $session->login_code,
                'phone' => $session->phone,
                'code_length' => (int) config('otp.code_length'),
                'expires_at' => $session->expires_at->toIso8601String(),
                'status' => $session->status,
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function resend(string $loginCode): JsonResponse
    {
        try {
            $session = $this->phoneAuthService->resend($loginCode);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'login_code' => $session->login_code,
            'phone' => $session->phone,
            'expires_at' => $session->expires_at->toIso8601String(),
            'status' => $session->status,
        ]);
    }

    public function verify(VerifyPhoneAuthRequest $request, string $loginCode): JsonResponse
    {
        try {
            $user = $this->phoneAuthService->verifyCode($loginCode, $request->validated('code'));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        Auth::loginUsingId($user->id, remember: true);
        $request->session()->regenerate();

        $kyc = $user->kycMeta();

        AppLog::auth('auth.phone.verify.success', [
            'user_id' => $user->id,
            'phone' => RequestLogContext::maskPhone($user->phone),
            'suggest_biometric' => ! $user->webauthnKeys()->exists(),
            'kyc_status' => $user->kyc_status,
        ]);

        return response()->json([
            'verified' => true,
            'redirect' => $kyc['needs_verification'] ? null : route('home'),
            'kyc' => $kyc,
            'kyc_status' => $user->kyc_status,
            'suggest_biometric' => ! $user->webauthnKeys()->exists(),
        ]);
    }
}
