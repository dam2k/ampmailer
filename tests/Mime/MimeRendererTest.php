<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Tests\Mime;

use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Mime\MimeRenderer;
use PHPUnit\Framework\TestCase;

final class MimeRendererTest extends TestCase
{
    public function testRendersTextMessageWithEnvelope(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('Sender <sender@example.com>')
                ->to('recipient@example.net')
                ->subject('Hello')
                ->text("Line one\nLine two")
        );

        self::assertSame('sender@example.com', $message->envelopeSender);
        self::assertSame(['recipient@example.net'], $message->envelopeRecipients);
        self::assertStringContainsString("From: Sender <sender@example.com>\r\n", $message->data);
        self::assertStringContainsString("To: recipient@example.net\r\n", $message->data);
        self::assertStringContainsString("Subject: Hello\r\n", $message->data);
        self::assertStringContainsString("Content-Type: text/plain; charset=UTF-8\r\n", $message->data);
        self::assertStringContainsString("Line one=0ALine two", $message->data);
        self::assertStringNotContainsString("\nLine two", str_replace("\r\n", '', $message->data));
    }

    public function testBccIsEnvelopeOnly(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->bcc('hidden@example.net')
                ->subject('Hidden')
                ->text('Body')
        );

        self::assertSame(['to@example.net', 'hidden@example.net'], $message->envelopeRecipients);
        self::assertStringNotContainsString('Bcc:', $message->data);
        self::assertStringNotContainsString('hidden@example.net', $message->data);
    }

    public function testRendersEncodedAddressDisplayName(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('Mario È <sender@example.com>')
                ->to('recipient@example.net')
                ->subject('Hello')
                ->text('Body')
        );

        self::assertStringContainsString(
            'From: =?UTF-8?B?' . base64_encode('Mario È') . "?= <sender@example.com>\r\n",
            $message->data,
        );
    }

    public function testRendersTextAndHtmlAsAlternative(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->subject('Alternative')
                ->text('Plain')
                ->html('<p>Html</p>')
        );

        self::assertStringContainsString('Content-Type: multipart/alternative;', $message->data);
        self::assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $message->data);
        self::assertStringContainsString('Content-Type: text/html; charset=UTF-8', $message->data);
    }

    public function testRendersAttachmentAsMixed(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->subject('Attachment')
                ->text('Attached')
                ->attachData('abc', 'sample.txt', 'text/plain')
        );

        self::assertStringContainsString('Content-Type: multipart/mixed;', $message->data);
        self::assertStringContainsString('Content-Type: text/plain; name="sample.txt"', $message->data);
        self::assertStringContainsString('Content-Disposition: attachment; filename="sample.txt"', $message->data);
        self::assertStringContainsString(chunk_split(base64_encode('abc'), 76, "\r\n"), $message->data);
        self::assertStringNotContainsString('AMPMAILER-MIXED-END', $message->data);
    }

    public function testRendersInlineAttachmentWithInlineDisposition(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->subject('Inline')
                ->html('<img src="cid:logo@example.net">')
                ->inlineData('image-bytes', 'logo.png', 'image/png', 'logo@example.net')
        );

        self::assertStringContainsString('Content-Type: image/png; name="logo.png"', $message->data);
        self::assertStringContainsString('Content-Disposition: inline; filename="logo.png"', $message->data);
        self::assertStringContainsString("Content-ID: <logo@example.net>\r\n", $message->data);
        self::assertStringNotContainsString('Content-Disposition: attachment; filename="logo.png"', $message->data);
    }

    public function testRendersInlineAttachmentAsRelated(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->subject('Inline')
                ->html('<img src="cid:logo@example.net">')
                ->inlineData('image-bytes', 'logo.png', 'image/png', 'logo@example.net')
        );

        self::assertStringContainsString('Content-Type: multipart/related;', $message->data);
        self::assertStringNotContainsString('Content-Type: multipart/mixed;', $message->data);
        self::assertStringContainsString('Content-Type: text/html; charset=UTF-8', $message->data);
        self::assertStringContainsString('Content-Type: image/png; name="logo.png"', $message->data);
        self::assertStringContainsString('Content-Disposition: inline; filename="logo.png"', $message->data);
    }

    public function testRendersInlineAndRegularAttachmentsAsMixedWithRelatedBody(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->subject('Inline and Attachment')
                ->html('<img src="cid:logo@example.net">')
                ->inlineData('image-bytes', 'logo.png', 'image/png', 'logo@example.net')
                ->attachData('pdf-bytes', 'invoice.pdf', 'application/pdf')
        );

        self::assertStringContainsString('Content-Type: multipart/mixed;', $message->data);
        self::assertStringContainsString('Content-Type: multipart/related;', $message->data);
        self::assertStringContainsString('Content-Disposition: inline; filename="logo.png"', $message->data);
        self::assertStringContainsString('Content-Disposition: attachment; filename="invoice.pdf"', $message->data);
        self::assertLessThan(
            strpos($message->data, 'Content-Disposition: attachment; filename="invoice.pdf"'),
            strpos($message->data, 'Content-Type: multipart/related;'),
        );
    }

    public function testRendersTextHtmlAndInlineAttachmentAsRelatedWithAlternativeBody(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->subject('Inline alternative')
                ->text('Logo')
                ->html('<img src="cid:logo@example.net">')
                ->inlineData('image-bytes', 'logo.png', 'image/png', 'logo@example.net')
        );

        self::assertStringContainsString('Content-Type: multipart/related;', $message->data);
        self::assertStringContainsString('Content-Type: multipart/alternative;', $message->data);
        self::assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $message->data);
        self::assertStringContainsString('Content-Type: text/html; charset=UTF-8', $message->data);
        self::assertStringContainsString('Content-Disposition: inline; filename="logo.png"', $message->data);
        self::assertLessThan(
            strpos($message->data, 'Content-Disposition: inline; filename="logo.png"'),
            strpos($message->data, 'Content-Type: multipart/alternative;'),
        );
    }

    public function testRendersUtf8AttachmentFilenameWithEncodedParameters(): void
    {
        $message = (new MimeRenderer())->render(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->subject('Attachment')
                ->text('Attached')
                ->attachData('abc', 'fattura è.pdf', 'application/pdf')
        );

        self::assertStringContainsString("Content-Type: application/pdf;\r\n name*=UTF-8''fattura%20%C3%A8.pdf\r\n", $message->data);
        self::assertStringContainsString("Content-Disposition: attachment;\r\n filename*=UTF-8''fattura%20%C3%A8.pdf\r\n", $message->data);
        self::assertStringNotContainsString('filename="fattura è.pdf"', $message->data);
    }
}
