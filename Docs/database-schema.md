# Minerva Digital Platform — Database Schema Design

**Version:** 1.1 (Review decisions incorporated — finalized)
**Source documents:** SRS v1.1 · Backend, Database, and API Integration Guide v1.1
**Target:** MySQL 8.0+ (primary, per SRS §9) · MariaDB 10.5+ (fully compatible — see §1.1) · InnoDB · `utf8mb4`
**Status:** ✅ Finalized — ready for Laravel migration implementation (decisions logged in §9).

---

## 1. Assumptions and Conventions

| Convention | Decision |
|---|---|
| Storage engine | InnoDB (all tables) |
| Character set / collation | `utf8mb4` / `utf8mb4_unicode_ci` |
| Timestamps | Stored in **UTC**. `created_at` / `updated_at` as `TIMESTAMP NULL`. |
| Primary keys | Surrogate `BIGINT UNSIGNED AUTO_INCREMENT` (Laravel convention) |
| Public identifiers | `ulid CHAR(26) UNIQUE` on `users` and `articles` — opaque IDs exposed via the API; internal joins stay on BIGINT keys (per backend.md §4.1) |
| Naming | `snake_case`, plural table names, singular pivot names (`role_user`) |
| File storage | **Never stored in the database.** Only `storage_disk` + `storage_key` (S3 object keys) are stored; delivery via short-lived signed URLs |
| Status fields | `VARCHAR` + Laravel request validation (Laravel-idiomatic — confirmed decision D4). Allowed values documented per table; optional MySQL `CHECK` constraints can be added at migration time |
| Soft deletes | Not used. `users` deactivation uses `is_active`; articles use status `unpublished`; comments use status `removed`. Audit logs are append-only |
| CHECK constraints | Enforced natively on MySQL ≥ 8.0.16 |
| Charset/collation | `utf8mb4_unicode_ci` chosen deliberately — it works on **both MySQL and MariaDB**; the MySQL-8-only `utf8mb4_0900_ai_ci` was avoided |

### 1.1 MySQL / MariaDB Compatibility

This schema is intentionally portable across **both** databases, so your suggestion works without changes:

| Feature used in this schema | MySQL 8.0+ | MariaDB 10.5+ |
|---|---|---|
| InnoDB + `utf8mb4` / `utf8mb4_unicode_ci` | ✅ | ✅ |
| `BIGINT UNSIGNED`, `TIMESTAMP`, `VARCHAR`, `MEDIUMTEXT` | ✅ | ✅ |
| `JSON` column (5 tables: `speaker_details`, `scopes`, `metadata`, `value`) | ✅ native | ✅ (stored as `LONGTEXT` with auto-validation — same Laravel casts work) |
| `CHECK` constraints — only 2 in the whole schema: `voting_periods (ends_at > starts_at)` and `timeline_events (ends_at IS NULL OR ends_at >= starts_at)` | ✅ enforced (8.0.16+) | ✅ enforced (10.2+) |
| Status fields as `VARCHAR` + Laravel validation (decision D4) | ✅ | ✅ |
| FK actions `RESTRICT` / `CASCADE` / `SET NULL` | ✅ | ✅ |

**Features deliberately avoided** (they are MySQL-8-only): native `ENUM` types (already removed per D4), `utf8mb4_0900_*` collations, generated-column unique tricks (flagged as optional, not used), and any optimizer-hint syntax.

> **Recommendation:** develop and deploy on **MySQL 8.0** — it's the database named in your SRS §9, it's Laravel's default configuration, and it has native JSON + CHECK. MariaDB is a **drop-in alternative** if the university's IT team standardizes on it: **no schema changes are required**, only `.env`/config values.
>
> **Laravel notes:** use the `mysql` driver for both — Laravel auto-detects MariaDB server versions and adapts its behavior accordingly. `doctrine/dbal` is only needed *later* if you modify existing columns in migrations; the initial table creation needs only the driver.

---

## 2. Key Design Decisions

1. **RBAC, not role-name checks.** A user's capabilities come from `roles → permissions`, plus independent direct grants in `user_permissions`. Participants, publishers, and administrators are **not mutually exclusive** (SRS §3.2 note, FR-16, BR-13).
2. **Publisher grant = one row per user.** MySQL has no partial unique indexes, so `publisher_permissions` enforces "at most one active grant per user" via `UNIQUE(user_id)`; a grant is *active* while `revoked_at IS NULL`. Grant/revoke history lives in `audit_logs` (backend.md §5.2).
3. **Duplicate votes prevented by the database, not by pre-queries.** `UNIQUE (user_id, article_id)` on `votes` + transactional inserts; duplicate-key errors map to HTTP 409 (FR-35, backend.md §5.4).
4. **Votes are immutable.** No `updated_at`, no change/withdraw path; FKs `RESTRICT` so history cannot be silently destroyed (FR-32, FR-33, BR-04).
5. **Analytics are derived, not stored.** Publisher/admin analytics are computed from `articles`, `votes`, and `comments`; the optional `article_vote_summaries` table is a cache only — `votes` remains authoritative (backend.md §5.4–5.5).
6. **Content visibility is explicit.** CMS tables (workshops, timeline, sponsors, committee, magazines) carry a `status` field; only `published` rows are returned by public routes (FR-25, backend.md §12).
7. **Ownership is mandatory.** `articles.author_id` is `NOT NULL` and `RESTRICT`-protected (SRS §10 "Critical Database Constraints").
8. **Post-moderation for comments.** Comments are visible immediately on submission; administrators remove afterwards — the `pending` status does not exist (decision D3).
9. **University eligibility via lookup table.** A `universities` lookup table with a nullable `users.university_id` FK prevents spelling mismatches; profile fields live directly on `users` while their count stays below the 6–7-field threshold for a separate profile table (decision D1).
10. **`platform_settings` included** for FR-10 dashboard-managed site content and configuration (decision D2).

---

## 3. SRS Entity → Table Mapping

| SRS §10 Entity | Table(s) | Section |
|---|---|---|
| Users | `users`, `password_reset_tokens` | 4.1 |
| Universities (eligibility lookup — decision D1) | `universities` | 4.1 |
| Roles | `roles`, `role_user` | 4.1 |
| Permissions | `permissions`, `permission_role`, `user_permissions` | 4.1 |
| Role assignments | `role_user` | 4.1 |
| Publisher permissions | `publisher_permissions` | 4.1 |
| Articles | `articles` | 4.2 |
| Article categories | `article_categories` | 4.2 |
| Article attachments | `article_attachments` | 4.2 |
| Votes | `votes`, `voting_periods`, `article_vote_summaries` | 4.3 |
| Comments | `comments` | 4.4 |
| Comment moderation records | `comment_moderation_records` | 4.4 |
| Workshops | `workshops` | 4.5 |
| Workshop resources | `workshop_resources` | 4.5 |
| Events and timeline entries | `timeline_events` | 4.5 |
| Sponsors | `sponsors` | 4.5 |
| Organizing committee members | `committee_members` | 4.5 |
| Magazine publications | `magazine_publications` | 4.5 |
| E-Magazine files | `e_magazine_files` | 4.5 |
| Article analytics / engagement records | `article_vote_summaries` + derived queries | 4.3 |
| Audit logs | `audit_logs` | 4.7 |
| *(backend.md §7.2)* Service API keys | `api_keys` | 4.6 |
| *(framework-managed)* Sanctum tokens | `personal_access_tokens` | 4.8 |

---

## 4. Schema by Module

### 4.1 Identity & Access

#### 4.1.1 `users` — FR-11, FR-12, FR-14, FR-15, FR-16

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `ulid` | `CHAR(26)` | NO | — | UNIQUE | Opaque public ID for API URLs |
| `name` | `VARCHAR(255)` | NO | — | | |
| `email` | `VARCHAR(255)` | NO | — | UNIQUE | Stored lowercase/normalized |
| `email_verified_at` | `TIMESTAMP` | YES | NULL | | |
| `password` | `VARCHAR(255)` | NO | — | | One-way hash only (bcrypt/argon2) |
| `university_id` | `BIGINT UNSIGNED` | YES | NULL | FK → `universities.id` | ON DELETE RESTRICT; nullable = not provided yet |
| `faculty` | `VARCHAR(255)` | YES | NULL | | Free text |
| `phone` | `VARCHAR(20)` | YES | NULL | | Format validated app-side; not unique |
| `remember_token` | `VARCHAR(100)` | YES | NULL | | Laravel standard |
| `is_active` | `TINYINT(1)` | NO | `1` | | Deactivation flag; users are never hard-deleted |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

> Profile fields (`university_id`, `faculty`, `phone`) live **directly on `users`** to avoid extra JOINs on core eligibility checks — currently 3 fields, below the 6–7-field threshold that would justify a separate profile table (decision D1).

#### 4.1.2 `password_reset_tokens` — FR-13

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `email` | `VARCHAR(255)` | NO | PK | |
| `token` | `VARCHAR(255)` | NO | UNIQUE | One-time reset token (hashed in Laravel) |
| `created_at` | `TIMESTAMP` | YES | | |

#### 4.1.3 `roles`

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PK | |
| `name` | `VARCHAR(100)` | NO | UNIQUE | Seeded: `participant`, `publisher`, `administrator` |
| `description` | `VARCHAR(255)` | YES | | |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | | |

#### 4.1.4 `permissions`

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PK | |
| `name` | `VARCHAR(100)` | NO | UNIQUE | Dot notation, e.g. `participant.vote` |
| `description` | `VARCHAR(255)` | YES | | |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | | |

#### 4.1.5 `role_user` — role assignments

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `user_id` | `BIGINT UNSIGNED` | NO | PK(1), FK → `users.id` | ON DELETE CASCADE |
| `role_id` | `BIGINT UNSIGNED` | NO | PK(2), FK → `roles.id` | ON DELETE CASCADE |

- Composite PK `(user_id, role_id)` = unique assignment.
- Secondary index on `role_id` for reverse lookup.
- No timestamps — assignment events are captured in `audit_logs`.

#### 4.1.6 `permission_role`

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `role_id` | `BIGINT UNSIGNED` | NO | PK(1), FK → `roles.id` | ON DELETE CASCADE |
| `permission_id` | `BIGINT UNSIGNED` | NO | PK(2), FK → `permissions.id` | ON DELETE CASCADE |

- Secondary index on `permission_id` for reverse lookup.

#### 4.1.7 `user_permissions` — direct/independent grants

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `user_id` | `BIGINT UNSIGNED` | NO | — | FK → `users.id` | ON DELETE CASCADE |
| `permission_id` | `BIGINT UNSIGNED` | NO | — | FK → `permissions.id` | ON DELETE CASCADE |
| `granted_by` | `BIGINT UNSIGNED` | YES | NULL | FK → `users.id` | Attribution; ON DELETE SET NULL |
| `expires_at` | `TIMESTAMP` | YES | NULL | | Optional time-limited grant |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- `UNIQUE (user_id, permission_id)` — one grant per user per permission.
- Index on `permission_id`.

#### 4.1.8 `publisher_permissions` — FR-18, FR-19, BR-10

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `user_id` | `BIGINT UNSIGNED` | NO | — | FK → `users.id`, UNIQUE | ON DELETE CASCADE |
| `can_publish_directly` | `TINYINT(1)` | NO | `0` | | Direct-publish eligibility (BR-10) |
| `granted_by` | `BIGINT UNSIGNED` | YES | NULL | FK → `users.id` | ON DELETE SET NULL |
| `granted_at` | `TIMESTAMP` | NO | — | | |
| `revoked_at` | `TIMESTAMP` | YES | NULL | | Active grant ⇔ `revoked_at IS NULL` |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- **`UNIQUE(user_id)`** is the MySQL-compatible "partial unique" strategy: at most one row (active or revoked) per user. Re-granting after revocation updates the same row.
- Grant/revoke/config-change history is recorded in `audit_logs`.

#### 4.1.9 `universities` — eligibility lookup (decision D1)

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `name` | `VARCHAR(200)` | NO | — | UNIQUE | One canonical spelling per university |
| `short_name` | `VARCHAR(50)` | YES | NULL | | e.g. `USJ` |
| `is_active` | `TINYINT(1)` | NO | `1` | | Deactivate rather than delete |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Seeded with the approved list of participating universities via a controlled seeder.
- Referenced by `users.university_id` (nullable — not all users provide one); `ON DELETE RESTRICT` — rows are deactivated instead of deleted.
- Purpose: guarantees consistent university reporting/eligibility checks across the inter-university competition.

---

### 4.2 Articles & Editorial

#### 4.2.1 `article_categories` — FR-26

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PK | |
| `name` | `VARCHAR(120)` | NO | | |
| `slug` | `VARCHAR(140)` | NO | UNIQUE | |
| `description` | `VARCHAR(500)` | YES | | |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | | |

#### 4.2.2 `articles` — FR-17…FR-27

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `ulid` | `CHAR(26)` | NO | — | UNIQUE | Opaque public ID |
| `author_id` | `BIGINT UNSIGNED` | NO | — | FK → `users.id` | ON DELETE RESTRICT — ownership is mandatory |
| `category_id` | `BIGINT UNSIGNED` | NO | — | FK → `article_categories.id` | ON DELETE RESTRICT |
| `title` | `VARCHAR(255)` | NO | — | | |
| `slug` | `VARCHAR(255)` | NO | — | UNIQUE | Used in public URLs (`{article:slug}`) |
| `abstract` | `TEXT` | NO | — | | Abstract / introduction |
| `body` | `MEDIUMTEXT` | NO | — | | Full article body |
| `status` | `VARCHAR(20)` | NO | `draft` | | Editorial workflow (FR-22); allowed: `draft, pending_review, approved, rejected, published, unpublished` (Laravel validation — D4) |
| `is_featured` | `TINYINT(1)` | NO | `0` | | FR-24 |
| `published_at` | `TIMESTAMP` | YES | NULL | | Set on publish |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

**Indexes:** `(status, published_at)` · `(author_id, status)` · `(category_id)` — plus UNIQUE `slug`, `ulid`.

**Allowed transitions (service-enforced, logged in `article_status_history`):** only an owner with `article.update_own` saves drafts; only reviewers approve/reject; direct publish requires `can_publish_directly`; only `published` rows are publicly visible (FR-25).

#### 4.2.3 `article_attachments`

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `article_id` | `BIGINT UNSIGNED` | NO | — | FK → `articles.id` | ON DELETE CASCADE |
| `storage_disk` | `VARCHAR(32)` | NO | `s3` | | |
| `storage_key` | `VARCHAR(512)` | NO | — | | S3 object key; never a public URL |
| `original_name` | `VARCHAR(255)` | NO | — | | |
| `mime_type` | `VARCHAR(127)` | NO | — | | Validated server-side |
| `size_bytes` | `BIGINT UNSIGNED` | NO | — | | |
| `kind` | `VARCHAR(20)` | NO | `document` | | Allowed: `image, document, other` (default kept — Q6) |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Index on `article_id`.

#### 4.2.4 `article_status_history` — FR-22, FR-27 (append-only)

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PK | |
| `article_id` | `BIGINT UNSIGNED` | NO | FK → `articles.id` | ON DELETE CASCADE |
| `from_status` | `VARCHAR(20)` | YES | | NULL on initial creation; same allowed values as `articles.status` |
| `to_status` | `VARCHAR(20)` | NO | | Same allowed values as `articles.status` |
| `acted_by` | `BIGINT UNSIGNED` | YES | FK → `users.id` | ON DELETE SET NULL |
| `reason` | `VARCHAR(500)` | YES | | e.g. rejection reason |
| `created_at` | `TIMESTAMP` | YES | | |

- Index `(article_id, created_at)`. **Append-only** — no `updated_at`; rows are never modified.

---

### 4.3 Voting

#### 4.3.1 `voting_periods` — FR-40, FR-41

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `name` | `VARCHAR(150)` | NO | — | | |
| `starts_at` | `TIMESTAMP` | NO | — | | |
| `ends_at` | `TIMESTAMP` | NO | — | | `CHECK (ends_at > starts_at)` |
| `is_active` | `TINYINT(1)` | NO | `0` | | |
| `created_by` | `BIGINT UNSIGNED` | YES | NULL | FK → `users.id` | ON DELETE SET NULL |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Index `(is_active, starts_at, ends_at)` for the active-period lookup.
- "Only one active period" is enforced by the application service; an optional MySQL trick is a generated column `active_flag = IF(is_active = 1, 1, NULL)` with a `UNIQUE` index — decision at implementation time.

#### 4.3.2 `votes` — FR-28…FR-41, BR-01…BR-06 ⚠️ critical table

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PK | |
| `user_id` | `BIGINT UNSIGNED` | NO | FK → `users.id` | ON DELETE RESTRICT |
| `article_id` | `BIGINT UNSIGNED` | NO | FK → `articles.id` | ON DELETE RESTRICT |
| `voting_period_id` | `BIGINT UNSIGNED` | NO | FK → `voting_periods.id` | ON DELETE RESTRICT |
| `created_at` | `TIMESTAMP` | YES | | Vote timestamp; **no `updated_at` — votes are final** |

**Constraints (the heart of the voting rules):**

- 🔒 **`UNIQUE (user_id, article_id)`** — named `votes_one_per_user_article`. Guarantees FR-35 (one vote per participant per article) under concurrent requests; duplicates map to `409 Conflict`.
- Indexes on `article_id` and `voting_period_id` for result aggregation.
- All FKs `RESTRICT` — vote history can never be silently destroyed.

#### 4.3.3 `article_vote_summaries` *(optional cache)*

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `article_id` | `BIGINT UNSIGNED` | NO | — | PK, FK → `articles.id` | ON DELETE CASCADE |
| `vote_count` | `INT UNSIGNED` | NO | `0` | | Derived cache — `votes` stays authoritative |
| `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Refreshed in the same transaction as the vote insert (or from the `VoteRecorded` event). Kept by default (review: not raised — see §9).

---

### 4.4 Comments & Moderation

#### 4.4.1 `comments` — FR-42…FR-49, BR-07

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `article_id` | `BIGINT UNSIGNED` | NO | — | FK → `articles.id` | ON DELETE RESTRICT; only `published` articles are commentable (app-enforced) |
| `user_id` | `BIGINT UNSIGNED` | NO | — | FK → `users.id` | ON DELETE RESTRICT |
| `body` | `TEXT` | NO | — | | Sanitized against XSS |
| `status` | `VARCHAR(20)` | NO | `visible` | | Post-moderation confirmed (D3): visible immediately; allowed: `visible, removed` |
| `removed_at` | `TIMESTAMP` | YES | NULL | | |
| `removed_by` | `BIGINT UNSIGNED` | YES | NULL | FK → `users.id` | ON DELETE SET NULL |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Indexes: `(article_id, created_at)` · `(user_id, created_at)` · `(status, created_at)` (moderation queue).
- Removal is a **soft status change** — comment rows are preserved for accountability (FR-49); only moderation records ever reference removals.

#### 4.4.2 `comment_moderation_records` — FR-45…FR-49 (append-only)

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PK | |
| `comment_id` | `BIGINT UNSIGNED` | NO | FK → `comments.id` | ON DELETE CASCADE |
| `action` | `VARCHAR(20)` | NO | | | Allowed: `removed, restored` |
| `reason` | `VARCHAR(500)` | YES | | |
| `acted_by` | `BIGINT UNSIGNED` | YES | FK → `users.id` | ON DELETE SET NULL |
| `created_at` | `TIMESTAMP` | YES | | |

- Indexes: `(comment_id)` · `(acted_by, created_at)`. **Append-only.**

---

### 4.5 Content Management (public website)

All content tables share `status VARCHAR(20) DEFAULT 'draft'` (allowed values `draft, published, archived`, Laravel-validated — D4) and are only exposed publicly when `status = 'published'`.

#### 4.5.1 `workshops` — FR-03

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PK | |
| `title` | `VARCHAR(200)` | NO | | |
| `description` | `TEXT` | NO | | |
| `starts_at` | `TIMESTAMP` | NO | | |
| `ends_at` | `TIMESTAMP` | YES | | |
| `speaker_details` | `JSON` | YES | | Array of `{name, title, affiliation, photo_url}` (default kept — review: not raised, see §9) |
| `status` | `VARCHAR(20)` | NO | `draft` | Allowed: `draft, published, archived` |
| `sort_order` | `SMALLINT` | NO | Default `0` | |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | | |

- Index `(status, starts_at)`.

#### 4.5.2 `workshop_resources` — FR-03

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `workshop_id` | `BIGINT UNSIGNED` | NO | — | FK → `workshops.id` | ON DELETE CASCADE |
| `title` | `VARCHAR(200)` | NO | — | | |
| `kind` | `VARCHAR(10)` | NO | `link` | | Allowed: `link, file` |
| `url_or_storage_key` | `VARCHAR(512)` | NO | — | | External URL or S3 key |
| `mime_type` | `VARCHAR(127)` | YES | NULL | | Files only |
| `size_bytes` | `BIGINT UNSIGNED` | YES | NULL | | Files only |
| `sort_order` | `SMALLINT` | NO | `0` | | |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Index `(workshop_id, sort_order)`.

#### 4.5.3 `timeline_events` — FR-05

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `title` | `VARCHAR(200)` | NO | — | | |
| `description` | `TEXT` | YES | NULL | | |
| `starts_at` | `TIMESTAMP` | NO | — | | |
| `ends_at` | `TIMESTAMP` | YES | NULL | | `CHECK (ends_at IS NULL OR ends_at >= starts_at)` |
| `sort_order` | `SMALLINT` | NO | `0` | | |
| `status` | `VARCHAR(20)` | NO | `draft` | | Allowed: `draft, published, archived` |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Index `(status, starts_at)`.

#### 4.5.4 `sponsors` — FR-06

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `name` | `VARCHAR(200)` | NO | — | | |
| `website_url` | `VARCHAR(500)` | YES | NULL | | Validated `http(s)` scheme |
| `logo_storage_key` | `VARCHAR(512)` | YES | NULL | | S3 key |
| `sort_order` | `SMALLINT` | NO | `0` | | |
| `status` | `VARCHAR(20)` | NO | `draft` | | Allowed: `draft, published, archived` |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Index `(status, sort_order)`.

#### 4.5.5 `committee_members` — FR-07

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `name` | `VARCHAR(200)` | NO | — | | |
| `position` | `VARCHAR(150)` | NO | — | | |
| `team` | `VARCHAR(150)` | YES | NULL | | e.g. "Design Team" |
| `photo_storage_key` | `VARCHAR(512)` | YES | NULL | | S3 key |
| `sort_order` | `SMALLINT` | NO | `0` | | |
| `status` | `VARCHAR(20)` | NO | `draft` | | Public visibility is explicit; allowed: `draft, published, archived` |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Indexes: `(status, sort_order)` · `(team)`.

#### 4.5.6 `magazine_publications` — FR-08, FR-09

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `title` | `VARCHAR(200)` | NO | — | | |
| `description` | `TEXT` | YES | NULL | | |
| `cover_storage_key` | `VARCHAR(512)` | YES | NULL | | S3 key |
| `published_at` | `TIMESTAMP` | YES | NULL | | |
| `status` | `VARCHAR(20)` | NO | `draft` | | Allowed: `draft, published, archived` |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Index `(status, published_at)`.

#### 4.5.7 `e_magazine_files` — FR-09

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `magazine_publication_id` | `BIGINT UNSIGNED` | NO | — | FK → `magazine_publications.id` | ON DELETE CASCADE |
| `storage_key` | `VARCHAR(512)` | NO | — | | Private S3 object + signed delivery |
| `mime_type` | `VARCHAR(127)` | NO | — | | Validated server-side |
| `size_bytes` | `BIGINT UNSIGNED` | NO | — | | |
| `sort_order` | `SMALLINT` | NO | `0` | | |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Index `(magazine_publication_id)`.

---

### 4.6 Integrations

#### 4.6.1 `api_keys` — backend.md §7.2

| Column | Type | Null | Default | Keys | Notes |
|---|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | auto | PK | |
| `public_id` | `VARCHAR(64)` | NO | — | UNIQUE | Embedded in `mnv_live_<public_id>_<secret>` |
| `name` | `VARCHAR(150)` | NO | — | | |
| `secret_hash` | `CHAR(64)` | NO | — | | SHA-256 hex; **plaintext never stored or re-shown** |
| `scopes` | `JSON` | NO | — | | e.g. `["analytics.read","content.read"]` |
| `created_by` | `BIGINT UNSIGNED` | YES | NULL | FK → `users.id` | ON DELETE SET NULL |
| `last_used_at` | `TIMESTAMP` | YES | NULL | | Updated asynchronously |
| `expires_at` | `TIMESTAMP` | YES | NULL | | Every new key gets an expiry |
| `revoked_at` | `TIMESTAMP` | YES | NULL | | Immediate revocation |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | NULL | | |

- Indexes: `(expires_at)`, `(created_by)`. Lookup path: parse `public_id` → unique index → constant-time hash comparison.

---

### 4.7 Audit

#### 4.7.1 `audit_logs` — SRS §8 Security, backend.md §9.3 (append-only)

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PK | |
| `actor_id` | `BIGINT UNSIGNED` | YES | FK → `users.id` | ON DELETE SET NULL; NULL = system action |
| `action` | `VARCHAR(100)` | NO | | e.g. `publisher_permission.granted`, `comment.removed` |
| `subject_type` | `VARCHAR(100)` | YES | | e.g. `article`, `user`, `api_key` |
| `subject_id` | `BIGINT UNSIGNED` | YES | | Polymorphic subject |
| `metadata` | `JSON` | YES | | Safe, redacted context (never secrets) |
| `ip_hash` | `CHAR(64)` | YES | | Hashed client IP — never raw IPs |
| `created_at` | `TIMESTAMP` | YES | | |

- Indexes: `(subject_type, subject_id)` · `(actor_id, created_at)` · `(action, created_at)`.
- **Append-only.** No `updated_at`. At deployment, the application DB user should have no `UPDATE`/`DELETE` privilege on this table.

---

### 4.8 Framework-Managed and Additional Tables

#### `personal_access_tokens` (created automatically by Laravel Sanctum — do not hand-write)

Standard Sanctum structure: `id`, `tokenable_type`, `tokenable_id`, `name`, `token` (UNIQUE), `abilities`, `last_used_at`, `expires_at`, timestamps.

#### `platform_settings` *(confirmed — decision D2, FR-10)*

| Column | Type | Null | Keys | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NO | PK | |
| `setting_group` | `VARCHAR(100)` | NO | UNIQUE(1) with key | e.g. `competition`, `homepage` |
| `setting_key` | `VARCHAR(150)` | NO | UNIQUE(2) with group | |
| `value` | `JSON` | YES | | |
| `updated_by` | `BIGINT UNSIGNED` | YES | FK → `users.id` | ON DELETE SET NULL |
| `created_at` / `updated_at` | `TIMESTAMP` | YES | | |

Covers FR-10 ("manage public website content without modifying source code") — e.g. homepage hero text, initiative page copy, competition rules text.

---

## 5. Critical Constraint Summary

| # | Constraint | Table | Enforces |
|---|---|---|---|
| 1 | 🔒 `UNIQUE (user_id, article_id)` | `votes` | One final vote per participant per article (FR-35, BR-03) — concurrent-safe |
| 2 | `UNIQUE (email)` | `users` | One account per email (FR-11) |
| 3 | `UNIQUE (slug)` | `articles` | Stable public URLs |
| 4 | `UNIQUE (user_id)` | `publisher_permissions` | At most one publisher grant per user (active ⇔ `revoked_at IS NULL`) |
| 5 | `UNIQUE (user_id, permission_id)` | `user_permissions` | No duplicate direct grants |
| 6 | `UNIQUE (user_id, role_id)` / `(role_id, permission_id)` | pivots | Clean RBAC assignments |
| 7 | `author_id NOT NULL` + RESTRICT | `articles` | Identifiable, non-orphanable ownership |
| 8 | `CHECK (ends_at > starts_at)` | `voting_periods` | Valid voting windows (FR-40) |
| 9 | `RESTRICT` on votes/comments FKs | `votes`, `comments` | Voting/comment history retention |
| 10 | Append-only | `audit_logs`, `article_status_history`, `comment_moderation_records` | Accountability (SRS §8, FR-49) |
| 11 | `UNIQUE (name)` | `universities` | One canonical spelling per university — no eligibility mismatches (D1) |

## 6. Foreign Key Action Policy

| FK group | Action | Rationale |
|---|---|---|
| `votes.*`, `comments.user_id`, `comments.article_id`, `articles.author_id`, `articles.category_id` | **RESTRICT** | Immutable/owned records; deletion is blocked, not cascaded |
| `users.university_id` → `universities.id` | **RESTRICT** | Lookup rows are deactivated (`is_active = 0`), never deleted while referenced |
| `article_attachments`, `article_status_history`, `workshop_resources`, `e_magazine_files`, `article_vote_summaries` → parent | **CASCADE** | Pure child rows of their parent entity |
| `role_user`, `permission_role` | **CASCADE** | Join rows have no meaning alone |
| `user_permissions.user_id/permission_id` | **CASCADE** | Grants die with their subject |
| Attribution columns (`granted_by`, `acted_by`, `removed_by`, `created_by`, `actor_id`) | **SET NULL** (nullable) | Records survive even if a user is ever purged |
| Users themselves | Never hard-deleted | `is_active = 0` deactivation only |

---

## 7. Recommended Seed Data (approved reference data only)

- **Roles:** `participant`, `publisher`, `administrator`
- **Permissions (19, from backend.md §4.3):** `participant.vote`, `participant.comment`, `participant.view_results`, `article.create`, `article.submit`, `article.update_own`, `article.publish_own`, `article.review`, `article.publish_any`, `article.unpublish`, `article.feature`, `comment.moderate`, `content.manage`, `user.manage`, `publisher.manage`, `analytics.view_own`, `analytics.view_all`, `settings.manage`, `audit.view`
- **Role → permission mapping:**
  - `participant` → the three `participant.*` permissions
  - `publisher` → `article.create`, `article.submit`, `article.update_own`, `article.publish_own`*, `analytics.view_own` (*direct publishing still gated by `publisher_permissions.can_publish_directly`*)
  - `administrator` → all permissions
- **Universities:** approved lookup list for the inter-university competition (decision D1) — managed via a controlled seeder, never typed free-form by users.
- **Initial administrator account** (created via seeder, credentials from secrets — never committed).
- Optionally a starter set of `article_categories`.

---

## 8. Entity-Relationship Diagram

> Render this file in any Mermaid-capable viewer (GitHub, GitLab, VS Code Markdown preview, Obsidian). A rendered `erd.svg` is generated alongside this document.

```mermaid
erDiagram
    UNIVERSITIES {
        bigint id PK
        varchar name
        varchar short_name
        tinyint is_active
    }
    USERS {
        bigint id PK
        char ulid UK
        varchar email UK
        varchar password
        bigint university_id FK
        timestamp email_verified_at
        tinyint is_active
    }
    PASSWORD_RESET_TOKENS {
        varchar email PK
        varchar token UK
        timestamp created_at
    }
    ROLES {
        bigint id PK
        varchar name UK
    }
    PERMISSIONS {
        bigint id PK
        varchar name UK
    }
    ROLE_USER {
        bigint user_id PK_FK
        bigint role_id PK_FK
    }
    PERMISSION_ROLE {
        bigint role_id PK_FK
        bigint permission_id PK_FK
    }
    USER_PERMISSIONS {
        bigint id PK
        bigint user_id FK
        bigint permission_id FK
        bigint granted_by FK
        timestamp expires_at
    }
    PUBLISHER_PERMISSIONS {
        bigint id PK
        bigint user_id UK_FK
        tinyint can_publish_directly
        timestamp granted_at
        timestamp revoked_at
    }
    ARTICLE_CATEGORIES {
        bigint id PK
        varchar name
        varchar slug UK
    }
    ARTICLES {
        bigint id PK
        char ulid UK
        varchar slug UK
        bigint author_id FK
        bigint category_id FK
        varchar status
        tinyint is_featured
        timestamp published_at
    }
    ARTICLE_ATTACHMENTS {
        bigint id PK
        bigint article_id FK
        varchar storage_key
        varchar mime_type
        bigint size_bytes
        varchar kind
    }
    ARTICLE_STATUS_HISTORY {
        bigint id PK
        bigint article_id FK
        varchar from_status
        varchar to_status
        bigint acted_by FK
        timestamp created_at
    }
    VOTING_PERIODS {
        bigint id PK
        varchar name
        timestamp starts_at
        timestamp ends_at
        tinyint is_active
        bigint created_by FK
    }
    VOTES {
        bigint id PK
        bigint user_id FK
        bigint article_id FK
        bigint voting_period_id FK
        timestamp created_at
    }
    ARTICLE_VOTE_SUMMARIES {
        bigint article_id PK_FK
        int vote_count
        timestamp updated_at
    }
    COMMENTS {
        bigint id PK
        bigint article_id FK
        bigint user_id FK
        text body
        varchar status
        bigint removed_by FK
        timestamp removed_at
    }
    COMMENT_MODERATION_RECORDS {
        bigint id PK
        bigint comment_id FK
        varchar action
        varchar reason
        bigint acted_by FK
        timestamp created_at
    }
    WORKSHOPS {
        bigint id PK
        varchar title
        timestamp starts_at
        json speaker_details
        varchar status
    }
    WORKSHOP_RESOURCES {
        bigint id PK
        bigint workshop_id FK
        varchar title
        varchar url_or_storage_key
        smallint sort_order
    }
    TIMELINE_EVENTS {
        bigint id PK
        varchar title
        timestamp starts_at
        timestamp ends_at
        smallint sort_order
        varchar status
    }
    SPONSORS {
        bigint id PK
        varchar name
        varchar logo_storage_key
        varchar website_url
        smallint sort_order
        varchar status
    }
    COMMITTEE_MEMBERS {
        bigint id PK
        varchar name
        varchar position
        varchar team
        varchar photo_storage_key
        smallint sort_order
        varchar status
    }
    MAGAZINE_PUBLICATIONS {
        bigint id PK
        varchar title
        varchar cover_storage_key
        timestamp published_at
        varchar status
    }
    E_MAGAZINE_FILES {
        bigint id PK
        bigint magazine_publication_id FK
        varchar storage_key
        varchar mime_type
        bigint size_bytes
    }
    API_KEYS {
        bigint id PK
        varchar public_id UK
        char secret_hash
        json scopes
        timestamp expires_at
        timestamp revoked_at
    }
    AUDIT_LOGS {
        bigint id PK
        bigint actor_id FK
        varchar action
        varchar subject_type
        bigint subject_id
        json metadata
        char ip_hash
        timestamp created_at
    }
    PLATFORM_SETTINGS {
        bigint id PK
        varchar setting_group
        varchar setting_key
        json value
        bigint updated_by FK
    }

    USERS ||--o{ ROLE_USER : "assigned"
    ROLES ||--o{ ROLE_USER : "holds"
    ROLES ||--o{ PERMISSION_ROLE : "bundles"
    PERMISSIONS ||--o{ PERMISSION_ROLE : "member-of"
    USERS ||--o{ USER_PERMISSIONS : "receives"
    USERS |o--o{ USER_PERMISSIONS : "grants"
    PERMISSIONS ||--o{ USER_PERMISSIONS : "directly-granted"
    USERS ||--o| PUBLISHER_PERMISSIONS : "publisher-grant"
    USERS |o--o| PUBLISHER_PERMISSIONS : "granted-by"
    USERS ||--o{ ARTICLES : "authors"
    ARTICLE_CATEGORIES ||--o{ ARTICLES : "categorizes"
    ARTICLES ||--o{ ARTICLE_ATTACHMENTS : "has"
    ARTICLES ||--o{ ARTICLE_STATUS_HISTORY : "tracks"
    USERS |o--o{ ARTICLE_STATUS_HISTORY : "acted-by"
    VOTING_PERIODS ||--o{ VOTES : "window"
    USERS ||--o{ VOTES : "casts"
    ARTICLES ||--o{ VOTES : "receives"
    ARTICLES ||--o| ARTICLE_VOTE_SUMMARIES : "cached-count"
    ARTICLES ||--o{ COMMENTS : "has"
    USERS ||--o{ COMMENTS : "writes"
    USERS |o--o{ COMMENTS : "removed-by"
    COMMENTS ||--o{ COMMENT_MODERATION_RECORDS : "moderated-via"
    USERS |o--o{ COMMENT_MODERATION_RECORDS : "acted-by"
    WORKSHOPS ||--o{ WORKSHOP_RESOURCES : "provides"
    MAGAZINE_PUBLICATIONS ||--o{ E_MAGAZINE_FILES : "contains"
    USERS |o--o{ API_KEYS : "created-by"
    USERS |o--o{ AUDIT_LOGS : "actor"
    USERS |o--o{ VOTING_PERIODS : "created-by"
    USERS ||--o{ PASSWORD_RESET_TOKENS : "resets"
    UNIVERSITIES |o--o{ USERS : "affiliates"
```

---

## 9. Review Decisions (Resolved)

**Reviewed:** 2026-11-20 — all raised questions decided; schema finalized at v1.1.

| # | Topic | Decision |
|---|---|---|
| D1 | Participant profile fields | Added **directly to `users`**: `university_id` (nullable FK to a new **`universities` lookup table** — prevents spelling mismatches), `faculty`, `phone`. A separate profile table is deferred unless profile fields exceed 6–7. |
| D2 | `platform_settings` | **Included** — supports FR-10 dashboard-managed content/configuration. |
| D3 | Comment moderation policy | **Post-moderation** — comments are visible immediately on submission; administrators remove afterwards. The `pending` status was removed from the comment status set. |
| D4 | Status column typing | **Laravel-idiomatic `VARCHAR` + request validation** instead of native MySQL `ENUM`. Allowed values are documented per table; optional `CHECK` constraints can be added at migration time if desired. |
| — | Defaults kept (not raised) | ULID public IDs on `users` + `articles` only · workshop speakers as JSON · attachment kinds `image/document/other` · `article_vote_summaries` cache kept · CMS status set `draft/published/archived`. |

---

## 10. Next Steps

1. ~~Confirm/answer the open questions~~ — done (§9); schema v1.1 is ready to implement.
2. Create the Laravel project and convert each §4 table block into module-prefixed migrations (Identity → Content → Articles → Voting → Comments → Audit → Integrations order, per backend.md §11).
3. Generate seeders for roles/permissions/initial admin.
4. Export the ERD to SVG/PNG for the SRS documentation pack.
5. Write the vote-concurrency feature test early to prove the `UNIQUE (user_id, article_id)` constraint (backend.md §10.2).

---

**Related documents:** SRS v1.1 · backend.md · *(planned)* Laravel migrations
