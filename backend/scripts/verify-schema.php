#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Minerva schema verifier
|--------------------------------------------------------------------------
|
| Compares the live database against `Docs/database-schema.md` (v1.1) and
| prints every deviation, then exits non-zero if there is at least one.
|
|   php backend/scripts/verify-schema.php
|
| The expectations below are transcribed from the doc, adjusted only for the
| deviations recorded in `docs/database-implementation-plan.md` (V1–V5). Each
| deviation is listed so a reader can tell an accepted difference from a bug.
|
*/

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var Connection $db */
$db = DB::connection();
$driver = $db->getDriverName();

$errors = [];
$checks = 0;
$notes = [];

/**
 * SQLite type families, used only when the driver is SQLite.
 *
 * @var array<string, list<string>>
 */
$sqliteTypeFamilies = [
    'char' => ['varchar'],
    'varchar' => ['varchar', 'string'],
    'tinyint' => ['integer'],
    'smallint' => ['integer'],
    'int' => ['integer'],
    'bigint' => ['integer'],
    'timestamp' => ['datetime'],
    'json' => ['text'],
    'text' => ['text'],
    'mediumtext' => ['text'],
    'longtext' => ['text'],
];

function normaliseDefault(mixed $value): mixed
{
    if (is_string($value)) {
        $value = trim($value);

        if (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) > 1) {
            return substr($value, 1, -1);
        }
    }

    return $value;
}

function fail(string $table, string $message): void
{
    global $errors;
    $errors[] = $table === '' ? $message : $table.': '.$message;
}

function check(string $table, string $label, bool $condition): void
{
    global $checks;
    $checks++;

    if (! $condition) {
        fail($table, $label);
    }
}

/**
 * CHECK clauses for one table, with backticks and whitespace stripped so the
 * expectations above can be written as plain SQL.
 *
 * MariaDB 10.5+ exposes `TABLE_NAME` on CHECK_CONSTRAINTS directly; MySQL 8
 * does not, so it is reached through TABLE_CONSTRAINTS. SQLite keeps the whole
 * table DDL in `sqlite_master`.
 *
 * @return list<string>
 */
function checkClauses(Connection $db, string $driver, string $table): array
{
    if ($driver === 'sqlite') {
        $sql = (string) $db->selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
            [$table],
        )->sql;

        return [normaliseSql($sql)];
    }

    try {
        $rows = $db->select(
            'SELECT CHECK_CLAUSE AS clause FROM information_schema.CHECK_CONSTRAINTS
              WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        );
    } catch (Throwable) {
        $rows = $db->select(
            'SELECT cc.CHECK_CLAUSE AS clause
               FROM information_schema.CHECK_CONSTRAINTS cc
               JOIN information_schema.TABLE_CONSTRAINTS tc
                 ON tc.CONSTRAINT_SCHEMA = cc.CONSTRAINT_SCHEMA
                AND tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
              WHERE cc.CONSTRAINT_SCHEMA = DATABASE() AND tc.TABLE_NAME = ?',
            [$table],
        );
    }

    return array_map(static fn (object $row): string => normaliseSql((string) $row->clause), $rows);
}

function normaliseSql(string $sql): string
{
    return trim(preg_replace('/\s+/', ' ', str_replace('`', '', $sql)) ?? '');
}

/**
 * Column types are compared exactly on MySQL and MariaDB, where the doc's
 * widths (`VARCHAR(120)`, `MEDIUMTEXT`, `BIGINT`) are all distinct.
 *
 * SQLite collapses whole families — every integer width reports as `integer`,
 * `CHAR` and `VARCHAR` as `varchar`, `MEDIUMTEXT`/`LONGTEXT` as `text`, and
 * `TIMESTAMP` as `datetime` — so it is compared on families instead. The
 * test database proves portability; production MySQL is the stricter gate.
 *
 * @param  array<string, list<string>>  $map
 */
function typeMatches(string $expected, string $actual, string $driver, array &$map): bool
{
    if ($expected === $actual) {
        return true;
    }

    if ($driver !== 'sqlite') {
        return false;
    }

    return in_array($actual, $map[$expected] ?? [$expected], true);
}

/*
|--------------------------------------------------------------------------
| Accepted, documented deviations
|--------------------------------------------------------------------------
*/
$deviations = [
    'ULID primary keys (V1): every `id` is CHAR(26), and `users`/`articles` do'
        .' not carry a separate `ulid` column.',
    'publisher_permissions (V2): surrogate `id` plus UNIQUE(user_id).',
    'users (V3): extra operational columns for lockout and TOTP.',
    'audit_logs (V4): no FK on the polymorphic subject pair.',
    'Identity extras (V5): oauth_accounts, staff_invitations, login_attempts, sessions.',
    'article_vote_summaries (V6): `updated_at` is NOT NULL and its FK is'
        .' RESTRICT rather than CASCADE; the table is a derived cache, not'
        .' authoritative, and articles are never hard-deleted.',
];

/*
|--------------------------------------------------------------------------
| Expected schema
|--------------------------------------------------------------------------
|
| columns     name => type_name (or null to accept any type)
| nullable    columns that must allow NULL
| defaults    columns that must have a default
| unique      list of unique column sets
| indexes     list of (non-unique) column sets that must be indexed
| fks         column => [table, on delete]
| absent      columns that must not exist
| check       substring that must appear in the table's CHECK constraint
|
*/
$spec = [
    'users' => [
        'columns' => [
            'id' => 'char',
            'name' => 'varchar',
            'email' => 'varchar',
            'email_verified_at' => 'timestamp',
            'password' => 'varchar',
            'university_id' => 'char',
            'faculty' => 'varchar',
            'phone' => 'varchar',
            'remember_token' => 'varchar',
            'is_active' => 'tinyint',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ],
        'nullable' => ['email_verified_at', 'university_id', 'faculty', 'phone', 'remember_token'],
        'unique' => [['email']],
        'indexes' => [['is_active']],
        'fks' => ['university_id' => ['universities', 'restrict']],
        'absent' => ['deleted_at', 'ulid'],
    ],

    'universities' => [
        'columns' => [
            'id' => 'char',
            'name' => 'varchar',
            'short_name' => 'varchar',
            'is_active' => 'tinyint',
            'created_at' => 'timestamp',
            'updated_at' => 'timestamp',
        ],
        'nullable' => ['short_name'],
        'unique' => [['name']],
    ],

    'password_reset_tokens' => [
        'columns' => ['email' => 'varchar', 'token' => 'varchar', 'created_at' => 'timestamp'],
        'nullable' => ['created_at'],
    ],

    'roles' => [
        'columns' => ['id' => 'char', 'name' => 'varchar', 'description' => 'varchar'],
        'nullable' => ['description'],
        'unique' => [['name']],
    ],

    'permissions' => [
        'columns' => ['id' => 'char', 'name' => 'varchar', 'description' => 'varchar'],
        'nullable' => ['description'],
        'unique' => [['name']],
    ],

    'role_user' => [
        'columns' => ['user_id' => 'char', 'role_id' => 'char'],
        'primary' => ['user_id', 'role_id'],
        'fks' => ['user_id' => ['users', 'cascade'], 'role_id' => ['roles', 'cascade']],
    ],

    'permission_role' => [
        'columns' => ['role_id' => 'char', 'permission_id' => 'char'],
        'primary' => ['role_id', 'permission_id'],
        'fks' => ['role_id' => ['roles', 'cascade'], 'permission_id' => ['permissions', 'cascade']],
    ],

    'user_permissions' => [
        'columns' => [
            'id' => 'char',
            'user_id' => 'char',
            'permission_id' => 'char',
            'granted_by' => 'char',
            'expires_at' => 'timestamp',
        ],
        'nullable' => ['granted_by', 'expires_at'],
        'unique' => [['user_id', 'permission_id']],
        'fks' => [
            'user_id' => ['users', 'cascade'],
            'permission_id' => ['permissions', 'cascade'],
            'granted_by' => ['users', 'set null'],
        ],
    ],

    'publisher_permissions' => [
        'columns' => [
            'id' => 'char',
            'user_id' => 'char',
            'can_publish_directly' => 'tinyint',
            'granted_by' => 'char',
            'granted_at' => 'timestamp',
            'revoked_at' => 'timestamp',
        ],
        'nullable' => ['granted_by', 'revoked_at'],
        'unique' => [['user_id']],
        'fks' => ['user_id' => ['users', 'cascade'], 'granted_by' => ['users', 'set null']],
    ],

    'article_categories' => [
        'columns' => [
            'id' => 'char',
            'name' => 'varchar',
            'slug' => 'varchar',
            'description' => 'varchar',
        ],
        'nullable' => ['description'],
        'unique' => [['slug']],
    ],

    'articles' => [
        'columns' => [
            'id' => 'char',
            'author_id' => 'char',
            'category_id' => 'char',
            'title' => 'varchar',
            'slug' => 'varchar',
            'abstract' => 'text',
            'body' => 'mediumtext',
            'status' => 'varchar',
            'is_featured' => 'tinyint',
            'published_at' => 'timestamp',
        ],
        'nullable' => ['published_at'],
        'defaults' => ['status' => 'draft', 'is_featured' => 0],
        'unique' => [['slug']],
        'indexes' => [['status', 'published_at'], ['author_id', 'status'], ['category_id']],
        'fks' => [
            'author_id' => ['users', 'restrict'],
            'category_id' => ['article_categories', 'restrict'],
        ],
        'absent' => ['deleted_at', 'ulid'],
    ],

    'article_attachments' => [
        'columns' => [
            'id' => 'char',
            'article_id' => 'char',
            'storage_disk' => 'varchar',
            'storage_key' => 'varchar',
            'original_name' => 'varchar',
            'mime_type' => 'varchar',
            'size_bytes' => 'bigint',
            'kind' => 'varchar',
        ],
        'defaults' => ['storage_disk' => 's3', 'kind' => 'document'],
        'indexes' => [['article_id']],
        'fks' => ['article_id' => ['articles', 'cascade']],
    ],

    'article_status_history' => [
        'columns' => [
            'id' => 'char',
            'article_id' => 'char',
            'from_status' => 'varchar',
            'to_status' => 'varchar',
            'acted_by' => 'char',
            'reason' => 'varchar',
            'created_at' => 'timestamp',
        ],
        'nullable' => ['from_status', 'acted_by', 'reason'],
        'indexes' => [['article_id', 'created_at']],
        'fks' => [
            'article_id' => ['articles', 'cascade'],
            'acted_by' => ['users', 'set null'],
        ],
        'absent' => ['updated_at'],
    ],

    'voting_periods' => [
        'columns' => [
            'id' => 'char',
            'name' => 'varchar',
            'starts_at' => 'timestamp',
            'ends_at' => 'timestamp',
            'is_active' => 'tinyint',
            'created_by' => 'char',
        ],
        'nullable' => ['created_by'],
        'defaults' => ['is_active' => 0],
        'indexes' => [['is_active', 'starts_at', 'ends_at']],
        'fks' => ['created_by' => ['users', 'set null']],
        'check' => 'ends_at > starts_at',
    ],

    'votes' => [
        'columns' => [
            'id' => 'char',
            'user_id' => 'char',
            'article_id' => 'char',
            'voting_period_id' => 'char',
            'created_at' => 'timestamp',
        ],
        'unique' => [['user_id', 'article_id']],
        'indexes' => [['article_id'], ['voting_period_id']],
        'fks' => [
            'user_id' => ['users', 'restrict'],
            'article_id' => ['articles', 'restrict'],
            'voting_period_id' => ['voting_periods', 'restrict'],
        ],
        'absent' => ['updated_at'],
    ],

    // Owned by the Voting module. Two small differences from the doc are
    // recorded as deviation V6 below, so they are not asserted as failures.
    'article_vote_summaries' => [
        'columns' => ['article_id' => 'char', 'vote_count' => 'int', 'updated_at' => 'timestamp'],
        'defaults' => ['vote_count' => 0],
        'primary' => ['article_id'],
        'fks' => ['article_id' => ['articles', 'restrict']],
    ],

    'comments' => [
        'columns' => [
            'id' => 'char',
            'article_id' => 'char',
            'user_id' => 'char',
            'body' => 'text',
            'status' => 'varchar',
            'removed_at' => 'timestamp',
            'removed_by' => 'char',
        ],
        'nullable' => ['removed_at', 'removed_by'],
        'defaults' => ['status' => 'visible'],
        'indexes' => [
            ['article_id', 'created_at'],
            ['user_id', 'created_at'],
            ['status', 'created_at'],
        ],
        'fks' => [
            'article_id' => ['articles', 'restrict'],
            'user_id' => ['users', 'restrict'],
            'removed_by' => ['users', 'set null'],
        ],
    ],

    'comment_moderation_records' => [
        'columns' => [
            'id' => 'char',
            'comment_id' => 'char',
            'action' => 'varchar',
            'reason' => 'varchar',
            'acted_by' => 'char',
            'created_at' => 'timestamp',
        ],
        'nullable' => ['reason', 'acted_by'],
        'indexes' => [['comment_id'], ['acted_by', 'created_at']],
        'fks' => [
            'comment_id' => ['comments', 'cascade'],
            'acted_by' => ['users', 'set null'],
        ],
        'absent' => ['updated_at'],
    ],

    'workshops' => [
        'columns' => [
            'id' => 'char',
            'title' => 'varchar',
            'description' => 'text',
            'starts_at' => 'timestamp',
            'ends_at' => 'timestamp',
            'speaker_details' => 'json',
            'status' => 'varchar',
            'sort_order' => 'smallint',
        ],
        'nullable' => ['ends_at', 'speaker_details'],
        'defaults' => ['status' => 'draft', 'sort_order' => 0],
        'indexes' => [['status', 'starts_at']],
    ],

    'workshop_resources' => [
        'columns' => [
            'id' => 'char',
            'workshop_id' => 'char',
            'title' => 'varchar',
            'kind' => 'varchar',
            'url_or_storage_key' => 'varchar',
            'mime_type' => 'varchar',
            'size_bytes' => 'bigint',
            'sort_order' => 'smallint',
        ],
        'nullable' => ['mime_type', 'size_bytes'],
        'defaults' => ['kind' => 'link', 'sort_order' => 0],
        'indexes' => [['workshop_id', 'sort_order']],
        'fks' => ['workshop_id' => ['workshops', 'cascade']],
    ],

    'timeline_events' => [
        'columns' => [
            'id' => 'char',
            'title' => 'varchar',
            'description' => 'text',
            'starts_at' => 'timestamp',
            'ends_at' => 'timestamp',
            'sort_order' => 'smallint',
            'status' => 'varchar',
        ],
        'nullable' => ['description', 'ends_at'],
        'defaults' => ['status' => 'draft', 'sort_order' => 0],
        'indexes' => [['status', 'starts_at']],
        'check' => 'ends_at >= starts_at',
    ],

    'sponsors' => [
        'columns' => [
            'id' => 'char',
            'name' => 'varchar',
            'website_url' => 'varchar',
            'logo_storage_key' => 'varchar',
            'sort_order' => 'smallint',
            'status' => 'varchar',
        ],
        'nullable' => ['website_url', 'logo_storage_key'],
        'defaults' => ['status' => 'draft', 'sort_order' => 0],
        'indexes' => [['status', 'sort_order']],
    ],

    'committee_members' => [
        'columns' => [
            'id' => 'char',
            'name' => 'varchar',
            'position' => 'varchar',
            'team' => 'varchar',
            'photo_storage_key' => 'varchar',
            'sort_order' => 'smallint',
            'status' => 'varchar',
        ],
        'nullable' => ['team', 'photo_storage_key'],
        'defaults' => ['status' => 'draft', 'sort_order' => 0],
        'indexes' => [['status', 'sort_order'], ['team']],
    ],

    'magazine_publications' => [
        'columns' => [
            'id' => 'char',
            'title' => 'varchar',
            'description' => 'text',
            'cover_storage_key' => 'varchar',
            'published_at' => 'timestamp',
            'status' => 'varchar',
        ],
        'nullable' => ['description', 'cover_storage_key', 'published_at'],
        'defaults' => ['status' => 'draft'],
        'indexes' => [['status', 'published_at']],
    ],

    'e_magazine_files' => [
        'columns' => [
            'id' => 'char',
            'magazine_publication_id' => 'char',
            'storage_key' => 'varchar',
            'mime_type' => 'varchar',
            'size_bytes' => 'bigint',
            'sort_order' => 'smallint',
        ],
        'defaults' => ['sort_order' => 0],
        'indexes' => [['magazine_publication_id']],
        'fks' => ['magazine_publication_id' => ['magazine_publications', 'cascade']],
    ],

    'platform_settings' => [
        'columns' => [
            'id' => 'char',
            'setting_group' => 'varchar',
            'setting_key' => 'varchar',
            'value' => 'json',
            'updated_by' => 'char',
        ],
        'nullable' => ['value', 'updated_by'],
        'unique' => [['setting_group', 'setting_key']],
        'indexes' => [['setting_group']],
        'fks' => ['updated_by' => ['users', 'set null']],
    ],

    'api_keys' => [
        'columns' => [
            'id' => 'char',
            'public_id' => 'varchar',
            'name' => 'varchar',
            'secret_hash' => 'char',
            'scopes' => 'json',
            'created_by' => 'char',
            'last_used_at' => 'timestamp',
            'expires_at' => 'timestamp',
            'revoked_at' => 'timestamp',
        ],
        'nullable' => ['created_by', 'last_used_at', 'expires_at', 'revoked_at'],
        'unique' => [['public_id']],
        'indexes' => [['expires_at'], ['created_by']],
        'fks' => ['created_by' => ['users', 'set null']],
    ],

    'audit_logs' => [
        'columns' => [
            'id' => 'char',
            'actor_id' => 'char',
            'action' => 'varchar',
            'subject_type' => 'varchar',
            'subject_id' => 'varchar',
            'metadata' => 'json',
            'ip_hash' => 'char',
            'created_at' => 'timestamp',
        ],
        'nullable' => ['actor_id', 'subject_type', 'subject_id', 'metadata', 'ip_hash'],
        'indexes' => [
            ['subject_type', 'subject_id'],
            ['actor_id', 'created_at'],
            ['action', 'created_at'],
        ],
        'fks' => ['actor_id' => ['users', 'set null']],
        'absent' => ['updated_at'],
    ],

    'personal_access_tokens' => [
        'columns' => [
            'id' => 'bigint',
            'tokenable_type' => 'varchar',
            'tokenable_id' => 'char',
            'name' => 'varchar',
            'token' => 'varchar',
            'abilities' => 'text',
            'last_used_at' => 'timestamp',
            'expires_at' => 'timestamp',
        ],
        'nullable' => ['last_used_at', 'expires_at'],
        'unique' => [['token']],
    ],
];

/*
|--------------------------------------------------------------------------
| Table presence
|--------------------------------------------------------------------------
*/
$actualTables = array_map(
    static fn (array $table): string => $table['name'],
    Schema::getTables(),
);

foreach (array_keys($spec) as $table) {
    check('', "table `{$table}` is missing", in_array($table, $actualTables, true));
}

/*
|--------------------------------------------------------------------------
| Engines and collation
|--------------------------------------------------------------------------
|
| MySQL and MariaDB only. SQLite has no storage engine or collation concept.
*/
if (in_array($driver, ['mysql', 'mariadb'], true)) {
    $rows = $db->select(
        'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COLLATION AS collation
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = ?',
        ['BASE TABLE'],
    );

    foreach ($rows as $row) {
        if (! in_array($row->name, $actualTables, true)) {
            continue;
        }

        if (strtolower((string) $row->engine) !== 'innodb') {
            fail($row->name, "engine is {$row->engine}, expected InnoDB");
        }

        if ($row->collation !== 'utf8mb4_unicode_ci') {
            fail($row->name, "collation is {$row->collation}, expected utf8mb4_unicode_ci");
        }
    }
}

/*
|--------------------------------------------------------------------------
| Per-table checks
|--------------------------------------------------------------------------
*/
foreach ($spec as $table => $rules) {
    if (! in_array($table, $actualTables, true)) {
        continue;
    }

    $columns = collect(Schema::getColumns($table))->keyBy('name');
    $indexes = Schema::getIndexes($table);
    $foreignKeys = Schema::getForeignKeys($table);
    $checkClauses = checkClauses($db, $driver, $table);

    // Columns + declared types.
    foreach ($rules['columns'] as $name => $type) {
        if (! $columns->has($name)) {
            fail($table, "column `{$name}` is missing");

            continue;
        }

        $actualType = $columns[$name]['type_name'];

        // MariaDB implements JSON as LONGTEXT + CHECK (json_valid(col)), so
        // the reported type is longtext while the column is still JSON.
        if ($type === 'json' && $actualType === 'longtext' && in_array($driver, ['mysql', 'mariadb'], true)) {
            $isJson = collect($checkClauses)->contains(
                static fn (string $clause): bool => preg_match('/json_valid\(`?'.preg_quote($name, '/').'`?\)/', $clause) === 1,
            );

            check($table, "column `{$name}` is longtext without a json_valid() CHECK", $isJson);

            continue;
        }

        if ($type !== null && ! typeMatches($type, $actualType, $driver, $sqliteTypeFamilies)) {
            fail($table, "column `{$name}` is {$actualType}, expected {$type}");
        }
    }

    // Nullability.
    foreach ($rules['nullable'] ?? [] as $name) {
        if ($columns->has($name) && ! $columns[$name]['nullable']) {
            fail($table, "column `{$name}` must allow NULL");
        }
    }

    // Defaults.
    foreach ($rules['defaults'] ?? [] as $name => $expected) {
        if (! $columns->has($name)) {
            continue;
        }

        $actual = normaliseDefault($columns[$name]['default']);
        $expectedIsInt = is_int($expected) || ctype_digit((string) $expected);

        if ($expectedIsInt) {
            check($table, "column `{$name}` default is ".var_export($actual, true).", expected {$expected}", (string) $actual === (string) $expected);
        } else {
            check($table, "column `{$name}` default is ".var_export($actual, true).", expected {$expected}", $actual === $expected);
        }
    }

    // Columns that must not exist (soft deletes, doc-declared ULID column, ...).
    foreach ($rules['absent'] ?? [] as $name) {
        check($table, "column `{$name}` should not exist", ! $columns->has($name));
    }

    // Primary key.
    if (isset($rules['primary'])) {
        $primary = collect($indexes)->firstWhere('primary', true);
        $actual = $primary['columns'] ?? [];

        check(
            $table,
            'primary key is ('.implode(', ', $actual).'), expected ('.implode(', ', $rules['primary']).')',
            $actual === $rules['primary'],
        );
    }

    // Unique constraints — order-insensitive.
    foreach ($rules['unique'] ?? [] as $expected) {
        sort($expected);

        $found = collect($indexes)->contains(function (array $index) use ($expected): bool {
            if (! $index['unique'] || $index['primary']) {
                return false;
            }

            $actual = $index['columns'];
            sort($actual);

            return $actual === $expected;
        });

        check($table, 'missing UNIQUE('.implode(', ', $expected).')', $found);
    }

    // Secondary indexes must exist (as a prefix of some index).
    foreach ($rules['indexes'] ?? [] as $expected) {
        $found = collect($indexes)->contains(function (array $index) use ($expected): bool {
            return array_slice($index['columns'], 0, count($expected)) === $expected;
        });

        check($table, 'missing index ('.implode(', ', $expected).')', $found);
    }

    // Foreign keys: target table and ON DELETE action.
    foreach ($rules['fks'] ?? [] as $column => [$foreignTable, $onDelete]) {
        $fk = collect($foreignKeys)->first(
            static fn (array $key): bool => $key['columns'] === [$column],
        );

        if ($fk === null) {
            fail($table, "missing foreign key on `{$column}` → {$foreignTable}");

            continue;
        }

        if ($fk['foreign_table'] !== $foreignTable) {
            fail($table, "foreign key on `{$column}` targets {$fk['foreign_table']}, expected {$foreignTable}");
        }

        if (strtolower((string) $fk['on_delete']) !== $onDelete) {
            fail($table, "foreign key on `{$column}` is ON DELETE {$fk['on_delete']}, expected {$onDelete}");
        }
    }

    // CHECK constraints.
    if (isset($rules['check'])) {
        if ($driver === 'sqlite') {
            // The migrations add these CHECK constraints only on MySQL and
            // MariaDB (see the migration comments); the app enforces the same
            // rule at validation time, and the test suite covers it.
            $notes[] = "{$table}: CHECK({$rules['check']}) runs on MySQL/MariaDB only; the equivalent rule is enforced at application level on SQLite.";

            continue;
        }

        $found = collect($checkClauses)->contains(
            static fn (string $clause): bool => str_contains($clause, $rules['check']),
        );

        check($table, "missing CHECK({$rules['check']})", $found);
    }
}

/*
|--------------------------------------------------------------------------
| Report
|--------------------------------------------------------------------------
*/
$tableCount = count(array_intersect(array_keys($spec), $actualTables));

if ($errors === []) {
    echo "Schema OK — {$tableCount} tables, {$checks} assertions, 0 deviations.".PHP_EOL;
    echo 'Driver: '.$driver.PHP_EOL;

    if ($notes !== []) {
        echo PHP_EOL.'Notes:'.PHP_EOL;
        foreach ($notes as $note) {
            echo '  • '.$note.PHP_EOL;
        }
    }

    echo PHP_EOL.'Accepted deviations:'.PHP_EOL;

    foreach ($deviations as $deviation) {
        echo '  • '.$deviation.PHP_EOL;
    }

    exit(0);
}

echo "Schema verification failed — {$tableCount} tables found, ".count($errors).' deviations:'.PHP_EOL.PHP_EOL;

foreach ($errors as $error) {
    echo '  ✗ '.$error.PHP_EOL;
}

exit(1);
