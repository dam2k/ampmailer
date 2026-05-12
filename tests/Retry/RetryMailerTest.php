<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Tests\Retry;

use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Mailer;
use Dam2k\AmpMailer\Retry\RetryMailer;
use Dam2k\AmpMailer\Retry\RetryPolicy;
use Dam2k\AmpMailer\Smtp\SmtpException;
use PHPUnit\Framework\TestCase;

final class RetryMailerTest extends TestCase
{
    public function testRetriesTemporaryFailureUntilSuccess(): void
    {
        $inner = new class implements Mailer {
            public int $calls = 0;

            public function send(Email $email): void
            {
                $this->calls++;
                if ($this->calls < 3) {
                    throw SmtpException::temporary(421, 'Try later');
                }
            }
        };

        $mailer = new RetryMailer($inner, new RetryPolicy(maxAttempts: 3, initialDelay: 0.0));
        $mailer->send(Email::new()->from('a@example.com')->to('b@example.com')->text('Body'));

        self::assertSame(3, $inner->calls);
    }

    public function testDoesNotRetryPermanentFailure(): void
    {
        $inner = new class implements Mailer {
            public int $calls = 0;

            public function send(Email $email): void
            {
                $this->calls++;
                throw SmtpException::permanent(550, 'Rejected');
            }
        };

        $mailer = new RetryMailer($inner, new RetryPolicy(maxAttempts: 3, initialDelay: 0.0));

        try {
            $mailer->send(Email::new()->from('a@example.com')->to('b@example.com')->text('Body'));
            self::fail('Expected permanent SMTP exception.');
        } catch (SmtpException $exception) {
            self::assertFalse($exception->isTemporary());
            self::assertSame(1, $inner->calls);
        }
    }

    public function testStopsAfterMaxAttemptsForTemporaryFailure(): void
    {
        $inner = new class implements Mailer {
            public int $calls = 0;

            public function send(Email $email): void
            {
                $this->calls++;
                throw SmtpException::temporary(421, 'Try later');
            }
        };

        $mailer = new RetryMailer($inner, new RetryPolicy(maxAttempts: 2, initialDelay: 0.0));

        try {
            $mailer->send(Email::new()->from('a@example.com')->to('b@example.com')->text('Body'));
            self::fail('Expected temporary SMTP exception.');
        } catch (SmtpException $exception) {
            self::assertTrue($exception->isTemporary());
            self::assertSame(2, $inner->calls);
        }
    }

    public function testAppliesBackoffBeforeRetryAttempts(): void
    {
        $inner = new class implements Mailer {
            public int $calls = 0;

            public function send(Email $email): void
            {
                $this->calls++;
                if ($this->calls < 4) {
                    throw SmtpException::temporary(421, 'Try later');
                }
            }
        };

        $delays = [];
        $mailer = new RetryMailer(
            $inner,
            new RetryPolicy(maxAttempts: 4, initialDelay: 0.5, multiplier: 3.0, maxDelay: 2.0),
            static function (float $delay) use (&$delays): void {
                $delays[] = $delay;
            },
        );

        $mailer->send(Email::new()->from('a@example.com')->to('b@example.com')->text('Body'));

        self::assertSame(4, $inner->calls);
        self::assertSame([0.5, 1.5, 2.0], $delays);
    }
}
