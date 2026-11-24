# Fang

A security-focused PHP contact form: visitors file a report through a public page, submissions are encrypted before storage, and a small admin area protected by a password and TOTP two-factor authentication reads them.

> **Project history:** originally created in 2013, modernized in 2026.

## Purpose

The form collects a playful "alien abduction" report that asks whether the visitor has seen a lost dog named Fang. The scenario is small on purpose. A contact form handles untrusted input, personal data, email, and privileged access, so it is a compact place to apply those controls correctly.

## Why this project exists

The 2013 version was a two-page learning exercise. It echoed input without escaping, built a mail header from user input, hardcoded a recipient address, and loaded assets over plain HTTP. Version 1.0.0 keeps the scenario and rebuilds the implementation around explicit security boundaries.

## What it does

- Serves a validated report form with CSRF protection and a strict Content Security Policy.
- Encrypts every free-text and personal field with XChaCha20-Poly1305 before it reaches the database.
- Sends a notification containing only a random reference, never submitted data.
- Provides an admin area to list, read, and delete reports, behind Argon2id passwords and TOTP.
- Rate limits submissions, password attempts, and TOTP attempts.

## Architecture

```text
Browser -> frontend/public/index.php -> Kernel -> Controllers -> Services -> SQLite
                                                       |-> Symfony Mailer -> SMTP
```

```text
backend/    Application code, CLI, and tests
frontend/   Web root, templates, and stylesheet
db/         SQL schema
docs/       Architecture and security audit
reports/    Committed test reports
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for details.

## Quick start

Requirements: PHP 8.4 or 8.5 with `mbstring`, `pdo_sqlite`, `sodium`, and Argon2 support, plus Composer. Running the tests also needs `dom`, `xml`, and `xmlwriter`.

```bash
composer install --no-scripts --no-plugins
cp .env.example .env
chmod 600 .env
mkdir -p var && chmod 700 var
php backend/bin/fang key:generate
```

Paste the generated key into `FANG_MASTER_KEY` in `.env`. For plain-HTTP local development, also set `FANG_COOKIE_SECURE=false`. Then:

```bash
set -a; . ./.env; set +a
php backend/bin/fang db:migrate
php backend/bin/fang admin:create site-admin
php -S 127.0.0.1:8080 -t frontend/public frontend/public/index.php
```

`admin:create` reads the password from standard input and prints an `otpauth://` URI once. Add it to an authenticator app and treat it as a secret.

## Usage guide

| Route | Access | Purpose |
| --- | --- | --- |
| `GET /` | Public | Report form |
| `POST /reports` | Public, CSRF | Store a report |
| `GET /thanks` | Public | Confirmation |
| `GET`, `POST /admin/login` | Public, CSRF on POST | Password step |
| `GET`, `POST /admin/two-factor` | Password verified | TOTP step |
| `GET /admin` | Admin | Report list |
| `GET /admin/reports/{id}` | Admin | Report details |
| `POST /admin/reports/{id}/delete` | Admin, CSRF | Delete a report |
| `POST /admin/logout` | CSRF | End the session |

## Configuration

Configuration comes only from environment variables.

| Variable | Required | Description |
| --- | --- | --- |
| `FANG_MASTER_KEY` | Yes | Base64 32-byte key. Losing it makes stored reports unreadable. |
| `FANG_DB_PATH` | Yes | SQLite database path, outside the web root. |
| `FANG_MAILER_DSN` | Yes | Symfony Mailer DSN. `null://null` disables delivery. |
| `FANG_MAIL_FROM` | Yes | Notification sender. |
| `FANG_MAIL_TO` | Yes | Notification recipient. |
| `FANG_COOKIE_SECURE` | No | Defaults to `true`. Set `false` only for local HTTP. |

## Database

[db/schema.sql](db/schema.sql) defines the reports, admin users, and rate-limit tables for SQLite. Apply it with `php backend/bin/fang db:migrate`.

## Testing

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse --no-progress
```

The suite has unit tests, in-process integration tests, and an end-to-end test that drives a real PHP server over HTTP. The latest committed results are in [reports/tests/](reports/tests/).

## Security notes

Controls, limitations, and the audit are documented in [SECURITY.md](SECURITY.md) and [docs/SECURITY_AUDIT.md](docs/SECURITY_AUDIT.md). Serve the application over HTTPS, point the web root at `frontend/public` only, and keep `FANG_MASTER_KEY` out of version control.

## Attribution

The "Aliens Abducted Me" scenario and the dog named Fang come from *Head First PHP & MySQL* by Lynn Beighley and Michael Morrison (O'Reilly Media, 2008). The original version of this repository follows that exercise. The scenario remains the work of its authors. Third-party libraries are listed in `composer.json` and keep their own licenses.

## License

This repository does not currently declare a license, so no reuse rights are granted beyond those provided by law.
