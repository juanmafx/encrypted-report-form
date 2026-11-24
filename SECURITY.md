# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 1.x | Yes |
| Earlier, unversioned code | No |

## Reporting a vulnerability

Please do not publish vulnerability details in a public issue. Use GitHub private vulnerability reporting on this repository if it is available. Otherwise, open a minimal issue asking for a private contact channel, without technical details.

## Security controls

- **Passwords:** Argon2id with a per-password random salt, 64 MiB memory, 3 iterations.
- **Two-factor authentication:** TOTP (RFC 6238) with replay protection. Secrets are encrypted at rest.
- **Data at rest:** XChaCha20-Poly1305 authenticated encryption with context binding.
- **Keys:** supplied by the environment and never stored in the repository.
- **Web protections:** CSRF tokens, contextual output escaping, strict CSP, SameSite cookies, and security headers.
- **Abuse controls:** rate limits on submissions, passwords, and TOTP codes, plus a request size limit.
- **Injection:** prepared SQL statements. Submitted values are never placed in mail headers.
- **Supply chain:** locked dependencies, install scripts disabled, CI actions pinned to commit SHAs.

## Known limitations

- There is no key-rotation command or TOTP recovery-code flow. A lost authenticator requires operator intervention in the database.
- Behind a reverse proxy, application rate limits see the proxy address.
- The per-username login limit can be used to temporarily delay sign-in for a known username.
- Encryption protects the database at rest. It does not protect against a compromised application host that holds the key.
