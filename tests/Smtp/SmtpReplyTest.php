<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Tests\Smtp;

use Dam2k\AmpMailer\Smtp\SmtpReply;
use PHPUnit\Framework\TestCase;

final class SmtpReplyTest extends TestCase
{
    public function testParsesSingleLineReply(): void
    {
        $reply = SmtpReply::parse(["250 OK"]);

        self::assertSame(250, $reply->code);
        self::assertSame('OK', $reply->message);
        self::assertFalse($reply->isTemporary());
    }

    public function testParsesTemporaryReply(): void
    {
        $reply = SmtpReply::parse(["421 4.7.0 Try later"]);

        self::assertSame(421, $reply->code);
        self::assertSame('4.7.0 Try later', $reply->message);
        self::assertTrue($reply->isTemporary());
    }

    public function testParsesMultilineReply(): void
    {
        $reply = SmtpReply::parse([
            '250-localhost',
            '250-AUTH PLAIN LOGIN',
            '250 OK',
        ]);

        self::assertSame(250, $reply->code);
        self::assertSame("localhost\nAUTH PLAIN LOGIN\nOK", $reply->message);
    }
}
