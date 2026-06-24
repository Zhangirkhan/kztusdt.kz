<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SubmitKycRequest;
use App\Services\AituPassportService;
use App\Services\KycService;
use App\Services\SumsubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class KycController extends Controller
{
    public function __construct(
        private readonly KycService $kycService,
        private readonly SumsubService $sumsubService,
        private readonly AituPassportService $aituPassport,
    ) {}

    public function show(Request $request): Response
    {
        abort_unless($request->user()?->phone_verified, 403);

        $profile = $request->user()->kycProfile?->load('documents');
        $provider = (string) config('kyc.provider', 'manual');

        // Fall back to the manual form while the external provider is not configured yet.
        if ($provider === 'sumsub' && ! $this->sumsubService->isConfigured()) {
            $provider = 'manual';
        }

        if ($provider === 'aitu' && ! $this->aituPassport->isConfigured()) {
            $provider = 'manual';
        }

        return Inertia::render('Kyc', [
            'profile' => $profile,
            'kycStatus' => $request->user()->kyc_status,
            'rejectionReason' => $profile?->rejection_reason,
            'provider' => $provider,
            // Re-run the Aitu authorization to (re)deliver the verification verdict.
            'aituVerifyUrl' => $provider === 'aitu' ? route('auth.aitu.redirect') : null,
        ]);
    }

    /**
     * Access token for the Sumsub WebSDK (also used by its refresh callback).
     */
    public function sumsubToken(Request $request): JsonResponse
    {
        abort_unless($request->user()?->phone_verified, 403);
        abort_unless(config('kyc.provider') === 'sumsub' && $this->sumsubService->isConfigured(), 404);

        try {
            return response()->json([
                'token' => $this->sumsubService->accessToken($request->user()),
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 502);
        }
    }

    public function sumsubSync(Request $request): JsonResponse
    {
        abort_unless($request->user()?->phone_verified, 403);
        abort_unless(config('kyc.provider') === 'sumsub' && $this->sumsubService->isConfigured(), 404);

        try {
            return response()->json($this->sumsubService->syncApplicantStatus($request->user()));
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 502);
        }
    }

    public function store(SubmitKycRequest $request): RedirectResponse
    {
        try {
            $this->kycService->submit(
                $request->user(),
                $request->safe()->except(['id_front', 'id_back', 'selfie']),
                [
                    'id_front' => $request->file('id_front'),
                    'id_back' => $request->file('id_back'),
                    'selfie' => $request->file('selfie'),
                ],
            );

            return redirect()->route('kyc')->with('success', 'KYC отправлен на проверку.');
        } catch (RuntimeException $exception) {
            return back()->withErrors(['form' => $exception->getMessage()]);
        }
    }
}
