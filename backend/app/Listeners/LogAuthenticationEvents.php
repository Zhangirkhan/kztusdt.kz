<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Support\AppLog;
use App\Support\RequestLogContext;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;

final class LogAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        AppLog::auth('auth.login', [
            'user_id' => $event->user->getAuthIdentifier(),
            'guard' => $event->guard,
            'remember' => $event->remember,
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        AppLog::authWarning('auth.failed', [
            'guard' => $event->guard,
            'phone' => RequestLogContext::maskPhone(is_string($event->credentials['phone'] ?? null) ? $event->credentials['phone'] : null),
            'email' => is_string($event->credentials['email'] ?? null)
                ? $this->maskEmail($event->credentials['email'])
                : null,
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        $userId = $event->user instanceof Authenticatable
            ? $event->user->getAuthIdentifier()
            : null;

        AppLog::auth('auth.logout', [
            'user_id' => $userId,
            'guard' => $event->guard,
        ]);
    }

    private function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);

        return substr($local, 0, 1).'***@'.$domain;
    }
}
