<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer;

interface Mailer
{
    public function send(Email $email): void;
}
