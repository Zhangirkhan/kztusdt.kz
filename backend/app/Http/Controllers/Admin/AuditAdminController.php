<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AuditAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('action', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return Inertia::render('Admin/Audit/Index', [
            'logs' => $logs,
            'filters' => ['q' => $search],
        ]);
    }
}
