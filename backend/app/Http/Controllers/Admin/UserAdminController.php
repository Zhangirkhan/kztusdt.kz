<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminManualKycRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\KycService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class UserAdminController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly KycService $kycService,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString() ?: 'all';
        $clientType = $request->string('client_type')->toString() ?: 'all';

        $users = User::query()
            ->withCount(['exchangeOrders', 'withdrawals', 'deposits'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('iin', 'like', "%{$search}%")
                        ->orWhere('bin', 'like', "%{$search}%");

                    if (is_numeric($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                });
            })
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($clientType !== 'all', fn ($q) => $q->where('client_type', $clientType))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'client_type' => $clientType,
            ],
            'stats' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('status', 'active')->count(),
                'suspended' => User::query()->where('status', 'suspended')->count(),
            ],
        ]);
    }

    public function show(User $user): Response
    {
        $user->loadCount(['exchangeOrders', 'withdrawals', 'deposits'])
            ->load(['kycProfile:id,user_id,status,first_name,last_name,company_name', 'roles:id,code']);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'client_type' => $user->client_type,
                'client_type_label' => $user->clientType()->label(),
                'company_name' => $user->company_name,
                'iin' => $user->iin,
                'bin' => $user->bin,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->status,
                'kyc_status' => $user->kyc_status,
                'phone_verified' => (bool) $user->phone_verified,
                'has_subscription' => (bool) $user->has_subscription,
                'created_at' => $user->created_at?->toIso8601String(),
                'roles' => $user->roles->pluck('code'),
                'kyc_profile' => $user->kycProfile ? [
                    'id' => $user->kycProfile->id,
                    'status' => $user->kycProfile->status,
                    'name' => $user->kycProfile->company_name
                        ?: trim(($user->kycProfile->first_name ?? '').' '.($user->kycProfile->last_name ?? '')),
                ] : null,
                'counts' => [
                    'orders' => $user->exchange_orders_count,
                    'withdrawals' => $user->withdrawals_count,
                    'deposits' => $user->deposits_count,
                ],
            ],
        ]);
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended,blocked'],
        ]);

        $user->update(['status' => $validated['status']]);

        $this->auditLogService->log(
            action: 'admin.user.status_updated',
            userId: $request->user()?->id,
            entityType: 'user',
            entityId: $user->id,
            payload: ['status' => $validated['status']],
            request: $request,
        );

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'Статус пользователя обновлён.');
    }

    public function manualKycApprove(AdminManualKycRequest $request, User $user): RedirectResponse
    {
        try {
            $this->kycService->adminManualApprove(
                $user,
                $request->user(),
                $request->safe()->only(['company_name', 'first_name', 'last_name', 'document_type', 'document_number']),
                $request->string('comment')->toString() ?: null,
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['manual_kyc' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'KYC одобрен вручную.');
    }
}
