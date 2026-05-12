<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Smtp;

use Amp\ByteStream\BufferedReader;
use Amp\ByteStream\BufferException;
use Amp\CancelledException;
use Amp\Socket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\TimeoutCancellation;
use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Mailer;
use Dam2k\AmpMailer\Mime\MimeRenderer;

final class SmtpMailer implements Mailer
{
    public function __construct(
        private readonly SmtpConfig $config,
        private readonly MimeRenderer $renderer = new MimeRenderer(),
    ) {
    }

    public function send(Email $email): void
    {
        $rendered = $this->renderer->render($email);
        $socket = $this->connect();
        $reader = new BufferedReader($socket);

        try {
            $this->expect($this->readReply($reader), 220);
            $ehlo = $this->command($socket, $reader, 'EHLO localhost', 250);

            if ($this->shouldStartTls($ehlo)) {
                $this->command($socket, $reader, 'STARTTLS', 220);
                $socket->setupTls();
                $reader = new BufferedReader($socket);
                $this->command($socket, $reader, 'EHLO localhost', 250);
            }

            if ($this->config->username !== null && $this->config->password !== null) {
                $this->authenticate($socket, $reader, $ehlo);
            }

            $this->command($socket, $reader, 'MAIL FROM:<' . $rendered->envelopeSender . '>', 250);
            foreach ($rendered->envelopeRecipients as $recipient) {
                $this->command($socket, $reader, 'RCPT TO:<' . $recipient . '>', 250);
            }

            $this->command($socket, $reader, 'DATA', 354);
            $socket->write($this->escapeData($rendered->data) . "\r\n.\r\n");
            try {
                $this->expect($this->readReply($reader), 250);
            } catch (SmtpException $exception) {
                if ($exception->replyCode === 0) {
                    throw UnknownDeliveryState::afterData($exception->getMessage());
                }

                throw $exception;
            }
            $this->command($socket, $reader, 'QUIT', 221);
        } finally {
            $socket->close();
        }
    }

    private function connect(): Socket\Socket
    {
        $uri = $this->config->host . ':' . $this->config->port;
        $context = (new ConnectContext())
            ->withConnectTimeout($this->config->timeout)
            ->withTlsContext(new ClientTlsContext($this->config->host));

        try {
            return $this->config->tlsMode === TlsMode::Implicit
                ? Socket\connectTls($uri, $context, $this->cancellation())
                : Socket\connect($uri, $context, $this->cancellation());
        } catch (CancelledException|Socket\ConnectException $exception) {
            throw SmtpException::temporary(0, $exception->getMessage());
        }
    }

    private function shouldStartTls(SmtpReply $ehlo): bool
    {
        $available = stripos($ehlo->message, 'STARTTLS') !== false;

        if ($this->config->tlsMode === TlsMode::StartTls && !$available) {
            throw SmtpException::permanent(0, 'SMTP server does not advertise STARTTLS.');
        }

        return match ($this->config->tlsMode) {
            TlsMode::StartTls => true,
            TlsMode::StartTlsIfAvailable => $available,
            default => false,
        };
    }

    private function command(Socket\Socket $socket, BufferedReader $reader, string $command, int $expectedCode): SmtpReply
    {
        $socket->write($command . "\r\n");
        $reply = $this->readReply($reader);
        $this->expect($reply, $expectedCode);

        return $reply;
    }

    private function authenticate(Socket\Socket $socket, BufferedReader $reader, SmtpReply $ehlo): void
    {
        $capabilities = strtoupper($ehlo->message);

        if (str_contains($capabilities, 'AUTH PLAIN')) {
            $this->command(
                $socket,
                $reader,
                'AUTH PLAIN ' . base64_encode("\0{$this->config->username}\0{$this->config->password}"),
                235,
            );

            return;
        }

        if (str_contains($capabilities, 'AUTH LOGIN')) {
            $this->command($socket, $reader, 'AUTH LOGIN', 334);
            $this->command($socket, $reader, base64_encode((string) $this->config->username), 334);
            $this->command($socket, $reader, base64_encode((string) $this->config->password), 235);

            return;
        }

        throw SmtpException::permanent(0, 'SMTP server does not advertise a supported AUTH mechanism.');
    }

    private function readReply(BufferedReader $reader): SmtpReply
    {
        $lines = [];
        try {
            do {
                $line = rtrim($reader->readUntil("\n", $this->cancellation()), "\r\n");
                $lines[] = $line;
            } while (isset($line[3]) && $line[3] === '-');
        } catch (CancelledException|BufferException $exception) {
            throw SmtpException::temporary(0, $exception->getMessage());
        }

        /** @var non-empty-list<string> $lines */
        return SmtpReply::parse($lines);
    }

    private function expect(SmtpReply $reply, int $expectedCode): void
    {
        if ($reply->code === $expectedCode) {
            return;
        }

        if ($reply->isTemporary()) {
            throw SmtpException::temporary($reply->code, $reply->message);
        }

        throw SmtpException::permanent($reply->code, $reply->message);
    }

    private function escapeData(string $data): string
    {
        $data = preg_replace("/(?<!\r)\n|\r(?!\n)/", "\r\n", $data) ?? $data;

        return preg_replace('/^\./m', '..', $data) ?? $data;
    }

    private function cancellation(): TimeoutCancellation
    {
        return new TimeoutCancellation($this->config->timeout, 'SMTP operation timed out.');
    }
}
