<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Tests\RateLimit;

use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Mailer;
use Dam2k\AmpMailer\RateLimit\RateLimitedMailer;
use Dam2k\AmpMailer\RateLimit\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimitedMailerTest extends TestCase
{
    public function testAcquiresLimiterBeforeSending(): void
    {
        $events = new \ArrayObject();
        $limiter = new class($events) implements RateLimiter {
            /** @param \ArrayObject<int, string> $events */
            public function __construct(private \ArrayObject $events)
            {
            }

            public function acquire(): void
            {
                $this->events->append('acquire');
            }
        };
        $inner = new class($events) implements Mailer {
            /** @param \ArrayObject<int, string> $events */
            public function __construct(private \ArrayObject $events)
            {
            }

            public function send(Email $email): void
            {
                $this->events->append('send');
            }
        };

        (new RateLimitedMailer($inner, $limiter))->send(
            Email::new()->from('a@example.com')->to('b@example.com')->text('Body')
        );

        self::assertSame(['acquire', 'send'], $events->getArrayCopy());
    }
}
