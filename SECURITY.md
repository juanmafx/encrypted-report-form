# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 1.x | Yes |
| Earlier, unversioned code | No |

## Reporting a vulnerability

Please do not publish vulnerability details in a public issue. Use GitHub private vulnerability reporting on this repository if it is available. Otherwise, open a minimal issue asking for a private contact channel, without technical details.

## Security controls

- **Passwords:** Argon2id with a per-password random salt, 64 MiB memory, 3 iterations. Unknown usernames perform one equivalent verification.
- **Two-factor authentication:** TOTP (RFC 6238, HMAC-SHA-1, 6 digits, 30-second steps) with replay protection. Secrets are encrypted at rest.
- **Data at rest:** XChaCha20-Poly1305 authenticated encryption with context binding for free-text and personal fields. The yes/no Fang answer and timestamps are not encrypted. `db:migrate` sets the database files to mode 0600.
- **Keys:** supplied by the environment and never stored in the repository.
- **Web protections:** CSRF tokens, HTML output escaping, strict CSP, SameSite cookies, and security headers.
- **Abuse controls:** rate limits on submissions, passwords, and TOTP codes, with IPv6 clients grouped by /64, plus rejection of requests declaring more than 32 KiB.
- **Injection:** prepared SQL statements. Submitted values are never placed in mail headers.
- **Supply chain:** locked dependencies, Composer scripts and plugins disabled in the documented install and in CI, CI actions pinned to commit SHAs.

## Known limitations

- There is no key-rotation command or TOTP recovery-code flow. A lost authenticator requires operator intervention in the database.
- Behind a reverse proxy, application rate limits see the proxy address.
- The per-username login limit can be used to temporarily delay sign-in for a known username.
- Someone who already holds the password can exhaust the TOTP attempt limit and delay the real admin's sign-in.
- PHP parses request bodies before the application checks their size, so also set limits in the web server and in `post_max_size`.
- Encryption protects the database at rest. It does not protect against a compromised application host that holds the key.
