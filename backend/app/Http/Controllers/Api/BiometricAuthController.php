<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PhoneAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BiometricAuthController extends Controller
{
    public function __construct(
        private readonly PhoneAuthService $phoneAuthService,
    ) {}

    public function check(Request $request): JsonResponse
    {
        $phone = $this->phoneAuthService->normalizePhone((string) $request->input('phone', ''));

        if ($phone === '+') {
            return response()->json(['available' => false]);
        }

        $user = User::query()
            ->where('phone', $phone)
            ->where('phone_verified', true)
            ->first();

        $available = $user !== null && $user->webauthnKeys()->exists();

        return response()->json([
            'available' => $available,
            'phone' => $available ? $phone : null,
        ]);
    }
}
