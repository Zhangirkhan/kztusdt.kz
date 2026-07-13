<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Support\AdminUrl;
use Illuminate\Testing\TestResponse;

trait InteractsWithAdminHost
{
    protected function adminHost(): string
    {
        return AdminUrl::domain();
    }

    /**
     * @return array<string, string>
     */
    protected function adminServerVariables(): array
    {
        return [
            'HTTP_HOST' => $this->adminHost(),
            'SERVER_NAME' => $this->adminHost(),
            'HTTPS' => 'on',
        ];
    }

    protected function getAsAdmin(string $uri, array $headers = []): TestResponse
    {
        return $this->withServerVariables($this->adminServerVariables())
            ->get($uri, $headers);
    }

    protected function postAsAdmin(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withServerVariables($this->adminServerVariables())
            ->post($uri, $data, $headers);
    }

    protected function patchAsAdmin(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->withServerVariables($this->adminServerVariables())
            ->patch($uri, $data, $headers);
    }

    protected function actingAsAdmin($user, ?string $driver = null): static
    {
        $this->withServerVariables($this->adminServerVariables());

        return $this->actingAs($user, $driver);
    }
}
