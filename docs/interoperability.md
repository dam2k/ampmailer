# SMTP Interoperability Checklist

AmpMailer is still experimental. Run these checks in a controlled environment
before considering a release usable with a real MTA.

## Safety Rules

- Use only sender and recipient addresses that are explicitly allowed for the
  test.
- Do not send to external recipients while testing against a production MTA.
- Do not commit credentials, host-specific secrets, message IDs, or delivery
  logs containing private data.
- Keep test messages clearly identifiable and low volume.
- Stop testing immediately if the MTA returns unexpected policy, relay, or rate
  limit errors.

## Minimum Manual Checks

1. Send a plain text message through authenticated submission on port 587 with
   STARTTLS enabled.
2. Confirm that SMTP authentication succeeds only after STARTTLS.
3. Confirm the MTA accepts the expected envelope sender and recipient.
4. Confirm the message is delivered to the controlled test mailbox.
5. Repeat with a small HTML message.
6. Repeat with a small attachment.
7. Record whether the MTA rewrites headers, applies filters, or adds warnings.
8. Keep retry and rate-limit decorators enabled in the application path when
   testing real sending behavior.

## Release Gate

Before tagging `0.6.1`, record a short non-secret summary of the environment
used for the manual SMTP check:

- MTA family and role, for example Exim submission server.
- Port and TLS mode, for example port 587 with STARTTLS.
- Authentication method advertised and used, for example `AUTH PLAIN`.
- Message variants tested: plain text, HTML, attachment.
- Result: accepted, delivered, rejected, or inconclusive.

