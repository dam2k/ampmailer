<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\RateLimit;

use Amp;

final class InMemoryRateLimiter implements RateLimiter
{
    private float $nextAllowedAt = 0.0;

    public function __construct(
        private readonly float $minimumIntervalSeconds,
    ) {
    }

    public static function perSecond(float $messagesPerSecond): self
    {
        return new self(1.0 / $messagesPerSecond);
    }

    public function acquire(): void
    {
        $now = microtime(true);
        if ($this->nextAllowedAt > $now) {
            Amp\delay($this->nextAllowedAt - $now);
            $now = microtime(true);
        }

        $this->nextAllowedAt = $now + $this->minimumIntervalSeconds;
    }
}
