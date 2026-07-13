<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\AdminUrl;
use App\Support\LocaleManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebAppManifestController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        if ($this->shouldServeAdminManifest($request)) {
            return $this->admin();
        }

        return $this->client($request);
    }

    private function shouldServeAdminManifest(Request $request): bool
    {
        if (AdminUrl::isAdminHost($request)) {
            return true;
        }

        return app()->environment('testing') && $request->query('manifest') === 'admin';
    }

    public function admin(): JsonResponse
    {
        return response()->json([
            'id' => '/',
            'name' => 'KZTUSDT Admin',
            'short_name' => 'Admin',
            'description' => 'Панель администрирования kztusdt.kz',
            'theme_color' => '#001529',
            'background_color' => '#001529',
            'display' => 'standalone',
            'orientation' => 'any',
            'start_url' => '/admin/login',
            'scope' => '/',
            'lang' => 'ru',
            'icons' => $this->icons(),
        ], 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }

    public function client(Request $request): JsonResponse
    {
        $locale = LocaleManager::resolve($request);
        $startUrl = '/'.$locale.'/';

        return response()->json([
            'id' => '/',
            'name' => (string) config('company.name', config('app.name')),
            'short_name' => (string) config('company.name', config('app.name')),
            'description' => 'PWA крипто-обменник USDT / KZT',
            'theme_color' => '#2563eb',
            'background_color' => '#eef1f6',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'start_url' => $startUrl,
            'scope' => '/',
            'lang' => $locale,
            'icons' => $this->icons(),
        ], 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function icons(): array
    {
        return [
            [
                'src' => '/icons/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
            [
                'src' => '/icons/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
            [
                'src' => '/icons/icon-512-maskable.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ];
    }
}
