



You are auditing a production Laravel point-of-sale application for correctness bugs,
incomplete work, and security vulnerabilities. Read real code and report evidence — never
infer a finding from a filename, a route name, or a test's existence.

## Stack and architecture

PHP 8.3 / Laravel 13 / Livewire 4. Fortify (web auth), Sanctum (API auth),
spatie/laravel-permission (RBAC), spatie/laravel-activitylog (audit trail), brick/money
(currency math), mike42/escpos-php (thermal receipts), barryvdh/laravel-dompdf (PDF),
picqer/php-barcode-generator.

- `app/Domain/{Catalog,Crm,Documents,Finance,Giftcards,Identity,Inventory,Loyalty,Promotions,Purchasing,Reporting,Sales,Taxation}`
  — domain logic as Actions/Models/Queries/Exceptions.
- `app/Livewire/**` — back-office CRUD (~90 components).
- `app/Http/Controllers/Api/**` — the register/terminal API (46 controllers total).
- `resources/js/pos/` — **offline-first PWA register**: Vue 3 + Pinia, IndexedDB (`db/`),
  and a `sync/` engine that queues sales taken while offline and replays them to the API.
- `routes/{web,api,backoffice}.php`, `app/Policies/`, `tests/` (97 test files, Pest).

## How to work

1. **Plan first.** Build a todo list covering the audit areas below, and work it in order.
   This is a long audit; the list is how you avoid dropping an area when context gets tight.

2. **Fan out with subagents.** Launch parallel `Explore`/`Task` agents for independent slices
   (e.g. one per domain group, one for the JS register, one for routes+policies coverage).
   Give each agent a narrow, concrete question and require it to return `file:line` evidence
   with a quoted snippet for each claim. Do not let an agent return a summary you cannot
   trace back to code.

3. **Protect your context — this is the single biggest risk to audit quality.**
   - `PROJECT_STATUS.md` is **161 KB**. Never read it whole. Grep it for specific terms
     (`bug`, `TODO`, the feature you're inspecting) and read only the surrounding lines.
   - Prefer `Grep` over `Read` for sweeps. Read whole files only when actually auditing them.
   - **Write findings to `AUDIT_FINDINGS.md` as you confirm them, not at the end.** If you run
     low on context, the report must already be on disk. Append incrementally.

4. **Report only — do not fix anything.** No edits to source, no refactors, no "while I was
   here" cleanups. The one file you write is `AUDIT_FINDINGS.md`. If you spot a one-line fix,
   describe it in the report and move on. I will decide what gets fixed.

5. **Never read or echo `.env`.** To confirm a config key exists, use `.env.example` or
   `config/*.php`. No secret values in your output, ever.

6. Note: the built-in `/security-review` skill reviews a *diff*, not an application. It is not
   a substitute for this audit. You may use it on recent commits as a supplement, but the
   scope here is the whole codebase.

## Audit areas

### 1. Money and financial correctness
- Every place money is summed, discounted, taxed, split, or refunded — cart totals, returns,
  gift cards, loyalty redemption, tips/commission, purchase orders, supplier invoices — must
  use `Brick\Money` consistently. Flag float/int arithmetic on prices, rounding applied before
  the final step, and unguarded currency mismatches.
- Multi-step financial operations (payment capture + stock decrement + ledger write; refund +
  restock; gift-card redeem + balance debit) must be inside a DB transaction. Hunt for the
  partial-write case: an exception after step one that leaves step two uncommitted.
- `app/Support/Idempotency/` — verify every mutating payment/order endpoint actually uses it,
  that keys are scoped so one terminal cannot block another's legitimate request, and that a
  replayed key cannot re-apply a stale mutation.

### 2. Offline register and sync trust boundary (`resources/js/pos/`)
Treat the browser as hostile; it holds the cart in IndexedDB while offline.
- **Does the server recompute totals, taxes, and discounts on sync, or does it trust
  client-supplied amounts?** A client-authoritative total is a critical finding.
- Replay: can a queued offline sale be submitted twice (retry, tab reopen, manual DB edit)
  and produce two sales or two stock decrements? Cross-check against the idempotency layer.
- Conflict resolution when an item's price, stock, or promotion changed between the offline
  sale and the sync.
- Clock skew: offline timestamps are client-controlled — check anything that trusts them
  (shift attribution, report bucketing, promotion validity windows).
- Auth: what happens to a queued sale when the token expired while offline?
- `PROJECT_STATUS.md` records a past bug in this sync engine — grep for it and check whether
  sibling occurrences of the same class were ever audited.

### 3. Concurrency and race conditions
- Stock adjustments, counts, and transfers under concurrent terminals — missing
  `lockForUpdate`, check-then-act races, overselling the last unit.
- `app/Livewire/Sales/Shift`, `Terminals` — double-open shifts, orphaned shifts, cash-drawer
  totals that can close inconsistently with the underlying transactions.

### 4. Authorization
- For **every** Livewire component and API controller action: confirm an actual policy or
  permission check gates it. Route middleware is not sufficient for Livewire — a component can
  be reached by direct component request. Any component whose siblings check but it doesn't is
  a likely bug; report the inconsistency.
- IDOR: can changing an order / cart / customer / gift-card ID reach another
  branch's/register's/tenant's record, for read or for write?
- Sanctum token abilities — are terminal tokens scoped, or does any valid token grant
  everything?
- `app/Policies/` — flag abilities that `return true` unconditionally as placeholders, and
  models referenced in policy registration with abilities left unimplemented.

### 5. Injection and input handling
- `app/Support/Printing/WindowsPrinterDiscovery.php` shells out to `powershell.exe` via
  Symfony `Process`. Confirm no user-controlled value reaches the argument array. Sweep the
  repo for every other `Process`/`proc_open`/`exec`/`shell_exec` and apply the same test.
- `DB::raw` / `whereRaw` / `orderByRaw` anywhere in Domain or Reporting queries — confirm
  bindings, not interpolation. Pay attention to sort/filter parameters reaching `orderByRaw`.
- `app/Livewire/Catalog/Items/Import.php` and any CSV/Excel path — server-side type and size
  validation, and CSV formula injection (`=`, `+`, `-`, `@` leading a cell) on export.
- Dompdf — check `isRemoteEnabled` / `isPhpEnabled` in config and whether any user-influenced
  data reaches a PDF template unescaped (SSRF / local file read).
- Mass assignment — audit each model's `$fillable`/`$guarded` against its real write paths
  (Livewire bindings, form requests). Look for user-settable fields that shouldn't be: roles,
  price overrides, discount amounts, `is_admin`-style flags, balances.

### 6. Recent feature work (verify these left no gaps)
Check `git log` for the last ~5 commits and audit each landed feature, specifically:
- *Business-type filtering for items and reports* — is the filter applied on **every** query
  path (list view, export, dashboard widget, API, report query object), or did a secondary
  path forget it and leak cross-business-type data?
- *Card payments requiring a terminal reference for manual methods* — enforced server-side, or
  bypassable by calling the API directly?
- *Change calculation in the payment process* — rounding direction, negative-change guard,
  and behavior when the tendered amount is edited after change is computed.
- *Windows printer discovery* — graceful fallback when `powershell.exe` is absent
  (Linux/container deploy); an uncaught exception here must not block checkout.

### 7. Incomplete work
- Grep `app/`, `routes/`, `resources/js/`, `resources/views/` for `TODO`, `FIXME`, `@todo`,
  `dd(`, `dump(`, `var_dump`, `ray(`, `console.log`.
- Actions with no caller; routes pointing at missing controller methods; **Blade `wire:click`
  handlers with no matching public method on the component** (a guaranteed runtime 500).
- Coverage gaps: domains under `app/Domain/` with no corresponding `tests/Feature/` directory,
  or a directory with conspicuously few tests relative to its surface.

### 8. Hardening
- Rate limiting on Fortify login and public API routes.
- Sanctum stateful domains / CORS vs. actual deployed origins.
- Are receipts, PDFs, and uploaded images written somewhere publicly browsable without an
  access check?
- Does the activity log actually capture the actions an auditor needs — voids, refunds, price
  overrides, permission changes, drawer opens — or only generic model saves?

## Output

Write `AUDIT_FINDINGS.md`, appending as you go. Group by the areas above. Order findings
**Critical → High → Medium → Low** within each group. Every finding gets:

| Field | Content |
|---|---|
| Severity | Critical / High / Medium / Low |
| Location | Clickable markdown link, e.g. `[CartLineController.php:88](app/Http/Controllers/Api/CartLineController.php#L88)` |
| What's wrong | One or two sentences |
| Why it matters | A concrete failure or exploit scenario — real inputs, real sequence of events, not "could be dangerous" |
| Suggested fix | What you'd change; do not make the change |

Separate **confirmed** findings (you read the code and traced the path) from **suspected** ones
(pattern-matched, needs verification). Do not pad the report — a short list of real,
traced bugs is worth far more than fifty speculative ones. If an area is clean, say so in one
line rather than inventing findings for it.

If you run `php artisan test` or `npm run test` to verify a hypothesis, quote failures verbatim
and flag them. Do not fix failing tests.
