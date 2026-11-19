# Minerva Digital Platform — Backend, Database, and API Integration Guide

**Document version:** 1.0  
**Source:** Minerva Digital Platform SRS v1.1  
**Proposed backend:** Laravel + MySQL + Laravel Reverb/WebSockets + AWS S3  
**Audience:** Backend developers, database engineers, API consumers, QA engineers, and DevOps engineers

---

## 1. Purpose and Scope

This document translates the SRS into an implementable backend plan. It defines the backend architecture, data model, database rules, REST API contract, API authentication, service API-key lifecycle, file handling, real-time vote-result events, and backend acceptance criteria.

The API remains the authoritative enforcement point for authentication, authorization, voting rules, editorial workflow, analytics ownership, and moderation. Frontend checks improve usability but never replace backend checks.

## 2. Architecture

```text
React + TypeScript client
        │ HTTPS / JSON
        ▼
Laravel REST API ───── Laravel Reverb (authenticated WebSocket events)
        │
        ├── MySQL: application data, permissions, votes, audit logs
        ├── AWS S3: article images, attachments, E-Magazine files
        └── Queue worker: notifications, media processing, audit/analytics jobs
```

### Recommended Laravel modules

- Authentication/session/token module
- User, roles, permissions, and publisher-grant module
- CMS module for workshops, events, sponsors, committee, and magazine content
- Article/editorial workflow module
- Voting module with transactional duplicate-vote protection
- Comment and moderation module
- Analytics/reporting module
- API-key management module for approved service integrations
- Audit-log module
- File-upload/storage module
- Broadcast-event module for live voting updates

## 3. Backend Conventions

### 3.1 Versioning and URLs

- Base URL: `https://api.<approved-domain>/api/v1`
- Use plural resource nouns: `/articles`, `/workshops`, `/comments`.
- Use JSON request and response bodies with `Content-Type: application/json`.
- Paginated list endpoints accept `page` and `per_page`; cap `per_page` at 100.
- Filtering/sorting must use an allow-list; never pass client field names directly into SQL.
- IDs should be opaque UUIDs/ULIDs externally (recommended) even if internal database keys are numeric.

### 3.2 Standard response envelope

Success:

```json
{
  "data": {},
  "meta": {}
}
```

Validation/operation error:

```json
{
  "message": "The submitted data is invalid.",
  "errors": {
    "title": ["The title field is required."]
  },
  "request_id": "01J..."
}
```

Use correct status codes: `200`, `201`, `202`, `204`, `400`, `401`, `403`, `404`, `409`, `422`, `429`, and `500`. Do not reveal stack traces, credential details, or internal authorization logic in responses.

### 3.3 Authorization model

Use permission checks/policies rather than a single role-name condition. A user can have independent participant and publisher capabilities.

Core permissions:

- `participant.vote`, `participant.comment`, `participant.view_results`
- `article.create`, `article.submit`, `article.update_own`, `article.publish_own`
- `article.review`, `article.publish_any`, `article.unpublish`, `article.feature`
- `comment.moderate`
- `content.manage`, `user.manage`, `publisher.manage`
- `analytics.view_own`, `analytics.view_all`, `settings.manage`, `audit.view`

Laravel policies must verify the authenticated subject, requested resource, ownership, status, and required permission on every protected action.

## 4. Database Integration Guide

### 4.1 MySQL configuration and migrations

- Use InnoDB and `utf8mb4` for all application tables.
- Store timestamps in UTC; convert only at presentation boundaries.
- Use Laravel migrations for every schema change and seeders only for approved reference data.
- Run migrations through CI/CD; do not make manual production schema changes.
- Use foreign keys, indexes, check constraints where supported, unique indexes, and transactions for integrity.
- Use soft deletes only where recovery/audit needs justify them; retain audit logs independently.
- Back up the database automatically and periodically test restore procedures.

### 4.2 Core identity and authorization tables

| Table | Key fields | Rules / indexes |
|---|---|---|
| `users` | `id`, `name`, `email`, `password`, `email_verified_at`, `is_active`, timestamps | Unique normalized `email`; password is a one-way hash only. |
| `roles` | `id`, `name`, `description` | Unique `name`; examples: participant, publisher, administrator. |
| `permissions` | `id`, `name`, `description` | Unique `name`. |
| `role_user` | `user_id`, `role_id` | Unique `(user_id, role_id)`; foreign keys cascade only where approved. |
| `permission_role` | `permission_id`, `role_id` | Unique `(permission_id, role_id)`. |
| `user_permissions` | `user_id`, `permission_id`, `granted_by`, `expires_at` | Supports independent/granular grants; unique `(user_id, permission_id)`. |
| `publisher_permissions` | `user_id`, `can_publish_directly`, `granted_by`, `granted_at`, `revoked_at` | At most one active grant per user; use a partial/derived uniqueness strategy appropriate to MySQL. |

Implementation note: participants may be publishers, and administrators may also have participant eligibility. The application must not model these identities as mutually exclusive.

### 4.3 Article and editorial tables

| Table | Key fields | Rules / indexes |
|---|---|---|
| `article_categories` | `id`, `name`, `slug`, `description` | Unique `slug`. |
| `articles` | `id`, `author_id`, `category_id`, `title`, `slug`, `abstract`, `body`, `status`, `is_featured`, `published_at` | FK to owner and category; unique `slug`; index `(status, published_at)` and `(author_id, status)`. |
| `article_attachments` | `id`, `article_id`, `storage_disk`, `storage_key`, `original_name`, `mime_type`, `size_bytes`, `kind` | FK to article; never store public S3 secrets/URLs. |
| `article_status_history` | `id`, `article_id`, `from_status`, `to_status`, `acted_by`, `reason`, `created_at` | Index `(article_id, created_at)`; append-only history. |

Allowed article statuses: `draft`, `pending_review`, `approved`, `rejected`, `published`, `unpublished`.

The state transition service must validate allowed transitions. For example, only an owner with article-update rights can save a draft; only an administrator/reviewer can approve or reject; direct publish requires the publisher’s explicit direct-publish permission; only published articles appear in public listings.

### 4.4 Voting tables and transactional integrity

| Table | Key fields | Rules / indexes |
|---|---|---|
| `voting_periods` | `id`, `name`, `starts_at`, `ends_at`, `is_active`, `created_by` | Validate `ends_at > starts_at`; use an active-period query index. |
| `votes` | `id`, `user_id`, `article_id`, `voting_period_id`, `created_at` | **Unique `(user_id, article_id)`**; indexes on `article_id` and `voting_period_id`. |
| `article_vote_summaries` (optional) | `article_id`, `vote_count`, `updated_at` | Derived/cached summary only; `votes` remains authoritative. |

Voting transaction:

1. Authenticate the user and require participant voting permission.
2. Lock/validate the configured active voting period against database/server time.
3. Verify the article exists and is published/eligible.
4. Insert one vote inside a transaction.
5. Let the unique index reject duplicates; map duplicate-key errors to `409 Conflict`.
6. Update/refresh the vote summary if used.
7. Commit, write an audit event where required, and broadcast the updated permitted result.

Never implement duplicate-vote prevention only with a pre-query. The unique constraint and transaction are mandatory safeguards against concurrent requests.

### 4.5 Comment, content, analytics, and audit tables

| Table | Key fields | Rules / indexes |
|---|---|---|
| `comments` | `id`, `article_id`, `user_id`, `body`, `status`, `removed_at`, `removed_by` | FK to article/user; index `(article_id, created_at)`; only published articles are commentable. |
| `comment_moderation_records` | `id`, `comment_id`, `action`, `reason`, `acted_by`, `created_at` | Append-only accountability record. |
| `workshops` | `id`, `title`, `description`, `starts_at`, `speaker_details`, `status` | Index start date/status. |
| `workshop_resources` | `id`, `workshop_id`, `title`, `url_or_storage_key`, `sort_order` | FK to workshop. |
| `timeline_events` | `id`, `title`, `description`, `starts_at`, `ends_at`, `sort_order`, `status` | Index dates/status. |
| `sponsors` | `id`, `name`, `logo_storage_key`, `website_url`, `sort_order`, `status` | Validate safe URL. |
| `committee_members` | `id`, `name`, `position`, `team`, `photo_storage_key`, `sort_order`, `status` | Public visibility is explicit. |
| `magazine_publications` | `id`, `title`, `description`, `cover_storage_key`, `published_at`, `status` | Index published status/date. |
| `e_magazine_files` | `id`, `magazine_publication_id`, `storage_key`, `mime_type`, `size_bytes` | Validate file type and access policy. |
| `audit_logs` | `id`, `actor_id`, `action`, `subject_type`, `subject_id`, `metadata`, `ip_hash`, `created_at` | Append-only; index `(subject_type, subject_id)` and `(actor_id, created_at)`. |

Publisher analytics should be derived from articles, votes, and comments, or maintained through read models. Every own-analytics query must include `articles.author_id = authenticated_user_id`, unless the caller has platform-wide analytics permission.

### 4.6 Example migration constraints

```php
Schema::table('votes', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->restrictOnDelete();
    $table->foreignId('article_id')->constrained()->restrictOnDelete();
    $table->unique(['user_id', 'article_id'], 'votes_one_per_user_article');
    $table->index('article_id');
});
```

Use `restrictOnDelete()` or equivalent retention rules for votes, comments, and audit-tracked records rather than deleting history accidentally.

## 5. REST API Endpoint Contract

All endpoint paths below are relative to `/api/v1`. “Auth” means a valid user bearer token. “Admin” and “Publisher” are permission-based checks, not client-side labels.

### 5.1 Authentication and profile

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| POST | `/auth/register` | Public | Create participant account. |
| POST | `/auth/login` | Public | Authenticate and issue/retrieve user session token. |
| POST | `/auth/logout` | Auth | Revoke current token/session. |
| POST | `/auth/forgot-password` | Public | Request password-reset message. |
| POST | `/auth/reset-password` | Public | Reset password using a valid one-time token. |
| GET | `/me` | Auth | Current user, roles, permissions, and capability flags. |
| PATCH | `/me` | Auth | Update permitted profile fields. |

### 5.2 Public content

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| GET | `/public/home` | Public | Aggregated homepage content. |
| GET | `/public/initiative` | Public | Initiative information. |
| GET | `/workshops` | Public | Published workshop list. |
| GET | `/workshops/{workshop}` | Public | Published workshop details/resources. |
| GET | `/timeline-events` | Public | Published timeline. |
| GET | `/sponsors` | Public | Published sponsors. |
| GET | `/committee-members` | Public | Published committee details. |
| GET | `/magazines` | Public | Published magazine list. |
| GET | `/magazines/{magazine}` | Public | Magazine and E-Magazine metadata. |
| GET | `/articles` | Public | Published articles; supports safe filters/pagination. |
| GET | `/articles/{article:slug}` | Public | Published article detail. |

### 5.3 Articles and publisher dashboard

| Method | Endpoint | Auth | Required permission | Purpose |
|---|---|---|---|---|
| GET | `/publisher/articles` | Publisher | `article.create` | List caller’s articles. |
| POST | `/publisher/articles` | Publisher | `article.create` | Create draft. |
| GET | `/publisher/articles/{article}` | Publisher | ownership/policy | View own editable article. |
| PATCH | `/publisher/articles/{article}` | Publisher | `article.update_own` | Update allowed draft/article fields. |
| POST | `/publisher/articles/{article}/submit` | Publisher | `article.submit` | Submit for review. |
| POST | `/publisher/articles/{article}/publish` | Publisher | `article.publish_own` | Direct-publish if granted. |
| POST | `/publisher/articles/{article}/attachments` | Publisher | ownership/policy | Upload permitted attachment. |
| DELETE | `/publisher/articles/{article}/attachments/{attachment}` | Publisher | ownership/policy | Remove permitted attachment. |
| GET | `/publisher/articles/{article}/analytics` | Publisher | `analytics.view_own` | Own-article analytics only. |

### 5.4 Voting, results, and comments

| Method | Endpoint | Auth | Required permission | Purpose |
|---|---|---|---|---|
| GET | `/voting/status` | Auth | participant results/view policy | Current voting window/status. |
| POST | `/articles/{article}/votes` | Auth | `participant.vote` | Cast final vote; idempotency/duplicate is enforced by DB. |
| GET | `/articles/{article}/vote-results` | Auth | `participant.view_results` | Live result data for permitted signed-in users. |
| GET | `/me/votes` | Auth | participant | Caller’s vote activity. |
| GET | `/articles/{article}/comments` | Public or Auth by policy | — | List displayable comments. |
| POST | `/articles/{article}/comments` | Auth | `participant.comment` | Create comment. |
| GET | `/me/comments` | Auth | participant | Caller’s comment activity. |

### 5.5 Administrator endpoints

| Method | Endpoint | Permission | Purpose |
|---|---|---|---|
| GET/PATCH | `/admin/users` and `/admin/users/{user}` | `user.manage` | Find/manage user accounts and roles. |
| POST/DELETE | `/admin/users/{user}/publisher-permission` | `publisher.manage` | Grant/revoke publisher capability. |
| PATCH | `/admin/users/{user}/publisher-permission` | `publisher.manage` | Configure direct-publish eligibility. |
| GET | `/admin/articles/review-queue` | `article.review` | Reviewable article list. |
| POST | `/admin/articles/{article}/approve` | `article.review` | Approve article. |
| POST | `/admin/articles/{article}/reject` | `article.review` | Reject article with reason. |
| POST | `/admin/articles/{article}/publish` | `article.publish_any` | Publish article. |
| POST | `/admin/articles/{article}/unpublish` | `article.unpublish` | Remove public visibility. |
| POST | `/admin/articles/{article}/feature` | `article.feature` | Mark/unmark featured. |
| GET/POST/PATCH/DELETE | `/admin/workshops`, `/admin/timeline-events`, `/admin/sponsors`, `/admin/committee-members`, `/admin/magazines` | `content.manage` | Manage public content. |
| GET/PATCH | `/admin/voting-periods` and `/admin/voting-periods/{period}` | `settings.manage` | Configure voting window. |
| GET | `/admin/comments` | `comment.moderate` | Moderation queue. |
| POST | `/admin/comments/{comment}/remove` | `comment.moderate` | Remove comment and record action/reason. |
| GET | `/admin/analytics` | `analytics.view_all` | Platform-wide analytics. |
| GET | `/admin/audit-logs` | `audit.view` | Auditable activity records. |

Use route model binding carefully: public article binding must resolve only `published` items; dashboard/admin bindings must then additionally enforce policy checks.

## 6. API Integration Guide

### 6.1 User authentication tokens

For the web SPA, use Laravel Sanctum (recommended) with secure, HttpOnly, `Secure`, `SameSite` cookies when the client and API are deployed on compatible first-party domains. If bearer tokens are used, issue short-lived tokens, store them safely, rotate/revoke them, and never expose them in URLs or logs.

Authenticated user request:

```http
GET /api/v1/me HTTP/1.1
Accept: application/json
Authorization: Bearer <user-access-token>
```

The client must treat `401` as an expired/invalid session and `403` as an authenticated but unauthorized request. It must not retry votes or moderation mutations automatically unless the API explicitly supports an idempotency key.

### 6.2 Server-to-server API keys

API keys are for explicitly approved service-to-service integrations (for example, a reporting tool or institutional system). They are **not** a substitute for user login and must never be embedded in the React app, browser storage, client-side environment variables, screenshots, or source control.

Recommended key format:

```text
mnv_live_<public_key_id>_<secret>
```

Only a SHA-256/HMAC hash of the full secret is stored. The plaintext key is displayed exactly once when created.

#### API-key table

| Table | Key fields | Security rules |
|---|---|---|
| `api_keys` | `id`, `public_id`, `name`, `secret_hash`, `scopes`, `created_by`, `last_used_at`, `expires_at`, `revoked_at` | Unique `public_id`; store only hash; encrypted/protected scopes metadata; index active keys. |

Suggested service scopes: `analytics.read`, `content.read`, `webhook.receive`. Do not grant broad user-management, voting, editorial approval, or database access to an API key unless a formally approved integration requires it.

#### API-key lifecycle endpoints

These endpoints are administrator-only and should require recent re-authentication/MFA where available.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/admin/api-keys` | List key metadata only—never return secrets. |
| POST | `/admin/api-keys` | Create scoped, expiring key; plaintext secret returned once. |
| GET | `/admin/api-keys/{apiKey}` | View key metadata and recent usage. |
| PATCH | `/admin/api-keys/{apiKey}` | Rename, modify permitted scopes/expiry where policy allows. |
| POST | `/admin/api-keys/{apiKey}/rotate` | Create replacement secret; revoke old secret on the defined grace schedule. |
| POST | `/admin/api-keys/{apiKey}/revoke` | Immediately revoke key and record audit entry. |

Service request example:

```http
GET /api/v1/integrations/analytics/articles HTTP/1.1
Accept: application/json
X-API-Key: mnv_live_publicid_secret
```

Alternatively use `Authorization: Bearer <service-api-key>`; choose one convention and document it in OpenAPI. Middleware must verify the hash in constant time, reject expired/revoked keys, apply scopes, record `last_used_at` asynchronously, and enforce a dedicated rate limit.

### 6.3 API-key safeguards

- Generate secrets from a cryptographically secure random source with at least 256 bits of entropy.
- Redact `Authorization`, `X-API-Key`, cookies, passwords, reset tokens, and presigned URLs in all logs/error tools.
- Rate-limit by key, route, IP, and endpoint sensitivity; return `429` with retry guidance.
- Add expiration to every new key; rotate regularly and revoke immediately if exposed.
- Audit creation, viewing metadata, rotation, scope change, and revocation.
- Restrict keys by minimum scope and optionally by IP allow-list for trusted integrations.
- Do not create an endpoint that reveals a previously issued full key.
- Store production keys only in a secrets manager/CI secret store, never in `.env` committed to Git.

### 6.4 File-upload integration

Option A (recommended): API validates metadata/authorization and returns a short-lived S3 presigned upload URL; client uploads directly, then confirms the object. Option B: API accepts multipart upload and streams to S3. Both paths must:

- Validate MIME type, file extension, size, ownership, and article/workshop association server-side.
- Generate storage keys server-side; never trust a browser-provided path.
- Scan files for malware where available; reject unsafe file types.
- Keep storage private by default and use short-lived signed download URLs when access-controlled.
- Strip/avoid unsafe active content and record file metadata.

## 7. Real-Time Voting Results

After a committed valid vote, Laravel broadcasts a minimal update event through Reverb/WebSockets to an authorized channel. A practical channel design is `private-voting-results.{periodId}`. Channel authorization must require an authenticated user with the view-results capability.

Event payload example:

```json
{
  "event": "VotingResultsUpdated",
  "period_id": "01J...",
  "article_id": "01J...",
  "vote_count": 42,
  "updated_at": "2026-11-19T12:30:00Z"
}
```

Do not broadcast voter identity, an API key, a user token, or an unpublished article’s data. Clients reconnect and refresh the authoritative result endpoint if events are missed.

## 8. Security, Validation, and Operations

### 8.1 Required middleware and protections

- HTTPS only; redirect/reject insecure production traffic.
- Authentication, permission/policy, throttling, and request-validation middleware.
- CSRF protection for cookie-authenticated state-changing requests.
- CORS allow-list for approved frontend origins only; never use wildcard origins with credentials.
- Laravel validation request objects for all mutations.
- Eloquent/query builder parameter binding; never concatenate untrusted SQL.
- Output encoding/sanitization strategy for article/comment rich text to prevent XSS.
- Security headers: CSP, HSTS, `X-Content-Type-Options`, frame policy, and referrer policy.
- Queue failed-job monitoring, structured error logging, health/readiness endpoints, and alerting.

### 8.2 Rate-limit baseline

| Endpoint class | Suggested initial limit | Notes |
|---|---:|---|
| Login/password reset | 5–10 attempts per minute per IP/account | Avoid account enumeration in errors. |
| Registration | 3–5 attempts per hour per IP | Add bot protection if abuse occurs. |
| Vote submission | 5/minute per user and IP | Duplicate protection remains database-enforced. |
| Comment creation | 5/minute per user; 30/hour | Tune against moderation capacity. |
| API-key integrations | Per key/scope agreement | Separately throttle expensive analytics endpoints. |

### 8.3 Observability and audit rules

- Attach/generate a request ID and return it on failures.
- Log route, status, latency, authenticated subject type/ID, and safe error code—not secrets or article body data unnecessarily.
- Audit user role/permission changes, publisher grants, direct-publish changes, status transitions, voting-period changes, comment removals, API-key lifecycle actions, and administrative content changes.
- Retain operational logs and audit logs according to the organization’s approved retention policy.

## 9. OpenAPI and Testing Requirements

### 9.1 OpenAPI 3.1 deliverable

Maintain `openapi.yaml` as the API contract. It must include paths, parameters, schemas, pagination metadata, validation errors, security schemes, required scopes, examples, and WebSocket-event documentation/reference. Publish interactive documentation only behind the appropriate environment access controls.

Security schemes to document:

- `userBearerAuth` or cookie/session authentication
- `serviceApiKey` (`X-API-Key` or selected Authorization convention)

### 9.2 Required automated tests

- Authentication, password reset, session/token revocation, and rate-limit tests.
- Policy tests for every protected endpoint, including participant/publisher overlap.
- Vote concurrency test proving only one `(user_id, article_id)` row is persisted.
- Voting-window boundary tests using controlled server time.
- Article workflow transition and ownership tests.
- Publisher analytics isolation test: one publisher cannot retrieve another publisher’s private analytics.
- Comment authorization/moderation and audit-record tests.
- File-type/size/ownership validation tests.
- API-key creation, one-time secret handling, scope, expiry, rotation, revocation, and redaction tests.
- OpenAPI contract tests and integration tests for success/error responses.

## 10. Implementation Order

1. Establish Laravel project, environment configuration, migrations, base response/error conventions, and OpenAPI skeleton.
2. Implement users, roles/permissions, authentication, `/me`, and route/policy middleware.
3. Implement public CMS data and read endpoints.
4. Implement article model, editorial state machine, attachments, and publisher endpoints.
5. Implement voting period, transaction-safe vote creation, vote results, and Reverb broadcasting.
6. Implement comments, moderation, and audit logging.
7. Implement publisher/platform analytics with ownership protections.
8. Implement administrator management endpoints and API-key lifecycle.
9. Add security hardening, queues, backups/monitoring integration, OpenAPI completion, and full test suite.

## 11. Backend Definition of Done

- All migrations run cleanly from a new database and support safe production rollout.
- Every protected endpoint authenticates and authorizes on the server.
- The unique vote constraint and transaction prevent duplicate votes under concurrency.
- Only published articles/content are returned through public routes.
- Publisher analytics are isolated to owned articles unless platform-wide permission is present.
- API keys are scoped, hashed, expiring, rotatable, revocable, audited, and never exposed to browser clients.
- OpenAPI 3.1 documentation, automated tests, logs/audits, error handling, and rate limits are complete.
- Security review and acceptance tests demonstrate the SRS requirements.

---

**Related documents:** SRS v1.1; Frontend Tasks  
**Next planned document:** Deployment tasks and rollout plan
