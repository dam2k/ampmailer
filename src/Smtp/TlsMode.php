<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Smtp;

enum TlsMode
{
    case Disabled;
    case Implicit;
    case StartTls;
    case StartTlsIfAvailable;
}
