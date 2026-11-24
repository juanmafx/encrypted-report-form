# Security Audit

**Date:** 2026-11-23. **Scope:** all tracked files and the five commits of history on `master` at the time of the audit. **Type:** source review plus automated tests. This was not an independent penetration test.

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
| F-08 | Medium | Personal email address hardcoded in source (masked: `j***@h***.com`) | Removed from the tree. Resolved in history by the 2026-11-24 history rewrite |
| F-09 | Low | Reliance on short open tags and undefined variables | Fixed: rewritten |

## Findings from the follow-up review

| ID | Severity | Finding | Status |
| --- | --- | --- | --- |
| R-01 | High | A relative `FANG_DB_PATH` followed the SAPI working directory, which under PHP-CGI resolved inside the web root | Fixed: resolved from the project root, and web-root paths are rejected |
| R-02 | Medium | Unknown usernames took about twice as long as known ones, revealing valid usernames | Fixed: both paths perform one Argon2id verification |
| R-03 | Medium | Rotating addresses within one IPv6 /64 evaded per-client rate limits | Fixed: IPv6 clients are grouped by /64 |
| R-04 | Medium | A request before `db:migrate` created a world-readable database, and migrating kept that mode | Fixed: the application no longer creates the database, and `db:migrate` enforces mode 0600 |
| R-05 | Low | A blocked client could keep adding rate-limit rows by varying the username | Fixed: the per-username bucket is only touched after the client check passes |
| R-06 | Low | C1 control characters passed input validation | Fixed |
| R-07 | Low | The `X-Powered-By` header disclosed the PHP version | Fixed |
| R-08 | Low | The mailer DSN, which can hold SMTP credentials, was not marked as a sensitive parameter | Fixed |

## Resolved after the audit

| ID | Severity | Item | Resolution |
| --- | --- | --- | --- |
| O-01 | Low | Personal email addresses were in the published git history, in the original source and in commit author metadata | Resolved on 2026-11-24: the published history was rewritten to keep only the 2026 modernization commits, authored with a GitHub noreply address. Old commits may stay reachable by direct SHA until GitHub purges unreferenced objects, so treat anything that was ever published as exposed |

## Controls added

Argon2id password hashing, TOTP two-factor authentication with replay protection, XChaCha20-Poly1305 field encryption, hardened sessions, rate limiting, security headers, and a maintained mail library. See [SECURITY.md](../SECURITY.md).

## Open items

| ID | Severity | Item | Required action |
| --- | --- | --- | --- |
| O-02 | Low | No key rotation or TOTP recovery flow | Add before handling real personal data |
| O-03 | Low | Rate limits are per proxy when deployed behind a reverse proxy | Enforce limits at the proxy |
| O-04 | Info | No license is declared and the scenario derives from a published book | Owner decides licensing |
| O-05 | Low | A holder of the password can exhaust the TOTP attempt limit and delay the real admin | Accepted for this scope |
| O-06 | Low | PHP parses request bodies before the application checks their size | Set limits in the web server and `post_max_size` |

## Verification

- PHPUnit suite on PHP 8.4 and 8.5, results in `reports/tests/`.
- PHPStan level 8 on `backend/src`.
- `composer audit` reported no known advisories on the audit date.
- `gitleaks` with redaction reported two matches, both the public RFC 6238 test secret used as a test fixture.
- The GitHub Actions workflow ran on `master` after publication on 2026-11-24 and passed.
