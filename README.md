# AmpMailer

Small AMPHP v3 mailer with MIME rendering, SMTP transport, retry, and rate limiting.

## Project Status

AmpMailer is experimental and still lightly tested. Test it carefully and for a
long period in your own environment before relying on it in production.

```bash
composer require dam2k/ampmailer
```

This package is inspired by the simplicity of `nette/mail`, but it is not API
compatible with Nette. DKIM is intentionally out of scope.

## Basic Usage

```php
use Dam2k\AmpMailer\Email;
use Dam2k\AmpMailer\Smtp\SmtpConfig;
use Dam2k\AmpMailer\Smtp\SmtpMailer;
use Dam2k\AmpMailer\Smtp\TlsMode;

$email = Email::new()
    ->from('Sender <sender@example.com>')
    ->to('recipient@example.net')
    ->subject('Hello')
    ->text('Plain text body')
    ->html('<p>HTML body</p>')
    ->attachFile('/path/to/file.pdf');

$mailer = new SmtpMailer(new SmtpConfig(
    host: 'smtp.example.com',
    port: 587,
    username: 'user',
    password: 'secret',
    tlsMode: TlsMode::StartTls,
));

$mailer->send($email);
```

## Retry

```php
use Dam2k\AmpMailer\Retry\RetryMailer;
use Dam2k\AmpMailer\Retry\RetryPolicy;

$mailer = new RetryMailer(
    $mailer,
    new RetryPolicy(maxAttempts: 3, initialDelay: 1.0),
);
```

Temporary SMTP failures are retried. Permanent SMTP failures are not retried.
If the connection is lost after the message body is sent but before the final
SMTP reply is received, `UnknownDeliveryState` is thrown and is not retried by
default.

## Rate Limiting

```php
use Dam2k\AmpMailer\RateLimit\InMemoryRateLimiter;
use Dam2k\AmpMailer\RateLimit\RateLimitedMailer;

$mailer = new RateLimitedMailer(
    $mailer,
    InMemoryRateLimiter::perSecond(5),
);
```

The built-in limiter is process-local. Use the `RateLimiter` interface for a
future shared implementation such as Redis.

## Notes

- `Email` uses a mutable fluent API to avoid cloning message state.
- `Bcc` recipients are used for the SMTP envelope and omitted from MIME headers.
- SMTP transport uses AMPHP sockets.
- STARTTLS is negotiated when requested and advertised by the SMTP server.
- SMTP DATA payloads are normalized to CRLF and dot-stuffed before delivery.
