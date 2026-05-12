<?php

declare(strict_types=1);

namespace Dam2k\AmpMailer\Tests\Smtp;

use Amp\Socket;
use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Smtp\SmtpConfig;
use Dam2k\AmpMailer\Smtp\SmtpException;
use Dam2k\AmpMailer\Smtp\SmtpMailer;
use Dam2k\AmpMailer\Smtp\TlsMode;
use Dam2k\AmpMailer\Smtp\UnknownDeliveryState;
use PHPUnit\Framework\TestCase;

final class SmtpMailerTest extends TestCase
{
    public function testSendsPlainMessageToLocalSmtpServer(): void
    {
        $port = random_int(25000, 35000);
        $server = Socket\listen('127.0.0.1:' . $port);
        $address = (string) $server->getAddress();
        $commands = [];

        $future = \Amp\async(static function () use ($server, &$commands): void {
            $client = $server->accept();
            $client->write("220 localhost ESMTP\r\n");
            $dataMode = false;
            $data = '';

            while (($line = $client->read()) !== null) {
                foreach (explode("\r\n", $line) as $command) {
                    if ($command === '') {
                        continue;
                    }

                    if ($dataMode) {
                        if ($command === '.') {
                            $commands[] = 'DATA-BODY:' . $data;
                            $client->write("250 queued\r\n");
                            $dataMode = false;
                            continue;
                        }

                        $data .= $command . "\r\n";
                        continue;
                    }

                    $commands[] = $command;

                    if (str_starts_with($command, 'EHLO ')) {
                        $client->write("250-localhost\r\n250 OK\r\n");
                    } elseif (str_starts_with($command, 'MAIL FROM:')) {
                        $client->write("250 sender ok\r\n");
                    } elseif (str_starts_with($command, 'RCPT TO:')) {
                        $client->write("250 recipient ok\r\n");
                    } elseif ($command === 'DATA') {
                        $client->write("354 send data\r\n");
                        $dataMode = true;
                    } elseif ($command === 'QUIT') {
                        $client->write("221 bye\r\n");
                        break 2;
                    } else {
                        $client->write("250 ok\r\n");
                    }
                }
            }
        });

        $mailer = new SmtpMailer(new SmtpConfig(
            host: '127.0.0.1',
            port: (int) parse_url($address, PHP_URL_PORT),
            tlsMode: TlsMode::Disabled,
        ));

        $mailer->send(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->subject('SMTP')
                ->text('Body')
        );

        $future->await();
        $server->close();

        self::assertContains('MAIL FROM:<sender@example.com>', $commands);
        self::assertContains('RCPT TO:<to@example.net>', $commands);
        self::assertContains('DATA', $commands);
        self::assertTrue((bool) preg_grep('/^DATA-BODY:.*Subject: SMTP/s', $commands));
    }

    public function testUsesAuthLoginWhenPlainIsNotAdvertised(): void
    {
        $port = random_int(35001, 45000);
        $server = Socket\listen('127.0.0.1:' . $port);
        $address = (string) $server->getAddress();
        $commands = [];

        $future = \Amp\async(static function () use ($server, &$commands): void {
            $client = $server->accept();
            $client->write("220 localhost ESMTP\r\n");
            $authStep = 0;
            $dataMode = false;

            while (($chunk = $client->read()) !== null) {
                foreach (explode("\r\n", $chunk) as $command) {
                    if ($command === '') {
                        continue;
                    }

                    $commands[] = $command;

                    if ($dataMode) {
                        if ($command === '.') {
                            $dataMode = false;
                            $client->write("250 queued\r\n");
                        }
                    } elseif (str_starts_with($command, 'EHLO ')) {
                        $client->write("250-localhost\r\n250-AUTH LOGIN\r\n250 OK\r\n");
                    } elseif ($command === 'AUTH LOGIN') {
                        $authStep = 1;
                        $client->write("334 VXNlcm5hbWU6\r\n");
                    } elseif ($authStep === 1) {
                        $authStep = 2;
                        $client->write("334 UGFzc3dvcmQ6\r\n");
                    } elseif ($authStep === 2) {
                        $authStep = 0;
                        $client->write("235 authenticated\r\n");
                    } elseif (str_starts_with($command, 'MAIL FROM:') || str_starts_with($command, 'RCPT TO:')) {
                        $client->write("250 ok\r\n");
                    } elseif ($command === 'DATA') {
                        $client->write("354 send data\r\n");
                        $dataMode = true;
                    } elseif ($command === 'QUIT') {
                        $client->write("221 bye\r\n");
                        break 2;
                    } else {
                        $client->write("504 unsupported\r\n");
                    }
                }
            }
        });

        $mailer = new SmtpMailer(new SmtpConfig(
            host: '127.0.0.1',
            port: (int) parse_url($address, PHP_URL_PORT),
            username: 'user',
            password: 'secret',
            tlsMode: TlsMode::Disabled,
        ));

        $mailer->send(Email::new()->from('sender@example.com')->to('to@example.net')->text('Body'));

        $future->await();
        $server->close();

        self::assertContains('AUTH LOGIN', $commands);
        self::assertContains(base64_encode('user'), $commands);
        self::assertContains(base64_encode('secret'), $commands);
    }

    public function testFallsBackToHeloWhenEhloIsNotSupported(): void
    {
        $port = random_int(35001, 45000);
        $server = Socket\listen('127.0.0.1:' . $port);
        $address = (string) $server->getAddress();
        $commands = [];

        $future = \Amp\async(static function () use ($server, &$commands): void {
            $client = $server->accept();
            $client->write("220 localhost SMTP\r\n");
            $dataMode = false;

            while (($chunk = $client->read()) !== null) {
                foreach (explode("\r\n", $chunk) as $command) {
                    if ($command === '') {
                        continue;
                    }

                    $commands[] = $command;

                    if ($dataMode) {
                        if ($command === '.') {
                            $dataMode = false;
                            $client->write("250 queued\r\n");
                        }

                        continue;
                    }

                    if (str_starts_with($command, 'EHLO ')) {
                        $client->write("500 EHLO not supported\r\n");
                    } elseif (str_starts_with($command, 'HELO ')) {
                        $client->write("250 localhost\r\n");
                    } elseif (str_starts_with($command, 'MAIL FROM:') || str_starts_with($command, 'RCPT TO:')) {
                        $client->write("250 ok\r\n");
                    } elseif ($command === 'DATA') {
                        $dataMode = true;
                        $client->write("354 send data\r\n");
                    } elseif ($command === 'QUIT') {
                        $client->write("221 bye\r\n");
                        break 2;
                    }
                }
            }
        });

        $mailer = new SmtpMailer(new SmtpConfig(
            host: '127.0.0.1',
            port: (int) parse_url($address, PHP_URL_PORT),
            tlsMode: TlsMode::Disabled,
        ));

        $mailer->send(Email::new()->from('sender@example.com')->to('to@example.net')->text('Body'));

        $future->await();
        $server->close();

        self::assertContains('EHLO localhost', $commands);
        self::assertContains('HELO localhost', $commands);
        self::assertContains('DATA', $commands);
    }

    public function testGreetingTimeoutBecomesTemporarySmtpException(): void
    {
        $port = random_int(45001, 55000);
        $server = Socket\listen('127.0.0.1:' . $port);
        $address = (string) $server->getAddress();

        $future = \Amp\async(static function () use ($server): void {
            $client = $server->accept();
            \Amp\delay(0.1);
            $client->close();
        });

        $mailer = new SmtpMailer(new SmtpConfig(
            host: '127.0.0.1',
            port: (int) parse_url($address, PHP_URL_PORT),
            tlsMode: TlsMode::Disabled,
            timeout: 0.01,
        ));

        try {
            $mailer->send(Email::new()->from('sender@example.com')->to('to@example.net')->text('Body'));
            self::fail('Expected timeout exception.');
        } catch (SmtpException $exception) {
            self::assertTrue($exception->isTemporary());
            self::assertSame(0, $exception->replyCode);
        } finally {
            $future->await();
            $server->close();
        }
    }

    public function testConnectionLossAfterDataBodyReportsUnknownDeliveryState(): void
    {
        $port = random_int(55001, 60000);
        $server = Socket\listen('127.0.0.1:' . $port);
        $address = (string) $server->getAddress();

        $future = \Amp\async(static function () use ($server): void {
            $client = $server->accept();
            $client->write("220 localhost ESMTP\r\n");
            $dataMode = false;

            while (($chunk = $client->read()) !== null) {
                foreach (explode("\r\n", $chunk) as $command) {
                    if ($command === '') {
                        continue;
                    }

                    if ($dataMode && $command === '.') {
                        $client->close();
                        break 2;
                    }

                    if (str_starts_with($command, 'EHLO ')) {
                        $client->write("250-localhost\r\n250 OK\r\n");
                    } elseif (str_starts_with($command, 'MAIL FROM:') || str_starts_with($command, 'RCPT TO:')) {
                        $client->write("250 ok\r\n");
                    } elseif ($command === 'DATA') {
                        $dataMode = true;
                        $client->write("354 send data\r\n");
                    }
                }
            }
        });

        $mailer = new SmtpMailer(new SmtpConfig(
            host: '127.0.0.1',
            port: (int) parse_url($address, PHP_URL_PORT),
            tlsMode: TlsMode::Disabled,
        ));

        try {
            $mailer->send(Email::new()->from('sender@example.com')->to('to@example.net')->text('Body'));
            self::fail('Expected unknown delivery state.');
        } catch (UnknownDeliveryState $exception) {
            self::assertFalse($exception->isTemporary());
            self::assertSame(0, $exception->replyCode);
        } finally {
            $future->await();
            $server->close();
        }
    }

    public function testDotsAtBeginningOfDataLinesAreEscaped(): void
    {
        $port = random_int(60001, 61000);
        $server = Socket\listen('127.0.0.1:' . $port);
        $address = (string) $server->getAddress();
        $bodyLines = [];

        $future = \Amp\async(static function () use ($server, &$bodyLines): void {
            $client = $server->accept();
            $client->write("220 localhost ESMTP\r\n");
            $dataMode = false;

            while (($chunk = $client->read()) !== null) {
                foreach (explode("\r\n", $chunk) as $command) {
                    if ($command === '') {
                        continue;
                    }

                    if ($dataMode) {
                        if ($command === '.') {
                            $client->write("250 queued\r\n");
                            $dataMode = false;
                            continue;
                        }

                        $bodyLines[] = $command;
                        continue;
                    }

                    if (str_starts_with($command, 'EHLO ')) {
                        $client->write("250-localhost\r\n250 OK\r\n");
                    } elseif (str_starts_with($command, 'MAIL FROM:') || str_starts_with($command, 'RCPT TO:')) {
                        $client->write("250 ok\r\n");
                    } elseif ($command === 'DATA') {
                        $dataMode = true;
                        $client->write("354 send data\r\n");
                    } elseif ($command === 'QUIT') {
                        $client->write("221 bye\r\n");
                        break 2;
                    }
                }
            }
        });

        $mailer = new SmtpMailer(new SmtpConfig(
            host: '127.0.0.1',
            port: (int) parse_url($address, PHP_URL_PORT),
            tlsMode: TlsMode::Disabled,
        ));

        $mailer->send(
            Email::new()
                ->from('sender@example.com')
                ->to('to@example.net')
                ->text(".first\r\n..second")
        );

        $future->await();
        $server->close();

        self::assertContains('..first', $bodyLines);
        self::assertContains('...second', $bodyLines);
        self::assertNotContains('.first', $bodyLines);
    }
}
