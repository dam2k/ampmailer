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

        return $this->name . ' <' . $this->email . '>';
    }
}
