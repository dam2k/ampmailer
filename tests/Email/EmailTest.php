<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Tests\Email;

use Dam2k\AmpMailer\Address;
use Dam2k\AmpMailer\Attachment;
use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Exception\InvalidEmail;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testFluentMethodsMutateAndReturnSameInstance(): void
    {
        $email = Email::new();

        $returned = $email
            ->from('John Example <john@example.com>')
            ->to('jane@example.net')
            ->cc('team@example.net')
            ->bcc('audit@example.net')
            ->replyTo('support@example.com')
            ->subject('Welcome')
            ->text('Hello')
            ->html('<p>Hello</p>');

        self::assertSame($email, $returned);
        self::assertSame('john@example.com', $email->getFrom()->email);
        self::assertSame('John Example', $email->getFrom()->name);
        self::assertSame('Welcome', $email->getSubject());
        self::assertSame('Hello', $email->getText());
        self::assertSame('<p>Hello</p>', $email->getHtml());
        self::assertCount(1, $email->getTo());
        self::assertCount(1, $email->getCc());
        self::assertCount(1, $email->getBcc());
        self::assertCount(1, $email->getReplyTo());
    }

    public function testAddressParsesDisplayName(): void
    {
        $address = Address::parse('Jane Doe <jane@example.net>');

        self::assertSame('Jane Doe', $address->name);
        self::assertSame('jane@example.net', $address->email);
        self::assertSame('Jane Doe <jane@example.net>', $address->format());
    }

    public function testInvalidAddressThrows(): void
    {
        $this->expectException(InvalidEmail::class);

        Address::parse('not-an-address');
    }

    public function testAttachmentsCanBeFileBackedOrMemoryBacked(): void
    {
        $email = Email::new()
            ->attachFile(__FILE__, 'email-test.php')
            ->attachData('payload', 'payload.txt', 'text/plain');

        self::assertCount(2, $email->getAttachments());
        self::assertSame('email-test.php', $email->getAttachments()[0]->name);
        self::assertSame(Attachment::SOURCE_FILE, $email->getAttachments()[0]->sourceType);
        self::assertSame('payload.txt', $email->getAttachments()[1]->name);
        self::assertSame(Attachment::SOURCE_DATA, $email->getAttachments()[1]->sourceType);
    }

    public function testHeadersUseExplicitGetter(): void
    {
        $email = Email::new()->header('X-Test', 'yes');

        self::assertSame(['X-Test' => 'yes'], $email->getHeaders());
    }
}
