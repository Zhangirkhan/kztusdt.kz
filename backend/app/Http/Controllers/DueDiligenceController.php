<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SubmitDueDiligenceRequest;
use App\Services\DueDiligenceService;
use Illuminate\Http\RedirectResponse;

final class DueDiligenceController extends Controller
{
    public function __construct(
        private readonly DueDiligenceService $dueDiligenceService,
    ) {}

    public function store(SubmitDueDiligenceRequest $request): RedirectResponse
    {
        $this->dueDiligenceService->submit($request->user(), $request->validated());

        return redirect()
            ->back()
            ->with('success', 'Анкета сохранена. Спасибо!');
    }
}
