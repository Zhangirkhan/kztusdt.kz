<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AituPassportService;
use App\Support\AppLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Logout Callback URI — server-side webhook that Aitu Passport calls after a
 * user logs out of the Aitu Passport session. Used to invalidate any locally
 * cached tokens/sessions tied to that user.
 */
final class AituPassportLogoutController extends Controller
{
    public function __construct(
        private readonly AituPassportService $aituPassport,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        $valid = $this->aituPassport->verifyLogoutWebhook(
            $rawBody,
            (string) $request->header('X-Signature', ''),
        );

        if (! $valid) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        AppLog::auth('auth.aitu.logout_webhook', [
            'payload' => $request->json()->all(),
        ]);

        return response()->json(['ok' => true]);
    }
}
