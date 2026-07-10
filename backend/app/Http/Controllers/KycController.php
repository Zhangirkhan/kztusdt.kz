<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SubmitKycRequest;
use App\Services\KycService;
use App\Services\SumsubService;
use App\Support\KycClientOptions;
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
    ) {}

    public function show(Request $request): Response
    {
        abort_unless($request->user()?->phone_verified, 403);

        $user = $request->user();
        $profile = $user->kycProfile?->load('documents');
        $options = KycClientOptions::forUser($user);

        return Inertia::render('Kyc', [
            'profile' => $profile,
            'kycStatus' => $user->kyc_status,
            'rejectionReason' => $profile?->rejection_reason,
            'provider' => $options['provider'],
            'manualEnabled' => $options['manual_enabled'],
            'showAitu' => $options['show_aitu'],
            'showSumsub' => $options['show_sumsub'],
            'showManualForm' => $options['show_manual_form'],
            'aituVerifyUrl' => $options['aitu_verify_url'],
            'aituKycScopeConfigured' => $options['aitu_kyc_scope_configured'],
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
        abort_unless(KycClientOptions::manualEnabledForUser($request->user()), 403, 'Ручная подача KYC отключена.');

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
