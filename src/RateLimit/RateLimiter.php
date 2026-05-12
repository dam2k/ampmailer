<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\RateLimit;

interface RateLimiter
{
    public function acquire(): void;
}
