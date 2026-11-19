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
| Framework | Laravel 12 |
| Database | MySQL 8.0+, InnoDB, `utf8mb4` |
| Auth (browser) | Sanctum first-party cookie sessions |
| Auth (readers) | Google OpenID Connect via Socialite |
| Auth (staff) | Password + TOTP (Google2FA) + recovery codes |
| Authorization | Roles + permissions + policies + middleware |
| Audit | Append-only `audit_logs` (Audit module) |
| Realtime / files | Reverb, S3 (wired in later modules) |

## Quick start

```bash
cd backend

composer install
cp .env.example .env
php artisan key:generate

# Create the database first (MySQL 8):
#   CREATE DATABASE minerva CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

php artisan migrate --seed
php artisan identity:create-admin admin@example.com

php artisan serve            # http://localhost:8000
php artisan queue:work       # in another terminal
```

Run checks:

```bash
php artisan test
./vendor/bin/pint --test
```

## Environment essentials

```dotenv
APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com
CORS_ALLOWED_ORIGINS=https://app.example.com
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

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
