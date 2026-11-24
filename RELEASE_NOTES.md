# Fang 1.0.0 Release Notes

**Status:** prepared, not tagged or published.

Version 1.0.0 is a full rewrite of the 2013 form as a small, security-focused PHP 8.4+ application. Reports are encrypted before storage and can be read only through an admin area protected by Argon2id passwords and TOTP.

## Highlights

- Separated `frontend/` and `backend/` structure.
- Authenticated encryption of stored personal data.
- CSRF protection, rate limiting, strict validation, and mail header injection protection.
- Automated tests with a committed report in `reports/tests/`.

## Upgrade notes

The original version stored nothing, so there is no data to migrate. Configure the environment variables, run `db:migrate`, create an admin with `admin:create`, and point the web root at `frontend/public`. Links to `index.php` or `report.php` must be updated.

## Known limitations

See [SECURITY.md](SECURITY.md). Personal email addresses from the original code and commit metadata remain in git history until the owner decides otherwise.
