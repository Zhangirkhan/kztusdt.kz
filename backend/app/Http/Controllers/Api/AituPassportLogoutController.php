<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AituPassportService;
use App\Support\AppLog;
use App\Support\RequestLogContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $phone = isset($payload['phone']) ? (string) $payload['phone'] : '';
        $sub = isset($payload['sub']) ? (string) $payload['sub'] : '';

        AppLog::auth('auth.aitu.logout_webhook', [
            'phone' => RequestLogContext::maskPhone($phone !== '' ? $phone : null),
            'sub_present' => $sub !== '',
        ]);

        $user = null;

        if ($phone !== '') {
            $normalized = preg_replace('/\D+/', '', $phone) ?? '';
            if (str_starts_with($normalized, '8') && strlen($normalized) === 11) {
                $normalized = '7'.substr($normalized, 1);
            }
            if ($normalized !== '') {
                $user = User::query()->where('phone', '+'.$normalized)->first();
            }
        }

        if ($user !== null) {
            $user->setRememberToken(Str::random(60));
            $user->save();

            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        }

        return response()->json(['ok' => true]);
    }
}
