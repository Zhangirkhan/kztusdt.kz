<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class ExampleTest extends TestCase
{
    public function test_the_application_redirects_root_to_phone_auth(): void
    {
        $this->get('/')->assertRedirect('/auth/phone');
    }
}
