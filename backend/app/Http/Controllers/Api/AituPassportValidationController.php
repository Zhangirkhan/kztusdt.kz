<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AppLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Валидация на стороне клиента" — endpoint вызываемый Aitu Passport для
 * валидации на стороне сервиса Партнёра.
 *
 * Тип авторизации в консоли — Basic: Aitu передаёт заголовок
 * `Authorization: Basic base64(validator_id:secret)`.
 *
 * Внимание: точный контракт тела запроса и ожидаемого ответа в публичной
 * документации Aitu Passport не описан. Реализация защитная:
 *   - GET  — проверка доступности URL (200 OK);
 *   - POST — проверка Basic-авторизации, логирование payload, ответ {"valid": true}.
 */
final class AituPassportValidationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->isMethod('GET')) {
            return response()->json(['ok' => true]);
        }

        if (! $this->authorized($request)) {
            return response()->json(['valid' => false, 'error' => 'unauthorized'], 401);
        }

        AppLog::auth('auth.aitu.client_validation', [
            'payload' => $request->json()->all(),
        ]);

        return response()->json(['valid' => true]);
    }

    /**
     * Verify HTTP Basic credentials against the configured validator_id/secret.
     */
    private function authorized(Request $request): bool
    {
        $secret = (string) config('aitu.validation.secret');

        if ($secret === '') {
            // Fail-closed in production: a public, always-"valid" endpoint would let
            // anyone spoof a passed validation. In local/testing it stays open.
            if (app()->environment('production')) {
                AppLog::authWarning('auth.aitu.client_validation.secret_missing');

                return false;
            }

            return true;
        }

        [$user, $password] = $this->basicCredentials($request);

        if (! hash_equals($secret, $password)) {
            return false;
        }

        $validatorId = (string) config('aitu.validation.validator_id');

        // Validator ID проверяется только если он задан в конфиге.
        if ($validatorId !== '' && ! hash_equals($validatorId, $user)) {
            return false;
        }

        return true;
    }

    /**
     * Parse the Authorization: Basic header (robust to setups where PHP does
     * not auto-populate PHP_AUTH_USER behind FPM/nginx).
     *
     * @return array{0: string, 1: string} [username, password]
     */
    private function basicCredentials(Request $request): array
    {
        $user = (string) $request->getUser();
        $password = (string) $request->getPassword();

        if ($user !== '' || $password !== '') {
            return [$user, $password];
        }

        $header = (string) $request->header('Authorization', '');

        if (stripos($header, 'Basic ') !== 0) {
            return ['', ''];
        }

        $decoded = base64_decode(substr($header, 6), true);

        if ($decoded === false || ! str_contains($decoded, ':')) {
            return ['', ''];
        }

        [$user, $password] = explode(':', $decoded, 2);

        return [$user, $password];
    }
}
