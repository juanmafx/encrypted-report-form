# Security Audit

**Date:** 2026-11-23. **Scope:** all tracked files and the five commits of history on `master`. **Type:** source review plus automated tests. This was not an independent penetration test.

## Findings in the original code

| ID | Severity | Finding | Status |
| --- | --- | --- | --- |
| F-01 | High | Mail header injection through a user-controlled sender | Fixed: submitted values never reach headers |
| F-02 | High | Reflected XSS from unescaped output | Fixed: output escaping and CSP |
| F-03 | Medium | No CSRF protection | Fixed: session-bound tokens |
| F-04 | Medium | No server-side validation | Fixed: allow-list validation |
| F-05 | Medium | No rate limiting, enabling mail abuse | Fixed |
| F-06 | Medium | Stylesheet and script loaded over plain HTTP from a third-party host | Fixed: assets are self-hosted |
| F-07 | Medium | End-of-life jQuery 1.10 | Fixed: removed |
| F-08 | Medium | Personal email address hardcoded in source (masked: `j***@h***.com`) | Removed from the tree. **Open in git history** |
| F-09 | Low | Reliance on short open tags and undefined variables | Fixed: rewritten |

## Controls added

Argon2id password hashing, TOTP two-factor authentication with replay protection, XChaCha20-Poly1305 field encryption, hardened sessions, rate limiting, security headers, and a maintained mail library. See [SECURITY.md](../SECURITY.md).

## Open items

| ID | Severity | Item | Required action |
| --- | --- | --- | --- |
| O-01 | Low | The personal email address remains in published git history | Owner decides whether to rewrite history |
| O-02 | Low | No key rotation or TOTP recovery flow | Add before handling real personal data |
| O-03 | Low | Rate limits are per proxy when deployed behind a reverse proxy | Enforce limits at the proxy |
| O-04 | Info | No license is declared and the scenario derives from a published book | Owner decides licensing |

## Verification

- PHPUnit suite on PHP 8.4 and 8.5, results in `reports/tests/`.
- PHPStan level 8 on `backend/src`.
- `composer audit` reported no known advisories on the audit date.
- The GitHub Actions workflow was written but has not run, because this branch has not been pushed.
