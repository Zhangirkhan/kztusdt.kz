<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LaravelWebauthn\Actions\PrepareAssertionData;
use LaravelWebauthn\Facades\Webauthn;

final class AppLockBiometricController extends Controller
{
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 401);
        abort_unless($user->webauthnKeys()->exists(), 422, 'Biometric unlock is not configured.');

        $publicKey = app(PrepareAssertionData::class)($user);

        return response()->json([
            'publicKey' => $publicKey,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $credentials = $request->only(['id', 'rawId', 'response', 'type']);

        if (! Webauthn::validateAssertion($user, $credentials)) {
            throw ValidationException::withMessages([
                'biometric' => [__('webauthn::errors.login_failed')],
            ]);
        }

        return response()->json(['verified' => true]);
    }
}
