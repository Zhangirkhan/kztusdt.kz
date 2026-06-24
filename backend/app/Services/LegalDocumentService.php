<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

final class LegalDocumentService
{
    /**
     * @return list<array{slug: string, title: string, description: string}>
     */
    public function catalog(): array
    {
        $configured = config('legal.documents');

        if (is_array($configured) && $configured !== []) {
            return $configured;
        }

        return $this->discoverDocuments();
    }

    /**
     * @return list<array{slug: string, title: string, description: string}>
     */
    private function discoverDocuments(): array
    {
        $files = glob(resource_path('legal/ru/*.php')) ?: [];
        sort($files);

        $documents = [];

        foreach ($files as $file) {
            $slug = basename($file, '.php');

            /** @var array{title?: string, description?: string} $document */
            $document = require $file;

            $documents[] = [
                'slug' => $slug,
                'title' => (string) ($document['title'] ?? $slug),
                'description' => (string) ($document['description'] ?? ''),
            ];
        }

        return $documents;
    }

    /**
     * @return array{slug: string, title: string, updated_at: string, sections: list<array{heading: string, paragraphs: list<string>}>}
     */
    public function get(string $slug): array
    {
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new InvalidArgumentException("Legal document [{$slug}] not found.");
        }

        $meta = collect($this->catalog())->firstWhere('slug', $slug);
        $path = resource_path("legal/ru/{$slug}.php");

        if ($meta === null && ! is_file($path)) {
            throw new InvalidArgumentException("Legal document [{$slug}] not found.");
        }

        if (! is_file($path)) {
            throw new InvalidArgumentException("Legal document file [{$slug}] not found.");
        }

        /** @var array{title?: string, sections: list<array{heading: string, paragraphs: list<string>}>} $document */
        $document = require $path;

        return [
            'slug' => $slug,
            'title' => (string) ($document['title'] ?? ($meta['title'] ?? $slug)),
            'updated_at' => (string) config('company.documents_updated_at'),
            'sections' => $this->applyReplacements($document['sections']),
        ];
    }

    /**
     * @param  list<array{heading: string, paragraphs: list<string>}>  $sections
     * @return list<array{heading: string, paragraphs: list<string>}>
     */
    private function applyReplacements(array $sections): array
    {
        return array_map(function (array $section): array {
            return [
                'heading' => $this->replace((string) $section['heading']),
                'paragraphs' => array_map(
                    fn (string $paragraph): string => $this->replace($paragraph),
                    $section['paragraphs'] ?? [],
                ),
            ];
        }, $sections);
    }

    private function replace(string $text): string
    {
        $replacements = [
            '{{company_name}}' => (string) config('company.name'),
            '{{legal_name}}' => (string) config('company.legal_name'),
            '{{director}}' => (string) config('company.director'),
            '{{bin}}' => (string) config('company.bin'),
            '{{address}}' => (string) config('company.address'),
            '{{bank_name}}' => (string) config('company.bank_name'),
            '{{bank_account}}' => (string) config('company.bank_account'),
            '{{bank_bic}}' => (string) config('company.bank_bic'),
            '{{website}}' => (string) config('company.website'),
            '{{support_email}}' => (string) config('company.support_email'),
            '{{documents_updated_at}}' => (string) config('company.documents_updated_at'),
            '{{fee_default}}' => (string) config('exchange.fee_default'),
        ];

        return strtr($text, $replacements);
    }
}
