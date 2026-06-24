<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Broadcast should be retried on the next scheduler pass without marking the withdrawal failed. */
final class WithdrawalRetryLaterException extends RuntimeException {}
