# Changelog

All notable changes are documented here. The format follows Keep a Changelog, and this project uses Semantic Versioning.

## [1.0.1] - 2026-11-24

### Changed

- Documentation corrections: release notes, changelog, and security audit now reflect the published 1.0.0 release and the rewritten repository history. No code behaviour change.

## [1.0.0] - 2026-11-24

### Added

- Admin area to list, read, and delete reports.
- Argon2id password hashing and TOTP two-factor authentication with replay protection.
- XChaCha20-Poly1305 encryption of stored personal data.
- CSRF protection, rate limiting, input validation, and security headers.
- SQLite schema, operator CLI, unit, integration, and end-to-end tests, and a CI workflow.
- Architecture, security, and release documentation.

### Changed

- **Breaking:** rebuilt as a PHP 8.4+ application separated into `frontend/` and `backend/`.
- **Breaking:** `index.php` and `report.php` were replaced by a front controller and the `/reports` route.
- **Breaking:** form field names now use English snake_case.
- Mail now uses Symfony Mailer and contains only a random reference.
- The confirmation page no longer echoes submitted data.

### Removed

- jQuery, the Bootstrap assets served from a third-party host, and the hardcoded recipient address.

### Security

- Fixed mail header injection, reflected XSS, missing CSRF protection, and insecure asset loading.
- Relative database paths resolve from the project root, and paths inside the web root are rejected.
- Login timing no longer reveals whether a username exists.
- Rate limits group IPv6 clients by /64, and blocked clients can no longer grow the rate-limit table.
- The application no longer creates the database, and `db:migrate` enforces owner-only file permissions.
- Input validation rejects C1 control characters, and responses no longer disclose the PHP version.
