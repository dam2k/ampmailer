<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Smtp;

use RuntimeException;

class SmtpException extends RuntimeException
{
    public function __construct(
        public readonly int $replyCode,
        string $message,
        private readonly bool $temporary,
    ) {
        parent::__construct($message, $replyCode);
    }

    public static function temporary(int $replyCode, string $message): self
    {
        return new self($replyCode, $message, true);
    }

    public static function permanent(int $replyCode, string $message): self
    {
        return new self($replyCode, $message, false);
    }

    public function isTemporary(): bool
    {
        return $this->temporary;
    }
}
