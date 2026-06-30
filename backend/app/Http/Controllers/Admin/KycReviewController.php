<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectKycRequest;
use App\Http\Requests\ResetKycRequest;
use App\Models\KycProfile;
use App\Services\KycService;
use App\Support\KycReviewPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class KycReviewController extends Controller
{
    private const DOCUMENT_TYPES = ['id_front', 'id_back', 'selfie'];

    public function __construct(
        private readonly KycService $kycService,
        private readonly KycReviewPresenter $kycReviewPresenter,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status', 'pending_review')->toString();

        $profiles = KycProfile::query()
            ->with(['user:id,name,phone,kyc_status', 'documents'])
            ->when(! $this->sumsubAdminEnabled(), fn ($q) => $q->where(fn ($q) => $q->whereNull('provider')->orWhere('provider', '!=', 'sumsub')))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();

        $statsQuery = fn () => KycProfile::query()
            ->when(! $this->sumsubAdminEnabled(), fn ($q) => $q->where(fn ($q) => $q->whereNull('provider')->orWhere('provider', '!=', 'sumsub')));

        return Inertia::render('Admin/Kyc/Index', [
            'profiles' => $profiles,
            'filterStatus' => $status,
            'sumsubAdminEnabled' => $this->sumsubAdminEnabled(),
            'stats' => [
                'pending' => $statsQuery()->where('status', 'pending_review')->count(),
                'approved' => $statsQuery()->where('status', 'approved')->count(),
                'rejected' => $statsQuery()->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function show(KycProfile $kycProfile): Response
    {
        abort_if(
            ! $this->sumsubAdminEnabled() && ($kycProfile->provider ?? 'manual') === 'sumsub',
            404,
        );

        return Inertia::render('Admin/Kyc/Show', [
            'profile' => $this->kycReviewPresenter->showPayload($kycProfile),
            'sumsubAdminEnabled' => $this->sumsubAdminEnabled(),
        ]);
    }

    public function approve(KycProfile $kycProfile, Request $request): RedirectResponse
    {
        abort_unless($kycProfile->status === 'pending_review', 422);

        $this->kycService->approve(
            $kycProfile,
            $request->user(),
            $request->string('comment')->toString() ?: null,
        );

        return redirect()->route('admin.kyc.show', $kycProfile)->with('success', 'KYC одобрен.');
    }

    public function reject(KycProfile $kycProfile, RejectKycRequest $request): RedirectResponse
    {
        abort_unless($kycProfile->status === 'pending_review', 422);

        $this->kycService->reject(
            $kycProfile,
            $request->user(),
            $request->validated('reason'),
        );

        return redirect()->route('admin.kyc.show', $kycProfile)->with('success', 'KYC отклонён.');
    }

    public function reset(KycProfile $kycProfile, ResetKycRequest $request): RedirectResponse
    {
        abort_unless(in_array($kycProfile->status, ['approved', 'rejected', 'pending_review'], true), 422);

        $this->kycService->reset(
            $kycProfile,
            $request->user(),
            $request->validated('comment'),
        );

        return redirect()->route('admin.kyc.show', $kycProfile)->with('success', 'Верификация сброшена. Клиент может пройти KYC заново.');
    }

    public function document(KycProfile $kycProfile, string $type): StreamedResponse
    {
        abort_unless(in_array($type, self::DOCUMENT_TYPES, true), 404);

        $document = $kycProfile->documents()->where('type', $type)->firstOrFail();

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_name,
            ['Content-Disposition' => 'inline'],
        );
    }

    private function sumsubAdminEnabled(): bool
    {
        return (bool) config('kyc.admin_show_sumsub', false);
    }
}
