<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LegalDocumentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LegalController extends Controller
{
    public function __construct(
        private readonly LegalDocumentService $legalDocumentService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Legal/Index', [
            'documents' => $this->legalDocumentService->catalog(),
            'updatedAt' => config('company.documents_updated_at'),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        try {
            $document = $this->legalDocumentService->get($slug);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        return Inertia::render('Legal/Show', [
            'document' => $document,
        ]);
    }
}
