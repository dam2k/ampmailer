<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Mime;

use Dam2k\AmpMailer\Address;
use Dam2k\AmpMailer\Attachment;
use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Exception\InvalidEmail;

final class MimeRenderer
{
    public function render(Email $email): RenderedMessage
    {
        $from = $email->getFrom();
        if (!$from instanceof Address) {
            throw new InvalidEmail('Email requires a From address.');
        }

        $recipients = [...$email->getTo(), ...$email->getCc(), ...$email->getBcc()];
        if ($recipients === []) {
            throw new InvalidEmail('Email requires at least one recipient.');
        }

        [$headers, $body] = $this->message($email, $from);

        return new RenderedMessage(
            $from->email,
            array_map(static fn (Address $address): string => $address->email, $recipients),
            $this->normalizeLines($headers . "\r\n" . $body),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function message(Email $email, Address $from): array
    {
        $headers = [
            'From' => $from->format(),
            'MIME-Version' => '1.0',
            'Date' => gmdate('D, d M Y H:i:s') . ' +0000',
            'Message-ID' => sprintf('<%s@ampmailer.local>', bin2hex(random_bytes(16))),
        ];

        if ($email->getTo() !== []) {
            $headers['To'] = $this->formatAddresses($email->getTo());
        }

        if ($email->getCc() !== []) {
            $headers['Cc'] = $this->formatAddresses($email->getCc());
        }

        if ($email->getReplyTo() !== []) {
            $headers['Reply-To'] = $this->formatAddresses($email->getReplyTo());
        }

        if ($email->getSubject() !== null) {
            $headers['Subject'] = $this->encodeHeaderValue($email->getSubject());
        }

        foreach ($email->getHeaders() as $name => $value) {
            $headers[$name] = $this->encodeHeaderValue($value);
        }

        $attachments = $email->getAttachments();
        if ($attachments !== []) {
            $boundary = $this->boundary();
            $headers['Content-Type'] = 'multipart/mixed; boundary="' . $boundary . '"';

            return [$this->formatHeaders($headers), $this->mixedBody($email, $attachments, $boundary)];
        }

        if ($email->getText() !== null && $email->getHtml() !== null) {
            $boundary = $this->boundary();
            $headers['Content-Type'] = 'multipart/alternative; boundary="' . $boundary . '"';

            return [$this->formatHeaders($headers), $this->alternativeBody($email, $boundary, true)];
        }

        if ($email->getHtml() !== null) {
            $headers['Content-Type'] = 'text/html; charset=UTF-8';
            $headers['Content-Transfer-Encoding'] = 'quoted-printable';
        } else {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
            $headers['Content-Transfer-Encoding'] = 'quoted-printable';
        }

        return [$this->formatHeaders($headers), quoted_printable_encode($email->getHtml() ?? $email->getText() ?? '')];
    }

    /**
     * @param list<Attachment> $attachments
     */
    private function mixedBody(Email $email, array $attachments, string $boundary): string
    {
        $body = "This is a multi-part message in MIME format.\r\n\r\n";
        if ($email->getText() !== null && $email->getHtml() !== null) {
            $innerBoundary = $this->boundary();
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Type: multipart/alternative; boundary="' . $innerBoundary . "\"\r\n\r\n";
            $body .= $this->alternativeBody($email, $innerBoundary, true);
        } else {
            $contentType = $email->getHtml() !== null ? 'text/html' : 'text/plain';
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: {$contentType}; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
            $body .= quoted_printable_encode($email->getHtml() ?? $email->getText() ?? '') . "\r\n\r\n";
        }

        foreach ($attachments as $attachment) {
            $body .= $this->attachmentPart($attachment, $boundary);
        }

        return $body . "--{$boundary}--\r\n";
    }

    private function alternativeBody(Email $email, string $boundary, bool $close): string
    {
        $body = "This is a multi-part message in MIME format.\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($email->getText() ?? '') . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($email->getHtml() ?? '') . "\r\n\r\n";

        if ($close) {
            $body .= "--{$boundary}--\r\n\r\n";
        }

        return $body;
    }

    private function attachmentPart(Attachment $attachment, string $boundary): string
    {
        $encoded = chunk_split(base64_encode($attachment->content()), 76, "\r\n");
        $part = "--{$boundary}\r\n";
        $part .= 'Content-Type: ' . $attachment->contentType . '; name="' . $this->quote($attachment->name) . "\"\r\n";
        $part .= "Content-Transfer-Encoding: base64\r\n";
        $part .= 'Content-Disposition: attachment; filename="' . $this->quote($attachment->name) . "\"\r\n";
        if ($attachment->isInline()) {
            $part .= 'Content-ID: <' . $attachment->contentId . ">\r\n";
        }
        $part .= "\r\n{$encoded}\r\n";

        return $part;
    }

    /**
     * @param list<Address> $addresses
     */
    private function formatAddresses(array $addresses): string
    {
        return implode(', ', array_map(static fn (Address $address): string => $address->format(), $addresses));
    }

    /**
     * @param array<string, string> $headers
     */
    private function formatHeaders(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines);
    }

    private function encodeHeaderValue(string $value): string
    {
        if (preg_match('//u', $value) !== 1 || preg_match('/[^\x20-\x7E]/', $value) !== 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function quote(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function boundary(): string
    {
        return '=_ampmailer_' . bin2hex(random_bytes(12));
    }

    private function normalizeLines(string $value): string
    {
        return preg_replace("/(?<!\r)\n|\r(?!\n)/", "\r\n", $value) ?? $value;
    }
}
