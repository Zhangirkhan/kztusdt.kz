<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null
            && $user->hasRole('security_officer')
            && ! $user->hasAnyRole(['super_admin', 'super_admin_manager'])
        ) {
            return redirect()->route('admin.kyc.index');
        }

        if ($user === null || ! $user->hasAnyRole(['super_admin', 'super_admin_manager'])) {
            abort(403, 'Недостаточно прав.');
        }

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users_total' => User::query()->count(),
                'kyc_pending' => KycProfile::query()->where('status', 'pending_review')->count(),
                'kyc_approved' => KycProfile::query()->where('status', 'approved')->count(),
            ],
        ]);
    }
}
