<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SumsubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SumsubWebhookController extends Controller
{
    public function __construct(
        private readonly SumsubService $sumsubService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        $valid = $this->sumsubService->verifyWebhookSignature(
            $rawBody,
            (string) $request->header('X-Payload-Digest', ''),
            (string) $request->header('X-Payload-Digest-Alg', 'HMAC_SHA256_HEX'),
        );

        if (! $valid) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload = $request->json()->all();

        if (($payload['type'] ?? '') === 'applicantReviewed') {
            $this->sumsubService->handleApplicantReviewed($payload);
        }

        return response()->json(['ok' => true]);
    }
}
