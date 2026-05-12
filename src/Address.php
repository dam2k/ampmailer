<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer;

use Dam2k\AmpMailer\Exception\InvalidEmail;

final class Address
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $name = null,
    ) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmail("Invalid email address: {$email}");
        }

        if ($name !== null && (str_contains($name, "\r") || str_contains($name, "\n"))) {
            throw new InvalidEmail('Address display name must not contain CR or LF.');
        }
    }

    public static function parse(string|self $address): self
    {
        if ($address instanceof self) {
            return $address;
        }

        $address = trim($address);
        if (preg_match('/^(.+?)\s*<([^>]+)>$/', $address, $matches) === 1) {
            $name = trim($matches[1], " \t\n\r\0\x0B\"'");

            return new self(trim($matches[2]), $name !== '' ? $name : null);
        }

        return new self($address);
    }

    public function format(): string
    {
        if ($this->name === null || $this->name === '') {
            return $this->email;
        }

        return $this->formatDisplayName($this->name) . ' <' . $this->email . '>';
    }

    private function formatDisplayName(string $name): string
    {
        if (preg_match('/[^\x20-\x7E]/', $name) === 1) {
            return '=?UTF-8?B?' . base64_encode($name) . '?=';
        }

        if (preg_match('/^[A-Za-z0-9!#$%&\'*+\-\/=?^_`{|}~ ]+$/', $name) === 1) {
            return $name;
        }

        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '"';
    }
}
