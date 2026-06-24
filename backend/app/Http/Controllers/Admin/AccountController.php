<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AccountController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Account', [
            'profile' => $this->profileService->profilePayload($user),
            'roles' => $user?->roles->pluck('code')->values()->all() ?? [],
        ]);
    }
}
