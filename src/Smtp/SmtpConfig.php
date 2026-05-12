<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Smtp;

use Amp\Socket\ClientTlsContext;

final class SmtpConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port = 587,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly TlsMode $tlsMode = TlsMode::StartTls,
        public readonly float $timeout = 30.0,
        public readonly ?ClientTlsContext $tlsContext = null,
    ) {
    }
}
