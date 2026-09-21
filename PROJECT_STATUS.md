# OSPOS Next — Laravel rebuild

A ground-up Laravel rebuild of Open Source Point of Sale, incorporating the
capability gaps identified in the audit of the CodeIgniter 4 original
(`../opensourcepos`).

## Decisions locked

| Decision | Choice |
|---|---|
| Tenancy | Single business, multi-location |
| Front-end | Livewire back-office + Vue 3 register PWA (offline) |
| Data | Greenfield with demo seed data |
| Tax | Single-rate VAT/GST with categories; jurisdiction matrix designed in, switched off |

## Stack

Laravel 13.25 · PHP 8.4 · MariaDB 10.4 (`utf8mb4`) · Livewire 4 · Sanctum ·
Fortify · spatie permission / settings / activitylog · brick/money · dompdf ·
picqer barcode · mike42/escpos-php · Pest 4 · Larastan · Pint

## Status

**Phase 0-3 and Phase 5-11 complete and verified** (Phase 4, payment
gateway drivers, was explicitly descoped — cash/card-in-person is all this
POS needs). 72 tables, 635 Pest tests + 67 Vitest tests passing.

### Done
- Full schema across 13 domain migrations
- Money handling via `brick/money` — no float arithmetic anywhere
- Pure, unit-tested `TaxEngine` (inclusive + exclusive, grouping, rounding)
- `CartPricer` — discounts, tax, Swedish cash rounding
- `CompleteSaleAction` — transactional checkout with `FOR UPDATE` stock locking
- `InventoryService` — append-only ledger + atomic projection + reconcile command
- `DocumentNumberGenerator` — row-locked, gap-free, no duplicate invoice numbers
- Roles/abilities seeded (Owner, Manager, Cashier, Stock Clerk, Accountant, Reports Only)
- Demo data: 2 locations, 2 terminals, 5 items, customer, supplier, 2 users
- **Shift lifecycle** — `OpenShiftAction`/`CloseShiftAction`, `ShiftException`,
  `ShiftOpened`/`ShiftClosed` events, denomination-based cash reconciliation
  (`app/Domain/Sales/Actions`). This is the prerequisite `CompleteSaleAction`
  already required (`CheckoutException::shiftClosed()`) but nothing could
  satisfy until now.
- **Livewire 4 back-office** (`app/Livewire/`, `resources/views/layouts/app.blade.php`,
  `routes/backoffice.php`): full CRUD with search/pagination/policies/tests for
  Categories, Items (+ primary barcode), Suppliers, Customers, Stock Locations
  (+ read-only stock levels view), Users & Roles (with a last-active-Owner
  safeguard). Auth wired via Fortify (`auth.login` view) + a `/dashboard` landing page.
- Authorization: one Policy per entity in `app/Policies/`, registered in
  `AppServiceProvider`, backed by spatie/laravel-permission abilities
  (`permission:` route middleware aliased in `bootstrap/app.php`). Added a
  `locations.*` ability group — the one real gap found in the seeded abilities.
- **Sanctum `/api/v1` JSON API** (`routes/api.php`,
  `app/Http/Controllers/Api/V1/`, `app/Http/Requests/Api/V1/`,
  `app/Http/Resources/Api/V1/`): token login/logout, catalog browse +
  barcode lookup, payment-method list, cart create/resume (idempotent on
  `client_uuid`), cart line add/update/remove (repeat scans of the same item
  merge quantity; price snapshotted at add-time), cart payment add/remove,
  live cart totals via `CartPricer` (never persisted), and checkout via
  `CompleteSaleAction` — reused exactly as built in Phase 1, untouched.
  New domain Actions: `CreateOrResumeCartAction`, `AddCartLineAction`,
  `AddCartPaymentAction` (same package/style as `OpenShiftAction`).
- **Idempotent checkout** — `POST /carts/{cart}/complete` requires an
  `Idempotency-Key` header. `app/Support/Idempotency/` (`IdempotencyGuard`,
  `IdempotencyKey`, `IdempotencyConflictException`) finally puts the
  `idempotency_keys` table (schema-only since Phase 0) to use: the guard
  wraps its own transaction around `CompleteSaleAction` so the `Sale` row and
  the cached response commit atomically together, replays byte-identical
  responses for a repeated key, and 409s if the same key is reused with a
  different request. `CheckoutException`/`ShiftException` now render as
  clean 4xx JSON (`bootstrap/app.php`) instead of 500s.
- **Vue 3 register PWA** (`resources/js/pos/`, served at `/pos` via
  `resources/views/pos.blade.php`, no Laravel session/`auth` middleware —
  it's a self-contained SPA authenticating with its own bearer token
  against `/api/v1`): installable, offline-capable barcode → qty → tender →
  complete flow.
  - **Auth/setup**: `stores/auth.js` (token in `localStorage`),
    `stores/terminal.js` (one-time terminal pick, persisted per-device —
    added `GET /api/v1/terminals` to Phase 2's API for this, scoped to
    `$user->stockLocations()`), `vue-router` guards in `router.js` route
    between `/login` → `/setup` → `/register` off those two local checks
    alone, so routing works fully offline after first setup.
  - **Offline data**: `stores/catalog.js` and `stores/paymentMethods.js`
    cache the item catalog (with barcodes) and tender list to
    `localStorage`, refreshed at login and on reconnect — without this,
    barcode scans and the payment screen would have nothing to resolve
    against while offline.
  - **Offline mutation queue** (`db/` via `idb`, `sync/engine.js`): every
    cart mutation applies optimistically to a local `cart_snapshot`
    (IndexedDB) and appends an op to an auto-incrementing `queue` store.
    `processQueue()` drains it strictly in insertion order, one request at
    a time; a connection-level failure stops the whole drain for a later
    retry, a real error response drops every remaining queued op for that
    cart and surfaces it as `cart.lastError` (with a "Discard sale" UI
    escape hatch). `create_cart` is safe to retry blindly (server-idempotent
    on `client_uuid`); `complete` carries a client-generated
    `Idempotency-Key` reused across retries of that same queued op, so
    checkout stays exactly-once. Deliberately **not** exactly-once for
    `add_line`/`add_payment`/etc. — see Known deviations.
  - `stores/cart.js` is the single point that reconciles the Pinia store's
    in-memory cart with IndexedDB (`sync()`); nothing else may call
    `sync/engine.js`'s `processQueue()` directly (see Known deviations for
    why that matters).
  - PWA shell: `vite-plugin-pwa` (Workbox `generateSW`) for the installable
    manifest and app-shell precaching — a secondary, install/asset-loading
    concern layered on top of the IndexedDB queue above, not the mechanism
    offline correctness depends on.
- **Purchasing** (`app/Domain/Purchasing/`, `app/Livewire/Purchasing/`):
  purchase order lifecycle (draft → submit → approve → receive/cancel),
  receivings (against a PO or fully ad-hoc — `receipt`, `return_to_supplier`,
  `transfer_in`, `transfer_out`), and supplier invoices with a
  record-payment action. `ReceiveGoodsAction` is the one new call site for
  `InventoryService` outside Phase 0/Sales — every stocked receiving line
  moves stock through it (`REASON_RECEIVING` for receipts, the new
  `REASON_RETURN_TO_SUPPLIER` for returns, `REASON_TRANSFER` for the two
  transfer types), optionally creating a `StockLot` when a lot number is
  given. Receiving against a PO advances that line's `quantity_received`
  and flips the PO's status to `partially_received`/`received` via
  `PurchaseOrder::isFullyReceived()`. No schema or ability changes were
  needed — both were already in place since Phase 0.
- **Promotions** (`app/Domain/Promotions/`, `app/Livewire/Promotions/`):
  a discount engine wired directly into `CartPricer`, so both the register
  API (`CartResource`, live on every cart read) and checkout
  (`CompleteSaleAction`) apply it identically with no separate integration
  point. `PromotionSelector` (query layer, mirrors `RateMatrix`) loads
  schedule-active, redemption-available, coupon-satisfied candidates;
  `PromotionEngine` (pure, no queries, mirrors `TaxEngine`) evaluates each
  candidate's conditions (`item`/`category`/`customer_group`/`cart_total`/
  `quantity`, operators `in`/`not_in`/`gte`/`lte`/`eq`, AND'd), resolves
  stacking (any number of `stackable` promotions plus at most one
  highest-priority non-stackable one, applied sequentially), and computes
  the reward (`percent_off`/`amount_off`/`fixed_price`, apportioned
  pro-rata across matched lines) before tax — same "discount before tax"
  ordering `CartPricer` already enforced for the existing manual line
  discount. `PricedLine`/`CartTotals` gained `promotionDiscount`/
  `appliedPromotions` fields; `discountTotal` (and therefore
  `Sale::discount_total`/`SaleLine::discount_amount`) picks up promotion
  discounts with no changes to its own accumulation logic. Redemption
  limits (`max_redemptions`, `max_redemptions_per_customer`,
  `coupons.max_uses`) are only advisory at pricing time — the authoritative,
  race-safe check is `RecordPromotionRedemptionsAction::verifyAndLock()`,
  row-locking inside `CompleteSaleAction`'s existing transaction exactly
  like `assertStockAvailable()` does for stock; a promotion lost to the
  race throws `CheckoutException::promotionExhausted()` and the whole sale
  rolls back. A new nullable `carts.coupon_code` column and
  `PATCH/DELETE /api/v1/carts/{cart}/coupon` let a coupon-gated promotion
  be exercised end to end without any register UI. Back-office CRUD
  (`Promotions/Index`+`Form`, conditions as a nested line-editor, inline
  coupon generation) reuses the Purchasing domain's established
  Index/Form/Policy/route conventions exactly.
- **Reporting** (`app/Domain/Reporting/Queries/`, `app/Livewire/Reporting/`,
  `app/Http/Controllers/Reports/`): the first domain to populate the
  `Queries/` scaffold that's existed since Phase 0 (every other domain's
  `Queries/` directory is still empty). Three report pages: Sales
  (date-ranged summary + margin, breakdowns by day/category/payment
  method/top items), Inventory (stock-movement ledger with item/location/
  reason filters, in/out summary by reason), Shift/cash (per-shift expected
  vs counted cash and variance, sales total via `withSum`, inline
  denomination/cash-movement drill-down — distinct from the existing
  simple "Shift History" list, which is untouched). Every money aggregate
  is computed with a raw SQL `SUM()` aliased to its real `MoneyCast`
  column name and queried through the Eloquent builder rather than
  `DB::table()`, so the result hydrates straight into a `Brick\Money\Money`
  instance with no manual wrapping — the one exception is `Shift`'s
  `withSum` alias (`sales_total`), which isn't a real cast key and needs a
  manual `Money::of()` at render/export time. CSV export only (three
  single-action controllers, `response()->streamDownload()` over a
  `LazyCollection` via `->lazy()`, not `->cursor()`, since eager-loading
  relations during export requires the former); PDF export added in the
  later Documents phase (see below). No schema or permission-seeder changes were
  needed — every report reads existing tables and the `reports.*`
  abilities were already seeded and already distributed sensibly
  (Accountant: sales+shifts, Stock Clerk: inventory).
- **Stock counts** (`app/Domain/Inventory/{Models,Actions,Exceptions}/`,
  `app/Livewire/Inventory/StockCounts/`): a five-status lifecycle
  (`draft → counting → review → approved`, plus `cancelled` from any of
  the first three) built against schema and permissions staged since
  Phase 0 — `inventory.count`/`inventory.count_approve` were already
  seeded with Stock Clerk deliberately holding `count` but not
  `count_approve`, an existing separation-of-duties design this phase
  preserves rather than invents. `GenerateStockCountLinesAction`
  snapshots `expected_quantity` from `stock_levels` (all stocked items
  with nonzero on-hand at the location, or a chosen subset) at
  draft→counting; blind counts (`is_blind`, defaulting true) hide
  expected quantity from the counting UI. `SubmitStockCountForReviewAction`
  computes and stores `variance` per line at submit time, before an
  approver ever sees numbers. `ApproveStockCountAction` — the
  `ReceiveGoodsAction`-shaped one — is `StockMovement::REASON_COUNT`'s
  first real call site anywhere in the codebase (the constant existed
  since Phase 0, unused); it posts one `InventoryService::record()` per
  nonzero-variance line inside one transaction and skips zero-variance
  lines entirely, so the ledger only ever records real corrections.
  Lot-level count lines are schema-ready (`stock_count_lines.stock_lot_id`)
  but unused this phase — `stock_levels` has no per-lot projection to
  snapshot an expected quantity against, so every line is item-level.
- **Documents** (`app/Domain/Documents/{SaleReceiptPdf,ReportPdf}.php`,
  `app/Http/Controllers/Documents/`, `resources/views/pdf/`,
  `app/Livewire/Sales/{Index,Show}`): one shared `barryvdh/laravel-dompdf`
  receipt/invoice template (conditional heading by `sale_type`), reachable
  from a new minimal back-office Sales lookup (search by number/invoice
  number/customer, `sales.view` — already-seeded, no permission-seeder
  change) and from the Vue register's post-checkout screen (bearer-token
  `fetch()` + blob download, since a plain `<a href>` can't carry the
  `Authorization` header the register uses). Reads `SaleLine`'s
  `item_name`/`sku` snapshot columns, never the live `item` relation, so a
  later catalog edit can't change what a historical receipt says. "Export
  PDF" added next to "Export CSV" on all 3 reports, reusing the existing
  Query classes' `*ForExport()`/summary methods with no query-layer
  rewrite; a new `countForExport()` guard on each Query class caps PDF
  exports at 2,000 rows (dompdf buffers the whole document in memory, no
  streaming) and redirects back with a flash error above that — CSV
  remains the tool for bulk data. Deliberately out of scope: Supplier
  Invoice (AP) PDF, thermal/ESC-POS receipt printing (this is A4
  downloadable/printable, a different output pipeline), a barcode on the
  receipt (picqer/php-barcode-generator is installed but unused, left as
  a fast-follow slot), a business-profile/settings model (no store
  name/address exists beyond `config('app.name')`), and the Accountant
  role's `sales.view` gap (Accountant has `reports.sales` but not
  `sales.view`, so it can see the aggregate report but not drill into an
  individual sale — deliberately left alone since the permission seeder
  wasn't part of this phase's scope).
- **Register UX fast-follows** (`app/Http/Controllers/Api/V1/{Customer,
  CartCustomer}Controller.php`, `resources/js/pos/stores/cart.js`,
  `resources/js/pos/sync/engine.js`): customer search
  (`GET /customers`, reusing already-seeded `customers.view`, no new
  permission) and attach/detach on an active cart from the register.
  Coupon-code entry wired into the register with real validation —
  `CartCouponController` now checks the code against
  `Coupon::isRedeemable()`/`Promotion::isCurrentlyActive()` before
  accepting it (previously a silent no-op on a bad code); a new
  `CheckoutException::couponInvalid()` renders as a clean 422.
  Cross-terminal suspended-cart browsing (`GET /carts?terminal_id=`,
  scoped to the requesting terminal's stock location, excluding its own
  terminal since those already show via the device's local parked list)
  turned up a real, previously-latent gap while building it: **parking a
  sale had never actually suspended the cart server-side** — `parkCurrent()`
  only ever cleared the client's local reference, so `Cart::STATUS_SUSPENDED`
  was set nowhere in the whole app despite `sales.suspend` already being
  seeded for exactly this. Added `POST /carts/{cart}/suspend` (gated by
  `sales.suspend`) and wired `parkCurrent()` through it via the offline
  queue, matching every other cart mutation. Resuming a suspended cart
  (`CreateOrResumeCartAction`, the existing `POST /carts` endpoint, no new
  route needed) now reassigns `terminal_id`/`shift_id`/`user_id` to
  whoever actually resumes it — required for cross-terminal resume to be
  safe at all: without it, a sale completed on a different terminal than
  the one that parked it would misattribute cash/till reconciliation to
  the wrong shift. Verified end-to-end via a real two-terminal Playwright
  walkthrough: Terminal 1 attaches a customer, rejects an invalid coupon,
  applies a valid one, and parks; Terminal 2 discovers it, resumes it, and
  completes the sale — confirmed via `artisan tinker` that the resulting
  `Sale` is attributed to Terminal 2's terminal/shift/user, not Terminal
  1's. `promotions.view` added to the Cashier role (one line, no
  cascade into `promotions.manage`).
- **Loyalty & Gift Cards** (`app/Domain/{Loyalty,Giftcards}/`,
  `app/Livewire/{Loyalty/Packages,Giftcards}/`): both subsystems started
  from the same unusual state — full schema and bare models since Phase 0,
  zero business logic anywhere. Points auto-earn on every completed sale
  with a customer whose `LoyaltyPackage` has a nonzero earn rate (basis:
  `subtotal - discount_total`, excluding tax and discounted amounts).
  Points and gift-card redemption both flow through the already-seeded
  `points`/`giftcard` payment methods — no new payment concept, just
  validation plus a debit. Both redemptions follow the exact two-phase
  shape `RecordPromotionRedemptionsAction` established: an optimistic
  check in `AddCartPaymentAction` (immediate cashier feedback, same as
  coupon validation) and the authoritative, row-locked debit inside
  `CompleteSaleAction`'s transaction (`RecordPointsRedemptionAction`,
  `RecordGiftcardRedemptionAction`) — deliberately not the `SaleCompleted`
  event, which has zero listeners anywhere and no transaction-safety
  guarantee. Back office: Loyalty Package CRUD (earn/redemption rates);
  Gift Card issue + top-up + transaction history, numbered via
  `DocumentNumberGenerator::next('gift_card')` (new `GC` sequence,
  previously unstaged); Customer Form gains a loyalty-package picker, a
  read-only points-balance display, and a manual points-adjustment action
  (goodwill award/correction) mirroring Promotions' coupon-generation
  pattern. One small additive migration:
  `points_transactions.user_id` (nullable FK) — the Phase-0 schema had it
  on `giftcard_transactions` but not its loyalty sibling, and manual
  adjustment needs to record who made it. New `loyalty.view`/`.manage`
  permissions, distributed identically to the existing `giftcards.*`
  pair (Cashier: view only). Verified end-to-end via a real Playwright
  walkthrough: created a package, attached it to a customer, completed a
  cash sale and confirmed exactly the expected points were earned;
  completed a second sale paid via a newly issued gift card and confirmed
  its balance debited by exactly the sale total; attempted a third sale
  redeeming far more points than the customer had and confirmed a clean
  rejection (visible error banner, no sale created, balance untouched) —
  all three confirmed against the database via `artisan tinker`, not just
  the UI. Deliberately out of scope: selling a new gift card as a
  register cart line (no product/SKU represents one), point-expiry
  processing (`points_transactions.expires_at`/`type=expire` exist in
  schema, no scheduled job), gift-card refunds auto-crediting the
  original card, multi-currency gift cards.
- **Receipt Hardware Bundle** (`mike42/escpos-php`, `app/Domain/Sales/
  {Actions/PrintReceiptAction,Actions/OpenCashDrawerAction,Support/
  PrintConnectorFactory,Exceptions/PrintingException}.php`) — thermal
  receipt printing, a cash-drawer kick, and a barcode on the printed
  receipt, prioritized after the user confirmed this POS targets a
  hardware store. `terminals` gained `printer_connector`
  (network/windows/cups) and `printer_paper_width`, reusing the
  previously-unused `receipt_printer` column as the connector target
  (IP:port or OS print-queue name) — a new, minimal back-office Terminal
  CRUD (`app/Livewire/Sales/Terminals/{Index,Form}.php`) finally consumes
  the `terminals.manage` permission, which had been seeded since Phase 0
  but never used anywhere. `PrintReceiptAction` builds the full ESC/POS
  job (header, lines, totals, payments, a native Code128 barcode of the
  sale number, cut) and fires `Printer::pulse()` when any payment on the
  sale used a `payment_methods.opens_drawer = true` method — already
  modeled per-method (true only for `cash`), not a hardcoded string
  check. `OpenCashDrawerAction` exposes the same pulse as a standalone
  manual action for no-sale opens. Both are triggered explicitly by the
  cashier (`POST sales/{sale}/print-receipt`, `POST
  terminals/{terminal}/open-drawer`) rather than hooked into
  `SaleCompleted`, so a jammed/offline printer never blocks an
  already-completed sale — Confirmation.vue shows a non-blocking inline
  error and the existing PDF-download fallback stays available. The A4
  PDF receipt's previously-empty barcode slot now renders a Code128 SVG
  of the sale number via the already-installed but previously-unused
  `picqer/php-barcode-generator`. Testing seam: `Mike42\Escpos\
  PrintConnectors\MemoryPrintConnector`, wrapped in
  `tests/Support/RetainedMemoryPrintConnector.php` (captures bytes before
  `Printer::close()` nulls the connector's buffer) and swapped in via
  `tests/Support/FakePrintConnectorFactory.php`, so the generated
  ESC/POS byte stream (pulse command present/absent, barcode/sale-number
  content) is asserted without real hardware. **Real hardware
  verification is out of reach in this environment** — the Playwright
  walkthrough could only prove the *failure* path end-to-end (a terminal
  configured with an unreachable network target correctly surfaces
  "Could not connect to the receipt printer: Connection refused" via a
  clean 422, with no crash and the sale itself unaffected); the success
  path (a real printer actually printing, a real drawer actually
  kicking) is proven only at the byte-stream level via the tests above,
  not against physical hardware. A caught bug along the way: the
  Terminal Form's printer-connector `<select>` needed `wire:model.live`,
  not plain `wire:model` — without it, Livewire's deferred-by-default
  binding meant the conditionally-shown printer-address field never
  appeared until an unrelated re-render.
- **Reorder-point PO Suggestions** (`app/Domain/Purchasing/Queries/
  ReorderSuggestionsQuery.php`, `app/Livewire/Purchasing/
  ReorderSuggestions/Index.php`) — a back-office screen that surfaces
  every active, stocked item whose on-hand quantity at a chosen location
  has fallen below its `items.reorder_level`, grouped by supplier (a
  `PurchaseOrder` and an `Item` are each already scoped to exactly one
  supplier, so the grouping falls out of the schema directly), with an
  editable suggested quantity and unit cost per line and a
  "Create purchase order" button per supplier group that calls the
  existing `CreatePurchaseOrderAction` unchanged and lands the buyer in
  the normal draft-PO edit screen for review — this phase does not touch
  the PO workflow itself. `items.reorder_level` existed since Phase 0 but
  had never been wired into the back-office Item Form (no way to
  actually set a reorder point without `tinker`) — fixed as part of this
  phase, alongside a new `items.reorder_quantity` (nullable) column so a
  buyer can configure a fixed reorder amount (e.g. "order 24 at a time")
  independent of the threshold; when unset, the suggestion falls back to
  topping the item back up to its `reorder_level`. An item with
  `reorder_level = 0` (the column's default) never appears — a
  deliberate "never auto-suggest" convention, not a bug. No new
  permission: reuses `purchasing.view`/`purchasing.manage`, identical to
  the existing Purchase Order screens. Verified via a real Playwright
  walkthrough: lowered a demo item's on-hand below a raised reorder
  level, confirmed it appeared in Reorder Suggestions under its supplier
  with the expected quantity (from `reorder_quantity`, not a naive
  top-up), confirmed a service item never appears, clicked "Create
  purchase order" and confirmed a correct draft PO was created and opened
  in the standard PO edit screen, and confirmed switching the location
  dropdown reactively reloads the list without a full page reload.
- **Purchasing-side Tax Computation** (`app/Domain/Purchasing/
  PurchaseLinePricer.php`, `app/Domain/Purchasing/Data/
  PurchaseLinesTotal.php`) — `purchase_orders.tax_total` and
  `receivings.tax_total` existed since Phase 0 but were never actually
  computed anywhere: every write site folded in whatever `tax_total`
  already held, and nothing ever set it, so it silently stayed `0`
  forever; neither the PO Form nor the Receiving Form even displayed a
  subtotal/tax/total breakdown. Fixed by reusing the existing, pure
  `TaxEngine`/`RateMatrix` Sales already built (`app/Domain/Taxation/`)
  — `PurchaseLinePricer` derives each line's tax category from the
  current `Item.tax_category_id` (never stored on the PO/receiving line
  itself, same as `cart_lines`), exactly mirroring how `CartPricer`
  already prices a cart. `CreatePurchaseOrderAction::writeLines()`
  (used by both create and edit, since `UpdatePurchaseOrderLinesAction`
  delegates to it) and `ReceiveGoodsAction::execute()` now write real
  `tax_total`/`total` values. Both back-office Forms gained a live
  Subtotal/Tax/Total preview that updates as lines are edited, before
  saving — line inputs switched from deferred `wire:model` to
  `wire:model.live` (debounced on the text fields), the same fix the
  Receipt Hardware Bundle phase already had to learn the hard way.
  Deliberately untouched: `supplier_invoices` (a single `total` field
  copying a supplier's actual paper/PDF invoice — not something an
  internal engine should compute) and `receiving_lines.discount_value`/
  `discount_type` (pre-existing dead columns, a separate gap from this
  one). Verified with exact-decimal Pest tests against the seeded 15%
  standard rate and a real Playwright walkthrough: a $0.80 x 10 PO line
  showed a live USD 8.00 / 1.20 / 9.20 subtotal/tax/total before saving,
  matched the Purchase Orders index after saving, and a $0.75 x 24
  receiving line showed 18.00 / 2.70 / 20.70 the same way.
- **Three-way Match** (`app/Domain/Purchasing/Actions/
  {MatchSupplierInvoiceAction,ResolveSupplierInvoiceDisputeAction}.php`)
  — **header-level**, by explicit user decision: supplier invoices have
  no line items in this schema (a single `total` column), so this
  compares an invoice's total against the sum of what was actually
  received (`Receiving.total`, type `receipt`) against its linked PO,
  not a per-line reconciliation — adding `supplier_invoice_lines` was
  considered and declined as materially larger, separate scope.
  `SupplierInvoice.status` already had a `disputed` value defined since
  Phase 0 and never once referenced anywhere in the codebase — it was
  clearly meant for exactly this. The match runs automatically whenever
  an invoice is created or edited (while still `open`/`disputed`, never
  re-evaluated once payment has started); outside a small rounding
  tolerance (`config('pos.purchasing.match_tolerance')`, default
  `0.01`) the invoice flips to `disputed`, and
  `RecordSupplierInvoicePaymentAction` now refuses to record a payment
  against one. A `purchasing.approve`-gated "Accept discrepancy" action
  (new `SupplierInvoicePolicy::approve`, mirroring the ability
  `PurchaseOrderPolicy::approve` already uses) lets an approver
  consciously override a legitimate difference (freight added at
  invoice time, a price change) without altering the recorded total —
  same `wire:confirm` confirmation-dialog convention as PO cancellation,
  no separate reason field, since `SupplierInvoice` now uses
  `LogsActivity` (the same one-method pattern `Sale` already
  established) to capture every status change and who made it. Also
  fixed along the way: `SupplierInvoices/Index::recordPayment()` was the
  only Purchasing Livewire action that didn't wrap its domain-action
  call in a `try/catch` — every sibling action already did. Verified
  with Pest (tolerance boundary, no-PO invoices never matched,
  zero-received disputes, editing a disputed invoice's total back into
  tolerance auto-resolves it, payment blocked/unblocked around a
  dispute) and a real Playwright walkthrough: created and fully received
  a PO, a matching invoice stayed `open`, a mismatched one against the
  same PO was immediately flagged `disputed` with the invoiced-vs-received
  amounts shown inline and no "Pay" control offered, and clicking
  "Accept discrepancy" returned it to `open` and payable.

- **Barcode label printing for newly received items** (`app/Domain/Inventory/{Support/TsplLabelBuilder,Support/LabelPrinterConnectorFactory,Actions/PrintItemLabelsAction,Exceptions/LabelPrintingException}.php`,
  `app/Domain/Catalog/Actions/AssignItemBarcodeAction.php`,
  `app/Livewire/Purchasing/Receivings/Labels.php`) — the user specified
  their actual hardware: an Xprinter XP-T361U label printer alongside
  the already-built XP-80T receipt printer. Unlike the ESC/POS receipt
  path, Xprinter's label printers speak **TSPL**, an unrelated raw
  command language (`SIZE`/`GAP`/`CLS`/`TEXT`/`BARCODE`/`PRINT`) — so
  `mike42/escpos-php`'s `Printer` class doesn't apply, but its transport
  layer does: `PrintConnector`/`NetworkPrintConnector`/`WindowsPrintConnector`/
  `CupsPrintConnector` are protocol-agnostic, so the same connector
  classes carry raw TSPL bytes with no new dependency. The label
  printer is configured per `StockLocation` (a label printer sits at
  the receiving desk, not on a specific sales register the way a
  receipt printer does), mirroring `Terminal`'s printer-config fields
  and Form UI exactly. An item with no barcode yet — genuinely common
  for a hardware store's own SKUs — gets one auto-assigned
  (`AssignItemBarcodeAction`: Code128, content = the item's already-unique
  SKU) the first time a label is printed for it, rather than requiring
  manual barcode entry first. The label itself carries only SKU, price,
  and barcode — item name was deliberately dropped after review to keep
  the 50x30mm layout uncluttered. Printing is reached from a new "Labels"
  link on the Receivings Index rather than wedged into the receiving
  save flow, so it also covers reprinting after a jam or for a past
  receiving. Deliberately out of scope: an ad-hoc "reprint any item's
  label" screen (the Receivings Labels screen already covers realistic
  reprints) and per-location label dimensions (one global
  `config('pos.labels')` default, since a store buys one label stock for
  one printer model). Verified with Pest (barcode auto-assignment vs.
  reuse of an existing barcode, N-copy printing, no-printer-configured
  failure) and a real Playwright walkthrough: created an item with no
  barcode, received it, opened its receiving's Labels screen and
  confirmed it listed as awaiting a barcode, then attempted to print
  against an unreachable configured target and confirmed a clean
  on-screen error rather than a crash. As with the Receipt Hardware
  Bundle phase, no physical TSPL printer was available in this
  environment to verify the real success path against — flagged here
  and in project memory.

- **Reporting: Items, Categories, Suppliers, Receivings, Customers,
  Payments, Taxes** (`app/Domain/Reporting/Queries/{Item,Category,Supplier,
  Receiving,Customer,Payment,Tax}ReportQuery.php`,
  `app/Livewire/Reporting/{Items,Categories,Suppliers,Receivings,Customers,
  Payments,Taxes}.php`, `app/Http/Controllers/Reports/*ReportExportController.php`
  + `*ReportPdfController.php`) — 7 of the 10 remaining seeded `reports.*`
  types, user-scoped down from all 10 to the hardware-store-relevant set
  plus three lower-priority ones (Employees, Discounts, Expenses stay
  not-started). Every report follows the exact same six-file shape the
  three pre-existing reports (Sales/Inventory/Shifts) already established:
  a Query class, a Livewire screen, CSV + PDF export controllers, and a
  PDF view — no new pattern invented, just the existing one applied 7
  more times. Items/Categories/Suppliers/Customers/Taxes are
  aggregate-only (row count bounded by catalog/supplier/customer/tax-rate
  count, not transaction volume, so no pagination and no PDF row-count
  guard, matching `SalesReportQuery::byCategory()`'s existing unpaginated
  precedent); Receivings/Payments get a paginated transaction list +
  `MAX_ROWS`-guarded PDF export, the exact shape `InventoryReportQuery`
  already used. Margin figures (Items/Categories) lean on a documented
  existing trick: aliasing `SUM(...)` aggregates to a real `MoneyCast`
  column name (`line_total`, `cost_price`) so the cast still applies on
  hydration with no manual `Money::of()` wrapping — see
  `SalesReportQuery`'s class doc comment, now also referenced from the
  two new query classes that reuse it. Verified with Pest (21 new query
  tests plus permission-gating/CSV/PDF-export coverage in
  `ReportingBackofficeTest`) and a real Playwright walkthrough of all 7
  pages with genuine seeded sale/receiving data, one live filter, and a
  followed CSV + PDF export. Surfaced along the way (not fixed, out of
  scope for this phase): this dev container's MySQL session `time_zone`
  is `SYSTEM` while the app runs in `UTC` — `timestamp()` columns like
  `received_at`/`sold_at` can round-trip to a different wall-clock value
  than what was written, which briefly looked like a report bug before
  being traced to the DB session timezone, not the query logic. Worth a
  `'timezone' => '+00:00'` pin in `config/database.php`'s mysql
  connection if this container is used for anything timestamp-precision
  sensitive.

- **Supplier Invoice (AP) PDF export** (`app/Domain/Documents/SupplierInvoicePdf.php`,
  `app/Http/Controllers/Documents/SupplierInvoicePdfController.php`,
  `resources/views/pdf/purchasing/supplier-invoice.blade.php`) — mirrors
  `SaleReceiptPdf`/`SaleReceiptController` exactly, the codebase's one
  existing single-record-PDF pattern (as opposed to `ReportPdf`, which is
  for tabular reports). Since a supplier invoice has no line items
  (header-level only, an explicit decision from the Three-way Match
  phase), the PDF is a header document: supplier, invoice/due dates,
  linked PO number if any, status, total, paid, balance due, and — only
  when the invoice is `disputed` — the invoiced-vs-received figures via
  the already-built `SupplierInvoice::receivedTotal()`. A "PDF" link was
  added to the Supplier Invoices Index, gated by the existing `view`
  ability (`purchasing.view`) — no policy change needed. Verified with
  Pest (real DomPDF render starts with `%PDF-`, PO number appears only
  when linked, discrepancy block appears only when disputed, balance due
  computes correctly, 403 for a user without `purchasing.view`) and a
  real Playwright walkthrough: opened the Supplier Invoices Index,
  followed the PDF link, confirmed a real PDF streamed back.

- **Accountant `sales.view` grant** (`database/seeders/RolesAndPermissionsSeeder.php`)
  — the Accountant role had `reports.sales` (the Sales report) but not
  `sales.view`, so it couldn't drill into an individual sale from the
  back-office Sales lookup (`SalePolicy::viewAny`/`view` both gate on
  `sales.view`, and the whole `sales.` route group is
  `permission:sales.view`-gated) or download a sale's receipt PDF. One
  permission added to the role's `syncPermissions()` list — no schema,
  policy, or route change needed. An existing test
  (`tests/Feature/Sales/SalesBackofficeTest.php`) had explicitly
  documented the gap as expected behavior (`it('denies the sales lookup
  to an Accountant without sales.view', ...)`); flipped to assert the
  now-correct access instead of leaving a passing-but-wrong assertion in
  place.

- **Un-approving a posted stock count** (`app/Domain/Inventory/Actions/UnapproveStockCountAction.php`,
  `stock_counts.unapproved_by_user_id`/`unapproved_at`) — a stock count
  approved in error can now be reversed. `ApproveStockCountAction` posts
  each line's variance to the inventory ledger via
  `InventoryService::record()`; the new action mirrors it exactly but
  posts the *negated* variance per nonzero-variance line (same
  `reason: count`, same `source`), then moves `status` back to `review`
  (not `counting` — re-snapshotting expected quantity / reopening
  counting stays a separate, unimplemented item, since variance is
  fixed at count time by design). `approved_at`/`approved_by_user_id`
  are left untouched as the historical record of the reversed approval;
  `unapproved_at`/`unapproved_by_user_id` are new columns tracking the
  reversal itself. The ledger stays append-only — unapprove never
  mutates or deletes the original movement rows, only adds an
  offsetting one, so stock history is always reconstructible. Gated by
  the same `inventory.count_approve` ability as approve (Owner/Manager
  only). Verified with a real approve→unapprove cycle via `tinker`
  against a freshly seeded DB: on-hand quantity moved 240 → 250 →
  240, and both the `+10.000` and `-10.000` ledger rows persisted
  side by side. Reversing the count's own ledger contribution isn't a
  guarantee of restoring the exact pre-approval quantity if other
  stock movements happened in between — the confirm dialog says so.

- **Business-profile settings** (`app/Settings/BusinessProfileSettings.php`,
  `app/Livewire/Settings/BusinessProfile.php`) — store name/address/phone
  are now editable at `/settings/business-profile` (gated by the
  `config.manage` permission, which existed in the seeder but was
  wired to nothing until now) and print on the sale receipt and
  supplier invoice PDFs, replacing the `config('app.name')` placeholder
  both previously showed. Built on `spatie/laravel-settings` — already
  a `composer.json` dependency with its `settings` table migration
  already applied, but completely unused until this feature; no new
  package or hand-rolled settings model needed. `BusinessProfileSettings`
  auto-discovers from `app/Settings/` (the package's default discovery
  path), so no `config/settings.php` publish was needed either. A
  companion settings migration (`database/settings/..._create_business_profile_settings.php`)
  seeds `store_name` from `config('app.name')` so it's never blank on
  a fresh install, with `address`/`phone` defaulting to empty. Scope
  intentionally stayed to what was asked — no per-location addresses,
  no report-PDF or label headers, no logo/footer. Verified live:
  saved new values through the actual settings form in a browser,
  reloaded to confirm persistence, then confirmed both PDF download
  routes (`/sales/{sale}/receipt`, `/supplier-invoices/{invoice}/pdf`)
  return `200 application/pdf` for an authenticated request; the PDF
  bytes themselves are compressed streams, so the actual name/address/phone
  content is asserted the same way the rest of this codebase's PDF
  suite does — against the underlying Blade view's rendered HTML,
  not the compressed binary.

- **Full settings area** (document numbering, tax defaults, label
  printer, receipt branding) — the previous phase deliberately scoped
  out everything beyond store identity ("no logo/footer" etc.); this
  phase fills in the rest except currency, which was explicitly
  descoped since it's a global value baked into every `Money` object
  with no retroactive conversion of existing stored amounts — too risky
  to fold into a casual settings edit. New pages, all gated
  `config.manage` like Business Profile: **Document Numbering**
  (`app/Livewire/Settings/Numbering.php`) edits each of the 8 known
  `document_sequences` rows (prefix/padding/next number) directly —
  `DocumentNumberGenerator` only reads `config('pos.documents.sequences.*')`
  the *first* time a key is used to seed that row, after which the DB
  row is authoritative, so this UI edits the actual source of truth,
  not a config default. The generator's `$scope` parameter for
  yearly-reset sequences is dead code (every real call site passes
  none) and isn't exposed here. **Tax Defaults**
  (`app/Settings/TaxSettings.php`, `app/Livewire/Settings/TaxDefaults.php`)
  makes the prices-include-tax toggle actually editable at runtime —
  `TaxEngine::fromConfig()` became `TaxEngine::fromSettings()` — and
  removes `config('pos.tax.rounding_mode')` entirely: reading
  `TaxEngine::taxFor()` closely showed it hardcodes `RoundingMode::HalfUp`
  on both operations and never reads that key, so it was dead
  configuration presenting a control that did nothing; deleting it
  beats faking a UI for it. Named "Tax Defaults" to stay distinct from
  the existing "Tax Categories" rates/cascade CRUD, which already
  covers everything else tax-related. **Label Printer**
  (`app/Settings/LabelSettings.php`) moves the one genuinely global
  hardware default — physical label stock dimensions
  (width/height/gap/density), the only knob `TsplLabelBuilder` reads —
  out of `config/pos.php` into an editable setting;
  per-`StockLocation`/per-`Terminal` printer fields were already
  editable through their own forms and are untouched. **Receipt
  branding** extends `BusinessProfileSettings` with `logo_path`
  (uploaded via `WithFileUploads` onto the `public` disk — the first
  file upload anywhere in this codebase, so `php artisan storage:link`
  was run as part of this change), `receipt_header`, and
  `receipt_footer`, rendered on the sale receipt PDF (logo also on the
  supplier invoice PDF; header/footer text is receipt-specific, not
  added there) via a local filesystem path
  (`Storage::disk('public')->path(...)`, since DomPDF's remote-image
  loading is off by default). **Found and fixed a real bug while
  wiring the thermal path**: `PrintReceiptAction` was printing
  `config('app.name')` as the receipt header instead of the
  configured store name — the PDF receipt and the thermal receipt
  showed *different* store names. It now prints the same
  `BusinessProfileSettings::store_name`, plus the new header/footer
  text if set; a logo is deliberately not printed on thermal receipts
  (ESC/POS raster image printing has zero precedent in this codebase,
  and dithering quality can't be verified without physical hardware).
  17 new Pest tests (5 numbering, 3 tax, 4 labels, 4 business-profile
  logo/header/footer, 1 thermal-receipt regression for the found bug),
  all reusing the existing permission-gate/persistence-check
  conventions from the original Business Profile tests. Verified live
  as Owner across all four settings pages (save → reload → confirm
  persistence, matching this session's own established pattern for
  non-redirecting settings forms) plus `tinker`: a changed numbering
  prefix flows through `DocumentNumberGenerator::next()`, a toggled
  tax setting flows through `TaxEngine::fromSettings()`, changed label
  dimensions flow through `TsplLabelBuilder`, and an uploaded logo +
  header/footer text render correctly in the actual `pdf.sales.receipt`
  Blade output. No console/HTTP errors.

- **BOGO / free_item promotion reward types** (`app/Domain/Promotions/PromotionEngine.php`,
  `app/Domain/Promotions/Models/Promotion.php`) — `Promotion::REWARD_BOGO`
  and `REWARD_FREE_ITEM` join the existing `percent_off`/`amount_off`/
  `fixed_price` types; no migration needed, since `reward_type` was
  already a plain unconstrained `string(24)` column and `reward_item_id`
  already existed as an unused nullable FK. Both new types discount
  exactly **one unit**, not a pro-rata share of the whole scope like
  the other three — `PromotionEngine::evaluate()` branches to a new
  `applyUnitReward()` before the existing rewardBase/apportion path.
  `bogo` discounts the cheapest-per-unit-net line within the
  promotion's existing item/category scope (mix-and-match: "buy 2 from
  category X, the cheaper one's free"); `free_item` discounts one unit
  of `reward_item_id`'s own cart line, only if that item is present in
  the cart as its own line. The "buy N" threshold reuses the existing
  `quantity`-subject condition (`gte`/`eq`) rather than adding new buy/
  get-quantity columns — `CreatePromotionAction`/`UpdatePromotionAction`
  now refuse to save a `bogo` promotion with no quantity condition, or
  a `free_item` promotion with no reward item, via two new
  `PromotionException` factories caught by the Livewire form's existing
  `conditions`-error path (no new Livewire validation rules needed).
  Deliberately out of scope: repeating buy/get ratios (e.g. "buy 4 get
  2 free") — v1 grants exactly one free unit per promotion match, same
  as every other reward type is evaluated once per checkout. Verified
  live: created one of each type through the real back-office form,
  then via `tinker` ran a real cart (2× cola + 1× water + 1× croissant)
  through `CartPricer` — bogo correctly discounted one cola unit
  (`2.40 / 2 = 1.20`), and free_item (checked in isolation, since two
  non-stackable promotions correctly don't combine — pre-existing,
  unrelated stacking behavior) discounted the croissant line's full
  net to zero.

- **Project-wide audit + four ship-blocking fixes.** A three-track
  audit (backend domain, frontend/UI, infra/ops — see `PROJECT_STATUS.md`
  history for the full findings) found the codebase itself clean (zero
  TODO/stub markers, every Action has a caller, every policy is
  registered) but surfaced four genuine defects and a large backlog of
  scaffolded-but-unbuilt capability that the roadmap hadn't been
  tracking. The four defects, all fixed here:
  1. **Public self-registration** (`config/fortify.php`'s
     `Features::registration()`) created role-less accounts for anyone
     who could reach the host, and `POST /register` succeeded even
     though `GET /register` 500'd (no view existed). Removed, along
     with password-reset/2FA/passkey features that were similarly
     enabled with no views ever written for them — `resources/views/auth/`
     has only ever had `login.blade.php`. Staff accounts are created the
     one correct way, by an Owner through `App\Livewire\Identity\Users\Form`,
     which assigns a role.
  2. **`users.is_active` was not enforced on web login** — the API
     checked it (`Api\V1\AuthController::store`) but the Fortify web
     guard had no `authenticateUsing()` override, so a deactivated
     employee could still sign into the back office.
     `app/Providers/FortifyServiceProvider.php` now closes this.
  3. **Resuming a parked sale on the same terminal silently destroyed
     it.** `resources/js/pos/stores/cart.js`'s `resume()` restored the
     local snapshot but never told the server to reactivate the cart
     (only the cross-terminal path did that correctly). The next
     scan/payment 422'd on `cartNotActive`, and the sync engine treats
     any non-network error as fatal — it discarded every queued op for
     that cart. `resume()` now queues the same `create_cart` op the
     cross-terminal path already used correctly, reusing
     `CreateOrResumeCartAction`'s existing resume branch server-side.
     Verified with a real Playwright run through the actual Vue
     register (park → resume → add a second line → correct
     server-computed total), not just the unit test.
  4. **Zero application logging.** Every one of the 18 Livewire
     catch-and-`addError()` sites for a domain exception
     (`CheckoutException`, `StockCountException`, `PrintingException`,
     etc.) left no trace anywhere. Added `App\Support\Logging\DomainLog::refused()`,
     called from all 19 sites (one file had two), and switched
     `LOG_STACK` to `daily` so the log actually rotates.
  - Tests: `tests/Feature/Identity/WebAuthenticationTest.php` (4 new —
    registration gone, deactivated user refused, active user admitted)
    plus 2 new Vitest cases for `resume()`. 366 Pest + 17 Vitest passing.
  - The rest of the audit's findings — real gaps, but each its own
    phase — are recorded in Not-started below rather than silently
    lost, which is the whole reason this bullet exists.

- **Returns / refunds** (`app/Domain/Sales/Actions/RefundSaleAction.php`,
  `app/Livewire/Sales/Refund.php`) — the largest gap the audit found: a
  cashier could not process a return despite `Sale::STATUS_REFUNDED`,
  `SaleLine.quantity_returned`, `return_reasons`, and
  `payments.refunds_payment_id` all already existing in schema.
  Backoffice-only by design (confirmed with the user): `sales.refund`/
  `sales.void` were seeded to Owner/Manager only, never Cashier, so a
  return is processed by a manager from a sale's back-office page, not
  self-service at the register.
  A return is recorded as its **own `Sale` row** (`sale_type: return`,
  linked back via `returns_sale_id` — exactly what
  `Sale::getActivitylogOptions()`'s pre-existing "refunds must be
  attributable" comment already implied), with **negative** money
  fields. That sign is not a new convention — `SalesReportQuery` and
  friends already do a plain `SUM(total)` over `Sale::revenue()`
  (pos+invoice+return, unconditionally), so a negative return is what
  makes revenue/COGS reporting correct with zero report-query changes.
  Per-line refund amounts are computed proportionally
  (`original_line_value / original_qty * returned_qty`, the same
  divide-then-multiply idiom `PromotionEngine::unitNet()` already
  uses), independently per line. Restocking is conditional on the
  chosen `ReturnReason.restocks` flag (already-seeded reasons: faulty/
  expired don't restock, wrong_item/changed_mind/price_error do) —
  `InventoryService::record()` reused unmodified with a positive delta
  and the already-existing but previously-orphaned
  `StockMovement::REASON_RETURN`. A refund `Payment` row is created
  against the return sale with a negative amount and
  `refunds_payment_id` set when exactly one original payment used the
  chosen method. The original sale's status flips to `refunded` or
  `partially_refunded` based on whether every line's
  `remainingReturnable()` is now zero. Guarded against over-returning
  per line and over-refunding in aggregate
  (`abs(sum of prior returns' totals) + this refund > sale.total`),
  with the original sale and its lines `lockForUpdate()`'d so two
  concurrent refund submissions can't double-spend the same quantity.
  Deliberately out of scope, each a distinct already-tracked item:
  loyalty-point clawback, promotion/coupon redemption-counter reversal,
  gift-card-tender auto-crediting (already on this list below), voiding
  a whole sale (`sales.void` — same-day cancellation is a different
  feature from returning a settled sale), and a `return_reasons` admin
  CRUD screen (reasons are seeded, matching how `PaymentMethod`/
  `TaxRate` are managed today too). 9 new tests (7 action-level
  covering full/partial returns, non-restocking reasons, and every
  guard; 2 Livewire permission/round-trip). Verified live: created a
  real 2-unit sale, processed two partial returns through the actual
  back-office form as Owner, confirmed the status badge went
  completed → partially refunded → refunded, confirmed `stock_levels`
  round-tripped exactly back to its pre-sale quantity, and confirmed
  the Sales index's `refunded` status filter (already wired, just
  never had a status to match) now finds it.

- **Voiding a sale** (`app/Domain/Sales/Actions/VoidSaleAction.php`,
  `app/Livewire/Sales/VoidSale.php`) — `sales.void` was seeded (Owner/
  Manager only, same as `sales.refund`) but nothing ever set
  `Sale::STATUS_VOIDED` outside a direct `->update()` in a test. A void
  is deliberately a different shape from a return: it's a same-day
  cancellation of a sale that never should have completed, undone **in
  place** on the original `Sale` row — no new `Sale` or `Payment` row,
  unlike `RefundSaleAction`. Stock is reversed with a positive delta
  per line via `InventoryService::record()` (mirroring
  `CompleteSaleAction`'s negative-delta call exactly), tagged with a
  new `StockMovement::REASON_VOID`. Every payment on the sale is
  flipped to the already-existing but previously-unused
  `Payment::STATUS_VOIDED`, which for a still-open shift automatically
  removes it from `CloseShiftAction::expectedCash()`'s till total with
  zero changes to that action, since it already only sums
  `STATUS_CAPTURED` payments. Guarded to only `STATUS_COMPLETED` sales
  — this single check also blocks voiding a sale that already has
  return activity against it (status would be `refunded`/
  `partially_refunded`, never `completed`) and blocks double-voiding.
  A required free-text reason plus `voided_by_user_id`/`voided_at` are
  stored directly on the sale (unlike return reasons, a void reason has
  no business logic branching on it, so a lookup table would be
  needless — added to the existing `sales`/`user`/status activity-log
  `logOnly()`). `void` is a reserved PHP word, so the Livewire
  component/route is `VoidSale`, not `Void`. Fixing this also exposed
  a real pre-existing bug: `show.blade.php`'s `$hasReturnableQuantity`
  never checked sale status, so a voided sale (lines untouched,
  `quantity_returned` still 0) incorrectly showed a live "Process
  return" link that would have thrown an uncaught exception inside
  `Refund::mount()`'s status guard — fixed by requiring
  `status` be `completed`/`partially_refunded` before the link renders,
  the same guard `Refund::mount()` already enforces server-side.
  Also fixed in passing: `sold_at`'s migration column had no default,
  which the dev MariaDB's current strict `sql_mode` (picked up
  `NO_ZERO_DATE`) now rejects on `CREATE TABLE` for a `NOT NULL`
  timestamp with an implicit zero-date default — added `useCurrent()`;
  harmless since the app always sets `sold_at` explicitly, but it was
  blocking every migration, not just this feature's. Deliberately out
  of scope, matching the returns/refunds phase's list: no loyalty/
  promotion/gift-card reversal, no same-day time-window enforcement,
  quotes/work orders not addressed (they never move stock or take
  payment). 7 new tests (5 action-level covering the happy path,
  service-line no-op, blank-reason/double-void/already-refunded
  guards; 2 Livewire permission/round-trip). Verified live: completed
  a real 3-unit sale, voided it through the actual back-office form as
  Owner, confirmed the flash message, the "voided by ... — reason"
  header line, `stock_levels` restored exactly to its pre-sale
  quantity, the payment flipped to `voided`, and that a Cashier gets a
  403 hitting the void route directly.

- **Cash in/out (till drops)** (`app/Domain/Sales/Actions/RecordCashMovementAction.php`)
  — `cash.movement` was seeded (Owner/Manager only, same manager-mediated
  pattern as `sales.refund`/`sales.void`) against an already-real
  `cash_movements` table, but nothing ever created a row: the shift
  report's "Cash movements" panel
  (`resources/views/livewire/reporting/shifts.blade.php`) was fully built
  and permanently rendered "No cash movements," and `shifts.cash_dropped`
  sat at its default `0` forever. Recording lives on the existing
  back-office shift-manage page (`app/Livewire/Sales/Shift/Manage.php`),
  right alongside the open/close-shift cards that already live there —
  no new route. `CloseShiftAction::expectedCash()` already summed
  `cash_movements` by direction into the till total; recording a movement
  makes that calculation correct with **zero changes** to that method.
  `cash_dropped` — a snapshot column with no prior writer — is now
  populated at close time as the shift's total `out`-direction movements,
  the same way `expected_cash`/`counted_cash`/`cash_variance` are already
  snapshotted there. 5 new tests (4 action-level: in/out recording,
  refusing a closed shift, `cash_dropped`/`expected_cash` correctness at
  close; 1 Livewire permission/round-trip). Verified live: opened a shift
  as Owner, recorded a cash-out ("safe drop") and cash-in ("petty cash
  return") through the actual form, confirmed both appear in the on-page
  list immediately, closed the shift and confirmed `cash_dropped` (50.00)
  and `expected_cash` (150 opening + 10 in − 50 out = 110.00, zero
  variance) matched exactly, and confirmed the back-office shift report's
  "Cash movements" panel — previously always empty — now lists both
  entries. Confirmed a Cashier does not see the cash in/out card. Out of
  scope, matching prior phases' discipline: no editing/voiding a recorded
  movement (an append-only ledger, same convention as `StockMovement`),
  no Cashier self-service drops (permission is deliberately manager-only),
  no standalone cash-movements report beyond the shift report's existing
  panel.

- **Tax rate management UI** (`app/Livewire/Taxation/Categories/{Index,Form}.php`,
  `app/Policies/TaxCategoryPolicy.php`) — `taxes.manage` was seeded
  (Owner/Manager) with real schema (`tax_categories`/`tax_rates`) behind
  it, but rates were only ever changeable via `TaxSeeder` or raw SQL.
  Built as plain CRUD (direct Eloquent in the Livewire component, no
  dedicated domain Action — matches `Terminals\{Index,Form}`, since
  there's no multi-step business process here, unlike `PurchaseOrder`).
  Deliberately **not** a flat "one rate per category" editor:
  `RateMatrix::forDate()` already loads every currently-effective
  `TaxRate` row ordered by `cascade_sequence`, so a category can have
  multiple stacked rates and/or a future-dated rate scheduled alongside a
  current one — the form manages a repeating array of rate rows (name,
  rate %, effective from/to), the same `addLine`/`removeLine`-array
  pattern already used by `PurchaseOrders\Form`, with `cascade_sequence`
  set from array position and the seeded default `TaxJurisdiction`
  assigned invisibly (jurisdiction management itself stays out of scope —
  the migration's own docblock frames the jurisdiction matrix as
  future-only infrastructure, and only one jurisdiction exists). A
  category with zero rates is valid and tax-exempt (matches the seeded
  `exempt` category). Category delete is a soft delete with no in-use
  guard, matching the exact precedent already set by
  `Catalog\Categories\Index::delete()` for `items.category_id` rather
  than inventing new guard behavior for this one lookup table — a
  deleted category simply stops appearing in the item form's tax
  category dropdown (Eloquent excludes soft-deleted rows from that query
  by default; zero changes needed there). No changes to `RateMatrix`,
  `CartPricer`, or any report query — they already correctly consume
  whatever's in these tables. 8 new tests (search/list, create with two
  cascading rates, create with zero rates, edit that removes/updates/adds
  rates in one save, effective-date-ordering validation, soft delete,
  permission denial for both the Form and delete). Verified live: edited
  the seeded "Standard Rate" category, confirmed its 15% rate loaded
  correctly, added a second cascading rate and saved — the list
  immediately showed "15% + 2.5%"; created a new zero-rate category,
  confirmed it appeared in the item form's tax-category dropdown, deleted
  it, and confirmed it vanished from both the list and that dropdown.
  Confirmed a Cashier gets a 403.

- **Register UX gaps** (`resources/js/pos/views/Register.vue`,
  `resources/js/pos/views/Payment.vue`) — four related fixes given the
  stated hardware-store use case:
  - **Manual quantity entry**: the qty `<span>` is now an editable number
    input (`cart.setLineQuantity()`, mirroring `incrementLine`/
    `decrementLine` exactly) alongside the existing `+`/`−` buttons — no
    backend change needed, `UpdateCartLineRequest` already accepted an
    arbitrary quantity.
  - **Item search result picker**: `runSearch()` no longer silently adds
    `items[0]`/`matches[0]`; a single match still adds immediately, but
    2+ matches now show a picker (same dropdown pattern as the existing
    customer search right below it) so the cashier chooses the right SKU
    instead of getting whatever sorted first.
  - **Register sign-out**: `auth.js`'s `logout()` — fully correct but
    never called from anywhere — is now wired to a "Log out" button.
    Deliberately does not clear the terminal pairing or touch an
    in-progress cart: the terminal is the physical till, not the
    operator, and "Park sale" is already the explicit way to set a cart
    aside.
  - **Gift-card balance lookup**: a new read-only endpoint
    (`GET /api/v1/giftcards/{number}`, gated `giftcards.view` — already
    granted to Cashier) plus a "Check balance" button next to the
    existing reference field on the payment screen. Unlike
    `assertGiftcardUsable()`'s submit-time validation, an
    inactive/expired card doesn't 404 — the cashier needs to see *why*
    it can't be used, so balance/status are always returned together.
    The authoritative validation in `AddCartPaymentAction` is untouched.

  Verifying the manual-quantity-entry fix live surfaced a real,
  pre-existing bug in the offline sync engine, unrelated to any of the
  four items above but exposed by them: `sync/engine.js`'s `update_line`
  case sent the PATCH but returned `{}`, a no-op, unlike every sibling
  case (`add_line`, `add_payment`, etc.), which all re-apply their own
  result onto the snapshot. If an item's own `add_line` op was still
  in-flight when a quantity edit landed (a normal race — the line renders
  optimistically before its creation round-trip resolves), the
  `add_line` response would later overwrite the whole line, silently
  reverting the edit on screen even though the server had already
  correctly applied it — reproduced live with the pre-existing `+`
  button too, not something this phase introduced, just newly exposed by
  testing a fast qty-edit-right-after-search flow end-to-end. Fixed by
  having `update_line` return its own `lineUpdate` patch, same as every
  other mutating case. No new Pest coverage needed for the three
  Vue-only changes (qty entry, search picker, sign-out) — they sit on
  top of already-tested backend endpoints; 2 new Vitest cases
  (`setLineQuantity`, and the zero-quantity-removes-the-line path) plus 5
  new Pest tests for the gift-card lookup endpoint (found/not-found/
  inactive-not-404/unauthenticated/unauthorized). Verified live end to
  end in the real Vue register: typed a manual quantity and watched the
  total recompute correctly (and stay correct, post sync-engine fix);
  searched a term matching 5 items and picked one from the resulting
  list; logged out and confirmed landing on `/login` with a reload not
  bouncing back in; checked a real gift card's balance ($75.00) and an
  unknown number's "not found" message on the payment screen.

- **Operational readiness bundle** (`bootstrap/app.php`,
  `app/Support/Idempotency/IdempotencyKey.php`) — no scheduler existed at
  all before this (`bootstrap/app.php` had no `withSchedule()`), so
  `ReconcileStockCommand` never ran automatically and `idempotency_keys`
  grew unbounded despite its declared 48h TTL. Now: `stock:reconcile`
  runs daily, deliberately **without** `--fix` — it only surfaces drift
  for a human to review, preserving the command's own existing safety
  contract ("reports the disagreement instead, and only repairs it when
  explicitly asked"). Idempotency-key cleanup reuses Laravel's own
  `Prunable` trait + built-in `model:prune` command (hourly) rather than
  a bespoke delete command — `IdempotencyKey::prunable()` is one line.
  `composer.json`'s `setup` script now runs `db:seed --force` after
  `migrate --force`, so a fresh install has roles and an Owner account
  without a manual step. Sanctum tokens now expire after 24h
  (`SANCTUM_EXPIRATION`, minutes, in `.env.example`) instead of never —
  narrowing token *abilities* was considered and skipped, since nothing
  in this codebase checks `tokenCan()` anywhere (all authorization goes
  through spatie/laravel-permission's route middleware), so it wouldn't
  restrict anything today. Point expiry was also named in the original
  audit bullet but turned out not to be a scheduling gap at all — the
  underlying feature (an expiry-window config, and a writer that sets
  `expires_at` on `earn` transactions) doesn't exist yet; confirmed with
  the user and moved to its own Not-started item below rather than either
  built as a surprise or silently dropped. Writing this phase's test
  surfaced a real Laravel testing gotcha worth remembering:
  `withSchedule()`'s callback only wires up via an internal
  `Artisan::starting` hook (`ApplicationBuilder::withSchedule()`), so a
  Feature test that resolves `Schedule::class` directly without first
  going through a console command sees zero registered events — the test
  must exercise the schedule through `Artisan::call('schedule:list')`
  (or any artisan call) instead, which is also a more realistic test
  since it's what an operator would actually run. 2 new tests (schedule
  registration via `schedule:list` output, and `model:prune` correctly
  pruning an expired key while keeping a fresh one). Verified live:
  `php artisan schedule:list` shows both entries with the right
  frequency; manually created an expired `idempotency_keys` row via
  `tinker`, ran `model:prune`, confirmed it was deleted and a fresh row
  survived; confirmed `config('sanctum.expiration')` resolves to 1440 by
  default.

- **Standalone stock adjust/transfer screens** (`app/Domain/Inventory/Actions/AdjustStockAction.php`,
  `app/Domain/Inventory/Actions/TransferStockAction.php`) — `inventory.adjust`/
  `inventory.transfer` were seeded (Stock Clerk + Owner/Manager) but
  gated nothing; the only way to move stock outside a sale/receiving/
  count was through the Receivings form's `transfer_in`/`transfer_out`
  types, which forces picking a `Supplier` for what's actually an
  internal movement and creates two entirely separate, unlinked
  receiving records with no relationship between them —
  `ReceiveGoodsAction`'s `Supplier` parameter is non-nullable even for
  transfers, and `Receiving::transferToLocation()` was never actually
  set by it. Both new actions are thin wrappers around
  `InventoryService::record()` (the only path stock is allowed to
  change, reused unmodified) — an adjustment requires a note (same
  "explain every manual override" requirement `VoidSaleAction` already
  enforces for `void_reason`) and writes one `stock_movements` row; a
  transfer writes two (`out` negative at the source, `in` positive at
  the destination) **linked to each other** via the ledger's existing
  polymorphic `source_type`/`source_id` columns — one `StockMovement`
  pointing at the other, zero schema changes. Neither guards against a
  resulting negative balance, matching this codebase's existing,
  universal precedent for every other manual stock mutation
  (`ApproveStockCountAction` and the Receivings transfer path both
  already work the same unguarded way). Reachable both from a new
  "Adjust Stock"/"Transfer Stock" nav link and from the Stock Levels
  page directly (pre-selecting that location). 13 new tests (adjust
  up/down, zero/blank-note/non-stock-item guards, transfer with linkage
  assertions, same-location/zero-quantity/non-stock-item guards,
  Livewire permission/round-trip for both). Verified live as a Stock
  Clerk: adjusted Cola 330ml down by 10 with a note, transferred 20 from
  Main Store to Warehouse, confirmed via `tinker` the exact math (240
  opening − 10 adjustment − 20 transfer-out = 210 at Main Store, 20 at
  Warehouse) and that the transfer-in movement's `source_id` correctly
  points at the transfer-out movement's id. Confirmed a Cashier gets a
  403 on both routes.

- **Back-office delete gaps** (Giftcards, Promotions, Loyalty Packages,
  Terminals) — all four were already `SoftDeletes`-enabled at the model
  level but had no delete UI anywhere, so a typo'd one was permanent.
  Followed the existing `Categories\Index::delete()` precedent
  (`Gate::authorize('delete', $model); $model->delete();` + a
  `wire:confirm` button) for Promotions, Loyalty Packages, and Terminals,
  each gated on the same `*.manage` ability as their `create`/`update`.
  Giftcards got one deliberate exception: unlike pure-metadata lookups, a
  gift card can hold real customer value, so `Giftcards\Index::delete()`
  refuses (flashing an error, no schema/authorization change) unless
  `balance->isEqualTo($initial_value)` — i.e. nothing has happened to it
  since issue — via a new `GiftcardException::cannotDeleteUsed()`. The
  delete button itself is hidden rather than shown-then-erroring once a
  card's balance has moved. 8 new Pest tests across the four resources'
  existing backoffice test files (delete succeeds + soft-deletes,
  non-`.manage` user gets 403; Giftcards additionally covers the
  balance-guard refusal). Verified live as Owner: created a terminal, a
  promotion, and a loyalty package via `tinker`, deleted each from its
  index and confirmed it disappeared from the list; issued a fresh gift
  card (delete button present, deleted successfully) and topped up a
  second gift card's balance (delete button no longer rendered on that
  row). No console/HTTP errors during the walkthrough.

- **Receivings detail view** (`app/Livewire/Purchasing/Receivings/Show.php`,
  `resources/views/livewire/purchasing/receivings/show.blade.php`) — a
  posted receiving previously had no way to be reviewed after the fact,
  only its labels reprinted. `ReceiveGoodsAction` posts stock
  immediately with no draft state and no update action exists for
  `Receiving`, so this is a read-only detail page (editing would mean
  reversing stock movements — a separate, unrequested feature), mirroring
  `Sales\Show`'s exact shape: `Gate::authorize('view', $receiving)` (the
  policy method already existed, just unused) then eager-load
  `lines.item`, `lines.stockLot`, `purchaseOrder`, `supplier`,
  `stockLocation`, `transferToLocation`, `user`. The view shows a header
  meta line (date, type, location, transfer-to location for transfers,
  received-by user, supplier, a link to the source PO if any, supplier
  reference/comment), a lines table (item, SKU, lot number + expiry from
  the linked `StockLot`, quantity, unit cost, discount, line total), and
  a totals box — plus a "Print labels" button reusing the existing
  `printLabels` gate. Added `Route::get('/{receiving}', Show::class)`
  inside the existing `receivings.` group (already gated
  `permission:receivings.view`, no middleware change needed) and a
  "View" link on the index, gated `@can('view', $receiving)`. 2 new Pest
  tests (`ReceivingShowTest.php`): a Stock Clerk sees the receiving's
  number/lines/lot number/total; a user without `receivings.view` (the
  seeded Cashier) gets a 403. Verified live as Owner: created a
  receiving with a lot number via `tinker`, opened it from the index's
  new "View" link, confirmed the header/lines/lot/expiry/totals render
  correctly, confirmed "Print labels" navigates correctly from the
  detail page, confirmed the Cashier gets a 403 hitting the show route
  directly. No console/HTTP errors during the walkthrough.

- **`wire:loading` feedback across forms** — none of the 16 form Blade
  views (the audit's "14" was stale; two more shipped this session)
  had any `wire:loading` anywhere, so a slow save gave no visual
  feedback and nothing stopped a second click from firing a second
  request. Applied one consistent pattern to every save/state-transition
  button across all 16 files: `wire:loading.attr="disabled"` +
  `wire:target="<action>"` on the button, plus a busy-label `<span>`
  swap ("Save" → "Saving…", etc.) — `wire:target` is required rather
  than bare `wire:loading` since several forms also fire unrelated
  `wire:model.live` requests (e.g. `StockCounts\Form`'s
  `countAllItems` checkbox) that would otherwise falsely show the
  button as busy. Deliberately excluded pure client-side array-mutation
  buttons (`addLine`/`removeLine`, `addCondition`/`removeCondition`,
  `addRate`/`removeRate`) — clicking those twice is harmless, no real
  double-submit risk to guard. No new Blade component was introduced
  (this codebase has none anywhere) — the snippet is repeated inline
  per button, matching every other view in the app. No new Pest tests
  either — this is a purely client-side attribute/markup change with
  no new server logic, verified live instead. Verified with Playwright
  by intercepting and delaying the Livewire update endpoint
  (`**/livewire*/update`) ~1.5s on a representative spread — a
  single-submit form (Categories), a two-form component (Giftcards:
  issue then top-up), and the seven-action Stock Counts workflow
  (create → generate lines → save counts → submit → approve): every
  button's `disabled` attribute was present and its label swapped
  while its request was in flight, a forced second click during that
  window produced zero additional requests each time (the browser
  itself won't dispatch a click on a genuinely disabled button — the
  actual double-submit guard), and sibling controls on the same page
  stayed interactive throughout. Full suite still 425/425 (unchanged,
  as expected for a client-side-only change) and Pint clean.

- **Mobile/tablet back-office nav** — the sidebar was `hidden lg:block`
  with no hamburger or alternate nav, so anyone below 1024px (a tablet
  on the floor, not just a fixed desktop terminal) lost all back-office
  navigation. Fixed with a single edit to
  `resources/views/layouts/app.blade.php` — the only `layouts.app` in
  the app, shared by all 54 back-office Livewire components, so one
  change covers everything (the POS register at `pos.blade.php` is a
  separate standalone document, untouched). Reused Alpine.js, which
  Livewire already bundles (`@livewireScripts`) — no new dependency.
  The `<aside>` became an off-canvas panel (`fixed`, `-translate-x-full`
  by default, toggled via `x-data="{ mobileNavOpen: false }"` on the
  outer wrapper) with a click-to-close backdrop and an in-panel close
  button; a hamburger button (`lg:hidden`) was added to the header to
  open it. Above the `lg` breakpoint the exact same classes that
  applied before (`lg:static lg:translate-x-0`) restore today's static,
  always-visible sidebar with zero visual change. Since no
  `wire:navigate` is used anywhere in this codebase, every nav link is
  still a normal full-page load, so the open/close state resets for
  free on navigation — no extra "close on click" JS needed. No new
  tests (pure markup/CSS, verified live). Verified live with Playwright
  across three viewports: at 390×844 (mobile) and 820×1180 (tablet) the
  sidebar starts off-screen with a visible hamburger, opening it slides
  the panel in with a backdrop, clicking a nav link navigates correctly
  and the panel is closed again after the fresh page load, and both the
  X button and a backdrop click independently dismiss it; at 1440×900
  (desktop) the hamburger and backdrop are both absent and the sidebar
  renders exactly as the pre-existing static layout, confirming no
  regression above `lg`. No console/HTTP errors. Full suite unchanged
  at 425/425, Pint clean.

- **Hardware-mode register UX + Business Type setting** — the user
  asked for a full Retail/Hardware/Restaurant/Salon/Services
  configurable POS platform. That conflicted with this project's own
  recorded scope decision (`PROJECT_STATUS.md` already stated the
  target deployment is a hardware store and restaurant UI "has been
  dropped from scope entirely as inapplicable, not merely deferred,"
  matching a stored decision from a prior session), and most non-
  retail/hardware modes need backend domain models that don't exist at
  all (appointments, dine-in tables, staff commissions, work-order
  deposits). Flagged this conflict to the user; the answer was
  "hardware and retail," so this phase built exactly that: a
  **Business Type setting** (`BusinessProfileSettings::business_type`,
  `retail`/`hardware`, default `retail`) on the existing Business
  Profile page, gated `config.manage` like every other Settings page
  (the request said "Owner/Manager," but `config.manage` is Owner-only
  in this codebase's actual role model — `Manager` explicitly excludes
  it — so this stays consistent with the rest of Settings rather than
  inventing a one-off broader gate), exposed to the POS SPA via a new
  `GET /api/v1/config` endpoint and a `stores/business.js` Pinia store
  (`isHardwareMode` getter) that every Hardware-only UI branch reads.
  Researching each requested register feature found most were already
  backend-complete and only missing frontend wiring: **quotes**
  (`Sale::TYPE_QUOTE`, `CompleteSaleAction`'s no-payment/no-stock-
  movement branch, and `DocumentNumberGenerator`'s `quote` sequence
  were all already fully wired — only `cart.js`'s `startNewSale` never
  threaded `sale_type` through, and there was no "Start quote"/"Save
  quote" UI) and **bill-to-account** (`payment_methods` already seeds a
  real `kind=account` row and checkout already accepts it unconditionally
  — this phase only added a one-tap "Bill $X to account" button in
  `Payment.vue`; deliberately did **not** add any accounts-receivable/
  balance ledger, since none exists and building a UI implying one
  would misrepresent what's actually tracked). **Decimal quantities**
  were already fully supported server-side (`quantity` is
  `decimal(15,3)`, validated `numeric` not `integer`) — purely a
  frontend `step` fix. **Stock by location** needed a real backend
  addition: `ItemController`/`ItemResource` exposed no stock figure at
  all, so a `stock_location_id` param + eager-loaded `stockLevels` +
  a new `stock_at_location` resource field were added; discovered live
  that the badge is only useful if it survives onto the cart line
  itself (a single exact-match search auto-adds directly, bypassing
  the multi-match dropdown where it was first shown), so `cart.js`'s
  `addLine` now carries it onto the line too. **Found and fixed a real,
  pre-existing bug**: `UpdateCartLineRequest`/`CartLineController::update()`
  validated and persisted only `quantity`, even though `cart_lines` has
  had `discount_value`/`discount_type`/`price_overridden`/
  `price_overridden_by_user_id` columns all along and `CartPricer`
  already reads them for every pricing calculation — no
  `UpdateCartLineAction` existed at all (breaking this codebase's own
  "every mutation gets an Action" convention). Added one, gated price/
  discount edits on `sales.change_price` (a seeded-but-previously-
  unused ability, the same "wired to nothing until now" story as
  `config.manage` in the Business Profile phase) while leaving quantity
  and the new line-note field (`description`, already wired at add-time,
  just missing from the update path) ungated, matching today's
  behavior. `sync/engine.js`'s `update_line` case was widened from a
  hardcoded `{ quantity }` re-apply to re-applying its own full payload
  (same race-safety reasoning already documented there, generalized to
  any field). **Found and fixed a second real bug live**: `auth.js`
  primed the payment-methods and catalog caches right after login but
  never called `business.refresh()`, so the configured Business Type
  never reached the POS frontend after a fresh sign-in (only after an
  already-authenticated reconnect) — the live walkthrough caught this
  immediately when "Start quote" never appeared. 24 new Pest tests
  (Business Type settings, the config endpoint, `stock_at_location`,
  and the `UpdateCartLineAction` permission/persistence matrix) and 5
  new Vitest tests (quote `sale_type` threading, price override,
  line notes, `stock_at_location` on add, and the widened `update_line`
  sync). Verified live end-to-end as both a Cashier and a Manager in
  Hardware mode: decimal quantity entry, a stock-at-location badge on
  the cart line, a line note, a Cashier's price-override attempt
  correctly denied (surfaced through the existing `lastError`/"Discard
  sale" banner — no new error-handling UI needed), a Manager's price
  override succeeding and surviving a full page reload (via the
  existing parked-cart resume flow — a reload always starts with an
  empty in-memory cart by design, offline-first), a full quote flow
  with no payment screen landing on "Quote saved" with a real quote
  number, and a sale completed via "Bill to account." Then confirmed
  Retail mode (the default) is pixel- and behavior-identical to before
  this phase — no quote button, no stock badge, no price-override
  control, integer quantity stepper — zero regression to the existing
  experience.

- **Register UI/UX redesign** — the register was functional but visually
  minimal: single-column, search-only (no product grid or category
  browsing), a large empty dead zone below the cart, no cashier/shift
  identity in the top bar, and plain text labels instead of icons. The
  earlier, much larger multi-vertical POS request (already descoped to
  Retail+Hardware two phases ago) included a detailed "Visual/UI
  Requirements" and "Retail Mode UX" brief that's still valid guidance
  on its own — professional terminal style, large touch targets, strong
  hierarchy for total/due/primary action, icons for common actions, no
  decorative elements — reused here as the design bar, with none of the
  business-type-switching machinery. **Top bar**: cashier name
  (`authStore.user.name`, already available) and a "Shift open"/"No
  shift" pill next to the terminal name — `TerminalResource` gained a
  `shift_open` boolean off the already-existing `Terminal::openShift()`;
  drawer/park/logout became icon+label buttons. **Category + product
  grid**: new `GET /api/v1/categories` endpoint
  (`permission:items.view`, same gate as `/items`) and a
  `stores/categories.js` cache-store mirroring `catalog.js`'s shape;
  `Register.vue` gained a horizontal category-tab row above a
  persistent grid of tappable product tiles (colored initial-letter
  avatar — no image upload infrastructure exists, so this is the
  honest substitute — name, price, and the Hardware-mode stock badge
  from two phases ago). The grid filters **client-side** from the
  already-offline-cached `catalog.items` as the cashier types or
  switches category — zero new network calls per keystroke; the
  existing server-backed search (Enter/Search button) stays as the
  barcode-precise/disambiguation-dropdown fallback it already was.
  **Cart panel**: "Remove"/"Edit" gained trash/pencil icons, same
  behavior. **Payment screen**: each payment method tile gets a small
  icon keyed off `method.code`/`kind` (cash, card, check, gift card,
  points, account, with a generic fallback for anything else — proved
  itself immediately on the seeded "Bank Transfer" method, which has
  none of those codes). All new icons are inline SVGs matching the
  only precedent in this codebase (the hamburger/close icons from the
  mobile-nav phase) — no icon library dependency added, and no new
  shared Vue component either, matching the codebase's existing
  two-small-components-total precedent. 4 new Pest tests (`shift_open`
  reflecting an open/closed shift, categories list/permission/auth) —
  no new Vitest tests, matching this codebase's established boundary
  that simple cache-stores (`catalog.js`, `business.js`,
  `paymentMethods.js`) have never had dedicated unit test files, only
  the two genuinely stateful modules (`cart.js`, `sync/engine.js`) do.
  Verified live with Playwright at desktop (1440×900) and tablet
  (820×1180): cashier name and shift pill render, category tabs filter
  the grid correctly, live search narrows it instantly, tapping a tile
  adds to the cart, the two-column layout holds up and reflows
  sensibly at both widths with no dead space, icon controls all work
  exactly as their text-only predecessors did, and the Hardware-mode
  stock badge still renders correctly on tiles. No console/HTTP errors.
  **Follow-up same session**: the user asked for the Payment screen to
  get the same treatment. `Payment.vue` moved from a single stacked
  column to the same two-panel language as the register — a left order-
  summary card whose "Amount due" is now the dominant number (turns
  green at zero, mirroring the register footer's total treatment) with
  an itemized payments list, and a right tender panel (payment-method
  tiles, amount field, reference/gift-card-balance check, "Add
  payment"). Added quick cash-tender chips — "Exact" plus up to three
  round-up-to-nearest amounts computed from the due total (nearest 5,
  10, 20, 50, or 100, whichever are actually above what's owed) — a
  standard cashier convenience that was entirely missing before,
  currency-agnostic since it's derived from the due amount rather than
  hardcoded bill denominations. No logic changes beyond the new chips;
  verified live end-to-end (search → add → pay via a quick chip →
  complete sale → confirmation), no console/HTTP errors.

- **Delete a parked sale** — reported as "cannot delete parked sales":
  once a sale was parked there was no way to remove it, only resume it,
  in either the "Parked sales" (this device) or "Parked on other
  terminals" list. `Cart` already had an unused `STATUS_ABANDONED`
  constant (and the migration comment already documented it) — a
  designed-but-never-wired fourth status, so this became a status
  transition (`active`/`suspended` → `abandoned`), not a hard row
  delete, preserving the audit trail the same way `suspend` does.
  `CartController::abandon()` mirrors `suspend()` exactly (`Gate::
  authorize('view', $cart)`, status guard, inline update — no Action
  class, matching the closest precedent), gated by a new
  `permission:sales.delete` route middleware. `sales.delete` was
  another seeded-but-unused permission (already in the ability catalog,
  already granted to Owner/Manager) — granted to Cashier too, since
  cashiers can already create and suspend/park a sale and should be
  able to abandon their own. New `abandoned_by_user_id`/`abandoned_at`
  audit columns via migration, mirroring `suspended_*`. On the frontend,
  a new `abandon` sync-engine op (mirrors `suspend`'s case) and a
  `deleteParked()` store action follow the exact optimistic-flip +
  queue + resync shape `parkCurrent()` already uses — works offline,
  the queued op waits and retries on reconnect. Carts parked on other
  terminals have no local snapshot to flip, so `abandonOtherTerminalCart()`
  is a direct online-only API call, matching `resumeFromOtherTerminal`'s
  existing precedent. Both parked lists in `Register.vue` gained a small
  trash-icon delete button (reusing the existing cart-line trash SVG)
  next to the existing resume button, guarded by a `window.confirm`
  prompt — no modal component exists in this codebase to justify adding
  one for a single confirm step. 4 new Pest tests (abandon active,
  abandon suspended, reject abandoning a completed cart, reject without
  `sales.delete`) and 3 new Vitest tests (`deleteParked`,
  `abandonOtherTerminalCart`, the `abandon` sync-engine op). Verified
  live with Playwright: parked a sale, deleted it, confirmed it
  disappeared and — critically — stayed gone after a full page reload,
  proving the delete reached the server rather than just clearing local
  state. No console/HTTP errors.

- **Keyboard-friendly register + payment screen** — "cashier interface
  should be very keyboard friendly, easy to move [between] element[s]
  without using [the] mouse every time." Every control was already a
  real `<button>`/`<input>` (native Tab-reachable), but focus never
  returned anywhere automatically and there was no fast way to cross a
  20+ tile grid or trigger the big actions without a mouse. **Search**:
  auto-focuses on new sale/quote, after adding an item (grid, dropdown,
  barcode scan), and after closing the line-edit panel — it's now the
  resting place focus always returns to. The multi-match dropdown
  gained arrow-key highlighting and Enter-to-add-the-highlighted-match
  (the existing single-match auto-add path was untouched). Escape
  clears search. **Product grid**: arrow keys move focus tile-to-tile,
  reading the grid's live (responsive) column count via
  `getComputedStyle` rather than hardcoding one, querying the
  container's current button children at keydown time so it stays
  correct as category/search filtering changes the tile count — Enter/
  Space to add is native, no new code needed. **F-keys**: F2 parks the
  sale, F4 triggers whichever primary CTA is currently showing (Take
  payment or Save quote), F9 opens the drawer — chosen specifically to
  avoid the F-keys with real browser bindings (F1/F3/F5/F6/F7/F11/F12).
  **Payment screen**: the tendered-amount input auto-focuses, Enter
  adds the typed amount and immediately completes the sale once it's
  covered the due total (repeatable single-key checkout), and digit
  keys 1-9 pick a payment-method tile — reusing `BarcodeCapture.vue`'s
  existing `event.target` tag-check convention so digits never leak
  into a field the cashier is typing into. Quick-tender chips and
  category tabs needed no new code (already short, native-Tab-
  reachable button rows). Pure frontend interaction wiring — no
  backend changes, no new Pest/Vitest tests (matches this codebase's
  established boundary that Vue views have zero dedicated test files).
  Verified live with a Playwright walkthrough that never once used
  `page.click()` after login: auto-focus, dropdown arrow-nav +
  Enter-add, grid arrow-nav + Enter-add, F2 park, F4 to payment,
  tendered auto-focus, digit-key method pick, and a single
  Enter-driven tender-and-complete all the way to Confirmation — no
  console/HTTP errors.

- **Fixed: register page randomly refreshing** — reported as "when
  adding new items to register page refreshed." Root cause:
  `main.js` registered `/sw.js` unconditionally, including under
  `vite dev`, and `public/sw.js` was a stale artifact left over from
  an earlier `npm run build` (`generateSW` only ever runs during a
  real build, never in dev). Once a browser tab had that service
  worker registered from any prior production/test session, it
  self-activated (`skipWaiting`/`clientsClaim`, already required for
  offline-navigation-on-first-visit — see the strategy's own comment
  in `vite.config.js`) and silently took over the page mid-session,
  serving its own day-old precached build shell and stale
  `NetworkFirst`-cached `/api/v1/items` responses instead of what the
  dev server and the (frequently reset) dev database actually had.
  Reproduced live via Playwright: a browser with the service worker
  pre-registered kept it active indefinitely; the fix (register only
  when `import.meta.env.PROD`, and actively `unregister()` any
  registrations found otherwise) made it disappear on the very next
  load. No effect on production builds, where registration is
  unchanged. No new tests (pure dev-environment wiring, nothing to
  assert against in a test suite that doesn't build+serve the PWA).

- **Close-shift screen: LKR labeling + closed-shift summary** —
  reported as "close shift currency records shold be LKR currencied."
  The currency itself was already correctly configured
  (`POS_CURRENCY=LKR`, live-confirmed) and every `MoneyCast`-cast
  field already rendered as `"LKR X.XX"` when echoed (Brick\Money's
  default `__toString()`), so the actual gaps were elsewhere: the
  close-shift form's raw `<input>`s (opening float, each denomination
  count, cash-movement amount) showed no currency indicator at all,
  and — the bigger gap — the closed-shift reconciliation record
  itself was never shown: `CloseShiftAction` computes and persists
  `expected_cash`/`counted_cash`/`cash_variance`/`cash_dropped`, but
  `Manage::close()` discarded its return value, and the screen
  immediately reverts to the "Open shift" form the instant a shift
  closes. Also found: `config/pos.php`'s cash denominations were a
  generic/USD-shaped list (`100, 50, 20, ... 0.5, 0.25, 0.1, 0.05,
  0.01`) with no relation to real LKR notes/coins — replaced with
  actual LKR denominations (`5000, 1000, 500, 100, 50, 20, 10, 5, 2,
  1`), the only place that config key is read
  (`Manage.php`'s `resetCashCounts()`). Fix: `Manage.php` now captures
  `CloseShiftAction::execute()`'s return into a new
  `lastClosedShift` property (cleared on re-opening or switching
  terminals); `manage.blade.php` renders it as a summary card
  (opening float / expected / counted / variance, colored by sign /
  cash dropped) above the reappeared open-shift form, and adds an
  "LKR" prefix adornment to the three previously-unlabeled inputs —
  all via plain-echoed `Brick\Money` fields, matching this codebase's
  existing money-display convention elsewhere (reporting/PDF views)
  rather than introducing the unused `Money::format()` locale
  formatter into just this one screen. 1 new Pest test
  (`ShiftManageTest`: closing exposes `lastClosedShift` with the
  correct `counted_cash`/`cash_variance`). Verified live: every input
  shows "LKR", the real LKR denomination list appears in the count
  grid, and after closing a shift the summary card shows all four
  reconciliation figures correctly in LKR with the next "Open shift"
  form still usable immediately after. No console/HTTP errors.

- **Reorganized back-office nav sidebar** — reported as "confusing":
  the sidebar was a near-flat list of 36 links with only one existing
  section ("Reports", 10 of the 36), and no active-page highlighting
  anywhere. Regrouped into 8 collapsible sections — Sales (Register,
  Shift, Shift History, Sales), Catalog, Customers & Suppliers,
  Inventory, Purchasing, Marketing, Reports (unchanged gating, just
  restyled to match), Settings — each with an icon, a chevron that
  rotates open/closed, and independent Alpine state
  (`x-data="{ open: ... }"`) nested inside the existing
  `mobileNavOpen` scope so the mobile off-canvas sidebar mechanics
  stay untouched. The section containing the current page auto-
  expands (`request()->routeIs(...)` glob per group) and its own link
  is highlighted (exact-match, so sibling routes sharing a name prefix
  never over-highlight) — both computed server-side, no client state/
  localStorage needed. Introduced this app's first two anonymous Blade
  components, `<x-nav-link>` and `<x-nav-group>`
  (`resources/views/components/`), specifically to collapse the 36
  near-identical `@can`/`@if(Route::has(...))`/`<a>` blocks into one-
  line calls — every individual permission gate was preserved exactly
  (verified against `routes/backoffice.php`'s middleware first), with
  a new `@canany(...)` wrapper per group solely to avoid rendering an
  empty collapsed header for a role with zero visible links in it.
  Verified live as both Owner (all 8 groups, correct auto-expand/
  collapse per page, manual toggle works) and Cashier (only Sales/
  Catalog/Customers & Suppliers/Inventory/Marketing show — no
  Purchasing/Reports/Settings, matching the seeded role's actual
  permissions) plus a mobile-width check that the hamburger/off-canvas
  toggle still works unaffected. No new tests (pure Blade/view markup,
  no Livewire component backing the nav, matching this session's
  existing precedent for layout-only changes). No console/HTTP errors.

- **Audit-log viewer** — first of the P1 capability gaps: `spatie/laravel-activitylog`
  was already writing rows (`Sale` and `SupplierInvoice` both log via
  `LogsActivity`) and `audit.view` was a seeded, granted permission
  (Owner/Manager/Accountant) gating nothing. New `App\Livewire\Audit\Index`
  (`/audit`, in the Settings nav group) borrows the filter-bar shape
  from `Reporting\Shifts` (from/to date range, defaulted to the last 30
  days, plus user and event-type selects) and the paginated-table shape
  from the CRUD index screens, since an audit log is genuinely both.
  Confirmed from the package source (not assumed) that this app's
  default `created`/`updated`/`deleted` auto-logging populates
  `attribute_changes` — not the separate `properties` column, which
  stays empty here — as `{attributes: {...new}, old: {...old}}`; the
  expandable detail row reads that directly. Found and fixed a real bug
  while verifying live: a `Money`-cast field (e.g. `Sale.total`)
  serializes into `attribute_changes` as `{"amount":..., "currency":...}`
  rather than a plain scalar, which the view was about to echo directly
  — added a small `formatChangeValue()` helper on the component so
  money changes render as `"LKR 150.00"` instead of the literal string
  `"Array"`. Subject rows resolve to a human label (`Sale #<number>`,
  `Supplier Invoice #<invoice_number>`, falling back to
  `ClassBasename #<id>` for anything else) via a `subjectLabel()`
  helper — no new model/trait, since only two subject types exist
  today. Added `audit.view` to the Settings nav group's `@canany(...)`
  gate. 6 new Pest tests (view/forbidden, subject-label resolution,
  and each of the three filters) using directly-created `Activity` rows
  rather than a full Sale-lifecycle fixture, since the goal is testing
  the viewer, not re-testing the package's own logging. Verified live:
  triggered a real authenticated Sale status change, confirmed it
  appears with the correct subject/causer/event badge, the expanded
  detail shows old→new values with money correctly formatted, each
  filter narrows results correctly, and a Cashier gets a 403 and never
  sees the nav link. No console/HTTP errors.

- **Real README** — second P1 operational-readiness item: `README.md`
  was 58 lines of stock Laravel boilerplate (marketing copy, framework
  links, a Boost pitch), no project-specific setup/run/deploy content
  at all despite the project's own `composer run setup` script never
  being mentioned anywhere. Rewrote it from the actual config/scripts
  (not assumed): stack summary, a quick-start that flags the one real
  trap in `composer run setup` (it leaves `DB_CONNECTION=sqlite`, the
  framework default, even though this project has only ever run
  against MariaDB/MySQL), an environment-variables table for the two
  `POS_*` keys (`POS_CURRENCY`, `POS_CASH_ROUNDING` — confirmed by
  grepping every `config/*.php` for `env('POS_`, not just `pos.php`;
  the "7 keys missing" P3 note elsewhere in this doc was stale from an
  earlier state of `config/pos.php` and has been corrected), the actual
  `withSchedule()` schedule table (`bootstrap/app.php`, Laravel 11+
  style — there's no `Kernel.php`), queue/Redis deployment notes, and
  the API quick-reference already maintained in this file's own
  "Running it" section. Also closed the gap the new README documents:
  added both `POS_*` keys to `.env.example` (previously absent
  entirely, silently relying on hardcoded defaults). No code/behavior
  change, no new tests — pure documentation.

- **Expenses CRUD** — third P1 capability gap: `Expense`/
  `ExpenseCategory` models existed (`app/Domain/Finance/`),
  `expenses.view`/`expenses.manage` were seeded and granted (Owner/
  Manager/Accountant), `DatabaseSeeder` even seeded 5 expense
  categories — but nothing outside the models referenced either one.
  Built two ordinary back-office CRUD pairs
  (`App\Livewire\Finance\{ExpenseCategories,Expenses}`), deliberately
  copying two already-existing templates verbatim rather than
  inventing new patterns: `Catalog\Categories` for the simple
  `ExpenseCategory` (name/code/description, no split-model or nesting
  complexity), `Crm\Suppliers`' shape for `Expense`'s `mount()`/
  `rules()`/`save()` structure. New `ExpensePolicy`/
  `ExpenseCategoryPolicy` (registered in `AppServiceProvider` —
  policies here are explicit, not auto-discovered), new **Finance**
  nav group. `Expense.currency` is silently set to the app's one
  configured `Money::currency()` on create rather than exposed as a
  field — no multi-currency capability exists anywhere else in the
  app, so a per-expense currency picker would be a fake capability;
  `user_id` similarly set from `auth()->id()`, not user-editable.
  `shift_id` intentionally left unset — no natural way to pick a
  historical shift from a days-later admin form. Found and fixed a
  real bug while writing tests: the edit form crashed
  (`Call to a member function getAmount() on null`) whenever
  `tax_amount` was null — a real, reachable state (any row without an
  explicit value, since the column's `default(0)` is DB-level only and
  never reflected back into a freshly-created Eloquent model without a
  `fresh()`/`refresh()` call) — fixed with a `?? Money::zero()` guard
  in `Form::mount()`. 11 new Pest tests across both resources (list/
  create/edit/delete/validation/permission-denial). Verified live as
  Owner: created a category, created an expense against it with a
  reference, edited its amount, deleted it, and confirmed a Cashier
  gets 403 and never sees the Finance nav group. No console/HTTP
  errors.

- **Jobs & Listeners** — fourth P1 item: `SaleCompleted`/`ShiftOpened`/
  `ShiftClosed` already dispatched (from `CompleteSaleAction`/
  `OpenShiftAction`/`CloseShiftAction`) but `app/Listeners/` didn't
  exist, so nothing observed them. User chose "low-stock alerts +
  audit trail" over email (nothing sends today — `MAIL_MAILER=log`) or
  a trivial no-op wiring. `LogLowStockOnSaleCompleted` reuses
  `ReorderSuggestionsQuery::forLocation()` (the exact same "below
  reorder level" comparison the Reorder Suggestions screen already
  uses) filtered to just the sale's own line items, logging one
  `activity('inventory')` row per item that's now low — confirmed the
  stock-movement write happens *before* `SaleCompleted` dispatches
  (`CompleteSaleAction.php:164-177` vs `:218`, same transaction), so
  on-hand is already accurate when the listener runs.
  `LogShiftOpened`/`LogShiftClosed` log an `activity('shift')` row
  each, with the opening/closing user as the `causedBy()` — a real
  audit trail, unlike the low-stock check which is system-triggered
  (no causer). All three run synchronously, not queued: no I/O, and
  this app has no queue-worker convention (`QUEUE_CONNECTION=database`
  but zero existing `ShouldQueue` usage) — queuing them would silently
  do nothing without a worker running. Both land in the Audit Log
  viewer built earlier this session with zero viewer changes required
  to *appear*; extended `subjectLabel()` with `Item`/`Shift` arms and
  the Event filter dropdown with the three new event names as a small,
  directly-connected polish. Laravel 11+ auto-discovers
  `app/Listeners/*` from the `handle()` type-hint (no
  `EventServiceProvider` in this app) — proved the actual binding
  exists (not just that the listener class works standalone) with a
  new `event:list`-based test, mirroring
  `OperationalReadinessTest.php`'s existing `schedule:list` check for
  the cron schedule. 5 new Pest tests (2 low-stock, 2 shift, 1 wiring).
  Verified live end to end: opened a shift, completed a real register
  sale for an item whose `reorder_level` was set above its stock,
  closed the shift, and confirmed all three activity rows appeared in
  the Audit Log with correct subjects, causers, and readable event
  badges. No console/HTTP errors.

- **Item Kits management UI** — fifth P1 item: `item_kits`/
  `item_kit_items` and the `ItemKit` model already existed and were
  already read by `CartLine::kit()`/`CompleteSaleAction`, but nothing
  ever set `item_kit_id` anywhere (confirmed by reading
  `AddCartLineAction`, which only ever accepts a plain `Item`) — kits
  were unreachable dead schema. Added a standard back-office CRUD screen
  (`ItemKitPolicy` gated on the already-seeded `item_kits.view`/
  `item_kits.manage` abilities, mirroring `ExpensePolicy`'s shape) for
  creating/editing kits: header fields plus a repeatable component-items
  list (mirrors `PurchaseOrders/Form`'s `addLine`/`removeLine` pattern),
  persisted via `$itemKit->items()->sync()` on the existing
  `BelongsToMany` pivot — no new Action class needed since kits have no
  stock/status side effects, unlike purchase orders. Since `item_kits`
  has no stored price column, the kit total is computed live in the form
  preview: sum of component `unit_price * quantity`, with the kit's
  `discount_value`/`discount_type` applied on top when `price_option` is
  `kit`/`both` (skipped for `components`), using the existing
  `Money::percentageOf()` helper already used by `PromotionEngine`. 7 new
  Pest tests (list/create/edit incl. add-remove-component/duplicate-kit-
  number/duplicate-component-item/empty-component-list/permission-denial/
  soft-delete). Verified live as Owner: created a 2-component kit with a
  10% discount, confirmed the preview math (component total minus
  discount = kit total), edited it down to 1 component, deleted it, and
  confirmed a Cashier (who only has `item_kits.view`) gets 403 on
  `/item-kits/create` but can still view the index. **Register/POS
  selling of kits is explicitly not part of this phase** — the user
  scoped it to back-office only, since making kits sellable would mean
  extending the offline-first IndexedDB sync engine, cart store, and
  catalog cache, none of which know about `item_kit_id` today. `Item`
  attributes (EAV) — the other half of the old combined "Item kits /
  attributes" bullet — remains a separate, not-yet-started P1 item below.

- **Serialized/lot tracking, back office** — sixth P1 item. `SerialNumber`
  had zero call sites and `items.is_serialized|tracks_lots|has_expiry`
  were cast but never branched on anywhere. Investigation found lot
  tracking was already *partly* wired — `ReceiveGoodsAction` accepted
  `lot_number`/`expires_on` per line and the Receivings form already had
  inputs for them — but nothing branched on whether an item actually
  tracks lots/has an expiry, and nothing captured individual serial
  numbers at all. Added: three flag checkboxes on the item form; the
  Receivings form now conditionally shows/requires the lot number and
  expiry fields per line based on the selected item's flags (previously
  shown unconditionally for every item), and a per-line "one serial per
  line" textarea appears for serialized items on a `receipt`-type
  receiving, validated (serial count must match quantity, no duplicates
  within the submission or against existing inventory) via a
  `withValidator()`/`after()` closure — the same pattern used by
  `ItemKits/Form.php` this session, including the same
  `errors()->add()`-must-run-inside-`after()` gotcha (calling
  `$validator->errors()->add()` directly inside the outer callback
  doesn't stick, since Laravel's `Validator::passes()` rebuilds the
  message bag from scratch on every call — only closures registered via
  `$validator->after()` run *inside* that rebuild). `ReceiveGoodsAction`
  now creates real `SerialNumber` rows (`status: in_stock`) for
  serialized items on receipt only — deliberately not for
  `return_to_supplier`/`transfer_out`, which would need to look up and
  transition *existing* serials rather than create new ones. Added a
  read-only Serial Numbers lookup screen (search/filter by status and
  location), gated on the existing `inventory.view` ability with a plain
  `Gate::authorize()` call, no new policy class — mirrors
  `StockAdjustments/Form.php`'s pattern for the same kind of screen. 8
  new Pest tests. Verified live: flagged an item serialized, received 2
  units entering matching serials, confirmed both appear `in_stock` on
  the lookup screen with correct item/location; confirmed a lot-tracked
  seeded item (`BAK-BREAD-WHT`) shows lot/expiry inputs on its receiving
  row while a plain item's row doesn't. **Register-time lot/serial
  picking is explicitly not part of this phase** — `cart.js` still always
  sends `stock_lot_id: null` and `CompleteSaleAction` does not transition
  serials to `sold`; same reasoning as Item Kits' deferred register
  integration.

- **Attributes (EAV) management** — seventh and final P1 capability-gap
  item. `attribute_definitions`/`attribute_values`/`attribute_links` had
  zero Eloquent models anywhere; added `AttributeDefinition`,
  `AttributeValue`, `AttributeLink` plus `Item::attributeLinks()`. Added
  an admin screen (`attributes.manage`, the only ability seeded for this
  — no separate `.view` exists, mirrored exactly on `TaxCategoryPolicy`'s
  single-ability shape) to define attributes (name, type, unit, an
  optional self-referencing parent for grouping, `show_in_receipt`/
  `show_in_search` flags — stored but not consumed by anything yet, same
  deliberate no-op as Item Kits' `print_option`). Item edit/create now has
  a repeatable "Attributes" section: pick a definition, enter a value
  whose input type switches on the definition's type (text with a
  `<datalist>` of prior values for reuse, number for decimal, date
  picker, and a Yes/No `<select>` for checkbox — deliberately not a raw
  `<input type=checkbox>`, to sidestep a Livewire quirk with checkboxes
  bound to array elements). Values are deduplicated via
  `AttributeValue::firstOrCreate()` (same shape as `ReceiveGoodsAction`'s
  `StockLot::firstOrCreate()` lot dedup) so two items sharing "Color: Red"
  share one row — confirmed live via a direct DB check (one
  `AttributeValue` row, two `AttributeLink` rows). Saving re-syncs an
  item's links by delete-then-recreate (mirrors
  `UpdatePurchaseOrderLinesAction`, not `sync()` — `attribute_links` isn't
  a fixed-column pivot). 9 new Pest tests. **Out of scope, deliberately**:
  no receipt/search wiring for `show_in_receipt`/`show_in_search`, no
  sale/receiving snapshotting of attribute values (the schema comment
  flags this as a future use), no dedicated "manage a dropdown's fixed
  option list" screen, no faceted "browse items by attribute" lookup.

- **Item bulk-edit and CSV import** — eighth and final P1
  capability-gap item, closing out the original P1 capability-gap audit
  entirely. Items index gained a checkbox column and a bulk-edit bar
  (visible once ≥1 row is selected) with four independently-toggled
  fields — category, supplier, tax category, active status — so an
  unchecked field is left untouched rather than blanked; applied via one
  `Item::whereIn('id', $selected)->update($updates)` mass-update, gated
  `Gate::authorize('items.bulk_edit')` (a plain ability check, same shape
  as `StockAdjustments/Form.php`'s `inventory.adjust` check — no new
  policy method). CSV import (`items.import`, separately gated — Stock
  Clerk has `bulk_edit` but not `import`, a real role split this exposed)
  mirrors `ItemReportExportController`'s `fputcsv` pattern in reverse
  with native `fgetcsv()`: uploads a CSV (`WithFileUploads`, mirroring
  `BusinessProfile.php`'s upload shape), upserts by SKU
  (`Item::updateOrCreate(['sku' => ...], ...)`, so re-importing the same
  file is idempotent), validates each row with the same rules
  `Items/Form.php` uses (`ValidDecimal`, `stock_type` enum), and resolves
  `category`/`supplier`/`tax_category` **by name** since a spreadsheet a
  person edits won't contain internal IDs — an unmatched name is a soft
  per-row warning (left blank), not a hard rejection, matching those
  columns' nullability on `Item`. A row failing required-field validation
  is skipped and reported with its row number and reason; valid rows in
  the same file still import. On update, a blank CSV cell for an
  optional field leaves the existing value alone rather than nulling it
  out — deliberately not a naive "overwrite every column" upsert, to
  avoid a partial re-export/re-import silently wiping data. Found and
  fixed a real bug while live-verifying: `applyBulkEdit()`'s
  `session()->flash('status', ...)` never rendered, because Livewire's
  component-scoped AJAX response never re-renders the outer layout where
  that banner lives (only a subsequent full navigation would show it,
  which this action doesn't do) — switched to a plain in-component
  property rendered directly in the Items index view instead. 11 new
  Pest tests. Verified live end to end: bulk-changed 2 items' active
  status, downloaded the CSV template, edited it (new SKU + a price
  change on an existing SKU + one row with a non-numeric price), uploaded
  it, confirmed 1 created/1 updated with the bad row correctly reported
  and not persisted, confirmed a Cashier gets 403 on `/items/import`.
  **Out of scope, deliberately**: no bulk price adjustment (e.g. "+10%
  to selected" — real value, but money arithmetic across many rows is a
  distinct, riskier feature better done separately); no bulk delete (the
  existing per-row delete already covers this, gated on the separate
  `items.delete` ability); no auto-creating missing categories/suppliers/
  tax categories from a CSV (typos would silently pollute the catalog);
  no async/queued import processing (no queue-worker convention exists
  in this app, confirmed multiple times this session, and catalog sizes
  here don't need it).

- **Loyalty point expiry** — closes the last open P1 operational-readiness
  item. Two design calls were needed before this was buildable and were
  made explicitly with the user this session: a **per-package** expiry
  window (`loyalty_packages.points_expire_after_days`, nullable —
  `null` means that package's points never expire, the default for every
  existing package), and **precise per-batch FIFO tracking** rather than
  a simpler balance-capped approximation. FIFO tracking works via a new
  `points_transactions.remaining_points` column, meaningful only on
  points-adding rows (`earn`, and a positive `adjustment`): it starts
  equal to that row's `points` and is drawn down as later
  redemptions/negative-adjustments consume it, maintaining the invariant
  `sum(remaining_points) == points_balance` per customer. `AwardLoyaltyPointsAction`
  now stamps `expires_at`/`remaining_points` on every `earn` row (computed
  once at earn time from the package's window, so a later change to the
  window only affects future earning). A new
  `ConsumeEarnBatchesAction` is the shared oldest-earned-first consumer,
  called from both `RecordPointsRedemptionAction::commit()` and a
  negative `AdjustPointsAction` — both already ran under a
  `lockForUpdate()`'d customer row, so no new locking was needed. A new
  scheduled command, `loyalty:expire-points` (registered in
  `bootstrap/app.php` alongside `stock:reconcile`/`model:prune`,
  matching that established pattern exactly), finds earn batches past
  their `expires_at` with `remaining_points > 0` and expires exactly
  that leftover — never a batch's original earned amount — under the
  same per-customer row lock, writing a `type='expire'` ledger row and
  naturally idempotent on re-run. The `remaining_points` migration
  backfills existing `earn`/positive-`adjustment` rows by replaying each
  customer's ledger in order, so the invariant holds immediately rather
  than reading pre-existing points as already fully consumed. Loyalty
  Packages form/list gained a "Points expire after (days)" field
  (blank = never). 11 new Pest tests. Verified live: created a package
  with a 7-day window and one with none, confirmed the list showed
  "7 days"/"Never" correctly, confirmed a 0-day window is rejected,
  confirmed blanking a package's window back out persists as `null`;
  separately, against the real dev database via `php artisan tinker` +
  `php artisan loyalty:expire-points`, backdated an earn batch past its
  expiry, ran the command, confirmed the customer's balance dropped by
  exactly the batch amount, an `expire` row was written, and a second
  run found nothing left to expire. **Out of scope, deliberately**: no
  ledger/points-history screen for a customer (nothing today shows
  individual `PointsTransaction` rows to a user — a separate feature);
  no "points expiring soon" notification/report; manually-awarded
  (positive-adjustment) points never expire, by design — there's no
  package-level concept of an adjustment expiry window.

- **Register-side selling of Item Kits** — the first of the two
  deliberately-deferred register-integration follow-ups. Turned out to
  need far less new backend work than expected: `cart_lines`/`sale_lines`
  already had a nullable `item_kit_id` column sitting alongside the
  required `item_id`, `AddCartLineAction` already excluded
  `item_kit_id`-tagged lines from its merge check, and
  `CompleteSaleAction` already copied `item_kit_id` onto the `SaleLine`
  it creates — the schema had clearly anticipated "a kit sale is N
  ordinary lines, one per component, each tagged with its kit" from the
  start, so stock deduction, cost, tax and `CartPricer` needed zero
  changes. New `AddKitToCartAction` explodes a kit into one `CartLine`
  per component: a `percent` kit discount (`price_option`
  `kit`/`both`) applies identically to every line (exact, no
  apportionment needed); a `fixed` kit discount is apportioned
  proportional to each component's share of the kit's gross total with
  the remainder on the last line — the same technique as
  `PromotionEngine::apportion()`, reimplemented locally rather than
  shared, to avoid refactor risk in that tested pricing engine.
  New `POST /carts/{cart}/kit-lines` (`CartKitLineController`,
  `AddCartKitLineRequest`) and `GET /item-kits` (`ItemKitController`,
  `ItemKitResource`, gated `permission:item_kits.view` — already granted
  to Cashier since the back-office phase). Client side: `catalog.js`
  gained a parallel `kits` cache (mirrors the existing `items` cache
  exactly); `cart.js` gained `addKit()`, pushing one optimistic line per
  cached component and enqueueing a single `add_kit` op whose `localRef`
  is an *array* of the N temp ids (every other queued op's `localRef` is
  a single id — `add_kit` is the first one-op-produces-many-lines case);
  `sync/engine.js` gained the matching `add_kit` case, zipping the
  server's returned lines back onto the right optimistic lines by
  position. `Register.vue`'s search now queries `/items` and
  `/item-kits` in parallel and merges the results (tagged client-side
  with `kind: 'item' | 'kit'`, never sent by either endpoint), with a
  "Kit" badge and an exact estimated total (summing cached component
  prices then applying the kit's discount — exact regardless of how a
  fixed discount is later split per line). 13 new Pest tests, 4 new
  Vitest tests. Verified live end-to-end through the actual register
  screen as a Cashier: searched "Beverage Bundle" (a 2-component,
  10%-off kit), confirmed it auto-added as two component lines (Cola
  x2, Spring Water x1) as a single-match search result does for a plain
  item, added it a second time and confirmed 4 independent lines (no
  merge), completed a cash sale, confirmed both stock levels decremented
  correctly and all four `sale_lines` carried `item_kit_id` with the
  correct per-line discount amount. **Out of scope, deliberately**: no
  visual "kit group" rendering on the cart/payment/confirmation screens
  or the PDF receipt — component lines display and behave as ordinary,
  independently-editable lines, same as any other line, with
  `item_kit_id` preserved in the data for reporting even though nothing
  groups by it visually yet; no kit-quantity-multiplier UI (add the kit
  again for a second set); no kit search in the persistent product grid
  (kits have no `category_id`); no barcode scanning for kits.

- **Register-side lot/serial picking** — the second and final
  deferred register-integration follow-up, closing out the last open P1
  item entirely. Two design calls were made explicitly with the user
  before planning: serials are **typed/scanned** by the cashier (no
  picker/dropdown), and lots are **assigned automatically via FEFO**
  with no cashier-facing UI at all — there's no per-lot quantity tracked
  anywhere in this system (`stock_levels` is keyed purely on
  item+location; `stock_lot_id` on `stock_movements` has only ever been
  a traceability tag), so a manual lot picker would just be choosing a
  label, not a meaningful quantity. `AddCartLineAction` and
  `AddKitToCartAction` now resolve `StockLot::fefo()->first()` for a
  lot-tracked item whenever no explicit lot was given (silently stays
  `null` if the item has no lots yet); the existing "merge a repeat
  scan into one line" check now matches on the *resolved* lot rather
  than only ever merging no-lot lines, so two scans that land on the
  same FEFO lot still merge into one line while scans resolving to
  different lots correctly don't. Serials reuse the existing
  add-then-edit-after shape a cart line already had for price/notes:
  `serial` was added to the `PATCH .../lines/{line}` allow-list
  (`UpdateCartLineRequest`/`UpdateCartLineAction`) rather than becoming
  a new mechanism. `CompleteSaleAction` gained
  `assertSerialsAvailable()`, mirroring `assertStockAvailable()`'s
  exact lock-before-check shape: every serialized line must have a
  `serial` assigned and that serial must lock-and-verify as `in_stock`
  before anything is written, and once the `Sale` exists the same
  per-line loop that already records the stock movement now also flips
  the serial to `sold` with `sold_on_sale_id` set. `ItemResource` and
  `CartLineResource` needed one new field each (`is_serialized`,
  `serial`) so the register's cached catalog and cart snapshots
  actually carry the data the new logic depends on — both were missing
  entirely before this. A kit line whose component is serialized is
  blocked by the exact same check with no special-casing, since kit
  lines are just `CartLine` rows like any other. 13 new Pest tests, 1
  new Vitest test. Verified live end-to-end through the actual register
  screen as a Cashier: added a serialized item, confirmed the
  serial-entry field appeared, confirmed "Take payment" was blocked
  client-side with a clear message before a serial was assigned, typed
  the serial and confirmed the line updated, added a lot-tracked item in
  the same cart, completed a cash sale, and confirmed directly in the
  database that the serial transitioned to `sold` with the correct
  `sold_on_sale_id` and the lot-tracked line's `sale_line` carried the
  auto-assigned `stock_lot_id` with no cashier action taken for it.
  **Out of scope, deliberately**: no manual lot picker; no serial
  picker/dropdown (typed/scanned only); a return does not transition a
  serial back to `in_stock` (`CompleteSaleAction`'s new checks are
  skipped entirely for `Sale::TYPE_RETURN` — this pass only covers the
  forward-sale path, and fixing returns means investigating that
  separate flow); no barcode-scan-to-serial shortcut (scanning a
  serial's own barcode doesn't auto-add the item, the item still has to
  be added first).

- **P3 hygiene sweep** — six independent, low-risk fixes in one pass.
  `ItemController`/`CustomerController` (and `ItemKitController`, added
  this session with the same copy-pasted bug and not yet in the
  original audit note) now clamp `per_page` with
  `max(1, min(..., 100))` instead of only capping the upper bound, so
  `per_page=0` no longer reaches `paginate()` and divides by zero.
  `serial_numbers.sold_on_sale_id` now has a real FK constraint
  (`->constrained('sales')->nullOnDelete()` via an additive migration —
  safe since nothing wrote that column before this session).
  `config/database.php`'s `mysql` connection now pins
  `'timezone' => env('DB_TIMEZONE', '+00:00')`, closing the exact
  session-timezone drift this session's own memory note flagged;
  confirmed live via `SELECT @@session.time_zone` returning `+00:00`
  instead of `SYSTEM`. New composite indexes on `sales(stock_location_id, sold_at)`
  and `receivings(supplier_id, received_at)` — confirmed genuinely
  needed by reading `ItemReportQuery`/`ReceivingReportQuery`, which
  filter on exactly those pairs together; the columns already had
  single-column indexes individually, which don't serve a two-predicate
  query the way a composite one does. Deleted the six empty
  `app/Domain/Restaurant/` directories (deliberately left the
  `dinner_tables` schema/FK columns alone — a live schema change is a
  bigger decision than directory cleanup and wasn't asked for). New
  `RecordLastLogin` listener (auto-discovered, same convention as every
  other listener in this app) writes `last_login_at`/`last_login_ip` on
  `Illuminate\Auth\Events\Login` — fired naturally by the web/Fortify
  session login, and fired explicitly (without `Auth::login()`, which
  would wrongly start a session for a stateless API client) from
  `AuthController::store()` for the POS register's token login too, so
  both paths are covered by one listener. 4 new Pest tests. Verified:
  `migrate:fresh` runs both new migrations cleanly against real seeded
  data, `GET /api/v1/items?per_page=0` returns a clean 200 (was a 500),
  the timezone check above ran against the live dev database, not just
  the config file.

- **Restaurant Phase A: business type + dine-in table selection** — this
  project's own scope note previously said restaurant support "has been
  dropped from scope entirely as inapplicable, not merely deferred"; the
  user reversed that, asking for multi-vertical support (restaurant,
  salons, etc.), restaurant first, scoped to table selection + status,
  kitchen tickets/printing, and split bills/tips. This phase covers only
  the first — the foundation the other two build on. Added `business_type`
  = `restaurant` (`BusinessProfileSettings`), a `DinnerTable` model built
  on the `dinner_tables` schema and `dinner_table_id` FK columns that
  already existed on `carts`/`sales` (deliberately left alone during the
  P3 Restaurant-directory cleanup above, anticipating this). Occupancy is
  a real state machine, not just a display flag: opening a cart against a
  table locks and flips it to `occupied` (`CreateOrResumeCartAction`,
  refusing a second cart against an already-occupied table with a new
  `CheckoutException::tableOccupied()`), completing or abandoning the cart
  releases it back to `available` (`CompleteSaleAction`, `CartController::abandon`),
  and — deliberately — suspending (parking) a cart does *not* release the
  table, since an order left mid-service should still read occupied on the
  floor. New `tables.view`/`tables.manage` permissions, `DinnerTablePolicy`
  mirroring `TerminalPolicy`, back-office CRUD at `/dinner-tables` mirroring
  `/terminals` field-for-field, and two new API endpoints
  (`GET /dinner-tables`, `GET /dinner-tables/{table}/cart`). The register
  (`Register.vue`) replaces "Start new sale" with a table-floor grid in
  restaurant mode: tapping an available tile opens a cart tagged to that
  table (name shown in the cart header, resolved from the already-fetched
  floor list rather than waiting on a round trip); tapping an occupied
  tile looks up and resumes its parked cart via the same
  `resumeFromOtherTerminal` mechanic already used to pick up a cart parked
  by a different terminal. 15 new Pest tests, 6 new Vitest tests. Verified
  live end-to-end with Playwright: set business type to Restaurant,
  created two tables in the new back-office page, opened a table from the
  register floor grid, added an item, parked it, watched the tile flip to
  Occupied, tapped it again and confirmed the same order (with its line)
  came back, completed the sale, confirmed the table released. Kitchen
  tickets/printing and split bills/tips are explicit next phases, not
  built here — see Not started below.

- **Restaurant Phase B: kitchen tickets / printing** — the second of the
  three restaurant capabilities from Phase A. Reused this project's
  existing ESC/POS printer infrastructure rather than inventing a new one:
  the shape already existed twice (`Terminal`'s per-till receipt printer
  via `PrintConnectorFactory`/`PrintReceiptAction`; `StockLocation`'s
  per-location label printer via `LabelPrinterConnectorFactory`/
  `LabelPrintingException`). A kitchen printer is shared by every register
  at a location, not owned by one till, so it follows the label-printer
  precedent: new `kitchen_printer_connector`/`kitchen_printer` columns on
  `stock_locations`, a `KitchenPrinterConnectorFactory`, a
  `KitchenPrintingException` (registered globally as a 422, unlike
  `LabelPrintingException`, since this one fires from an API endpoint, not
  only a back-office Livewire action). New `kitchen_sent_at` timestamp on
  `cart_lines` tracks what's already gone out — `SendCartToKitchenAction`
  only prints and marks the *unsent* lines each time the cashier taps
  "Send to kitchen," so a failed print (unreachable printer) leaves lines
  untouched and retryable, and a second tap after adding more items only
  fires the new ones. `PrintKitchenTicketAction` builds a deliberately
  simple ticket — table name, time, `qty x item name` plus notes — no
  prices, totals, or payments, unlike the customer receipt. Kitchen
  printer fields added to the Stock Locations back-office form, mirroring
  the label printer fields exactly. Register gets a "Send to kitchen"
  button (restaurant mode + dine-in cart only) using the same
  live-hardware-action pattern as the existing "Open drawer" button (a
  direct API call, not the offline queue), and a "Sent" badge per line. 13
  new Pest tests (including a `FakeKitchenPrinterConnectorFactory`
  mirroring the existing label-printer test double). Verified live:
  configured and confirmed persistence of a kitchen printer on Main Store,
  then — since there's no real ESC/POS hardware in dev — cleared it again
  and confirmed tapping "Send to kitchen" on a dine-in cart surfaces the
  "no kitchen printer configured" error inline rather than crashing, the
  same path a real unconfigured location hits in production.

- **Restaurant Phase B2: kitchen display view** — Phase B only printed
  tickets; asked directly whether any on-screen kitchen view existed, the
  answer was no, and the user asked for one. Runs alongside the printer,
  not instead of it. New `cart_lines.kitchen_prepared_at` (mirrors
  `kitchen_sent_at`) tracks completion per item — the user picked
  per-item "Done" over bumping a whole ticket at once. `GET /kitchen/tickets`
  groups every fired-but-unprepared line into tickets by cart (table name,
  oldest-first), excluding lines whose cart was abandoned/completed so
  nothing clutters the screen forever; `POST /kitchen/lines/{line}/prepare`
  marks one line done — a ticket needs no separate "complete" transition,
  it simply stops appearing once every one of its lines is prepared. No
  broadcasting infrastructure exists in this app, so the new
  `/kitchen` register-app route polls every 8s rather than pushing
  updates — consistent with how every other store in the POS app already
  refreshes (fetch-on-demand, not push). Reused the existing
  terminal-selection flow to scope the display to a location rather than
  inventing a second location picker. New `kitchen.view` permission and a
  narrow `Kitchen` role (mirrors the existing `Reports Only` role's
  shape) for a shared kitchen-display device that needs no POS/sales/
  reporting access at all. 8 new Pest tests, 4 new Vitest tests. Verified
  live: added two items to a dine-in order, marked them sent (the printer
  leg was already proven in Phase B, so this pass seeded `kitchen_sent_at`
  directly rather than depending on real ESC/POS hardware), confirmed the
  kitchen display showed one ticket with both items under the correct
  table name, marked one item done and confirmed the ticket stayed with
  one item left, marked the second done and confirmed the whole ticket
  disappeared, and confirmed "Back to register" returns cleanly.

  **Fix, same day**: real usage surfaced that `SendCartToKitchenAction`
  had print and "visible on the kitchen display" wrongly coupled — a
  location with no kitchen printer configured (or one that's unreachable)
  made the entire "Send to kitchen" action fail, so nothing ever reached
  the digital display either, defeating the point of having added a
  screen-only option. Decoupled them: marking a line `kitchen_sent_at` no
  longer depends on a printer existing or succeeding at all; printing is
  now attempted only when a printer is actually configured, and a
  connection failure is reported back as a soft `print_error` in the
  response (surfaced as a non-blocking warning in the register) rather
  than aborting the send. 2 more Pest tests (a location with no printer
  now sends successfully; a configured-but-unreachable printer still
  sends and reports the failure). Verified live end-to-end: with no
  kitchen printer configured, "Send to kitchen" now shows the "Sent"
  badge and the order actually appears on `/kitchen` — confirmed
  reproducing the exact "No tickets waiting" symptom first, then
  confirming the fix.

  **Fix, same day**: asked "kitchen dont need to access to register" —
  a kitchen-only login (the new `Kitchen` role) still landed on
  `Register.vue` after picking a terminal, one tap away from a register
  it has no ability to actually use. `UserResource` now returns
  `can_operate_register` (`$user->can('sales.create')`), and the POS
  router (`resources/js/pos/router.js`) uses it to send such a login
  straight to `/kitchen` after setup and hard-block `/register`,
  `/pay`, `/confirm` for it (falls back to allowing access if the field
  is ever missing, so an older cached session for a real cashier is
  never locked out by this). `KitchenDisplay.vue`'s "Back to register"
  is replaced with "Log out" for a login that has nowhere to go back to.
  Fixing this surfaced a real chicken-and-egg bug: `GET /terminals` (the
  one endpoint `/setup` needs before anything else can load) was gated
  behind `sales.create` — exactly the permission a kitchen-only role
  correctly doesn't have — so it couldn't even get past terminal
  selection. Moved that one route out from behind `sales.create`
  (`terminals/{id}/open-drawer` and everything else stayed gated); any
  authenticated POS login can list terminals, same treatment `GET /config`
  already had, since picking one is bootstrapping every POS screen needs,
  not a sales action. The router's guard logic was factored into a pure
  `resolveGuard()` function for direct testing. 3 new Pest tests, 10 new
  Vitest tests (a dedicated `router.test.js`). Verified live: a
  kitchen-only login now lands on `/kitchen` directly, manually forcing
  the URL to `#/register` bounces straight back, and a normal cashier
  login is unaffected (still lands on `/register` as before).

  **Fix, same day**: reported as "additional items add to the parked
  order..cannot send it to kitchen" — reproduced live: send 1x Cola to
  the kitchen, park the table, resume it, add another Cola (which merges
  into the *same already-sent line* rather than creating a new one, per
  `AddCartLineAction`'s existing merge-by-item+lot logic), tap "Send to
  kitchen" again → **"Every item on this cart has already been sent to
  the kitchen."** `kitchen_sent_at` is a per-line flag, and neither the
  merge-on-add path (`AddCartLineAction`) nor the general line-update
  path (`UpdateCartLineAction`, used by the qty +/- buttons and notes)
  ever reset it when a sent line's quantity or note changed — so the
  extra units were silently invisible to the kitchen, and worse, the
  whole cart falsely read as fully sent, blocking any further send.
  Fixed at the root: both actions now reset `kitchen_sent_at` (and
  `kitchen_prepared_at`, since "prepared" only ever meant what was
  actually fired) whenever a change the kitchen needs to know about
  lands on an already-sent line — quantity, either direction, or the
  note; price/discount/serial edits don't concern the kitchen and are
  left untouched. Mirrored client-side too (`cart.js`'s `addLine`,
  `incrementLine`, `decrementLine`, `setLineQuantity`, `setLineNote`)
  so the "Sent" badge doesn't lie in the moment between the edit and the
  next server round-trip. 3 new Pest tests, 4 new Vitest tests. Verified
  live: reproduced the exact reported failure first, then confirmed a
  second Cola on an already-sent line no longer errors and both units
  actually reach the kitchen (checked directly in the database:
  `quantity` 2.000, `kitchen_sent_at` freshly reset, `kitchen_prepared_at`
  cleared).

  **Fix, same day**: reported as "after done kitchen and add another same
  item, show total count, instead of additional" — the previous fix
  correctly stopped the extra units from being *lost*, but merging them
  into the same line meant a line the kitchen had already prepared and
  bumped it back up would make a *closed* ticket read as a bigger order
  than what was actually fired (e.g. re-order 1 more after 1 was already
  made and served → ticket reappears saying "2 x Cola", not "1 x Cola").
  Root fix: a line already marked `kitchen_prepared_at` is now excluded
  from the merge-lookup both server-side (`AddCartLineAction`) and
  client-side (`cart.js`'s `addLine`) — re-ordering the same dish after
  it's done creates a clean new line (and so a clean new ticket showing
  just the additional quantity) instead of resurrecting the closed one.
  The direct quantity stepper can't create a new line the way re-ordering
  can, so a quantity *increase* on an already-prepared line is refused
  outright (`UpdateCartLineAction`, new `CheckoutException::cannotIncreasePreparedLineQuantity()`)
  with a message pointing the cashier at re-ordering instead — refused
  client-side too, before anything is enqueued, since letting the server
  reject a queued op wipes every other queued op on the cart via the sync
  engine's generic failure handling. A decrease on an already-prepared
  line (a correction, not a new order) stays allowed and leaves its
  kitchen state untouched. Along the way, live reproduction surfaced a
  second, deeper bug: the register never actually learns when the kitchen
  marks something prepared at all -- this app has no push/broadcast, and
  nothing refetched the cart's line state after the fact, so the new
  "already prepared" guard was silently ineffective (still merged/showed
  cumulative totals) until the register happened to reload. Fixed by
  having `refreshTotals()` (already called after every cart mutation, and
  now also on `Register.vue` mount) pull `kitchen_sent`/`kitchen_prepared`
  per line from the server and merge them in, leaving quantity/price/etc.
  purely optimistic as before -- the only two fields a different actor
  (the kitchen display, possibly a different physical device) can change
  without this register ever being told. New `CartLineResource.kitchen_prepared`
  field to support this. 4 new Pest tests, 4 new Vitest tests. Verified
  live end-to-end: sent 1x Cola, marked it done on `/kitchen`, went back
  to the register, ordered another Cola — confirmed it became a genuinely
  new line (not a merged qty-2), sent to kitchen cleanly with no error,
  and the new ticket showed only "1.000 x Cola", never the cumulative
  total; separately confirmed tapping the qty stepper's "+" directly on
  the already-prepared line surfaces the friendly refusal message without
  corrupting the cart.

- **Waiter sales commission** — no commission tracking existed anywhere.
  Scoped explicitly to waiters only (kitchen staff share one login with no
  per-person attribution at all, deliberately deferred). New
  `users.commission_rate` (editable on the User form); `carts.waiter_id`
  set once in `CreateOrResumeCartAction`'s new-cart branch and — unlike
  `user_id` — never reattributed when a different person resumes/completes
  the sale, so commission always credits whoever actually took the order.
  `sales.waiter_id`/`commission_rate_applied` (the rate frozen at that
  moment, so a later rate change never rewrites history)/`commission_amount`
  computed in `CompleteSaleAction` from the subtotal net of discounts,
  before tax. Reversal on void needs no code at all (a void never creates
  a row, so the report's `status != voided` exclusion is enough); on
  refund, `RefundSaleAction` writes the same formula against the return's
  own already-negative subtotal figures using the *original* sale's frozen
  rate, so it nets to zero automatically — mirrors this codebase's
  existing "every return field is negative, no report ever special-cases
  sale_type" convention exactly. New Commission report
  (`/reports/commissions`, CSV export) reusing the `reports.employees`
  permission that was declared but unused since before this session;
  added it to the Accountant role. 15 new Pest tests. Verified live:
  set a 10% rate on the cashier, rang a $1.20 sale as that user, confirmed
  in the database the sale recorded `waiter_id`/`commission_rate_applied: 10.00`/
  `commission_amount: 0.12`, and confirmed the report page shows the
  entry with a working drill-down to the individual sale.

- **Waiter order-taking without cashier access** — a waiter needed to be
  able to build and fire a customer's order without ever being able to
  touch money. Split the single `sales.create` ability that used to gate
  everything cart-related into `sales.create` (build/fire an order:
  create carts, lines, kit-lines, kitchen ticket, coupon, customer,
  suspend, abandon) plus a new `sales.checkout` stacked on top for the
  three genuinely money-moving routes (`carts/{cart}/complete`,
  `carts/{cart}/payments`, `terminals/{terminal}/open-drawer`) —
  mirrors how `sales.suspend`/`sales.delete` already stack on the same
  group. New `Waiter` role: `sales.create`, `sales.suspend`,
  `sales.delete`, `customers.view`, `items.view`, `item_kits.view`,
  `tables.view` — no `sales.checkout` and no `shifts.open`, so a waiter
  always works a shift a cashier already opened on the terminal.
  `UserResource` exposes the new `can_checkout` flag alongside the
  existing `can_operate_register`; `router.js`'s `resolveGuard()` gained
  a second gate (`CHECKOUT_ONLY_ROUTES`) bouncing `/pay`/`/confirm` back
  to `/register` for a register-capable-but-non-checkout login, the same
  shape as the existing kitchen-only gate. In `Register.vue`, "Take
  payment" and "Open drawer" are hidden (and their `F4`/`F9` shortcuts
  no-op into `park()`/do nothing) for a non-checkout user; the footer's
  primary button becomes "Send order to cashier", which just calls the
  existing `park()` — a waiter's order becomes a parked sale the way any
  parked sale already works, requiring no new suspend/resume mechanics.
  `waiter_id` attribution (from the earlier commission phase) already
  credits whoever originally opened the cart regardless of who resumes
  and completes it, so this reuses that unchanged. 3 new Pest tests (a
  waiter can build/fire/park an order; is forbidden from payments,
  complete, and open-drawer; a cashier resuming a waiter-parked cart
  completes it with `user_id` on the cashier and `waiter_id` still on
  the waiter), 5 new Vitest tests for the router's new gate. Verified
  live end-to-end: logged in as a new Waiter-role user, confirmed no
  "Take payment"/"Open drawer" buttons and that navigating straight to
  `/pay` bounces back to `/register`; added an item and clicked "Send
  order to cashier"; logged in as the cashier, resumed the same parked
  sale from the Parked Sales drawer (now showing "Take payment" as
  expected), and confirmed in the database the cart's `waiter_id` still
  pointed at the waiter while `user_id` had moved to the cashier.

  Live verification surfaced a separate, pre-existing gap this same fix
  needed: the `stock_location_user` pivot that every register/terminal/
  drawer permission check filters on (`TerminalController`,
  `OpenCashDrawerController`, `Shift\Manage`) had **no UI anywhere** —
  it was only ever populated by seeders and tests, so a brand-new user
  created through the real Users form could never pick a terminal at
  all ("No terminals available for your account."), regardless of role.
  Added a `stock_location_ids` checkbox group to the Users form
  (mirrors the `active_days` checkbox-array pattern already used in the
  Promotions form), synced via `stockLocations()->sync()` in `save()`
  alongside `syncRoles()`. 4 new Pest tests (assigns on create, syncs
  removals on edit, preloads existing assignments, the full 16-test
  Users suite still green). Verified live end-to-end through the actual
  backoffice form: created a Waiter, checked "Main Store", confirmed
  the pivot row in the database, then logged in as that exact user in
  the POS app and confirmed the terminal picker now lists Main Store's
  terminals instead of the empty-state message.

  One follow-up polish: tapping an occupied table before its waiter has
  parked the order (existing, pre-session behavior — a cart still active
  on another terminal is deliberately never snatched away) surfaced as
  "Table 1 is in use on another register," which read like an error
  rather than "wait for the waiter." Reworded to "Table 1's order is
  still being taken on another register. Ask them to send/park it, then
  try again." No behavior change, confirmed against the live cart this
  message is worded around.

- **Restaurant split bills & tips** — the last remaining phase of the
  original restaurant ask. **Split bill**: evenly N ways, chosen as the
  simplest useful shape over splitting into separate tickets/receipts —
  purely a client-side tendering convenience on `Payment.vue` (a "Split N
  ways" control that pre-fills each guest's share as the *original* due
  amount ÷ N, fixed at the moment N is chosen rather than recomputed
  against the shrinking due as shares get paid — a live `computed()` off
  `due` was tried first and is exactly wrong here, confirmed by a live
  repro before switching it to a plain `ref` snapshotted in `chooseSplit()`).
  Still exactly one Sale/receipt at the end; no schema change needed for
  this half at all. **Tips**: 15/18/20% quick buttons (off the same
  subtotal-net-of-discount basis already established for commission) plus
  a custom amount, entered on `Payment.vue`, gated by `sales.checkout`
  (same as taking payment — a waiter never sets one). New
  `carts.tip_amount`/`sales.tip_amount` (both `decimal(19,4)`, never
  nullable — a DB-level default doesn't backfill onto the in-memory model
  that triggered the insert, which crashed `CartResource` until both it
  and `CreateOrResumeCartAction` were made to set/read it explicitly).
  Folded into `CompleteSaleAction`'s underpaid/change math so payments
  must cover total+tip, not just total. Unlike commission, a tip is
  **never reversed on refund** (a returned item doesn't claw back the
  gratuity) — only a full void drops it from the report, via the same
  `status != voided` filter every other report already uses. Credited to
  the waiter and reported alongside commission on the existing
  `/reports/commissions` page (new Tips column + CSV column), not folded
  into the commission figure itself. Receipt (screen confirmation + PDF)
  shows the tip and grand total. 9 new Pest tests, 2 new Vitest tests —
  plus, while adding those, found and fixed a latent test-isolation bug
  in `cart.test.js`: `vi.clearAllMocks()` doesn't undo a `.mockImplementation()`
  override from an earlier test, so three existing tests overriding
  `getCartSnapshot` were leaking a stale cart into every test that ran
  after them; `beforeEach` now explicitly restores its default. Verified
  live end-to-end through the actual register: rang a table, picked an
  18% tip, split the bill 2 ways, added both share payments, completed
  the sale, and confirmed the database's `commission`/`tip_amount`/
  `paid_total`/`change_given` all matched exactly.

### Not started
Target deployment is a hardware store (large/diverse SKU count, frequent
receiving), now expanding to multi-vertical (restaurant first, salons
noted as a likely future vertical) per explicit user request — see the
Restaurant Phase A/B/B2 entries above, plus split bills & tips just
above. That closes out everything originally asked for in the restaurant
vertical. Kitchen-staff commission is explicitly deferred until kitchen
logins are individual rather than one shared device/account. Salon
(appointments) is unscoped greenfield work, not started.

**P1 is fully resolved.** Every originally-flagged capability-gap
ability (`sales.refund`/`sales.void`/`taxes.manage`/`inventory.adjust`/
`inventory.transfer`/`sales.delete`/`audit.view`/`expenses.view`/
`expenses.manage`/`item_kits.view`/`item_kits.manage`/`attributes.manage`/
`items.import`/`items.bulk_edit`) plus both deferred register-integration
follow-ups (Item Kit selling, lot/serial picking) and loyalty point
expiry are now wired — see Done above. What's left below is P2/P3, not
P1.

**P2 — register UX, given the stated hardware-store use case.**
- The offline catalog cache pages the entire SKU list into ~5MB
  `localStorage` on every login/reconnect and silently swallows a quota
  error — doesn't scale for a large-SKU catalog offline.

**P3 — hygiene.** Six of seven originally-flagged items resolved this
session (see the "P3 hygiene sweep" Done entry below); the seventh —
`tests/Unit/` holds exactly one file, no CI (`.github/`) runs the suite
on push — is deliberately not part of that sweep (an infra/CI decision,
not a code fix, wasn't in scope for what was asked).

**Smaller/previously-deferred items, still open:** localisation ·
payment gateway drivers (declined by the user — cash/card-in-person
only, no online gateway integration) · per-line supplier invoice entry
and per-line three-way match (schema has only a header `total` on
`supplier_invoices`) · selling a new gift card as a register cart line ·
gift-card refunds auto-crediting the original card · multi-currency
gift cards · multi-coupon stacking on one cart (schema only supports
one `coupon_code` per cart) · bulk coupon-code generation
(single-coupon-per-click only) · 2 of the 13 seeded `reports.*` report
types (discounts, expenses — `employees` is now the Commission report,
done this session) · charting/graphs on any report
(tabular only, no charting library in the stack) · lot-level stock
count lines (schema-ready via `stock_count_lines.stock_lot_id`, no
per-lot on-hand projection to snapshot against) · cycle-count
scheduling/recurrence (ad-hoc subset counts exist; recurring schedules
don't) · re-snapshotting expected quantity at stock-count approval time
(variance is fixed at count time, by design).

### Known deviations from the original plan
- Livewire validation lives directly on each component's `rules()` method,
  not in standalone `Http/Requests` Form Request classes — Laravel's
  `FormRequest` depends on real HTTP-kernel route resolution (`$this->route()`,
  `$this->user()`) that isn't available when a Livewire component
  instantiates one manually outside that lifecycle. **Phase 2's API
  controllers do use real `FormRequest` classes** (`app/Http/Requests/Api/V1/`)
  since they're resolved through the actual HTTP kernel — this was exactly
  the blocker that doesn't apply there.
- A `JsonResource` wrapping a freshly-created model auto-returns HTTP 201
  (Laravel's documented `wasRecentlyCreated` behavior), so `POST /carts`
  returns 201 on create and 200 on resume — more precise than the original
  plan's "always 200," and used deliberately in the test suite to assert
  create-vs-resume.
- The register's offline queue only guarantees exactly-once delivery for
  cart creation and checkout (both already idempotent server-side, per
  Phase 2). Line/payment mutation endpoints have no `Idempotency-Key`
  support — extending them would mean reopening Phase 2's API, deliberately
  out of scope per the roadmap's "Idempotency-Key on completion" phrasing —
  so the sync engine only retries those on a connection-level failure
  (request never reached the server), not on an ambiguous "sent, response
  lost" case, accepting a small residual double-apply risk there rather
  than pretending it's solved. The cashier reviewing line quantities before
  completing is the practical backstop.

## Gaps from the audit, and where each is addressed

| Legacy gap | Addressed by |
|---|---|
| No payment processing | `payments` table with provider/auth/capture lifecycle; `payment_methods.provider` selects a driver |
| No register or shift entity | `terminals`, `shifts`, `shift_cash_counts`, `cash_movements`; lifecycle via `OpenShiftAction`/`CloseShiftAction` |
| No offline capability | `carts` persisted with `client_uuid`; `IdempotencyGuard` over `idempotency_keys` for safe checkout replay; register PWA's IndexedDB mutation queue (Phase 3) actually uses it end to end |
| No API | Sanctum `/api/v1` — auth, catalog, cart, checkout (see Phase 2 above) |
| No audit trail | `activity_log`; `Sale` logs status/total/refund changes |
| No purchase orders / AP | `purchase_orders`, `purchase_order_lines`, `supplier_invoices` — full draft/submit/approve/receive lifecycle (Phase 5) |
| No lot/expiry/serial tracking | `stock_lots` (FEFO scope), `serial_numbers` |
| No promotions | `PromotionEngine`/`PromotionSelector` evaluated inside `CartPricer`; conditions, stacking, scheduling, redemption limits, coupons (Phase 6) |
| No stock take | `app/Domain/Inventory/Actions/` — draft/counting/review/approved lifecycle, blind counts, variance computed at submit, posted to the ledger on approval (Phase 8) |
| No reporting | `app/Domain/Reporting/Queries/` — sales, inventory ledger, shift/cash reports with CSV export (Phase 7); 10 of 13 seeded report types still unbuilt |
| Payment type stored as translated string | `payment_methods.code` — label is presentation only |
| Invoice number race | `document_sequences` allocated under `lockForUpdate` |
| Stock drift between ledger and quantity | Ledger is source of truth; `php artisan stock:reconcile` |
| Permission wipe on location rename | `stock_location_user` pivot keyed on immutable id |
| `LIKE`-prefix permission matching | Exact-match abilities via spatie/laravel-permission |
| Float money arithmetic | `App\Support\Money` + `MoneyCast`, decimal(19,4) |
| No tests | Pest suite; 28 tests, tax engine covered without a database |

## Running it

```bash
php artisan migrate:fresh --seed     # rebuild schema + demo data
php artisan serve                    # http://127.0.0.1:8000
vendor/bin/pest                      # 635 tests
vendor/bin/pint                      # format
npm run build                        # production JS/CSS + service worker
npm run test                         # 32 Vitest unit tests (sync engine, cart store)
php artisan stock:reconcile          # ledger vs projection
```

Demo logins (password `password`): `admin` (Owner), `cashier` (Cashier).
Back-office nav: `/dashboard`, `/items`, `/categories`, `/customers`,
`/suppliers`, `/stock-locations`, `/users`, `/shift`, `/pos` (Register),
`/purchase-orders`, `/receivings`, `/supplier-invoices`.

API: `POST /api/v1/login` (`{username, password}` → bearer token), then
`Authorization: Bearer <token>` on everything else — `GET terminals`,
`GET items`, `GET items/barcode/{barcode}`, `GET payment-methods`,
`POST carts`, `GET carts/{cart}`,
`POST|PATCH|DELETE carts/{cart}/lines[/{line}]`,
`POST|DELETE carts/{cart}/payments[/{payment}]`,
`POST carts/{cart}/complete` (requires an `Idempotency-Key` header). A
terminal needs an open shift (via the back-office `/shift` screen, or
`OpenShiftAction` directly) before a cart can be created against it.

The register PWA at `/pos` needs `npm run build` first (it isn't served by
`npm run dev`'s HMR path in a meaningfully different way, but the service
worker only installs from a real production build). Sign in with
`cashier`/`password`, pick a terminal, then scan works via any HID
keyboard-wedge barcode scanner (or physically type a barcode's digits +
Enter) — demo item `Cola 330ml` has barcode `5012345678900`.

## Notes

- Redis is not installed locally, so cache/queue/session run on the database
  driver. Switch to Redis for production.
- MariaDB 10.4 is older than ideal; nothing here depends on 10.5+ features.
- `tests/Unit` boots the framework but is forbidden from touching the database —
  that constraint is what keeps the tax engine honest.
- **Two pre-existing bugs found and fixed while building the back-office:**
  `.env`'s `APP_URL` was `http://localhost/pos/public` (an XAMPP/Apache
  leftover) — harmless when a real request supplies its own host (e.g. via
  `php artisan serve`), but it broke `route()`/`url()` calls made outside a
  live request (test setup code, console commands, queued jobs), which is
  exactly what `phpunit.xml` now also pins explicitly. And `UserFactory`
  still imported the deleted `App\Models\User` and never set `person_id`/
  `username`, so `User::factory()` was silently unusable since the User model
  moved to `App\Domain\Identity\Models`; fixed via `newFactory()` overrides
  on `User`/`Person` (the documented escape hatch for models outside
  `App\Models`) plus a new `PersonFactory`.
- **Several non-obvious bugs found and fixed while building the register
  PWA, all only surfaced by driving it in a real browser (Playwright) —
  the Pest suite and `vite build` both stayed green through every one:**
  - `public/pos/icons/` (created for the PWA manifest) silently shadowed
    the `/pos` route: both Apache's `.htaccess` rewrite and PHP's built-in
    dev server serve an existing file/directory directly instead of
    routing to `index.php`, so `/pos` 404'd before Laravel ever saw the
    request. Icons moved to `public/icons/`.
  - Saving a Pinia store's reactive cart object straight into IndexedDB
    throws `DataCloneError` (structured clone can't serialize a Vue
    reactive Proxy in Chromium) — `saveCartSnapshot()` now does a
    `JSON.parse(JSON.stringify(...))` unwrap first.
  - Nothing ever re-fetched a cart's authoritative totals after the first
    line was added: only `create_cart`'s response includes `CartPricer`
    totals, and by construction that's always while the cart is still
    empty. Added `refreshTotals()` to `stores/cart.js`, called once the
    queue drains.
  - `vite-plugin-pwa`, given the custom `outDir` this app needs (see
    `vite.config.js`) to get `sw.js` served from the site root instead of
    `/build/` (a service worker can't widen its own scope past its serving
    directory without a `Service-Worker-Allowed` header), still writes
    `manifest.webmanifest` into the *default* outDir — so the generated
    service worker's own precache list referenced a copy of that file that
    didn't exist at the path it expected. Workbox aborts the entire
    install on any single precache miss, so the worker silently went
    straight from `installing` to `redundant` on every load. Fixed with a
    small `closeBundle` plugin that copies the file to where the precache
    entry expects it.
  - Even once installed, a newly-activated service worker doesn't control
    pages that were already open before it finished activating — so the
    very first session that triggers install stayed uncontrolled, and its
    own route chunks 404'd the instant the network dropped. Fixed via
    `skipWaiting`/`clientsClaim` in the Workbox config.
  - There was no local item catalog at all, so a barcode scan or search
    had nothing to resolve against while offline — every "offline-capable"
    scan was silently just failing. Added `stores/catalog.js` (and
    `stores/paymentMethods.js` for tender options), both cached to
    `localStorage` at login and on reconnect.
  - The worst one: `processQueue()` was called from two places — the app's
    reconnect handler directly, and every cart mutation's own `sync()` —
    with no mutual exclusion. A reconnect-triggered drain could update
    IndexedDB's cart snapshot (e.g. set the real server `id`) in the
    background while the Pinia store's in-memory `cart` was never told
    about it (only `sync()` reloads the store from IndexedDB); the next
    store-driven mutation would then persist that stale in-memory object
    right back over the correct one, wiping out the `id` — a subsequent
    queued payment would then hit `POST /carts/null/payments` and 404.
    Fixed two ways: an `inFlight` guard in `sync/engine.js` so concurrent
    `processQueue()` calls collapse onto one drain, and routing App.vue's
    reconnect handler through `stores/cart.js`'s `sync()` instead of
    calling the engine directly, since only the store keeps its in-memory
    state reconciled.
