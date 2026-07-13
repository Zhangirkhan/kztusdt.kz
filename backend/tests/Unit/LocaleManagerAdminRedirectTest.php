<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LocaleManager;
use Illuminate\Http\Request;
use Tests\TestCase;

final class LocaleManagerAdminRedirectTest extends TestCase
{
    public function test_redirect_after_locale_change_keeps_admin_path_without_locale_prefix(): void
    {
        $request = Request::create(
            'https://admin.kztusdt.kz/locale',
            'POST',
            server: [
                'HTTP_REFERER' => 'https://admin.kztusdt.kz/admin/orders',
                'HTTP_HOST' => 'admin.kztusdt.kz',
            ],
        );

        $url = LocaleManager::redirectAfterLocaleChange($request, 'en');

        $this->assertSame('https://admin.kztusdt.kz/admin/orders', $url);
    }

    public function test_localized_url_does_not_prefix_admin_paths_with_locale(): void
    {
        $url = LocaleManager::localizedUrl('en', 'https://admin.kztusdt.kz/admin/account');

        $this->assertSame('https://admin.kztusdt.kz/admin/account', $url);
    }

    public function test_redirect_strips_existing_locale_prefix_from_admin_path(): void
    {
        $request = Request::create(
            'https://admin.kztusdt.kz/locale',
            'POST',
            server: ['HTTP_REFERER' => 'https://admin.kztusdt.kz/en/admin/kyc'],
        );

        $url = LocaleManager::redirectAfterLocaleChange($request, 'kk');

        $this->assertSame('https://admin.kztusdt.kz/admin/kyc', $url);
    }
}
