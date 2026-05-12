<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Tests\Smoke;

use Dam2k\AmpMailer\Mailer;
use PHPUnit\Framework\TestCase;

final class PackageSmokeTest extends TestCase
{
    public function testMailerInterfaceIsLoadable(): void
    {
        self::assertTrue(interface_exists(Mailer::class));
    }
}
