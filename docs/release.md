# Release Procedure

This document describes the manual release flow for AmpMailer.

## Pre-Release Checks

Run these commands from the repository root:

```bash
php /home/dino/temp/prove/php/composer.phar validate --strict
php /home/dino/temp/prove/php/composer.phar test
php /home/dino/temp/prove/php/composer.phar analyse
```

Check GitHub Actions after pushing release metadata:

- The `CI` workflow must be green on `main`.
- PHP 8.2, 8.3, and 8.4 jobs must all pass.
- Do not tag a release from a red commit.

Check repository hygiene:

```bash
git status --short --branch
rg -n "password|passwd|secret|192\\.168\\.|@tuxweb" README.md CHANGELOG.md docs src tests composer.json phpunit.xml.dist phpstan.neon.dist
```

The second command is a defensive scan. Review any match before tagging.

## Packagist

Package name:

```text
dam2k/ampmailer
```

Suggested description:

```text
Small AMPHP v3 mailer with MIME rendering, SMTP transport, retry, and rate limiting.
```

Use the `CHANGELOG.md` entry for the GitHub release body and Packagist release
notes. Keep the experimental warning in the release text.

## Tag 0.6.1

After all checks are green:

```bash
git fetch origin
git status --short --branch
git tag -a 0.6.1 -m "Release 0.6.1"
git push origin 0.6.1
```

Then verify:

- The tag appears on GitHub.
- GitHub Actions for the tag, if triggered, are green.
- Packagist sees the new version.

## Failure Handling

If CI fails:

1. Open the failed GitHub Actions job.
2. Read the first real error in the log.
3. Reproduce the same command locally.
4. Fix the code or documentation.
5. Run all pre-release checks again.
6. Commit and push the fix.
7. Tag only after the latest commit is green.

