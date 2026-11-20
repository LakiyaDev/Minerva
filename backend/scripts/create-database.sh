#!/usr/bin/env bash
#
# create-database.sh — create the schema and reference data for Minerva.
#
#   scripts/create-database.sh                    # create database + user, migrate, seed
#   scripts/create-database.sh --fresh            # additionally drop the schema first
#   scripts/create-database.sh --dry-run          # print the SQL, execute nothing
#
# The target database and application user are read from `backend/.env`
# (DB_CONNECTION / DB_DATABASE / DB_USERNAME / DB_PASSWORD). The privileged
# connection used to create them comes from, in order of precedence:
#
#   --admin-user / --admin-password / --socket   command-line flags
#   DB_ADMIN_USER / DB_ADMIN_PASSWORD            environment variables
#   the `root` account over the configured socket   local default, no password
#
# Credentials are never written to disk and never echoed unless --dry-run is
# used, which prints them redacted.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

FRESH=0
DRY_RUN=0
ADMIN_USER="${DB_ADMIN_USER:-root}"
ADMIN_PASSWORD="${DB_ADMIN_PASSWORD:-}"
SOCKET="${MYSQL_SOCKET:-}"

usage() {
    sed -n '3,17p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --fresh) FRESH=1 ;;
        --dry-run) DRY_RUN=1 ;;
        --admin-user) ADMIN_USER="$2"; shift ;;
        --admin-password) ADMIN_PASSWORD="$2"; shift ;;
        --socket) SOCKET="$2"; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
    esac
    shift
done

env_value() {
    # $1 = variable name, $2 = optional default
    local name="$1" default="${2:-}" line
    line="$(grep -E "^${name}=" .env 2>/dev/null | tail -n 1 || true)"
    if [[ -z "$line" ]]; then
        printf '%s' "$default"
        return
    fi
    printf '%s' "${line#*=}"
}

DB_CONNECTION="$(env_value DB_CONNECTION mysql)"
DB_HOST="$(env_value DB_HOST 127.0.0.1)"
DB_PORT="$(env_value DB_PORT 3306)"
DB_DATABASE="$(env_value DB_DATABASE minerva)"
DB_USERNAME="$(env_value DB_USERNAME minerva)"
DB_PASSWORD="$(env_value DB_PASSWORD '')"
DB_CHARSET="$(env_value DB_CHARSET utf8mb4)"
DB_COLLATION="$(env_value DB_COLLATION utf8mb4_unicode_ci)"

if [[ "$DB_CONNECTION" != "mysql" && "$DB_CONNECTION" != "mariadb" ]]; then
    echo "DB_CONNECTION is '${DB_CONNECTION}'; this script only provisions MySQL/MariaDB." >&2
    echo "Switch backend/.env to the MySQL database, or run 'php artisan migrate' for SQLite." >&2
    exit 1
fi

if [[ "$FRESH" -eq 1 ]]; then
    echo "!! --fresh will DROP the '${DB_DATABASE}' schema and every table in it." >&2
    echo "!! Press Ctrl-C within 5 seconds to abort." >&2
    sleep 5
fi

# Statements, in order: optional drop, schema, user at each host, then grants.
STATEMENTS=()

if [[ "$FRESH" -eq 1 ]]; then
    # Only with --fresh: the schema is dropped so no stale table survives.
    STATEMENTS+=("DROP DATABASE IF EXISTS \`${DB_DATABASE}\`;")
fi

STATEMENTS+=("CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\`
    CHARACTER SET ${DB_CHARSET} COLLATE ${DB_COLLATION};")

for host in localhost 127.0.0.1; do
    STATEMENTS+=("CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'${host}' IDENTIFIED BY '${DB_PASSWORD}';")
    STATEMENTS+=("ALTER USER '${DB_USERNAME}'@'${host}' IDENTIFIED BY '${DB_PASSWORD}';")
done

STATEMENTS+=("GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'localhost';")
STATEMENTS+=("GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'127.0.0.1';")
printf -v SQL '%s\n' "${STATEMENTS[@]}" 2>/dev/null || SQL="$(printf '%s\n' "${STATEMENTS[@]}")"

CLIENT=""
for candidate in mariadb mysql; do
    if command -v "$candidate" >/dev/null 2>&1; then
        CLIENT="$candidate"
        break
    fi
done

if [[ -z "$CLIENT" && "$DRY_RUN" -eq 0 ]]; then
    echo "Neither 'mariadb' nor 'mysql' was found on PATH. Install mariadb-clients or mysql-clients." >&2
    exit 1
fi

ADMIN_ARGS=(--host="$DB_HOST" --port="$DB_PORT" --user="$ADMIN_USER")
[[ -n "$ADMIN_PASSWORD" ]] && ADMIN_ARGS+=("--password=${ADMIN_PASSWORD}")
[[ -n "$SOCKET" ]] && ADMIN_ARGS+=(--socket="$SOCKET")

if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "--- dry run: nothing executed ---"
    printf '%s\n' "$SQL" | sed "s/'${DB_PASSWORD}'/'<redacted>'/g"
    echo "--- dry run: nothing executed ---"
    exit 0
fi

echo "Provisioning database '${DB_DATABASE}' for user '${DB_USERNAME}' on ${DB_HOST}:${DB_PORT} ..."
printf '%s\n' "$SQL" | "$CLIENT" "${ADMIN_ARGS[@]}" --batch

echo "Database and user ready."
echo

if [[ "$FRESH" -eq 1 ]]; then
    echo "Running: php artisan migrate:fresh --seed --force"
    php artisan migrate:fresh --seed --force
else
    echo "Running: php artisan migrate --seed --force"
    php artisan migrate --seed --force
fi

echo
echo "Verifying the result against Docs/database-schema.md ..."
php scripts/verify-schema.php