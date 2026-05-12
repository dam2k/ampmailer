<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\RateLimit;

use Amp;

final class InMemoryRateLimiter implements RateLimiter
{
    private float $nextAllowedAt = 0.0;
    private readonly \Closure $now;
    private readonly \Closure $delay;

    public function __construct(
        private readonly float $minimumIntervalSeconds,
        ?callable $now = null,
        ?callable $delay = null,
    ) {
        $this->now = $now === null
            ? static fn (): float => microtime(true)
            : \Closure::fromCallable($now);
        $this->delay = $delay === null
            ? static fn (float $delay): null => Amp\delay($delay)
            : \Closure::fromCallable($delay);
    }

    public static function perSecond(float $messagesPerSecond): self
    {
        return new self(1.0 / $messagesPerSecond);
    }

    public function acquire(): void
    {
        $now = ($this->now)();
        if ($this->nextAllowedAt > $now) {
            ($this->delay)($this->nextAllowedAt - $now);
            $now = ($this->now)();
        }

        $this->nextAllowedAt = $now + $this->minimumIntervalSeconds;
    }
}
