# Architecture

## Overview

Fang is a server-rendered PHP application with no client-side JavaScript. The web server exposes only `frontend/public`. All logic lives in `backend/src`, and all persistent state lives in a SQLite database outside the web root.

```text
                 +-----------------------------+
 Browser ------> | frontend/public/index.php   |
                 +--------------+--------------+
                                v
                 +-----------------------------+
                 | Http\Kernel                 |
                 | size limit, CSRF, routing,  |
                 | security headers            |
                 +------+---------------+------+
                        v               v
              ReportController    AdminController
                        |               |
        +---------------+-------+-------+--------------+
        v               v       v                      v
  ReportValidator  ReportRepository  Authenticator  RateLimiter
                        |               |              |
                   DataCipher      PasswordHasher      |
                        |          TotpService         |
                        v               v              v
                 +-----------------------------------------+
                 | SQLite                                  |
                 +-----------------------------------------+
```

## Module structure

```text
backend/
  bin/fang                  Operator CLI
  src/
    Application.php         Composition root
    Admin/                  Admin accounts and two-step authentication
    Config/                 Environment parsing and validation
    Console/                CLI commands
    Http/                   Kernel, request, response, renderer, headers
      Controller/           Public and admin controllers
    Mail/                   Notification through Symfony Mailer
    Persistence/            PDO connection and schema application
    Reports/                Validation and encrypted storage
    Security/               Cipher, key derivation, hashing, TOTP, CSRF, limits
    Session/                Session abstraction and hardened native sessions
  tests/
    Unit/                   Isolated security and validation tests
    Integration/            Full request handling with in-memory SQLite
    EndToEnd/               Real HTTP server and CLI
    Support/                Test doubles and harness
frontend/
  public/                   Web root: front controller and stylesheet
  templates/                Escaped PHP views
db/schema.sql               Database definition
```

## Request flow

1. The front controller builds the kernel from environment variables.
2. The kernel rejects oversized bodies and unsupported methods, then verifies the CSRF token on every POST.
3. A controller applies rate limits and calls domain services.
4. Views escape all dynamic output.
5. The kernel adds security headers to every response.

## Data protection

A single master key is expanded with the libsodium KDF into separate subkeys for data encryption and rate-limit hashing. Each encrypted value uses a random 192-bit nonce and binds its table, record, and field as associated data, so a ciphertext copied to another row or column fails authentication.

## Authentication flow

```text
Password (Argon2id) -> pending state, 5 minutes -> TOTP -> admin session
```

Session IDs are regenerated at each step. Accepted TOTP time steps are recorded atomically so a code cannot be reused. Admin sessions expire after 15 idle minutes or 8 hours in total.

## Recommended deployment

- Terminate TLS at Nginx or Caddy and forward to PHP-FPM.
- Point the document root at `frontend/public`.
- Store the database in a private directory owned by the PHP user.
- Inject configuration from a secret manager or a restricted environment file.
- Back up the database and the master key separately.

SQLite suits this single-node, low-write workload. Moving to several application nodes would require a client-server database and shared session storage.

If the application runs behind a reverse proxy, rate limits currently see the proxy address because forwarded headers are deliberately not trusted. Apply per-client limits at the proxy in that topology.
