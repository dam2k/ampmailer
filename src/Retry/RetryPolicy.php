<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Retry;

final class RetryPolicy
{
    public function __construct(
        public readonly int $maxAttempts = 3,
        public readonly float $initialDelay = 1.0,
        public readonly float $multiplier = 2.0,
        public readonly float $maxDelay = 30.0,
    ) {
    }

    public function delayForAttempt(int $attempt): float
    {
        if ($attempt <= 1) {
            return 0.0;
        }

        return min($this->maxDelay, $this->initialDelay * ($this->multiplier ** ($attempt - 2)));
    }
}
