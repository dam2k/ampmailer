# AmpMailer Design Specification

Date: 2026-05-12
Status: Draft
License: MIT
Composer package: `dam2k/ampmailer`
PHP namespace: `Dam2k\AmpMailer`

## Goal

AmpMailer is a small PHP mail package built natively for AMPHP v3. It takes
inspiration from `nette/mail`, but it does not aim for API compatibility with
Nette.

The package should provide a simple way to build MIME email messages and send
them through asynchronous SMTP transports. It should also provide built-in
decorators for retry/backoff on temporary SMTP failures and process-local rate
limiting for outgoing mail.

DKIM signing is explicitly out of scope.

## Design Priorities

1. Keep the public API small and predictable.
2. Keep message composition independent from transport logic.
3. Use AMPHP v3 for network I/O and timing.
4. Avoid framework coupling.
5. Make retry and rate limiting explicit decorators, not hidden behavior.
6. Prefer correctness and clear failure states over aggressive automation.

## Non-Goals

- Compatibility with `Nette\Mail\Message` or other Nette APIs.
- DKIM signing.
- Full HTML email transformation engine.
- Distributed rate limiting in the first release.
- Queue persistence.
- Background daemon management.
- Complex template rendering.
- OAuth SMTP authentication in the first release.

## Target Runtime

- PHP: `^8.2`
- AMPHP: v3 ecosystem
- License: MIT

Planned dependencies:

- `amphp/amp:^3`
- `amphp/socket:^2`
- `amphp/byte-stream:^2`

`amphp/sync` and `revolt/event-loop` are intentionally not direct package
requirements unless the code starts using them directly. They can still be
installed transitively by AMPHP packages.

Optional dependencies may be added later for MIME type detection, CSS inlining,
or HTML parsing, but the first release should avoid optional complexity unless
it directly improves correctness.

## Package Shape

The package should be organized around a few small components:

- `Email`: mutable fluent message object optimized for low allocation overhead.
- `Address`: parsed and validated email address with optional display name.
- `Attachment`: file-backed or memory-backed attachment descriptor.
- `MimeRenderer`: converts `Email` into a MIME message string and SMTP envelope.
- `Mailer`: interface for sending one email.
- `SmtpMailer`: AMPHP SMTP transport.
- `RetryMailer`: decorator that retries temporary failures.
- `RateLimitedMailer`: decorator that throttles all sends through a limiter.
- `FallbackMailer`: optional decorator that tries multiple mailers in order.

The preferred composition model is:

```php
$mailer = new RateLimitedMailer(
    new RetryMailer(
        new SmtpMailer($smtpConfig),
        $retryPolicy,
    ),
    $rateLimiter,
);

$mailer->send($email);
```

## Public API Direction

The API should be concise and fluent:

```php
$email = Email::new()
    ->from('John Example <john@example.com>')
    ->to('jane@example.net')
    ->cc('team@example.net')
    ->replyTo('support@example.com')
    ->subject('Welcome')
    ->text('Welcome to the service.')
    ->html('<p>Welcome to the service.</p>')
    ->attachFile('/path/to/manual.pdf');

$mailer->send($email);
```

The exact API can change during implementation, but the design should preserve:

- a compact builder for common use;
- explicit support for text and HTML bodies;
- explicit attachments;
- explicit inline attachments;
- clear validation errors before SMTP delivery starts.

`Email` should use a mutable fluent API. Methods should update the current
instance and return `$this` instead of cloning the message. This keeps memory
usage low for messages with many recipients, headers, or attachments, and it
keeps the public API direct. The object should still hide its internal arrays
and expose controlled methods so the implementation remains easy to validate.

## Email Model

`Email` should support:

- sender: one `From` address;
- recipients: `To`, `Cc`, `Bcc`;
- `Reply-To`;
- subject;
- text body;
- HTML body;
- custom headers;
- file attachments;
- memory attachments;
- inline attachments with content IDs;
- message date;
- generated `Message-ID`.

`Bcc` recipients must be included in the SMTP envelope but omitted from rendered
message headers.

The model should not automatically mutate HTML unless explicitly requested.
Automatic embedding of referenced local files is out of scope for the first
release.

## MIME Rendering

`MimeRenderer` is responsible for converting `Email` into:

- SMTP envelope sender;
- SMTP envelope recipients;
- RFC/MIME message data.

It should handle these message forms:

- plain text only;
- HTML only;
- text and HTML as `multipart/alternative`;
- attachments as `multipart/mixed`;
- inline attachments as `multipart/related`;
- combinations of mixed, related, and alternative parts.

Encoding requirements:

- UTF-8 headers must be encoded correctly.
- Body transfer encoding should default to quoted-printable for text-like
  content and base64 for binary content.
- MIME boundaries must be generated safely.
- Lines must be normalized to CRLF for SMTP.

The renderer should be deterministic enough to test with stable patterns while
allowing generated boundaries, dates, and message IDs to vary through injectable
generators where useful.

## SMTP Transport

`SmtpMailer` should use AMPHP socket APIs and should not perform blocking
network I/O.

Supported first-release features:

- TCP connection.
- Optional implicit TLS.
- Optional STARTTLS.
- EHLO/HELO.
- AUTH PLAIN.
- AUTH LOGIN.
- MAIL FROM.
- RCPT TO for all envelope recipients.
- DATA.
- QUIT.
- configurable timeout.

Potential later features:

- connection pooling;
- SMTP pipelining;
- LMTP;
- OAuth;
- proxy support.

SMTP replies should be parsed into structured exceptions or result objects that
preserve:

- reply code;
- enhanced status code, when present;
- reply text;
- command phase;
- whether the failure is temporary or permanent.

## Error Model

The package should distinguish:

- local validation errors;
- MIME rendering errors;
- connection errors;
- TLS negotiation errors;
- authentication errors;
- temporary SMTP errors;
- permanent SMTP errors;
- unknown delivery state errors.

Unknown delivery state is important. If the connection fails after the message
body has been sent but before the final SMTP reply is read, the package cannot
know whether the server accepted the message. This case should not be hidden as
a normal temporary failure.

The implementation should expose this case as `UnknownDeliveryState`, a distinct
SMTP exception that is not treated as retryable by default.

## Retry And Backoff

Retry should be implemented by `RetryMailer`, not by `SmtpMailer`.

Default retry candidates:

- SMTP `4xx` replies before the message is accepted;
- connection failures before `DATA`;
- timeout before `DATA`;
- temporary TLS or greeting failures.

Default non-retry candidates:

- SMTP `5xx` replies;
- validation failures;
- rendering failures;
- authentication failures unless explicitly configured;
- unknown delivery state unless explicitly configured.

`RetryPolicy` should support:

- maximum attempts;
- initial delay;
- maximum delay;
- multiplier;
- jitter;
- optional callback or event hook per attempt.

Backoff should use `Amp\delay()` so it cooperates with the event loop.

## Rate Limiting

Rate limiting should be implemented by `RateLimitedMailer`, not by individual
transport classes.

First-release limiter scope:

- process-local only;
- shared by all sends using the same limiter instance;
- safe under concurrent fibers in the same process.

Policy options:

- maximum sends per second;
- maximum sends per minute;
- burst size;
- maximum concurrent sends.

The implementation should expose a small `RateLimiter` interface so a future
Redis-backed or database-backed distributed limiter can be added without
changing `RateLimitedMailer`.

## Fallback Delivery

`FallbackMailer` is optional for the first release but fits the model well.

It should try mailers in order and stop after the first successful send. It
should not retry permanent failures through fallback unless explicitly
configured, because a permanent recipient or message error will usually fail
with every transport.

## Sendmail Transport

`SendmailMailer` is not required for the first release.

If included later, it should either:

- be clearly documented as blocking; or
- use `amphp/process` to avoid blocking the event loop.

## Configuration

SMTP configuration should be represented by a small value object:

```php
new SmtpConfig(
    host: 'smtp.example.com',
    port: 587,
    username: 'user',
    password: 'secret',
    tls: TlsMode::StartTls,
    timeout: 30.0,
);
```

TLS modes:

- disabled;
- implicit TLS;
- STARTTLS;
- STARTTLS if available.

For production safety, STARTTLS-required should fail if the server does not
advertise STARTTLS.

## Testing Strategy

Tests should focus on behavior, not internal structure.

Unit tests:

- address parsing and formatting;
- header encoding;
- MIME rendering for text, HTML, attachments, inline parts, and Bcc omission;
- retry decision matrix;
- backoff schedule;
- process-local rate limiter behavior.

Integration tests:

- SMTP dialogue against a local fake SMTP server;
- STARTTLS path if practical;
- temporary and permanent SMTP replies;
- connection loss before and after `DATA`;
- concurrent sends under rate limiting.

The fake SMTP server should be part of the test suite or test fixtures so the
test suite does not depend on external services.

## Documentation Requirements

The README should include:

- installation;
- basic text email;
- HTML email;
- attachments;
- SMTP configuration;
- retry/backoff;
- rate limiting;
- error handling;
- AMPHP usage notes;
- explicit DKIM non-goal.

The package should also include a concise license file with MIT terms.

## Open Questions

1. Should the first release include `FallbackMailer`, or should it wait until
   the SMTP transport is stable?
2. Should CSS inlining be omitted entirely, or provided as an optional add-on
   later?
3. Should unknown delivery state be retried only by explicit opt-in?
4. Should the first release require PHP `^8.2`, or should it target a newer
   baseline such as `^8.3`?

## Recommended MVP

The first implementation should include:

- `Email`;
- `Address`;
- `Attachment`;
- `MimeRenderer`;
- `Mailer`;
- `SmtpMailer`;
- `RetryMailer`;
- `RateLimitedMailer`;
- unit tests for MIME and policies;
- fake SMTP integration tests;
- README;
- MIT license.

The first implementation should defer:

- DKIM;
- sendmail;
- distributed rate limiting;
- connection pooling;
- SMTP pipelining;
- CSS inlining;
- automatic HTML asset embedding.
