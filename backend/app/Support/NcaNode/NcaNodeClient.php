<?php

declare(strict_types=1);

namespace App\Support\NcaNode;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NcaNodeClient
{
    /**
     * @return array<string, mixed>
     */
    public function verifyCms(string $cmsBase64, ?string $dataBase64 = null): array
    {
        $payload = [
            'cms' => $this->normalizeBase64($cmsBase64),
        ];

        if ($dataBase64 !== null) {
            $payload['data'] = $this->normalizeBase64($dataBase64);
        }

        $revocation = [];

        if (config('ncanode.verify_ocsp')) {
            $revocation[] = 'OCSP';
        }

        if (config('ncanode.verify_crl')) {
            $revocation[] = 'CRL';
        }

        if ($revocation !== []) {
            $payload['revocationCheck'] = $revocation;
        }

        return $this->post('/cms/verify', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function extractCms(string $cmsBase64): array
    {
        return $this->post('/cms/extract', [
            'cms' => $this->normalizeBase64($cmsBase64),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function x509Info(string $certBase64): array
    {
        return $this->post('/x509/info', [
            'cert' => $this->normalizeBase64($certBase64),
            'revocationCheck' => array_values(array_filter([
                config('ncanode.verify_ocsp') ? 'OCSP' : null,
                config('ncanode.verify_crl') ? 'CRL' : null,
            ])),
        ]);
    }

    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(5)
                ->get(config('ncanode.base_url').'/actuator/health');

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $url = config('ncanode.base_url').$path;

        try {
            $response = Http::timeout((int) config('ncanode.timeout'))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload)
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Сервис проверки ЭЦП недоступен. Попробуйте позже.', previous: $exception);
        } catch (RequestException $exception) {
            throw new RuntimeException('Ошибка проверки ЭЦП: '.$exception->getMessage(), previous: $exception);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        return $body;
    }

    private function normalizeBase64(string $value): string
    {
        return str_replace(["\r", "\n", ' '], '', trim($value));
    }
}
