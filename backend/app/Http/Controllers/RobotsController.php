<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

final class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $siteUrl = rtrim((string) config('seo.site_url'), '/');

        $lines = [
            'User-agent: *',
            'Allow: /auth/phone',
            'Allow: /legal',
            'Disallow: /home',
            'Disallow: /wallet',
            'Disallow: /exchange',
            'Disallow: /withdraw',
            'Disallow: /profile',
            'Disallow: /kyc',
            'Disallow: /admin',
            'Disallow: /auth/telegram',
            'Disallow: /auth/whatsapp',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /api',
            '',
            'Sitemap: '.$siteUrl.'/sitemap.xml',
        ];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
