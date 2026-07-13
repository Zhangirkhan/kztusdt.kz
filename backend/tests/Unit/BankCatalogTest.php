<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\BankCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BankCatalogTest extends TestCase
{
    #[Test]
    public function it_resolves_bank_name_from_catalog_entry(): void
    {
        $this->assertSame('Kaspi Bank', BankCatalog::nameForCode('kaspi'));
        $this->assertSame('CASPKZKA', BankCatalog::bikForCode('kaspi'));
    }

    #[Test]
    public function it_returns_options_with_string_names(): void
    {
        $options = BankCatalog::options();

        $this->assertNotEmpty($options);
        $this->assertSame('kaspi', $options[0]['code']);
        $this->assertSame('Kaspi Bank', $options[0]['name']);
        $this->assertIsString($options[0]['name']);
    }

    #[Test]
    public function it_returns_options_with_bik(): void
    {
        $options = BankCatalog::optionsWithBik();
        $kaspi = collect($options)->firstWhere('code', 'kaspi');

        $this->assertNotNull($kaspi);
        $this->assertSame('Kaspi Bank', $kaspi['name']);
        $this->assertSame('CASPKZKA', $kaspi['bik']);
    }
}
