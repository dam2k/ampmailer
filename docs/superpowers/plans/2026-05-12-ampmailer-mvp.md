# AmpMailer MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the first usable `dam2k/ampmailer` package with message composition, MIME rendering, async SMTP, retry/backoff, and process-local rate limiting.

**Architecture:** Keep composition, rendering, transport, retry, and throttling separate. `Email` is a mutable fluent object; `MimeRenderer` creates the envelope and RFC/MIME string; mailers are composable decorators around a `Mailer` interface.

**Tech Stack:** PHP 8.2+, Composer, AMPHP v3 packages, PHPUnit for tests, MIT license.

---

### Task 1: Package Skeleton And Test Harness

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `LICENSE`
- Create: `README.md`
- Create: `src/Mailer.php`
- Create: `tests/bootstrap.php`
- Create: `tests/Smoke/PackageSmokeTest.php`

- [ ] Write a smoke test that loads the test bootstrap and asserts the `Mailer` interface exists.
- [ ] Run the smoke test and confirm it fails before `Mailer` exists.
- [ ] Add Composer metadata, PSR-4 autoloading, MIT license, README starter, and the `Mailer` interface.
- [ ] Run the smoke test and confirm it passes.

### Task 2: Email, Address, And Attachment Model

**Files:**
- Create: `src/Email.php`
- Create: `src/Address.php`
- Create: `src/Attachment.php`
- Create: `src/Exception/InvalidEmail.php`
- Create: `tests/Email/EmailTest.php`

- [ ] Write tests for fluent mutation, address parsing, Bcc retention, file attachments, and memory attachments.
- [ ] Run the tests and confirm missing classes fail.
- [ ] Implement the minimal model classes.
- [ ] Run the tests and confirm they pass.

### Task 3: MIME Rendering

**Files:**
- Create: `src/Mime/MimeRenderer.php`
- Create: `src/Mime/RenderedMessage.php`
- Create: `tests/Mime/MimeRendererTest.php`

- [ ] Write tests for text mail, HTML alternative mail, Bcc omission from headers, attachment rendering, and CRLF normalization.
- [ ] Run the tests and confirm renderer failures.
- [ ] Implement the renderer.
- [ ] Run the tests and confirm they pass.

### Task 4: Retry And Rate Limit Decorators

**Files:**
- Create: `src/Retry/RetryMailer.php`
- Create: `src/Retry/RetryPolicy.php`
- Create: `src/RateLimit/RateLimitedMailer.php`
- Create: `src/RateLimit/RateLimiter.php`
- Create: `src/RateLimit/InMemoryRateLimiter.php`
- Create: `tests/Retry/RetryMailerTest.php`
- Create: `tests/RateLimit/RateLimitedMailerTest.php`

- [ ] Write tests using in-memory fake mailers and clocks where possible.
- [ ] Run the tests and confirm missing classes fail.
- [ ] Implement retry decisions and a process-local limiter.
- [ ] Run the tests and confirm they pass.

### Task 5: SMTP Transport

**Files:**
- Create: `src/Smtp/SmtpConfig.php`
- Create: `src/Smtp/TlsMode.php`
- Create: `src/Smtp/SmtpMailer.php`
- Create: `src/Smtp/SmtpReply.php`
- Create: `src/Smtp/SmtpException.php`
- Create: `tests/Smtp/SmtpReplyTest.php`
- Create: `tests/Smtp/SmtpMailerTest.php`

- [ ] Write parser tests for single-line and multi-line SMTP replies.
- [ ] Write an integration-style test using a local fake SMTP server for a plain text message.
- [ ] Run the tests and confirm missing SMTP classes fail.
- [ ] Implement SMTP reply parsing and the async SMTP sender.
- [ ] Run the tests and confirm they pass.

### Task 6: Final Verification And Documentation

**Files:**
- Modify: `README.md`
- Modify: `docs/superpowers/specs/2026-05-12-ampmailer-design.md` if implementation decisions changed.

- [ ] Update README examples for Email, SMTP, retry, and rate limiting.
- [ ] Run the full test suite.
- [ ] Run Composer validation.
- [ ] Run PHP syntax checks over `src` and `tests`.
