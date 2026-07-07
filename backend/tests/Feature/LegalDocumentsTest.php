<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class LegalDocumentsTest extends TestCase
{
    public function test_legal_index_page_is_available(): void
    {
        $this->get('/ru/legal')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Legal/Index')
                ->has('documents', 6));
    }

    public function test_legal_document_pages_are_available(): void
    {
        foreach (['terms', 'privacy', 'personal-data', 'offer', 'aml-kyc', 'requisites'] as $slug) {
            $this->get("/ru/legal/{$slug}")
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Legal/Show')
                    ->where('document.slug', $slug)
                    ->has('document.sections'));
        }
    }

    public function test_unknown_legal_document_returns_not_found(): void
    {
        $this->get('/ru/legal/unknown-document')->assertNotFound();
    }

    public function test_requisites_document_contains_company_bin(): void
    {
        config(['company.bin' => '260340021560']);

        $this->get('/ru/legal/requisites')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('document.slug', 'requisites')
                ->where('document.sections.0.paragraphs.1', 'БИН: 260340021560'));
    }
}
