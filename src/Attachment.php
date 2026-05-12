<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer;

use InvalidArgumentException;

final class Attachment
{
    public const SOURCE_FILE = 'file';
    public const SOURCE_DATA = 'data';

    private function __construct(
        public readonly string $sourceType,
        public readonly string $source,
        public readonly string $name,
        public readonly string $contentType,
        public readonly ?string $contentId = null,
    ) {
    }

    public static function file(string $path, ?string $name = null, string $contentType = 'application/octet-stream', ?string $contentId = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException("Attachment file is not readable: {$path}");
        }

        return new self(self::SOURCE_FILE, $path, $name ?? basename($path), $contentType, $contentId);
    }

    public static function data(string $data, string $name, string $contentType = 'application/octet-stream', ?string $contentId = null): self
    {
        return new self(self::SOURCE_DATA, $data, $name, $contentType, $contentId);
    }

    public function content(): string
    {
        if ($this->sourceType === self::SOURCE_FILE) {
            $content = file_get_contents($this->source);
            if ($content === false) {
                throw new InvalidArgumentException("Attachment file could not be read: {$this->source}");
            }

            return $content;
        }

        return $this->source;
    }

    public function isInline(): bool
    {
        return $this->contentId !== null && $this->contentId !== '';
    }
}
