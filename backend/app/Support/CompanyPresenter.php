<?php

declare(strict_types=1);

namespace App\Support;

final class CompanyPresenter
{
    /**
     * @return array{name: mixed, tagline: mixed}
     */
    public static function layout(): array
    {
        return [
            'name' => config('company.name'),
            'tagline' => config('company.tagline'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function hero(): array
    {
        return [
            ...self::layout(),
            'home_intro' => config('company.home_intro'),
            'legal_name' => config('company.legal_name'),
            'bin' => config('company.bin'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function intro(): array
    {
        return [
            ...self::layout(),
            'description' => config('company.description'),
            'features' => config('company.features'),
            'legal_name' => config('company.legal_name'),
            'bin' => config('company.bin'),
            'director' => config('company.director'),
            'support_email' => config('company.support_email'),
        ];
    }
}
