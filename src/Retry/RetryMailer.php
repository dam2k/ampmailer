<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Retry;

use Amp;
use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Mailer;
use Dam2k\AmpMailer\Smtp\SmtpException;

final class RetryMailer implements Mailer
{
    public function __construct(
        private readonly Mailer $inner,
        private readonly RetryPolicy $policy = new RetryPolicy(),
    ) {
    }

    public function send(Email $email): void
    {
        $attempt = 1;

        while (true) {
            try {
                $delay = $this->policy->delayForAttempt($attempt);
                if ($delay > 0.0) {
                    Amp\delay($delay);
                }

                $this->inner->send($email);

                return;
            } catch (SmtpException $exception) {
                if (!$exception->isTemporary() || $attempt >= $this->policy->maxAttempts) {
                    throw $exception;
                }

                $attempt++;
            }
        }
    }
}
