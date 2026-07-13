<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AdminNavPresenter;
use App\Support\AdminUrl;
use App\Support\CompanyPresenter;
use App\Support\LocaleManager;
use App\Support\SeoPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        $manifest = public_path('build/manifest.json');

        if (! is_readable($manifest)) {
            return parent::version($request);
        }

        return (string) filemtime($manifest);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user !== null) {
            $user->loadMissing('roles:id,code');
        }

        return [
            ...parent::share($request),
            'auth' => fn () => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone_verified' => (bool) $user->phone_verified,
                    'kyc_status' => (string) $user->kyc_status,
                    'can_use_wallet' => $user->canUseWallet(),
                ] : null,
                'isStaff' => AdminNavPresenter::canAccessAdmin($user),
                'canAccessPwa' => AdminNavPresenter::canAccessPwa($user),
            ],
            'adminNav' => fn () => AdminNavPresenter::forUser($user),
            'company' => fn () => CompanyPresenter::layout(),
            'seo' => fn () => SeoPresenter::forRequest($request),
            'locale' => fn () => [
                'current' => app()->getLocale(),
                'options' => LocaleManager::options(),
            ],
            'push' => fn () => [
                'vapidPublicKey' => $user !== null ? (string) config('webpush.vapid.public_key') : '',
            ],
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
            ],
            'adminApp' => fn () => [
                'url' => AdminUrl::base(),
                'isSubdomain' => AdminUrl::isAdminHost($request),
            ],
            'ziggy' => fn () => array_merge(
                (new Ziggy(AdminNavPresenter::ziggyGroup($user)))->toArray(),
                ['url' => $request->getSchemeAndHttpHost()],
            ),
        ];
    }
}
