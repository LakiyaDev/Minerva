# Minerva Digital Platform

Full-stack web platform for the **Minerva** science communication and article competition initiative by the **IEEE EMBS Student Branch Chapter, University of Sri Jayewardenepura**.

## Repository Structure

```text
Minerva/
├── backend/    Laravel 12 modular-monolith REST API (/api/v1)
├── Frontend/   React 19 + TypeScript + Vite + Tailwind CSS v4 SPA
└── Docs/       SRS v1.1, backend architecture, modular tasks, and frontend tasks
```

## Implemented Modules

* **Identity & Access (`App\Modules\Identity`)**: Google OAuth participant sign-in, invite-only staff accounts (password + TOTP MFA + recovery codes), RBAC roles/permissions, direct-publish grants, and Sanctum cookie/token sessions.
* **Articles & Editorial (`App\Modules\Articles`)**: Categories, six-state editorial workflow (`draft`, `pending_review`, `approved`, `rejected`, `published`, `unpublished`), append-only status history, publisher workspace, and admin review queue.
* **Voting (`App\Modules\Voting`)**: Configurable voting windows, transaction-safe irrevocable votes (`UNIQUE(user_id, article_id)`), derived vote summaries, participant vote history, live standings, admin voting statistics, and `VotingResultsUpdated` Reverb broadcasts.
* **Audit (`App\Modules\Audit`)**: Append-only `audit_logs` trail via `Shared\Contracts\AuditLogger`.

## Quick Start

### Backend (`backend/`)
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Run backend tests and style checks (with SQLite PDO extensions enabled for `:memory:` tests):
```bash
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/pest
php vendor/bin/pint --test app/Modules/Articles app/Modules/Voting tests/Feature/Modules/Articles tests/Feature/Modules/Voting
```

### Frontend (`Frontend/`)
```bash
cd Frontend
pnpm install   # or npm install
pnpm dev       # proxies /api and /sanctum to VITE_BACKEND_URL (default http://localhost:8000)
```

Typecheck and production build:
```bash
./node_modules/.bin/tsc --noEmit && ./node_modules/.bin/vite build
```

## Further Documentation

* [SRS v1.1](Docs/minerva-digital-platform-srs-v1.1.md)
* [Backend Architecture & Modular Tasks](Docs/backend_modular_tasks.md)
* [Frontend Tasks](Docs/frontendtasks.md)
* [Backend README](backend/README.md) & [OpenAPI 3.1 Spec](backend/openapi/openapi.yaml)
* Module Guides: [Identity & Access](backend/docs/identity-and-access.md) · [Articles & Editorial](backend/docs/articles-and-editorial.md) · [Voting](backend/docs/voting.md)
