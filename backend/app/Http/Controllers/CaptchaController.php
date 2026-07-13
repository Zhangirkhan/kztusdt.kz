<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CaptchaService;
use Illuminate\Http\Response;

final class CaptchaController extends Controller
{
    public function __construct(
        private readonly CaptchaService $captchaService,
    ) {}

    public function image(): Response
    {
        return $this->captchaService->image();
    }
}
