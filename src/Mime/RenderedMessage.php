<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Mime;

final class RenderedMessage
{
    /**
     * @param list<string> $envelopeRecipients
     */
    public function __construct(
        public readonly string $envelopeSender,
        public readonly array $envelopeRecipients,
        public readonly string $data,
    ) {
    }
}
