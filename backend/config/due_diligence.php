<?php

declare(strict_types=1);

return [
    // USDT amount that triggers the enhanced due diligence questionnaire.
    'threshold_usdt' => (float) env('DUE_DILIGENCE_THRESHOLD_USDT', 10000),
];
