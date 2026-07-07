<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LegalDocumentService;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(LegalDocumentService $legalDocumentService): Response
    {
        $urls = [];

        foreach ((array) config('locales.supported', ['ru']) as $locale) {
            URL::defaults(['locale' => $locale]);

            $urls[] = [
                'loc' => route('auth.phone'),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ];

            $urls[] = [
                'loc' => route('legal.index'),
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];

            foreach ($legalDocumentService->catalog() as $document) {
                $urls[] = [
                    'loc' => route('legal.show', ['slug' => $document['slug']]),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ];
            }
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
