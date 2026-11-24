# Changelog

All notable changes are documented here. The format follows Keep a Changelog, and this project uses Semantic Versioning.

## [1.0.0] - Unreleased

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
