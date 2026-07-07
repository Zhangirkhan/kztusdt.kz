<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\LegalDocumentService;
use Illuminate\Http\Request;
use InvalidArgumentException;

final class SeoPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function forRequest(Request $request): array
    {
        return self::build($request);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forBlade(Request $request): array
    {
        return self::build($request);
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(Request $request): array
    {
        $routeName = $request->route()?->getName();
        $siteUrl = (string) config('seo.site_url');
        $companyName = (string) config('company.name');

        if (! self::isIndexable($request, $routeName)) {
            return self::noindex();
        }

        return match ($routeName) {
            'auth.phone' => self::landing($siteUrl, $companyName),
            'legal.index' => self::legalIndex($siteUrl, $companyName),
            'legal.show' => self::legalShow(
                $request,
                $siteUrl,
                $companyName,
                (string) $request->route('slug'),
            ),
            default => self::noindex(),
        };
    }

    private static function isIndexable(Request $request, ?string $routeName): bool
    {
        if ($request->user() !== null) {
            return false;
        }

        return in_array($routeName, config('seo.indexable_routes', []), true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function landing(string $siteUrl, string $companyName): array
    {
        $title = (string) __('seo.landing_title', ['name' => $companyName]);
        $description = (string) __('seo.landing_description');
        $canonical = route('auth.phone');
        $image = (string) config('seo.image');

        return self::indexable([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogUrl' => $canonical,
            'ogImage' => $image,
            'ogType' => 'website',
            'ogSiteName' => $companyName,
            'twitterCard' => 'summary',
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebSite',
                        'name' => $companyName,
                        'url' => $siteUrl,
                        'inLanguage' => ['ru', 'kk', 'en'],
                    ],
                    [
                        '@type' => 'FinancialService',
                        'name' => $companyName,
                        'url' => $canonical,
                        'description' => $description,
                        'image' => $image,
                        'areaServed' => [
                            '@type' => 'Country',
                            'name' => 'Kazakhstan',
                        ],
                        'currenciesAccepted' => 'USDT, KZT',
                        'provider' => [
                            '@type' => 'Organization',
                            'name' => (string) config('company.legal_name'),
                            'url' => $siteUrl,
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function legalIndex(string $siteUrl, string $companyName): array
    {
        $title = (string) __('seo.legal_index_title', ['name' => $companyName]);
        $description = (string) __('seo.legal_index_description', ['name' => $companyName]);
        $canonical = route('legal.index');

        return self::indexable([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogUrl' => $canonical,
            'ogImage' => (string) config('seo.image'),
            'ogType' => 'website',
            'ogSiteName' => $companyName,
            'twitterCard' => 'summary',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function legalShow(Request $request, string $siteUrl, string $companyName, string $slug): array
    {
        $service = app(LegalDocumentService::class);

        try {
            $document = $service->get($slug);
            $meta = collect($service->catalog())->firstWhere('slug', $slug);
        } catch (InvalidArgumentException) {
            return self::noindex();
        }

        $title = $document['title'].' — '.$companyName;
        $description = (string) ($meta['description'] ?? $document['title']);
        $canonical = route('legal.show', [
            'locale' => LocaleManager::resolve($request),
            'slug' => $slug,
        ]);

        return self::indexable([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogUrl' => $canonical,
            'ogImage' => (string) config('seo.image'),
            'ogType' => 'article',
            'ogSiteName' => $companyName,
            'twitterCard' => 'summary',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function indexable(array $payload): array
    {
        return [
            ...$payload,
            'robots' => 'index, follow',
            'indexable' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function noindex(): array
    {
        return [
            'title' => null,
            'description' => null,
            'canonical' => null,
            'ogTitle' => null,
            'ogDescription' => null,
            'ogUrl' => null,
            'ogImage' => null,
            'ogType' => null,
            'ogSiteName' => null,
            'twitterCard' => null,
            'jsonLd' => null,
            'robots' => 'noindex, nofollow',
            'indexable' => false,
        ];
    }
}
