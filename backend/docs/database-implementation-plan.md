# Database Implementation Plan

**Source of truth:** `Docs/database-schema.md` (schema v1.1, finalized)
**Target:** MySQL 8.0+ / MariaDB 10.5+ · InnoDB · `utf8mb4` / `utf8mb4_unicode_ci`
**Status:** approved — see §2 for the decisions this plan implements.

---

## 1. Current state vs. the schema document

The backend already carries a working modular-monolith schema. This table is
the authoritative gap analysis.

### 1.1 Tables that already exist

| Doc § | Table | Migration | State |
|---|---|---|---|
| 4.1.1 | `users` | Identity `…000001_create_users_table` | ✅ exists (soft deletes to be removed) |
| 4.1.2 | `password_reset_tokens` | Identity `…000004_create_identity_security_tables` | ✅ exists |
| 4.1.3 | `roles` | Identity `…000003_create_access_control_tables` | ✅ exists |
| 4.1.4 | `permissions` | Identity `…000003…` | ✅ exists |
| 4.1.5 | `role_user` | Identity `…000003…` | ✅ exists |
| 4.1.6 | `permission_role` | Identity `…000003…` | ✅ exists |
| 4.1.7 | `user_permissions` | Identity `…000003…` | ✅ exists |
| 4.1.8 | `publisher_permissions` | Identity `…000003…` | ✅ exists (constraint to be made portable) |
| 4.1.9 | `universities` | Identity `…000000_create_universities_table` | ✅ exists |
| 4.2.1 | `article_categories` | Articles `…000020…` | ✅ exists (column widths to match doc) |
| 4.2.2 | `articles` | Articles `…000021…` | ✅ exists (soft deletes to be removed) |
| 4.2.3 | `article_attachments` | Articles `…000022…` | ✅ exists (`storage_disk` default to be added) |
| 4.2.4 | `article_status_history` | Articles `…000023…` | ✅ exists |
| 4.3.1 | `voting_periods` | Voting `…000030…` | ✅ exists (CHECK constraint to be added) |
| 4.3.2 | `votes` | Voting `…000031…` | ✅ exists |
| 4.3.3 | `article_vote_summaries` | Voting `…000032…` | ✅ exists |
| 4.4.1 | `comments` | Comments `…000030…` | ✅ exists |
| 4.4.2 | `comment_moderation_records` | Comments `…000031…` | ✅ exists |
| 4.7.1 | `audit_logs` | Audit `…000010…` | ✅ exists (`actor_id` FK to be added) |
| 4.8 | `personal_access_tokens` | Identity `…000004…` | ✅ exists (hand-written Sanctum table) |
| — | `oauth_accounts`, `staff_invitations`, `login_attempts`, `sessions` | Identity `…000002`, `…000004` | ✅ implementation extras (Google OAuth, staff onboarding) |

### 1.2 Tables that are missing — this is the work

| Doc § | Table | New module | Notes |
|---|---|---|---|
| 4.5.1 | `workshops` | `Content` | JSON `speaker_details`, `status`, `sort_order` |
| 4.5.2 | `workshop_resources` | `Content` | child of `workshops`, CASCADE |
| 4.5.3 | `timeline_events` | `Content` | `CHECK (ends_at IS NULL OR ends_at >= starts_at)` |
| 4.5.4 | `sponsors` | `Content` | `status`, `sort_order` |
| 4.5.5 | `committee_members` | `Content` | `status`, `sort_order`, `team` index |
| 4.5.6 | `magazine_publications` | `Content` | `status`, `published_at` |
| 4.5.7 | `e_magazine_files` | `Content` | child of `magazine_publications`, CASCADE |
| 4.6.1 | `api_keys` | `Integrations` | `public_id` UNIQUE, `secret_hash` SHA-256, JSON `scopes` |
| 4.8 | `platform_settings` | `Content` | `UNIQUE (setting_group, setting_key)`, JSON `value` |

### 1.3 Conformance gaps in existing tables

| # | Doc requirement | Current state | Action |
|---|---|---|---|
| C1 | §1 "Soft deletes: not used" | `users.deleted_at`, `articles.deleted_at` + `SoftDeletes` on both models | Drop both columns, remove the trait, re-point deactivation to `is_active` / article status |
| C2 | §4.3.1 `CHECK (ends_at > starts_at)` | absent | Add to `voting_periods` (MySQL/MariaDB enforced) |
| C3 | §4.3.3 `CHECK (ends_at IS NULL OR ends_at >= starts_at)` | absent | Add to `timeline_events` (new table) |
| C4 | §4.2.3 `storage_disk VARCHAR(32) DEFAULT 's3'` | `VARCHAR(64)`, no default | Set length 32 + default `'s3'` |
| C5 | §4.2.3 `kind VARCHAR(20)` | `VARCHAR(64)` | Set length 20 |
| C6 | §4.2.1 `name VARCHAR(120)`, `slug VARCHAR(140)` | `VARCHAR(128)` / `VARCHAR(128)` | Match the doc widths |
| C7 | §4.2.2 `body MEDIUMTEXT` | `LONGTEXT` | Use `mediumText()` (16 MB) — matches the doc |
| C8 | §4.2.4 `reason VARCHAR(500)` | `TEXT` | Set length 500 |
| C9 | §2/§5 "at most one active publisher grant" | MySQL-only functional index; SQLite has **no** constraint | Use the doc's own §4.1.8 strategy: one row per user + `UNIQUE(user_id)`, portable to every engine; re-granting updates the row |
| C10 | §4.7.1 `actor_id` FK → `users.id` ON DELETE SET NULL | plain column, no FK | Add the FK (`SET NULL` keeps the audit row) |
| C11 | §1 InnoDB + `utf8mb4` / `utf8mb4_unicode_ci` | inherited from `config/database.php` (`engine = InnoDB`, charset/collation set) | ✅ already satisfied — assert it in the verification step |
| C12 | §5 #2 `UNIQUE(email)` on `users` | ✅ unique | — |
| C13 | §5 #3 `UNIQUE(slug)` on `articles` | ✅ unique | — |
| C14 | §5 #5 `UNIQUE(user_id, permission_id)` | ✅ `user_permissions_user_permission_unique` | — |
| C15 | §5 #6 composite pivots | ✅ both pivots have composite PKs | — |
| C16 | §5 #1 `UNIQUE(user_id, article_id)` on `votes` | ✅ `votes_one_per_user_article` | — |
| C17 | §5 #11 `UNIQUE(name)` on `universities` | ✅ unique | — |

---

## 2. Decisions taken (approved)

| # | Question | Decision | Consequence |
|---|---|---|---|
| D-a | Surrogate `BIGINT` PK + separate `ulid` column (doc §1) vs. ULID-as-PK (code) | **Keep ULID as the primary key** | The API still exposes an opaque 26-character identifier and internal joins stay on it. The doc's intent — "opaque IDs exposed via the API, internal joins stay on BIGINT keys" — is met in spirit: ULIDs are opaque *and* sort by creation time. Documented deviation. Knock-on: `audit_logs.subject_id` is a `VARCHAR(191)` (it holds ULIDs), not `BIGINT UNSIGNED`. |
| D-b | Local database engine | **MariaDB locally, MySQL 8 in production** | The doc targets MySQL 8; MariaDB is a documented drop-in (§1.1) with native JSON and enforced CHECK constraints. Migrations use only features present in both. |
| D-c | Soft deletes (doc §1 says "not used") | **Remove them** | `users` deactivation is `is_active = 0`; articles retire through status `unpublished`. |
| D-d | Scope | **Gap-fill + conformance** | The 9 missing tables are created; the existing tables are brought to doc conformance (C1–C10). Tables already conforming are left alone. |

### 2.1 Deviations that remain (documented, not changed)

| # | Deviation | Why it is acceptable |
|---|---|---|
| V1 | ULID PK instead of `BIGINT` + `ulid` column | Decision D-a. Changing it would rewrite every FK, model, factory and seeder across four modules. |
| V2 | `publisher_permissions` keeps an `id` surrogate key in addition to `UNIQUE(user_id)` | The doc's §4.1.8 model (one row per user, re-grant updates the row) is followed exactly; the surrogate key only exists because the whole codebase uses ULID keys, and `audit_logs` already records every grant/revoke. |
| V3 | `users` carries extra operational columns (`last_login_at`, `failed_login_attempts`, `locked_until`, `two_factor_*`) | The doc's §4.1.1 table is a simplification; the SRS requires lockout counters and TOTP enrolment. Removing them would break the Identity module. |
| V4 | `audit_logs` has no FK on the polymorphic `subject_type`/`subject_id` pair | Polymorphic references cannot carry a foreign key. `actor_id` does carry one (C10). |
| V5 | Extra Identity tables: `oauth_accounts`, `staff_invitations`, `login_attempts`, `sessions` | Required by the Google reader sign-in and staff onboarding flows in `backend.md`. |
| V6 | `article_vote_summaries` has `updated_at NOT NULL` and `ON DELETE RESTRICT` (doc: nullable, `CASCADE`) | The table is a derived cache owned by the Voting module; `votes` is authoritative and articles are never hard-deleted, so `RESTRICT` cannot strand data. Revisit if articles ever gain hard deletion. |

---

## 3. Execution plan

### Phase 0 — Environment (MariaDB) ✅ done

1. `pacman -S mariadb mariadb-clients` — installed (13.0.2).
2. `mariadb-install-db` — data directory initialised.
3. **The system service was not used.** `systemctl enable --now mariadb` needs an
   interactive polkit prompt (`sudo` is unusable in this environment), so a
   user-owned instance runs instead:
   ```bash
   mariadb-install-db --datadir=$HOME/.local/share/minerva-mariadb
   mariadbd --datadir=$HOME/.local/share/minerva-mariadb \
            --socket=/tmp/opencode/mariadb-run/mysqld.sock \
            --port=3307 --bind-address=127.0.0.1
   ```
   Production stays on the system `mariadb`/`mysql` service on 3306.
4. The application database and least-privilege user are created by
   `scripts/create-database.sh`:
   ```sql
   CREATE DATABASE minerva CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'minerva'@'127.0.0.1' IDENTIFIED BY '<secret from .env>';
   GRANT ALL PRIVILEGES ON minerva.* TO 'minerva'@'127.0.0.1';
   ```
5. `backend/.env` → `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3307`
   (local instance), `DB_DATABASE=minerva`, `DB_USERNAME=minerva`,
   `DB_PASSWORD=…`
6. Verified: `php artisan db:show` reports InnoDB + `utf8mb4_unicode_ci`, and
   `scripts/verify-schema.php` asserts both on every table.

### Phase 1 — Conformance edits to existing migrations (C1–C10) ✅ done

The project is pre-production, so the existing *create* migrations are edited
in place rather than followed by a trail of `ALTER` migrations. Every
developer then runs `php artisan migrate:fresh --seed`.

| Migration | Edit |
|---|---|
| Identity `…000001_create_users_table` | drop `softDeletes()`; index `is_active` alone |
| Identity `…000003_create_access_control_tables` | `permission_role` PK order → `(role_id, permission_id)`; `publisher_permissions` → `UNIQUE(user_id)` (the doc's own §4.1.8 strategy) |
| Articles `…000020_create_article_categories_table` | `name` 120, `slug` 140; `description` → `string(500)` |
| Articles `…000021_create_articles_table` | drop `softDeletes()`; `body` → `mediumText()` |
| Articles `…000022_create_article_attachments_table` | `storage_disk` 32 + default `s3`; `kind` 20 |
| Articles `…000023_create_article_status_history_table` | `reason` → `string(500)` |
| Voting `…000030_create_voting_periods_table` | `CHECK (ends_at > starts_at)` |
| Audit `…000010_create_audit_logs_table` | `actor_id` FK → `users.id` `SET NULL`; `ip_hash` → `char(64)`; index → `(action, created_at)` |
| Identity `…000004_create_identity_security_tables` | `personal_access_tokens.id` → standard Sanctum auto-increment `id` |

Model/service follow-ups for C1:
`User` (drop `SoftDeletes`), `Article` (drop `SoftDeletes`),
`ArticleWorkflowService::generateSlug()` (no `withTrashed`),
`UserManagementService`, `RoleService`, `StaffInvitationService`,
`GoogleAuthenticationService` (drop the trashed-user branches),
`PublisherPermissionService::grant()` (re-grant updates the single row).

### Phase 2 — New tables ✅ done

New migrations (ordered after the existing ones so a fresh migrate resolves
every FK):

| Migration | Table |
|---|---|
| `2026_01_01_000040_create_content_tables` | `workshops`, `workshop_resources`, `timeline_events`, `sponsors`, `committee_members`, `magazine_publications`, `e_magazine_files` |
| `2026_01_01_000041_create_platform_settings_table` | `platform_settings` |
| `2026_01_01_000050_create_api_keys_table` | `api_keys` |

New modules: `App\Modules\Content` and `App\Modules\Integrations`, each with
its own `Database/Migrations`, `Models` and `Providers`, wired into
`bootstrap/providers.php` exactly like the existing modules.

Route files are deliberately **not** added yet: a module route file needs
controllers behind it, and this task was schema creation. The modules are
registered (migrations load), so when the Content/Integrations modules are
built their `Routes/api.php` drop into `routes/api.php` like every other
module's.

### Phase 3 — Models & seeders ✅ done

- Models: `Workshop`, `WorkshopResource`, `TimelineEvent`, `Sponsor`,
  `CommitteeMember`, `MagazinePublication`, `EMagazineFile`,
  `PlatformSetting`, `ApiKey`.
- Seeders: `UniversitySeeder` (approved university lookup list, read from
  `database/data/universities.php` so the organising committee owns the list),
  `PlatformSettingSeeder` (FR-10 default groups), plus the existing
  `RolePermissionSeeder` and `ArticleCategorySeeder`.
- `DatabaseSeeder` calls all four in dependency order.

### Phase 4 — Bootstrap & verification tooling ✅ done

- `backend/scripts/create-database.sh` — one command: create the database and
  user on a fresh MariaDB/MySQL server, run `migrate --seed` (or
  `migrate:fresh --seed` with `--fresh`), then run the verifier.
  `--dry-run` prints the SQL with the password redacted.
- `backend/scripts/verify-schema.php` — reads the live schema and asserts
  every table, column (+ type), nullability, default, unique constraint,
  index, FK `ON DELETE` action and CHECK constraint from the doc. Exits
  non-zero on any deviation. Type matching is strict on MySQL/MariaDB and
  family-based on SQLite (SQLite collapses `BIGINT`/`SMALLINT`/`INT` to
  `integer`, `MEDIUMTEXT`/`LONGTEXT` to `text`).

### Phase 5 — Verification ✅ results

| Check | Command | Result |
|---|---|---|
| Schema matches the doc | `php scripts/verify-schema.php` | 29 tables, 105 assertions, **0 deviations** on MariaDB; same on SQLite |
| Fresh migrate on MariaDB | `scripts/create-database.sh --fresh` | 29 tables, no errors |
| Fresh migrate on SQLite | `php artisan migrate:fresh --seed` | 29 tables, no errors (portability) |
| Test suite | `php artisan test` | **140 passed** (571 assertions) — SQLite `:memory:`, unaffected by the engine switch |
| Formatter | `vendor/bin/pint --test` | pass on every file this change touched |
| Engine / collation | `php artisan db:show` | InnoDB, `utf8mb4_unicode_ci` on all 39 tables (29 domain + framework) |

---

## 4. Table inventory after this work (29 tables)

**Identity & Access (9):** `users`, `universities`, `password_reset_tokens`,
`roles`, `permissions`, `role_user`, `permission_role`, `user_permissions`,
`publisher_permissions`

**Articles & Editorial (4):** `article_categories`, `articles`,
`article_attachments`, `article_status_history`

**Voting (3):** `voting_periods`, `votes`, `article_vote_summaries`

**Comments & Moderation (2):** `comments`, `comment_moderation_records`

**Content Management (8):** `workshops`, `workshop_resources`,
`timeline_events`, `sponsors`, `committee_members`, `magazine_publications`,
`e_magazine_files`, `platform_settings`

**Integrations (1):** `api_keys`

**Audit (1):** `audit_logs`

**Framework / implementation extras (6):** `personal_access_tokens`,
`oauth_accounts`, `staff_invitations`, `login_attempts`, `sessions`,
`migrations` (+ `cache`, `cache_locks`, `jobs`, `job_batches`,
`failed_jobs` from the Laravel framework defaults)

---

## 5. Rollback

Every migration implements `down()`. To return to the previous state:

```bash
php artisan migrate:rollback --step=1   # last batch
php artisan migrate:fresh --seed        # known-good rebuild
```

Because the create migrations are edited in place, the reliable rollback for
a developer machine is `migrate:fresh`. Production deployments must run
`migrate` forward only, and the first deployment to a real database must be
a `migrate:fresh` on an empty schema.

---

## 6. Out of scope

- File storage (S3 buckets, signed URLs) — only `storage_disk` +
  `storage_key` columns are created, per doc §1.
- Object-storage lifecycle, CDN configuration, backups.
- Data backfill from any legacy system — the platform is greenfield.
