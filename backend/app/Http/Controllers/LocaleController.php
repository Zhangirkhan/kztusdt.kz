<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\LocaleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', LocaleManager::supported())],
        ]);

        $locale = (string) $validated['locale'];

        if ($request->user() !== null) {
            $request->user()->update(['locale' => $locale]);
        }

        return redirect()
            ->to(LocaleManager::localizedUrl($locale))
            ->withCookie(LocaleManager::remember($locale));
    }
}
