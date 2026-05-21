# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project layout

All application code lives inside `StoreProject/` (the repo root is just a wrapper). Run every command below from `StoreProject/` unless noted.

## Stack

Laravel 12 + Inertia.js v2 starter kit with a React 19 + TypeScript frontend. Vite is the asset bundler; Tailwind CSS 4 (via `@tailwindcss/vite`) and shadcn/ui (lucide-react icons, neutral base color) handle styling. SQLite is the default DB (`database/database.sqlite`). Ziggy exposes named Laravel routes to JS as a global `route()` function.

## Common commands

Run all of these from `StoreProject/`:

- `composer dev` — runs `php artisan serve`, `php artisan queue:listen --tries=1`, and `npm run dev` concurrently (the standard dev loop). Equivalent to starting the `laravel`, `queue`, and `vite` entries in `.claude/launch.json`.
- `npm run dev` / `npm run build` / `npm run build:ssr` — Vite dev server / SPA build / SSR build (entry `resources/js/ssr.jsx`).
- `npm run lint` — ESLint with `--fix`.
- `npm run format` / `npm run format:check` — Prettier over `resources/`.
- `vendor/bin/pint` — Laravel Pint (PHP formatter). CI runs Pint, then `npm run format`, then `npm run lint`.
- `vendor/bin/phpunit` — full test suite. Pest is layered on top of PHPUnit; tests can use either style.
- `vendor/bin/pest --filter <name>` — run a single test by name.
- `php artisan test --filter <name>` — alternative single-test runner via Artisan.

CI test setup (mirror locally if needed): `touch database/database.sqlite && cp .env.example .env && php artisan key:generate && npm run build`.

## Architecture

### Request → page flow (Inertia)

Routes in `routes/web.php`, `routes/auth.php`, `routes/settings.php` return `Inertia::render('<name>')`. The string maps to `resources/js/pages/<name>.tsx` via `resolvePageComponent` in `resources/js/app.tsx`. There is no separate API layer for normal navigation — controllers pass props directly to React pages.

The Blade root template is `resources/views/app.blade.php` (referenced as `$rootView = 'app'` in `app/Http/Middleware/HandleInertiaRequests.php`). That middleware also defines the **shared props** available on every page (`name`, `quote`, `auth.user`) — add to its `share()` method when you need a value globally.

### Frontend conventions

- Path alias `@/*` → `resources/js/*` (see `tsconfig.json` and `components.json`). Always import via `@/components/...`, `@/lib/utils`, `@/hooks/...`.
- shadcn/ui components live in `resources/js/components/ui/`. Use `components.json` aliases when adding new shadcn primitives.
- Layouts are in `resources/js/layouts/` (`app-layout.tsx`, `auth-layout.tsx`, and a `settings/` layout) — wrap page components rather than duplicating chrome.
- Theme/appearance handled by `hooks/use-appearance.tsx`; `initializeTheme()` is called at app bootstrap in `app.tsx`.

### Backend conventions

- Form validation uses dedicated request classes under `app/Http/Requests/Auth` and `app/Http/Requests/Settings`. Add new request classes alongside controllers rather than validating inline.
- `app/Models/User.php` is the only model so far; new models go in `app/Models/`.
- Auth and settings routes are split into their own files and `require`d from `web.php`. Follow that pattern for new feature areas.

### Tests

Pest is configured in `tests/Pest.php` to extend `Tests\TestCase` and apply `RefreshDatabase` to everything in `tests/Feature`. `phpunit.xml` forces `DB_CONNECTION=sqlite` with `:memory:`, `QUEUE_CONNECTION=sync`, and array-based cache/session/mail — so tests do not need a real database file or queue worker.
