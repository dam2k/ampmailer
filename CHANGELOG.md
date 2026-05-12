# Changelog

All notable changes to this project are documented here.

## 0.6.1 - 2026-05-12

First usable experimental pre-1.0 release.

### Added

- Fluent `Email` builder with sender, recipients, subject, text, HTML, custom
  headers, attachments, data attachments, and inline data attachments.
- MIME renderer for plain text, HTML, `multipart/alternative`,
  `multipart/mixed`, `multipart/related`, UTF-8 subjects, UTF-8 display names,
  UTF-8 attachment filenames, CRLF normalization, and `Bcc` envelope-only
  handling.
- SMTP client built directly on AMPHP sockets with TCP, implicit TLS,
  STARTTLS, STARTTLS-if-available, `AUTH PLAIN`, `AUTH LOGIN`, envelope
  commands, `DATA`, dot-stuffing, and `QUIT`.
- Retry decorator for temporary SMTP failures with tested max-attempt and
  backoff behavior.
- Process-local rate limiting decorator with tested timing behavior.
- `UnknownDeliveryState` for connection loss after the DATA body is sent.
- Header and attachment safety validation for common injection vectors.
- GitHub Actions CI for PHP 8.2, 8.3, and 8.4.
- Manual SMTP interoperability checklist and one recorded controlled real-MTA
  submission test.

### Notes

- AmpMailer is experimental and still lightly tested. Test it carefully and for
  a long period in your own environment before relying on it in production.
- DKIM is intentionally out of scope.
- This package is inspired by `nette/mail`, but it is not API compatible with
  Nette.
- Use for spam, unsolicited email, abusive bulk email, phishing, malware,
  harassment, unlawful mail, or provider-policy violations is strongly
  prohibited.

### Packagist Release Notes

AmpMailer `0.6.1` is the first usable experimental release of a small AMPHP v3
mailer. It includes a fluent message builder, MIME rendering, direct SMTP
transport over AMPHP sockets, STARTTLS, SMTP AUTH PLAIN/LOGIN, retry/backoff,
process-local rate limiting, local test coverage, PHPStan, CI, and a controlled
real-MTA interoperability smoke check.

This release is experimental and still lightly tested. Test carefully and for a
long period in your own environment before using it in production.

