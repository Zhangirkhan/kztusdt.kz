<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartLegalEntityEdsRequest;
use App\Http\Requests\VerifyLegalEntityEdsRequest;
use App\Services\LegalEntityEdsRegistrationService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class LegalEntityEdsController extends Controller
{
    public function __construct(
        private readonly LegalEntityEdsRegistrationService $edsRegistrationService,
    ) {}

    public function start(StartLegalEntityEdsRequest $request): JsonResponse
    {
        try {
            $result = $this->edsRegistrationService->startRegistration(
                $request->validated('phone'),
                $request->validated('bin'),
                $request->validated('company_name'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result, 201);
    }

    public function verify(VerifyLegalEntityEdsRequest $request, string $loginCode): JsonResponse
    {
        try {
            $result = $this->edsRegistrationService->verifySignature(
                $loginCode,
                $request->validated('cms'),
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json($result);
    }
}
