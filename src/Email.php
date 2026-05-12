<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer;

final class Email
{
    private ?Address $from = null;
    /** @var list<Address> */
    private array $to = [];
    /** @var list<Address> */
    private array $cc = [];
    /** @var list<Address> */
    private array $bcc = [];
    /** @var list<Address> */
    private array $replyTo = [];
    /** @var array<string, string> */
    private array $headers = [];
    /** @var list<Attachment> */
    private array $attachments = [];
    private ?string $subject = null;
    private ?string $text = null;
    private ?string $html = null;

    public static function new(): self
    {
        return new self();
    }

    public function from(string|Address $address): self
    {
        $this->from = Address::parse($address);

        return $this;
    }

    public function to(string|Address $address): self
    {
        $this->to[] = Address::parse($address);

        return $this;
    }

    public function cc(string|Address $address): self
    {
        $this->cc[] = Address::parse($address);

        return $this;
    }

    public function bcc(string|Address $address): self
    {
        $this->bcc[] = Address::parse($address);

        return $this;
    }

    public function replyTo(string|Address $address): self
    {
        $this->replyTo[] = Address::parse($address);

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function html(string $html): self
    {
        $this->html = $html;

        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function attachFile(string $path, ?string $name = null, string $contentType = 'application/octet-stream'): self
    {
        $this->attachments[] = Attachment::file($path, $name, $contentType);

        return $this;
    }

    public function attachData(string $data, string $name, string $contentType = 'application/octet-stream'): self
    {
        $this->attachments[] = Attachment::data($data, $name, $contentType);

        return $this;
    }

    public function inlineData(string $data, string $name, string $contentType, string $contentId): self
    {
        $this->attachments[] = Attachment::data($data, $name, $contentType, $contentId);

        return $this;
    }

    public function getFrom(): ?Address
    {
        return $this->from;
    }

    /** @return list<Address> */
    public function getTo(): array
    {
        return $this->to;
    }

    /** @return list<Address> */
    public function getCc(): array
    {
        return $this->cc;
    }

    /** @return list<Address> */
    public function getBcc(): array
    {
        return $this->bcc;
    }

    /** @return list<Address> */
    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @return list<Attachment> */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getHtml(): ?string
    {
        return $this->html;
    }

}
