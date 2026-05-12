# AmpMailer

Small AMPHP v3 mailer with MIME rendering, SMTP transport, retry, and rate limiting.

## Project Status

AmpMailer is experimental and still lightly tested. Test it carefully and for a
long period in your own environment before relying on it in production.

The current target is `0.6.1`: a small, usable pre-1.0 release with a stable
core API, strong local test coverage, and explicit warnings about the remaining
production validation work.

## Anti-Spam Policy

Use of this software to send spam, unsolicited email, abusive bulk email,
phishing, malware, harassment, or any mail that violates applicable law,
provider policy, or recipient consent is strongly prohibited.

This prohibition applies to every user, software system, automation pipeline,
bot, and AI agent using this package directly or indirectly. AmpMailer is meant
for legitimate transactional and consent-based email only.

The authors and contributors of this software assume no responsibility or
liability for unwanted email sent with this package, unlawful or abusive use,
provider policy violations, delivery failures, missed deliveries, blocked
messages, bounces, or any consequence of using or failing to use this software.
Use is free, but each user is solely responsible for their own configuration,
mailing practices, compliance, consent management, and delivery outcomes.

```bash
composer require dam2k/ampmailer
```

This package is inspired by the simplicity of `nette/mail`, but it is not API
compatible with Nette. DKIM is intentionally out of scope.

## Current Scope

Implemented:

- Fluent `Email` builder with `From`, `To`, `Cc`, `Bcc`, `Reply-To`, subject,
  text body, HTML body, custom headers, file attachments, data attachments, and
  inline data attachments.
- MIME rendering for plain text, HTML, `multipart/alternative`,
  `multipart/mixed`, attachments, UTF-8 subjects, UTF-8 address display names,
  UTF-8 attachment filenames, `Bcc` envelope-only handling, CRLF normalization,
  and SMTP dot-stuffing.
- Header safety checks for custom headers, subject, and address display names.
- AMPHP-based SMTP client with TCP, implicit TLS, STARTTLS,
  `STARTTLS if available`, `AUTH PLAIN`, `AUTH LOGIN`, `MAIL FROM`, `RCPT TO`,
  `DATA`, and `QUIT`.
- Retry decorator for temporary SMTP failures.
- `UnknownDeliveryState` for connection loss after the DATA body is sent.
- Process-local rate limiting decorator.
- PHPUnit tests, PHPStan analysis, Composer validation, and GitHub Actions CI
  for PHP 8.2, 8.3, and 8.4.

Known gaps before tagging `0.6.1`:

- Add more SMTP protocol tests: EHLO fallback behavior, STARTTLS re-EHLO
  capability handling, temporary/permanent failures at each SMTP phase, AUTH
  failure paths, and malformed server replies.
- Improve MIME tree generation for inline resources: HTML with inline
  attachments should use `multipart/related`, and combinations of text, HTML,
  inline resources, and normal attachments should be covered by explicit tests.
- Add validation for attachment names, content types, and content IDs to reject
  unsafe header characters.
- Add tests for retry limits and backoff timing without making the suite slow.
- Add tests for `InMemoryRateLimiter` timing behavior.
- Add at least one documented manual interoperability checklist against real
  SMTP servers or local SMTP tools before publishing a release tag.
- Prepare release metadata: changelog, CI badge, Packagist notes, and `0.6.1`
  tag procedure.

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
- `amphp/sync` and `revolt/event-loop` are not direct dependencies of this
  package. They may still be installed transitively by AMPHP components.
