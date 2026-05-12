<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\RateLimit;

use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Mailer;

final class RateLimitedMailer implements Mailer
{
    public function __construct(
        private readonly Mailer $inner,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function send(Email $email): void
    {
        $this->limiter->acquire();
        $this->inner->send($email);
    }
}
