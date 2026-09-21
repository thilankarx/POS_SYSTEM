# OSPOS Next

A ground-up rebuild of an open-source point-of-sale system for a
hardware/retail store: Laravel 13 back office (Livewire 4) plus an
offline-first Vue 3 register PWA, built domain-first with a dedicated
Action class behind every mutation. See [PROJECT_STATUS.md](PROJECT_STATUS.md)
for the full build log, architectural decisions, and current gaps —
this file only covers getting it running.

## Stack

- **Backend**: PHP 8.3+, Laravel 13, Livewire 4, MariaDB/MySQL.
- **Register PWA**: Vue 3 (Composition API) + Pinia, hash-routed SPA at
  `/pos`, IndexedDB-backed offline queue, served through Vite.
- **Money**: `brick/money`, pinned to a single configured currency —
  no float arithmetic anywhere in the domain.
- **Permissions**: `spatie/laravel-permission`, exact-match abilities
  (no `LIKE`-prefix matching).
- **Settings**: `spatie/laravel-settings` — DB-backed, editable at
  runtime, distinct from `config/*.php`.
- **Testing**: Pest 4 (470+ feature/unit tests), Vitest (register
  store/sync-engine tests), Pint (formatting), Larastan.
- **Other**: `spatie/laravel-activitylog` (audit log), `barryvdh/laravel-dompdf`
  (receipts/reports), `mike42/escpos-php` (thermal receipt printing),
  `picqer/php-barcode-generator` (labels).

## Quick start

```bash
composer run setup   # install deps, .env, key:generate, migrate --force,
                      # db:seed --force, npm install, npm run build
```

`composer run setup` leaves `DB_CONNECTION=sqlite` (the framework
default in `.env.example`) unless you edit `.env` first — this project
has only ever been run against MariaDB/MySQL, so for anything beyond a
quick look, set `DB_CONNECTION=mysql` plus `DB_HOST`/`DB_DATABASE`/
`DB_USERNAME`/`DB_PASSWORD` in `.env` before running setup, or run the
steps by hand:

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: DB_CONNECTION=mysql, DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan migrate:fresh --seed     # schema + demo data
npm install
npm run build                        # required for the register PWA's
                                      # service worker to install at all
php artisan serve                    # http://127.0.0.1:8000
```

Demo logins (password `password`): `admin` (Owner role), `cashier`
(Cashier role).

For day-to-day development, `npm run dev` (Vite HMR) is faster than
rebuilding for every change, but the register PWA's service worker
only installs from a real `npm run build` — see
[PROJECT_STATUS.md](PROJECT_STATUS.md#running-it) if `/pos` behaves
oddly after a build.

## Environment variables

Beyond the standard Laravel `.env` keys, this app reads:

| Key | Default | Meaning |
|---|---|---|
| `POS_CURRENCY` | `USD` | ISO currency code every `Money` value is pinned to (e.g. `LKR`). No multi-currency support — this is a single, app-wide setting. |
| `POS_CASH_ROUNDING` | `0` | Smallest cash denomination a cash total rounds to (Swedish rounding). `0` disables cash rounding entirely. |

These settings ship in `.env.example`; confirm them before the first
real transaction because currency is app-wide and must not be changed
after go-live.

## Running tests

```bash
vendor/bin/pest      # full suite
vendor/bin/pint      # format (run before committing)
npm run test         # Vitest: register store + offline sync engine
```

`tests/Unit` boots the framework but is deliberately forbidden from
touching the database — that constraint is what keeps the tax engine
covered without a database round-trip.

## Scheduled tasks

Defined in `bootstrap/app.php`'s `withSchedule()` (Laravel 11+ style —
there is no `app/Console/Kernel.php`):

| Command | Frequency | What it does |
|---|---|---|
| `stock:reconcile` | daily | Compares the stock ledger against the projected on-hand quantity and reports drift. Deliberately never run with `--fix` on schedule — drift is surfaced for a human to review, not auto-corrected. |
| `model:prune --model=IdempotencyKey` | hourly | Prunes expired idempotency keys (checkout's `Idempotency-Key` dedup records). |

A real deployment needs `php artisan schedule:work` (or a cron entry
calling `schedule:run` every minute) running continuously for these to
fire.

## Queue

`QUEUE_CONNECTION=database` by default — no separate broker required to
run this app as-is. `app/Jobs/` is currently empty (see
[PROJECT_STATUS.md](PROJECT_STATUS.md) for what's planned there), so a
queue worker isn't required for anything today, but keep one running if
you add queued jobs later: `php artisan queue:work`.

## Deployment notes

- **Redis**: not required locally (cache/session/queue all run on the
  `database` driver), but swap in `REDIS_*`/`CACHE_STORE=redis`/
  `SESSION_DRIVER=redis` for production — the database driver doesn't
  scale past a handful of concurrent terminals.
- **No Docker/Procfile in this repo** — deploy it like any standard
  Laravel app (PHP-FPM + a web server serving `public/`, `php artisan
  migrate --force` on release, the scheduler + queue worker as
  supervised processes).
- The register PWA's service worker (`public/sw.js`) is generated by
  `npm run build` and served from the site root (not `/build/`) so its
  scope can cover `/pos` — see `vite.config.js`'s comments if you
  change the build output layout.

## API quick reference

`POST /api/v1/login` (`{username, password}`) → bearer token, then
`Authorization: Bearer <token>` on everything else: `GET terminals`,
`GET items`, `GET items/barcode/{barcode}`, `GET payment-methods`,
`POST carts`, `GET carts/{cart}`, `POST|PATCH|DELETE carts/{cart}/lines[/{line}]`,
`POST|DELETE carts/{cart}/payments[/{payment}]`, `POST carts/{cart}/complete`
(requires an `Idempotency-Key` header). A terminal needs an open shift
(via the back-office `/shift` screen, or `OpenShiftAction` directly)
before a cart can be created against it.

## More

[PROJECT_STATUS.md](PROJECT_STATUS.md) has the full picture: what's
built, architectural decisions and why, known deviations from the
original audit/plan, and what's genuinely not started yet.
