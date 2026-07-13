<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ExampleTest extends TestCase
{
    public function test_the_application_redirects_root_to_locale_home(): void
    {
        $this->get('/')->assertRedirect('/ru/');
    }
}
