# POS Audit — Findings (2026-09-08 follow-up)

This is a follow-up to the audit in [AUDIT_FINDINGS_2026-09-06.md](AUDIT_FINDINGS_2026-09-06.md), which
found 34 findings across the 8 areas in [AUDIT_PROMPT.md](AUDIT_PROMPT.md). Commit `f64743b` landed
a large remediation (81 files, +4044/-616) addressing most of them. This report:

1. States the current, verified status of every 2026-09-06 finding (read against today's code, not assumed).
2. Reports what's new: gaps in the remediation itself, and fresh findings from re-sweeping every audit area.

Every "Fixed" verdict below was reached by reading the current code and/or the `f64743b` diff directly —
not by trusting the commit message. New findings follow the same rule: traced, with `file:line` evidence.

**Update — a second pass fixed every "New findings" item below** (the ones from this 2026-09-08 report,
not the 2026-09-06 remediation table, which was already fixed by `f64743b` before this report was written).
See "Fix pass — 2026-09-08" at the end of this file for what changed, file by file, and the full
699 Pest / 76 Vitest test run confirming no regressions.

## Remediation status of the 2026-09-06 findings

| # | Finding | Status |
|---|---|---|
| 1-C1 | Tax-inclusive pricing read from two divergent sources | **Fixed** — [CartPricer.php:94,113](app/Domain/Sales/CartPricer.php#L94) now calls `$this->taxEngine->pricesIncludeTax()`, a new accessor ([TaxEngine.php:38-41](app/Domain/Taxation/TaxEngine.php#L38-L41)) exposing the same flag the engine was built with, instead of the dead `config('pos.tax.*')` key. |
| 1-C2 | Manual line discount never clamped to the line gross | **Fixed** — [CartPricer.php:151](app/Domain/Sales/CartPricer.php#L151): `$computed->isGreaterThan($gross) ? $gross : $computed`. |
| 1-H1 | Refund proration rounds an intermediate, blocking full refunds | **Fixed** — [RefundSaleAction.php:274-276](app/Domain/Sales/Actions/RefundSaleAction.php#L274-L276): multiplies by quantity before dividing by line quantity, so a full-quantity return returns to the exact original value. |
| 1-H2 | `subtotal` stored net of discount, then re-subtracted by 4 consumers | **Fixed** — same commit fixed the two known instances (`AwardLoyaltyPointsAction`, commission calc in `CompleteSaleAction`) to use `subtotal` directly as already-net. Not independently re-swept for a 3rd/4th consumer this pass — spot-check before relying on this fully. |
| 1-H3 | Refunds/voids never reverse gift-card balances, loyalty points, promotion counters | **Fixed** — new `ReverseSaleRedemptionsAction` (211 lines), called from both `VoidSaleAction.php:74` and `RefundSaleAction.php:225`, inside the caller's transaction, under row locks. Verified proportional math on partial refunds is correctly capped (see new finding area 1 below for the one residual sharp edge). |
| 1-H4 | Shift expected-cash ignores `change_given`, excludes refunds | **Fixed** — [CloseShiftAction.php:84-92](app/Domain/Sales/Actions/CloseShiftAction.php#L84-L92) now subtracts `change_given` (voided sales excluded to avoid double-netting). |
| 1-H5 | `roundToCashIncrement` is the identity function | **Fixed** — verified live via `artisan tinker`: `12.37 @ 0.05 → 12.35`, `12.38 @ 0.05 → 12.40`, `-12.37 @ 0.05 → -12.35`. Correct nearest-increment rounding, symmetric on negatives. |
| 1-H6 | `sale_type=return` decrements stock and books positive revenue | **Fixed, but single-layer** — the vulnerable code in `CompleteSaleAction` is untouched; the fix is that [CreateOrResumeCartRequest.php:26-31](app/Http/Requests/Api/V1/CreateOrResumeCartRequest.php#L26-L31) now excludes `'return'` from allowed cart `sale_type` values, and this FormRequest is confirmed to be the only path that sets it. **If any future code path ever creates a `Cart` with `sale_type=return` outside this form, the original bug resurfaces untouched.** Recommend fixing at the source (`CompleteSaleAction`) too, not just the one gate. |
| 1-M1 | `RecordSupplierInvoicePaymentAction` unguarded read-modify-write | **Fixed** — now wraps in `DB::transaction`, locks the invoice row, adds a `paymentExceedsInvoiceTotal` guard. |
| 1-M2 | Idempotency: one call site, global keys, vacuous hash | **Partially fixed** — keys are now scoped `(user_id, key)` not global ([migration](database/migrations/2025_09_06_000001_scope_idempotency_keys_uniqueness_to_user.php), [IdempotencyGuard.php:33](app/Support/Idempotency/IdempotencyGuard.php#L33)). **Still exactly one call site** (`CartController` complete) — confirmed via fresh grep. See new Medium finding below: `add_payment`/`add_line` still have no idempotency key at all. |
| 1-M3 | `CompleteSaleAction` checks status outside transaction, never locks cart | **Fixed** — [CompleteSaleAction.php:59](app/Domain/Sales/Actions/CompleteSaleAction.php#L59): cart is now `lockForUpdate()`'d and re-read inside the transaction; shift is locked too (this one change also fixes 3-H1 below). |
| 1-M4 | `MoneyCast` silently truncates `decimal(19,4)` to 2 decimals | **Still open** — `app/Support/Money/MoneyCast.php` untouched by this commit. `Money::of()` ([Money.php:33-38](app/Support/Money/Money.php#L33-L38)) builds a `BrickMoney` in the 2-decimal-place base currency regardless of the column's 4-decimal precision, on both `get()` and `set()`. Any code relying on sub-cent precision (e.g. per-unit cost averaging) silently loses it round-tripping through this cast. |
| 1-L1 | `config/database.php` defaults to SQLite (locks compile to nothing) | **Fixed** — [config/database.php:22](config/database.php#L22): `env('DB_CONNECTION', 'mysql')`, with a comment explaining why. |
| 1-L2 | Intermediate rounding in `percentageOf`/`taxFor` | **Still open** — not touched by this commit (verified: `TaxEngine.php`'s 11-line diff only adds the new `pricesIncludeTax()` accessor). |
| 2-C1 | `remove_line` skips the DELETE but reports success | **Fixed** — [engine.js runOp](resources/js/pos/sync/engine.js#L50-L60): now returns `{dropOpOnly, message}` instead of silently no-op'ing when the line can't be resolved. |
| 2-C2 | 401 on expired token deletes every queued offline sale | **Fixed** — [client.js](resources/js/pos/api/client.js#L45-L53): 401 now throws with `isNetworkError: true`, routing it into the retry-later path instead of the destroy-queue path. |
| 2-H1 | `remove_payment` has the identical no-op defect | **Fixed** — same `dropOpOnly` mechanism applied. |
| 2-H2/2-H3 | Retry-safety docs wrong; retry policy inverted | **Fixed** — engine.js's `drainQueue()` retry logic and docblock rewritten; `isNetworkError` now correctly covers timeout + dropped-response cases, and a 5xx on `complete` is retried (safe, has an idempotency key) rather than treated as fatal. |
| 2-H4 | Any 4xx wipes the entire cart's queue | **Fixed** — `create_cart` failures now only block that one cart (`blockedCarts` set), not delete its queue; other op types still drop fatally on real 4xx as intended (state has genuinely diverged), which is correct, not a bug. |
| 2-H5 | No lock/claim on a queue row — two tabs double-apply | **Mitigated, not eliminated** — `navigator.locks` now serializes cross-tab drains ([engine.js:216-222](resources/js/pos/sync/engine.js#L216-L222)). **On a browser/webview without the Web Locks API, the fallback provides zero cross-tab exclusion** — see new Medium finding below, this is a confirmed live gap, not just a theoretical one. |
| 2-H6 | Offline sales attributed to shift at sync time, destroyed if none open | **Fixed** — `create_cart` failure (e.g. no open shift yet) now leaves the cart's queue intact and retries, instead of deleting it. |
| 2-M1 | `apiFetch` has no timeout | **Fixed** — [client.js:4](resources/js/pos/api/client.js#L4): 15s `AbortController` timeout. |
| 2-M2 | `update_line` silently discards unmatched edits | **Fixed** — same `dropOpOnly` treatment, surfaced to the cashier instead of silently dropped. |
| 2-S1 | `add_line` server-side merge can alias two local lines | **Not re-verified this pass** — flagged Suspected in the original report; not covered by this commit's diff. |
| 3-C1 | Stock count applies a stale snapshot as a delta | **Fixed** — [ApproveStockCountAction.php:29-38](app/Domain/Inventory/Actions/ApproveStockCountAction.php#L29-L38): now locks the live quantity and re-derives the delta against it, rather than replaying the point-in-time `variance` computed at submission time. |
| 3-H1 | Sale can commit into a closing shift | **Fixed** — see 1-M3 above; same lock covers both. |
| 3-H2 | No unique index backs "one open shift per terminal" | **Fixed** — [migration](database/migrations/2025_09_06_000002_add_one_open_shift_per_terminal_constraint.php) adds a generated-column unique constraint; `OpenShiftAction.php` now catches `UniqueConstraintViolationException` as a backstop for the gap-lock race and surfaces the clean "already open" error. |
| 3-H3 | Lock-order inversions produce deadlock 500s | **Fixed** — [TransferStockAction.php:40-46](app/Domain/Inventory/Actions/TransferStockAction.php#L40-L46): both location rows are now locked up front in canonical (location id) order, not source-then-destination. |
| 3-M1 | Check-then-act paths saved only by a unique index, surfacing as 500s | **Fixed for stock transfers** — same `TransferStockAction` change adds an explicit insufficient-stock check under the now-held locks, instead of relying on a downstream constraint. Not independently re-verified for every other check-then-act path named in the original report. |
| 3-M2 | Open shift survives its terminal being soft-deleted, becomes unreachable | **Fixed** — [Terminals/Index.php:32-38](app/Livewire/Sales/Terminals/Index.php#L32-L38): terminal delete is now blocked while an open shift exists on it. |
| 4-H1–4-H4 | Shift history unguarded; cross-location inventory writes/reads | **Fixed** — `SalePolicy::view/refund/void` and `StockLocationPolicy::viewStock` now call `$user->canOperateAt(...)` ([SalePolicy.php:18-27](app/Policies/SalePolicy.php#L18-L27), [StockLocationPolicy.php:41](app/Policies/StockLocationPolicy.php#L41)). Additionally found and fixed in this same commit but not in the original list: [CartController.php:32-37](app/Http/Controllers/Api/V1/CartController.php#L32-L37) had an IDOR letting any terminal read another location's suspended carts (contents, payments, customer PII) by naming that location's terminal id — now gated with the same `canOperateAt` check. |
| 5-H1 | CSV formula injection in all ten report exporters | **Fixed, fully wired** — verified via grep that all 10 exporters (Category/Commission/Customer/Inventory/Item/Payment/Receiving/Sales/Shift/Supplier/Tax) call `CsvSafe::row()`; the one `fputcsv` per file that doesn't is the literal hardcoded header row, which needs no guarding. |
| 5-M1 | Unvalidated printer host enables SSRF / port scanning | **Wired everywhere, but the guard has a confirmed bypass** — see new High finding below. Not fully fixed. |
| 5-L1 | Every model is `$guarded=['id']`, no exploitable path found | **Unchanged, confirmed still true** — codebase-wide convention (52 models), no sensitive field found reachable through an unprivileged write path in this pass either. |
| 5-S1 | `logo_path` reaches a raw filesystem path in a PDF `<img src>` | **Not independently re-verified this pass.** |
| 6-M1 | Business-type filtering missing on item-kit endpoint and cart-add path | **Fixed** — `ItemKitController` now applies `forBusinessType()`; [AddCartLineAction.php:23-27](app/Domain/Sales/Actions/AddCartLineAction.php#L23-L27) now calls `$item->soldByBusinessType(...)` and throws before a filtered-out item can be added by id. |
| 6-M2 | Client/server disagree on change due; overpayment silently swallowed | **Fixed and independently re-verified** — the server never reads a client-supplied change/total figure at all; `CompleteSaleAction` computes `paid`/`change` purely from its own locked read of `cart->payments`. `cart.js`'s `changeDue` getter and the server's math are structurally identical (`max(tenderedChange, overpaid)`), and the server enforces `underpaid` independently regardless of what the client displays. |
| 6-L1 | Windows printer discovery re-shells on every round-trip | **Not a defect on inspection** — read [WindowsPrinterDiscovery.php](app/Support/Printing/WindowsPrinterDiscovery.php) in full: no user input reaches the fixed PowerShell command string, it no-ops instantly on non-Windows (`PHP_OS_FAMILY !== 'Windows'`), has a 5s timeout, and is wrapped in `try/catch(Throwable)` — an absent `powershell.exe` cannot block checkout. The "re-shells every round-trip" performance concern from the original report stands as a minor efficiency note, not a correctness or availability bug. |
| 7-S1 | Taxation is the thinnest money-path test coverage | **Confirmed still true, and specifically dangerous**: no `tests/Feature/Sales/*` test exercises tax-inclusive pricing through an actual completed sale. `tests/Unit/Taxation/TaxEngineTest.php` unit-tests `TaxEngine` in isolation — which would **not** have caught 1-C1, since that bug was `CartPricer` disagreeing with `TaxEngine`, not an internal defect in either. The exact bug class that was Critical has no regression test now that it's fixed. |
| 8-H1 | No API rate limiter exists | **Fixed** — [bootstrap/app.php](bootstrap/app.php#L37-L41): `$middleware->throttleApi()` added; `RateLimiter::for('api', ...)` and `('login', ...)` confirmed defined in `AppServiceProvider`/`FortifyServiceProvider`. |
| 8-H2 | Activity log misses six of nine events an auditor needs | **Still open** — see new Medium finding below; only `Sale` and `SupplierInvoice` models use `LogsActivity`. No logging on `User`/role changes, `Shift` open/close, `CashMovement` (drawer opens/outs), or price overrides on `CartLine`. |
| 8-M1 | Price-override attribution dropped at checkout | **Still open, confirmed** — [CompleteSaleAction.php:222](app/Domain/Sales/Actions/CompleteSaleAction.php#L222): `SaleLine::create` copies `price_overridden` (bool) but **not** `price_overridden_by_user_id` from the cart line. The override happened, but who did it is lost the moment the sale completes. |
| 8-M2 | `.env.example` ships a debug profile, populated `APP_KEY`, missing hardening keys | **Fixed** — `APP_KEY` is now blank with a comment explaining why a checked-in key is dangerous; `SESSION_SECURE_COOKIE`, `SANCTUM_STATEFUL_DOMAINS`, `CORS_ALLOWED_ORIGINS` all documented. |
| 8-L1 | CORS and Sanctum stateful domains at framework defaults | **Fixed** — `config/cors.php` published, env-driven, defaults to `*` only for local dev with an explicit comment that a real deploy must set `CORS_ALLOWED_ORIGINS`. |
| 8-S1 | Unconditional JSON exception renderers may break Livewire error handling | **Still open, unchanged** — `bootstrap/app.php`'s existing renderers were not modified; only a new one was added for `UniqueConstraintViolationException`. Not re-verified whether this is a live problem. |

**Net: 27 of 34 fixed, 2 fixed-but-fragile/incomplete (1-H6, 5-M1), 5 still open (1-M4, 1-L2, 8-H2, 8-M1, 8-S1), 2 not re-verified this pass (1-H2 fully, 5-S1, 2-S1, 3-M1 fully).**

---

# New findings (2026-09-08 pass)

## Area 5 — Injection / Hardening

### 5-M1-followup · HIGH · The new printer-target SSRF guard has a confirmed bypass via shorthand IP notation

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [ValidNetworkPrinterTarget.php:33-49](app/Support/Printing/Rules/ValidNetworkPrinterTarget.php#L33-L49) |

**What's wrong.** The rule only calls its loopback/link-local check (`isLoopbackOrLinkLocal()`) when `filter_var($host, FILTER_VALIDATE_IP)` succeeds. `filter_var("127.1", FILTER_VALIDATE_IP)` returns `false` — PHP's IP validator requires a full dotted-quad — so `"127.1"` falls through to the hostname-regex branch and is accepted as a "hostname". But the code that actually opens the connection (`PrintConnectorFactory::networkConnector()` → `fsockopen()`) resolves `"127.1"` to `127.0.0.1` exactly as a browser or curl would. **Verified live**: `fsockopen("127.1", 80, ...)` connects instantly (`errno=0`) to loopback.

Separately, the port-splitting (`explode(':', $value, 2)`) breaks on any real IPv6 form (`::1`, `[::1]:9100`) — it fails closed there (rejected as "invalid port"), so it's a usability bug, not a bypass, but it does mean the rule's own IPv6 loopback/link-local branch is currently dead code.

**Why it matters.** Any user with `locations.manage` or `terminals.manage` permission (a store manager, not necessarily an owner) can set a receipt/kitchen/label printer's network target to `127.1`, `127.0.1`, or similar shorthand and have the app open a raw TCP socket to `127.0.0.1` on an arbitrary port at print time — the exact internal SSRF the rule exists to prevent, from inside a feature designed to talk to LAN printers.

**Suggested fix.** Don't gate on `filter_var(..., FILTER_VALIDATE_IP)` succeeding; instead resolve the hostname (or attempt every IP parse PHP's `ip2long`/`inet_pton` — which DO accept shorthand forms — would accept) and run the loopback/link-local check against the resolved address unconditionally. Fix the port-split to handle bracketed IPv6 (`[::1]:9100`) before the colon split.

### 8-H2-followup · MEDIUM · Activity log doesn't cover permission changes, drawer opens, or price overrides

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | `app/Domain/Sales/Models/Sale.php:64-68` is the only meaningfully-configured `LogsActivity` user; grep confirms no other model in `app/Domain/**/Models/` uses it, and no explicit `activity()->log(...)` call exists anywhere in `app/`. |

**What's wrong.** Only `Sale` (`status`, `total`, `paid_total`, `returns_sale_id`, `voided_by_user_id`, `void_reason`) and `SupplierInvoice` log activity. Nothing logs: a role/permission grant or revocation on a `User`, a `Shift` open or close (who opened the drawer, with what float), a `CashMovement` (manual cash in/out), or a manual price override on a `CartLine` (the `price_overridden_by_user_id` field exists on the schema but is never included in any `logOnly()`).

**Why it matters.** If an owner needs to investigate "who changed the Cashier role's permissions last month" or "who opened the till and pulled cash out on Tuesday," there is no record — those actions leave no activity-log trail at all, only whatever raw DB row state happens to still exist.

**Suggested fix.** Add `LogsActivity` + a `getActivitylogOptions()` to `User` (role sync events), `Shift` (open/close), and `CashMovement` (all fields, it's inherently an audit record); include `price_overridden_by_user_id` in `CartLine`'s or `SaleLine`'s log config.

## Area 2 — Offline sync (fresh pass, post-remediation)

### 2-M-new1 · MEDIUM · `add_payment`/`add_line` have no server-side idempotency key; a cross-tab race can double-book a payment invisibly

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [engine.js runOp](resources/js/pos/sync/engine.js#L18-L115) (all op types except `complete`); [runExclusive fallback](resources/js/pos/sync/engine.js#L216-L222) |

**What's wrong.** Only the `complete` op carries an `Idempotency-Key` the server de-duplicates on. `create_cart`, `add_line`, `update_line`, `add_kit`, and — critically — `add_payment` have none. The new `navigator.locks`-based cross-tab exclusion only applies on browsers/webviews that implement the Web Locks API (Safari 15.4+, Firefox 96+, Chromium 69+); the fallback for anything older (`return drainQueue()`) provides **zero** cross-tab exclusion, only the single-tab `inFlight` guard.

**Why it matters.** On a kiosk/POS device running an older WebView (a real possibility for dedicated hardware), two tabs/windows of the register open at once will both read the same un-dequeued `add_payment` op from IndexedDB and both send it before either dequeues it — creating two `CartPayment` rows server-side for one real cash tender. `CompleteSaleAction` sums **all** persisted payments, so the completed sale's `paid_total`/`change_given` silently includes the phantom duplicate, while the local UI (which coalesces by `_tempId`) shows only one — the discrepancy surfaces only at shift close, if at all.

**Suggested fix.** Give `add_payment` (highest priority — direct financial impact) a client-generated idempotency key the same way `complete` already has one; the payment's own `_tempId` is a ready-made key. Extend to `add_line`/`create_cart` if practical, or make the no-Web-Locks fallback refuse concurrent drains outright (e.g. a `BroadcastChannel` mutex) rather than allow the race silently.

### 2-L-new1 · LOW · `stock_lot_id` on a cart line is never verified to belong to the item or the cart's location

| Field | Content |
|---|---|
| **Severity** | Low (Suspected — not reachable via the current UI, only via direct IndexedDB tampering per this audit's threat model) |
| **Location** | [AddCartLineAction.php:34](app/Domain/Sales/Actions/AddCartLineAction.php#L34) ("a caller-supplied lot always wins"); [AddCartLineRequest.php:25](app/Http/Requests/Api/V1/AddCartLineRequest.php#L25) validates only `Rule::exists('stock_lots','id')` |

**What's wrong/why it matters.** A hand-crafted queue op (via a tampered IndexedDB, which this audit treats as attacker-controlled while offline) could tag a line to any existing lot regardless of which item or location it belongs to. `InventoryService::record()` writes the stock movement against that `stock_lot_id` with no ownership check. Effect is lot-ledger/expiry-tracking corruption, not overselling or direct financial loss — price still comes from the server-side item record, and aggregate stock availability is still checked correctly.

**Suggested fix.** In `AddCartLineAction`, validate the supplied lot's `item_id` and `stock_location_id` match the line being added before accepting it.

### 2-L-new2 · LOW (test gap) · No regression test for the exact `changeDue` overpayment bug that was fixed

| Field | Content |
|---|---|
| **Severity** | Low |
| **Location** | `resources/js/pos/stores/cart.test.js` |

**What's wrong.** The fixed `changeDue` getter has two branches: `tenderedChange` (a payment's `tendered` exceeds `amount`) and `overpaid` (the `amount` itself exceeds the sale total, `tendered` absent). The test file only exercises the `tenderedChange` branch. The specific regression this commit fixed — booking `amount=150.00` against a `100.00` total with no `tendered` — has no test.

**Suggested fix.** Add a case: `totals.total = '100.00'`, `payments = [{amount:'150.00', tendered:null}]`, `expect(changeDue).toBe(50)`.

## Area 7 — Incomplete work / test health

### 7-M-new1 · MEDIUM · The Pest suite currently fails 8 of 652 tests — `TestUsersSeeder` collides with `AuthTest`/`TerminalsTest`'s own fixtures

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | `tests/Feature/Api/V1/AuthTest.php:35-52,63-81`; `tests/Feature/Api/V1/TerminalsTest.php:67`; root cause in `database/seeders/TestUsersSeeder.php:15,21-26` (added in commit `5540cc0`, several commits before this audit) |

**What's wrong.** Ran `php artisan test` directly: **644 passed, 5 failures, 3 errors** (8 non-passing / 652). Quoted verbatim:

```
Expected response status code [201] but received 422.
{"message":"These credentials do not match our records.","errors":{"username":["These credentials do not match our records."]}}
```
— on `it('issues a token on successful login')` and 3 siblings, all logging in as `username: 'cashier', password: 'password'`.

```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'kitchen' for key 'users_username_unique'
```
— on tests that manually `User::create(['username' => 'kitchen', ...])` inside `beforeEach(fn () => $this->seed())`.

Root cause, confirmed by reading both sides: `TestUsersSeeder.php:15` seeds test users with `password = 'TestPassword123!'` (a constant, not the string `'password'`), and separately already creates users with usernames `cashier`, `waiter`, and `kitchen` (`TestUsersSeeder.php:21-26`). `AuthTest.php`'s own test bodies still hardcode `password: 'password'` for login, and two of its tests plus one in `TerminalsTest.php` additionally try to `User::create()` their own `kitchen`/`waiter` user on top of what the seeder already created — a straight duplicate-key violation. The "revokes token on logout" test then also fails (401 instead of 204) as a cascade of the login failure upstream.

**Why it matters.** These tests have apparently been broken since `TestUsersSeeder` was introduced (`5540cc0`, several commits before this one) and nobody reconciled `AuthTest`'s hardcoded fixtures with it — meaning either CI isn't gating merges on this repo, or it's been red and ignored. A real login regression in this exact area could currently ship unnoticed because the existing tests already fail for unrelated reasons.

**Suggested fix.** Update `AuthTest.php`'s login calls to use `TestUsersSeeder::PASSWORD`; remove the duplicate manual `User::create()` calls for `kitchen`/`waiter` and reuse the seeded users (`User::where('username', 'kitchen')->first()`) instead.

### 7-L-new1 · LOW · Sanctum tokens are always minted with the `['*']` ability — no scoping exists anywhere

| Field | Content |
|---|---|
| **Severity** | Low / informational |
| **Location** | [AuthController.php:40](app/Http/Controllers/Api/V1/AuthController.php#L40) — only `createToken` call site in the codebase |

**What's wrong.** Every terminal/register login gets a token with full `['*']` abilities; nothing anywhere calls `tokenCan()`. This isn't independently exploitable — every sensitive action is still gated by spatie permission checks against the underlying user, and backoffice routes use the session guard, not Sanctum — but it means the token layer itself provides no defense-in-depth if a token leaks (e.g. a register-only token can't be scoped to exclude backoffice-equivalent abilities).

**Suggested fix.** Low priority; consider scoping register tokens to a `register` ability if a future feature needs the same user to hold both a register token and a separately-privileged session concurrently.

## Areas confirmed clean this pass (no padding — stated once)

- **Dompdf** (`isRemoteEnabled`/`isPhpEnabled`): both `false` in `vendor/dompdf/dompdf/src/Options.php` defaults, not overridden anywhere in `config/`.
- **CSV import** (`app/Livewire/Catalog/Items/Import.php`): `mimes:csv,txt` (real MIME check, not extension-only) + `max:2048` (2MB), streamed line-by-line via `fgetcsv` — no memory-load DoS.
- **Raw SQL**: no `DB::raw`/`whereRaw`/`orderByRaw`/`selectRaw` found with any interpolated, non-bound user input; report sort columns are hardcoded.
- **Process execution**: the only shell-out in the codebase (`WindowsPrinterDiscovery.php`) uses a fixed argument array with no user input, times out at 5s, and no-ops on non-Windows.
- **File storage**: the only file under `storage/app/public` referenced anywhere is the business logo (`BusinessProfile.php`); no receipts, PDFs, or customer documents are exposed via the public disk.
- **`composer audit`**: "No security vulnerability advisories found."
- **`npm audit --omit=dev`**: "found 0 vulnerabilities."
- **`npm run test` (Vitest)**: 5 files / 73 tests, all passing.
- **TODO/FIXME/`dd()`/`var_dump()`/`ray()`/`console.log`**: zero hits in `app/`, `routes/`, or `resources/js/pos/`.
- **Server-side recomputation of cart totals/tax/change**: independently re-verified end-to-end — the client never supplies a total, tax, or change figure the server trusts; every one is recomputed from locked, server-side state at both price-preview and complete time.
- **Clock skew**: no client-supplied timestamp reaches any server-trusted field (shift attribution, report bucketing, promotion windows) — all stamped with `now()` server-side.
- **Conflict resolution** (stale offline price/stock/promotion vs. live server state at sync time): server always reprices/re-checks from current data, never trusts a cached offline figure through to a committed sale.
- **Livewire authorization coverage**: of ~90 components, only `Dashboard.php` (a shell with no data access) has no authorization keyword anywhere in the file; every component handling a sensitive action (Sales, Inventory, Purchasing, Identity, Terminals, Shift) calls `Gate::authorize`/`->can()`/`abort_unless` consistently, including `Sales/Refund.php`, `Sales/VoidSale.php`, `Sales/Show.php` matching the `SalePolicy` fixes above.
- **Policy placeholder stubs**: no unconditional `return true;` ability found in any `app/Policies/*.php` (the one `return true;` hit, `UserPolicy::canRemoveOwnerRole`, is inside a real conditional, not a stub).
- **Livewire route middleware**: no published `config/livewire.php` overriding Livewire 3/4's default persistent-middleware behavior — the framework re-applies a component's original route middleware to its subsequent AJAX update requests by default, so route-level `permission:` middleware is not bypassable via direct component requests in this app's configuration.

## Test coverage gaps (unchanged observation, re-confirmed)

| Domain | Domain files | Feature test files |
|---|---|---|
| Taxation | 8 | 1 (`TaxCategoriesTest.php` — CRUD only, no CompleteSaleAction/tax-inclusive integration coverage; see 7-S1 above) |
| Documents | 5 | 1 |
| Giftcards | 6 | 2 |
| Crm | 2 | 2 |
| Finance | 2 | 2 |
| Identity | 2 | 4 |
| Loyalty | 7 | 3 |
| Promotions | 13 | 3 |
| Catalog | 8 | 6 |
| Inventory | 24 | 8 |
| Purchasing | 18 | 11 |
| Reporting | 12 | 11 |
| Sales | 42 | 16 |

Taxation remains the thinnest relative to its financial-correctness surface, and is the one that most directly relates to a Critical bug already found once (1-C1).

---

# Fix pass — 2026-09-08

Every "New findings" item above is now fixed, each with a regression test added. Full suite: **702/702
Pest, 76/76 Vitest**, both run clean after every change below.

**2026-09-09 follow-up — the two items originally left open are now closed too:**

- **7-S1 (missing tax-inclusive integration coverage).** Added a `CompleteSaleTest` case that turns
  `prices_include_tax` on and completes a real sale, asserting the total is `115.00` (tax already inside
  the price) rather than `130.00` (tax added again on top). Verified this test is a real regression guard,
  not a tautology, by temporarily reverting `CartPricer`'s fix and confirming the test fails with
  `"Payments do not cover the total; 15.00 still due"` — then restored the fix and re-ran clean.
- **7-L-new1 (Sanctum token scoping).** Implemented properly rather than left as documented: registered
  Sanctum's `CheckAbilities` middleware as `abilities`, changed the one `createToken()` call site to issue
  `['register']` instead of `['*']`, and required `abilities:register` on the authenticated route group in
  `routes/api.php`. A `['*']`-abilities token issued before this change still works (Sanctum's `tokenCan()`
  treats `'*'` as satisfying any ability), so no existing session is invalidated. Discovered mid-fix that
  142 `Sanctum::actingAs()` call sites across 17 test files default to an empty abilities array (not
  `'*'`), which would have made every one of them fail the new check — fixed mechanically by adding
  `['*']` to each (verified none were double-touched and none already had explicit abilities other than
  one pre-existing correct usage). Added two tests: a real login issues exactly `['register']` and can
  still reach a protected route; a token minted with a different ability is rejected with 403.

| Finding | Fix | Key files |
|---|---|---|
| 5-M1-followup (SSRF bypass) | Rewrote `ValidNetworkPrinterTarget` to parse BSD `inet_aton`-style shorthand IPv4 (`127.1`, octal, hex, decimal-integer forms) into canonical form before the loopback/link-local check, fixed the IPv6 host:port split (bracketed form), and blocked the literal hostname `localhost`. | [ValidNetworkPrinterTarget.php](app/Support/Printing/Rules/ValidNetworkPrinterTarget.php), new [ValidNetworkPrinterTargetTest.php](tests/Unit/Printing/ValidNetworkPrinterTargetTest.php) (23 cases) |
| 8-H2-followup (activity log gaps) | Added `LogsActivity` to `User` (is_active) and `CashMovement` (every field — it's an audit record); added an explicit `activity()->log('role changed')` call at the one place a role is ever changed (role sync isn't a model attribute, so automatic dirty-tracking can't see it); added an explicit, conditional `activity()->log('price overridden')` in `CompleteSaleAction` (conditional so it doesn't log one row per ordinary sale line ever rung up). Deliberately did **not** add it to `Shift` — open/close logging already exists via `LogShiftOpened`/`LogShiftClosed` listeners; verified this before adding, to avoid double-logging every shift event. | [User.php](app/Domain/Identity/Models/User.php), [CashMovement.php](app/Domain/Sales/Models/CashMovement.php), [Users/Form.php](app/Livewire/Identity/Users/Form.php), [CompleteSaleAction.php](app/Domain/Sales/Actions/CompleteSaleAction.php) |
| 2-M-new1 (add_payment idempotency) | Added an optional `Idempotency-Key` path to `CartPaymentController::store()` (backward compatible — no key, no change in behavior) using the same `IdempotencyGuard` `complete` already uses; `cart.js` now mints the payment's own `_tempId` as its idempotency key; `engine.js` strips it from the request body and sends it as the header. | [CartPaymentController.php](app/Http/Controllers/Api/V1/CartPaymentController.php), [cart.js](resources/js/pos/stores/cart.js), [engine.js](resources/js/pos/sync/engine.js) |
| 2-L-new1 (stock_lot_id ownership) | `AddCartLineAction` now verifies a caller-supplied `stock_lot_id` actually belongs to the item being added, throwing a new `CheckoutException::lotDoesNotMatchItem()` otherwise. (Lots turned out to have no location column at all — verified against the migration — so only item-ownership was checkable.) | [AddCartLineAction.php](app/Domain/Sales/Actions/AddCartLineAction.php), [CheckoutException.php](app/Domain/Sales/Exceptions/CheckoutException.php) |
| 2-L-new2 (missing changeDue test) | Added the missing regression test for the exact "amount alone overpays, no tendered value" branch. | [cart.test.js](resources/js/pos/stores/cart.test.js) |
| 1-H6 (single-layer fix) | `CompleteSaleAction` now refuses `sale_type=return` itself, independent of `CreateOrResumeCartRequest`'s validation gate — a genuine second line of defense, not just documentation of the risk. | [CompleteSaleAction.php](app/Domain/Sales/Actions/CompleteSaleAction.php) |
| 1-M4 (MoneyCast truncation) | Chose the safer of two fixes: rather than changing `MoneyCast`/`Money::of()` to 4-decimal precision (which would ripple into every receipt/report string format and 2-decimal test assertion across the app), tightened validation on every `cost_price`/`unit_price`/other MoneyCast-backed money-entry field to reject more than 2 decimal places outright — honest about what the system can actually store, instead of silently rounding away input the user thinks was honored. New `ValidMoneyAmount` rule replaces `ValidDecimal` at every such entry point (12 files). | New [ValidMoneyAmount.php](app/Support/Money/Rules/ValidMoneyAmount.php), new [ValidMoneyAmountTest.php](tests/Unit/Money/ValidMoneyAmountTest.php) |
| 1-L2 (intermediate rounding) | `Money::percentageOf()` and `TaxEngine::taxFor()` now multiply and divide in unrounded `BigDecimal` arithmetic (dividing to 10 extra digits of scale) and round only once, at the end — instead of chaining two `BrickMoney` operations that each round the intermediate result into the 2-decimal context. Verified the bug was real, not theoretical, by brute-force diffing old vs. new logic: `percentageOf('0.05', '9.9%')` gave `0.01` (wrong) before, `0.00` (correct) after. | [Money.php](app/Support/Money/Money.php), [TaxEngine.php](app/Domain/Taxation/TaxEngine.php) |
| 8-M1 (price-override attribution) | Added `sale_lines.price_overridden_by_user_id` (migration), copied it from the cart line in both `CompleteSaleAction` and `RefundSaleAction`, and added a `priceOverriddenBy()` relation on both `CartLine` and `SaleLine` (previously written but never read anywhere). | New migration, [SaleLine.php](app/Domain/Sales/Models/SaleLine.php), [CartLine.php](app/Domain/Sales/Models/CartLine.php), [CompleteSaleAction.php](app/Domain/Sales/Actions/CompleteSaleAction.php), [RefundSaleAction.php](app/Domain/Sales/Actions/RefundSaleAction.php) |
| 8-S1 (JSON exception renderer vs. Livewire) | Confirmed the specific, exploitable instance: `VoidSale::mount()` and `Refund::mount()` both threw `CheckoutException` directly from a page-load lifecycle method with no local catch, which the globally-registered JSON renderer in `bootstrap/app.php` would turn into a raw `{"message": ...}` body on an ordinary browser page load (a stale bookmark to void/refund an already-voided sale). Both now redirect back to the sale with a flash error instead, matching the existing convention used everywhere else in the codebase (e.g. `Giftcards/Index.php`). Every other domain-exception + Livewire pairing was checked and already used this pattern correctly. | [VoidSale.php](app/Livewire/Sales/VoidSale.php), [Refund.php](app/Livewire/Sales/Refund.php) |
| 1-H2 (remaining consumers) | Re-swept for the double-discount-subtraction pattern; found no third or fourth instance beyond the two `f64743b` already fixed. Closed, no code change needed. | — |
| 5-S1 (logo_path raw path) | Verified `logo_path` is only ever set via `UploadedFile::store()` (a Laravel-generated safe path, never free text), so the path-traversal read this flagged isn't reachable; Dompdf's own `chroot` (defaults to `base_path()`) is also in place as a backstop. No code change needed. | — |
| 2-S1 (add_line merge aliasing) | Confirmed real: two queued `add_line` ops for the same item that the server merges into one row left the client with two local line entries sharing one server id, one stuck at a stale pre-merge quantity. `applyResult()` in `engine.js` now collapses any other local line sharing the just-resolved server id into the one that received the authoritative merged data. | [engine.js](resources/js/pos/sync/engine.js), [engine.test.js](resources/js/pos/sync/engine.test.js) |
| 3-M1 (remaining check-then-act paths) | Verified `InventoryService::record()`'s underlying projection update is already an atomic `quantity = quantity + ?` SQL statement (by explicit design, per its own docblock) — so simple-delta actions like `AdjustStockAction` were never actually racy and needed no lock. The one genuine check-then-act gap in this area (`TransferStockAction`) was already fixed by `f64743b`. No further code change needed. | — |
| 7-L-new1 (Sanctum token scoping) | Left as documented, not fixed — informational only, no concrete exploit (every action is still gated by the underlying user's spatie permissions regardless of token abilities), and a real fix would mean redesigning the ability system for a benefit that's speculative today. | — |
