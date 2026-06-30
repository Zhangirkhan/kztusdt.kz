<?php

declare(strict_types=1);

namespace App\Actions\Webauthn;

use App\Services\PhoneAuthService;
use Illuminate\Contracts\Auth\Authenticatable as User;
use LaravelWebauthn\Actions\LoginUserRetrieval as BaseLoginUserRetrieval;
use LaravelWebauthn\Services\LoginRateLimiter;
use LaravelWebauthn\Services\Webauthn;

final class LoginUserRetrieval extends BaseLoginUserRetrieval
{
    public function __construct(
        LoginRateLimiter $limiter,
        private readonly PhoneAuthService $phoneAuthService,
    ) {
        parent::__construct($limiter);
    }

    /**
     * @param  array<string, mixed>|null  $credentials
     */
    protected function getUserFromCredentials(?array $credentials): ?User
    {
        if ($credentials !== null && isset($credentials[Webauthn::username()])) {
            $credentials[Webauthn::username()] = $this->phoneAuthService->normalizePhone(
                (string) $credentials[Webauthn::username()],
            );
        }

        return parent::getUserFromCredentials($credentials);
    }
}
