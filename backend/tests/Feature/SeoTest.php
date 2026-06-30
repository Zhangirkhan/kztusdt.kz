<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class SeoTest extends TestCase
{
    use ExchangeTestHelpers;

    public function test_landing_page_has_indexable_seo_props(): void
    {
        config([
            'seo.site_url' => 'https://kztusdt.kz',
            'company.name' => 'kztusdt.kz',
        ]);

        $this->get('/auth/phone')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Phone')
                ->where('seo.indexable', true)
                ->where('seo.robots', 'index, follow')
                ->where('seo.canonical', 'https://kztusdt.kz/auth/phone')
                ->has('seo.jsonLd'));
    }

    public function test_landing_page_renders_seo_meta_in_html(): void
    {
        config([
            'seo.site_url' => 'https://kztusdt.kz',
            'company.name' => 'kztusdt.kz',
        ]);

        $this->get('/auth/phone')
            ->assertOk()
            ->assertSee('name="description"', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('index, follow', false);
    }

    public function test_legal_index_has_indexable_seo_props(): void
    {
        config(['seo.site_url' => 'https://kztusdt.kz']);

        $this->get('/legal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Legal/Index')
                ->where('seo.indexable', true)
                ->where('seo.robots', 'index, follow')
                ->where('seo.canonical', 'https://kztusdt.kz/legal'));
    }

    public function test_authenticated_home_page_is_not_indexable(): void
    {
        $user = $this->createClient();

        $this->actingAs($user)
            ->get('/home')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('seo.indexable', false)
                ->where('seo.robots', 'noindex, nofollow'));
    }

    public function test_telegram_wait_page_is_not_indexable(): void
    {
        $this->get('/auth/telegram/invalid-code')->assertNotFound();

        $session = \App\Models\AuthSession::query()->create([
            'phone' => '+77071234567',
            'login_code' => 'SEO'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(29)),
            'code_hash' => null,
            'gateway_request_id' => null,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);

        $loginCode = $session->login_code;

        $this->get("/auth/telegram/{$loginCode}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/TelegramWait')
                ->where('seo.indexable', false)
                ->where('seo.robots', 'noindex, nofollow'));
    }

    public function test_robots_txt_disallows_pwa_paths(): void
    {
        config(['seo.site_url' => 'https://kztusdt.kz']);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /home')
            ->assertSee('Disallow: /wallet')
            ->assertSee('Disallow: /admin')
            ->assertSee('Allow: /auth/phone')
            ->assertSee('Allow: /legal')
            ->assertSee('Sitemap: https://kztusdt.kz/sitemap.xml');
    }

    public function test_sitemap_lists_public_pages(): void
    {
        config(['seo.site_url' => 'https://kztusdt.kz']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('content-type', 'application/xml')
            ->assertSee('https://kztusdt.kz/auth/phone')
            ->assertSee('https://kztusdt.kz/legal')
            ->assertSee('https://kztusdt.kz/legal/terms');
    }
}
