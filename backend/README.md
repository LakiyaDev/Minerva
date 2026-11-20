# Minerva API — Backend

Laravel modular-monolith API for the Minerva Digital Platform (IEEE EMBS Student
Branch Chapter, University of Sri Jayawardenepura).

This repository currently implements the **Identity & Access Management (IAM)**
module: reader Google sign-in, staff password + TOTP authentication, RBAC,
publisher grants, invitations, session control and audit logging. Later modules
(Content, Articles, Voting, Comments, Analytics, Integrations, Media) plug into
the same module structure.

- API base path: `/api/v1`
- Architecture: `docs/identity-and-access.md`
- API contract: `openapi/openapi.yaml`
- Requirements source: `Docs/` on the `main` branch (SRS v1.1, backend guide)

## Stack

| Layer | Choice |
|---|---|
| Runtime | PHP 8.3+ |
| Extensions | `ext-xmlwriter` (TOTP QR SVG), `ext-mbstring`+`ext-iconv` |
| Framework | Laravel 12 |
| Database | MySQL 8.0+, InnoDB, `utf8mb4` |
| Auth (browser) | Sanctum first-party cookie sessions |
| Auth (readers) | Google OpenID Connect via Socialite |
| Auth (staff) | Password + TOTP (pragmarx/google2fa v9) + recovery codes |
| Authorization | Roles + permissions + policies + middleware |
| Audit | Append-only `audit_logs` (Audit module) |
| Realtime / files | Reverb, S3 (wired in later modules) |

## Quick start

Requirements: PHP 8.3+ (`ext-xmlwriter`, `ext-mbstring`, `ext-iconv`, `pdo_mysql`),
Composer 2, and MySQL 8+.

```bash
cd backend

composer install
cp .env.example .env
php artisan key:generate

# Create the database and its user first (MySQL 8):
#   CREATE DATABASE minerva CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
#   CREATE USER 'minerva'@'127.0.0.1' IDENTIFIED BY 'minerva-dev';
#   GRANT ALL PRIVILEGES ON minerva.* TO 'minerva'@'127.0.0.1';

php artisan migrate --seed
php artisan identity:create-admin admin@example.com

php artisan serve            # http://localhost:8000
php artisan queue:work       # in another terminal
```

Set `DB_PASSWORD` in `.env` to the password of the database user you created.

Run checks:

```bash
php artisan test
./vendor/bin/pint --test
```

## Google OAuth (readers) — local setup

Google sign-in is a server-side OAuth 2.0 / OpenID Connect flow. The SPA never
sees the client secret.

1. **Google Cloud Console** → *APIs & Services* → *Credentials* → *Create
   credentials* → **OAuth client ID** → application type **Web application**.
2. Under **Authorized redirect URIs** add exactly:

   ```text
   http://localhost:8000/api/v1/auth/google/callback
   ```

   The URI must match `GOOGLE_REDIRECT_URI` character for character (scheme,
   port and path). `http://localhost` and `http://127.0.0.1` are *different*
   URIs to Google — register both if you use both hosts.
3. Copy the client ID and client secret into `backend/.env`:

   ```dotenv
   GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=xxxx
   GOOGLE_REDIRECT_URI=http://localhost:8000/api/v1/auth/google/callback
   GOOGLE_SUCCESS_REDIRECT=http://localhost:8443/auth/callback
   GOOGLE_FAILURE_REDIRECT=http://localhost:8443/auth/callback
   ```

4. Make sure the SPA origin is allowed to talk to the API (see
   *Frontend integration* below): `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS`
   and `CORS_ALLOWED_ORIGINS` must all contain `http://localhost:8443`.
5. While the app is in "Testing" status in Google Cloud Console, add your own
   Google account under **OAuth consent screen → Test users**, otherwise
   Google rejects the consent request with `access_denied`.

Verify the wiring without opening a browser:

```bash
curl -s http://localhost:8000/api/v1/auth/google/redirect | jq -r .data.authorization_url
```

The URL must contain your `client_id`, the `redirect_uri` above and a `state`
parameter. The callback rejects a missing, unknown or replayed `state` with
`reason=invalid_state`, and a denied consent screen with
`reason=access_denied`, both redirected to `GOOGLE_FAILURE_REDIRECT`.

## Environment essentials

```dotenv
APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com
CORS_ALLOWED_ORIGINS=https://app.example.com
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# Comma-separated proxy IPs/CIDRs, or "*" behind a trusted TLS load balancer.
TRUSTED_PROXIES=*

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://api.example.com/api/v1/auth/google/callback
GOOGLE_SUCCESS_REDIRECT=https://app.example.com/auth/callback
GOOGLE_FAILURE_REDIRECT=https://app.example.com/auth/callback

STAFF_ALLOWED_EMAIL_DOMAINS=yourdomain.lk
STAFF_MFA_REQUIRED=true
```

Google Cloud Console → **Credentials → OAuth client ID (Web application)**:
authorised redirect URI must exactly equal `GOOGLE_REDIRECT_URI`.

## Authentication summary

| Audience | Method | Endpoint |
|---|---|---|
| Readers | Google OAuth (auto-provisioned participant) | `GET /api/v1/auth/google/redirect` |
| Staff | Email + password → TOTP challenge | `POST /api/v1/auth/staff/login` |
| Staff (new) | Admin invitation → password → TOTP enrolment | `POST /api/v1/auth/staff/invitations/accept` |
| All | Session profile | `GET /api/v1/me` |

Readers never have a password hash and can never use the staff endpoint. Staff
can never complete a Google sign-in: this prevents social login from bypassing
MFA. See `docs/identity-and-access.md` for the full flows.

## Frontend integration (Sanctum cookies)

The React SPA authenticates with the HttpOnly session cookie, not a bearer token.

1. Set `VITE_API_BASE_URL=http://localhost:8000` in the frontend root `.env.local`
   for local development; production builds should point it at the deployed API.
2. Send requests with credentials (`fetch(url, { credentials: "include" })`) and
   make sure the page origin is listed in `SANCTUM_STATEFUL_DOMAINS`.
3. Before the first state-changing request, call `GET /sanctum/csrf-cookie`, then
   send the `X-XSRF-TOKEN` header (read the value from the `XSRF-TOKEN` cookie).
4. For this project's Vite preview, set `FRONTEND_URL` and `CORS_ALLOWED_ORIGINS`
   to `http://localhost:8443`, `SANCTUM_STATEFUL_DOMAINS` to include
   `localhost:8443`, and both Google callback redirects to
   `http://localhost:8443/auth/callback`.
5. After the Google callback redirect (`/auth/callback?auth=success`), refresh
   the CSRF cookie before voting/commenting because the session was replaced.
6. Treat `403 mfa_enrollment_required` from a staff call as the signal to run the
   TOTP setup flow (`POST /api/v1/me/two-factor/setup` → `confirm`).

## Module layout

```text
app/
  Modules/
    Identity/   users, OAuth, staff auth, MFA, RBAC, publisher grants, sessions
    Audit/      append-only audit trail + AuditLogger implementation
  Shared/       response envelope, error renderer, middleware, small primitives
routes/
  api.php       mounts /api/v1 and each module route file
```

Each module owns its migrations, models, routes, controllers, requests,
resources, policies, services, events and tests. Other modules must use a
public contract (for example `Identity\Contracts\IdentityAccess`) instead of
reading Identity tables.

## Current status

- [x] Modular monolith skeleton, API envelope, request IDs, security headers
- [x] Google reader registration/sign-in with single-use state
- [x] Staff password auth, lockout, TOTP enrolment/verification, recovery codes
- [x] Invitation-only staff onboarding + password reset
- [x] RBAC (roles, permissions, direct grants, publisher grants) with caching
- [x] `/me`, profile update, session revocation
- [x] Admin user/role/permission/publisher/invitation endpoints
- [x] Audit logging for security-relevant actions
- [x] Feature tests for IAM
- [ ] Email delivery provider (SMTP/SES) for invitations and resets
- [ ] Redis-backed cache/rate limiting for multi-node production
- [ ] OpenAPI contract tests and CI pipeline
