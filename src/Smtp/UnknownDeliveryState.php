<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Smtp;

final class UnknownDeliveryState extends SmtpException
{
    public static function afterData(string $message): self
    {
        return new self(0, $message, false);
    }
}
