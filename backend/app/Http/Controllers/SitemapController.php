<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\LegalDocumentService;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(LegalDocumentService $legalDocumentService): Response
    {
        $siteUrl = rtrim((string) config('seo.site_url'), '/');

        $urls = [
            [
                'loc' => $siteUrl.'/auth/phone',
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => $siteUrl.'/legal',
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ],
        ];

        foreach ($legalDocumentService->catalog() as $document) {
            $urls[] = [
                'loc' => $siteUrl.'/legal/'.$document['slug'],
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ];
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
