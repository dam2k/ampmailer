<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Smtp;

use RuntimeException;

final class SmtpReply
{
    public function __construct(
        public readonly int $code,
        public readonly string $message,
    ) {
    }

    /**
     * @param non-empty-list<string> $lines
     */
    public static function parse(array $lines): self
    {
        $code = -1;
        $messages = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\d{3})(?:[ -])(.*)$/', $line, $matches) !== 1) {
                throw new RuntimeException("Invalid SMTP reply line: {$line}");
            }

            if ($code === -1) {
                $code = (int) $matches[1];
            }

            if ((int) $matches[1] !== $code) {
                throw new RuntimeException('SMTP reply code changed across multiline reply.');
            }

            $messages[] = $matches[2];
        }

        return new self($code, implode("\n", $messages));
    }

    public function isTemporary(): bool
    {
        return $this->code >= 400 && $this->code < 500;
    }

    public function isPositiveCompletion(): bool
    {
        return $this->code >= 200 && $this->code < 300;
    }

    public function isPositiveIntermediate(): bool
    {
        return $this->code >= 300 && $this->code < 400;
    }
}
