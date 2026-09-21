<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Giftcards\Actions\IssueGiftcardAction;
use App\Domain\Giftcards\Actions\TopUpGiftcardAction;
use App\Domain\Giftcards\Exceptions\GiftcardException;
use App\Domain\Giftcards\Models\GiftcardTransaction;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();

    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->giftcardMethod = PaymentMethod::where('code', 'giftcard')->firstOrFail();

    $this->shift = Shift::create([
        'terminal_id' => $this->terminal->id,
        'opened_by_user_id' => $this->user->id,
        'opening_float' => '100.00',
        'status' => Shift::STATUS_OPEN,
        'opened_at' => now(),
    ]);
});

function giftcardCart(array $overrides = []): Cart
{
    return Cart::create(array_merge([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->user->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ], $overrides));
}

function giftcardLine(Cart $cart, string $sku, string $quantity): CartLine
{
    $item = Item::where('sku', $sku)->firstOrFail();

    return CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => $cart->nextLineNumber(),
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => $quantity,
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
}

it('issues a gift card with a generated number and an issue transaction', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('50.00');

    expect($giftcard->number)->toStartWith('GC-')
        ->and((string) $giftcard->balance->getAmount())->toBe('50.00')
        ->and((string) $giftcard->initial_value->getAmount())->toBe('50.00')
        ->and($giftcard->is_active)->toBeTrue();

    $transaction = GiftcardTransaction::where('giftcard_id', $giftcard->id)->where('type', 'issue')->first();
    expect($transaction)->not->toBeNull()
        ->and((string) $transaction->amount->getAmount())->toBe('50.00');
});

it('tops up an existing gift card', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('20.00');

    app(TopUpGiftcardAction::class)->execute($giftcard, '10.00', $this->user);

    expect((string) $giftcard->fresh()->balance->getAmount())->toBe('30.00');

    $transaction = GiftcardTransaction::where('giftcard_id', $giftcard->id)->where('type', 'topup')->first();
    expect($transaction)->not->toBeNull()
        ->and((string) $transaction->amount->getAmount())->toBe('10.00')
        ->and($transaction->user_id)->toBe($this->user->id);
});

it('refuses to top up an inactive gift card', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('20.00');
    $giftcard->update(['is_active' => false]);

    expect(fn () => app(TopUpGiftcardAction::class)->execute($giftcard, '10.00', $this->user))
        ->toThrow(GiftcardException::class);
});

it('redeems a gift card at checkout, debiting the balance', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('20.00');

    $cart = giftcardCart();
    giftcardLine($cart, 'BEV-COLA-330', '1'); // total 1.38
    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->giftcardMethod->id,
        'amount' => '1.38',
        'reference' => $giftcard->number,
    ]);

    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect((string) $sale->paid_total->getAmount())->toBe('1.38')
        ->and((string) $giftcard->fresh()->balance->getAmount())->toBe('18.62');

    $transaction = GiftcardTransaction::where('giftcard_id', $giftcard->id)->where('type', 'redeem')->first();
    expect($transaction)->not->toBeNull()
        ->and((string) $transaction->amount->getAmount())->toBe('-1.38')
        ->and($transaction->sale_id)->toBe($sale->id);
});

it('rejects checkout for a nonexistent gift card number', function () {
    $cart = giftcardCart();
    giftcardLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->giftcardMethod->id,
        'amount' => '1.38',
        'reference' => 'GC-DOESNOTEXIST',
    ]);

    expect(fn () => app(CompleteSaleAction::class)->execute($cart->fresh()))
        ->toThrow(GiftcardException::class, 'No gift card');
});

it('rejects checkout for an inactive gift card', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('20.00');
    $giftcard->update(['is_active' => false]);

    $cart = giftcardCart();
    giftcardLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->giftcardMethod->id,
        'amount' => '1.38',
        'reference' => $giftcard->number,
    ]);

    expect(fn () => app(CompleteSaleAction::class)->execute($cart->fresh()))
        ->toThrow(GiftcardException::class, 'not active');
});

it('rejects checkout for an expired gift card', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('20.00');
    $giftcard->update(['expires_at' => now()->subDay()]);

    $cart = giftcardCart();
    giftcardLine($cart, 'BEV-COLA-330', '1');
    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->giftcardMethod->id,
        'amount' => '1.38',
        'reference' => $giftcard->number,
    ]);

    expect(fn () => app(CompleteSaleAction::class)->execute($cart->fresh()))
        ->toThrow(GiftcardException::class, 'expired');
});

it('rejects checkout when the payment amount exceeds the gift card balance', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('1.00');

    $cart = giftcardCart();
    giftcardLine($cart, 'BEV-COLA-330', '1'); // total 1.38
    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->giftcardMethod->id,
        'amount' => '1.38',
        'reference' => $giftcard->number,
    ]);

    expect(fn () => app(CompleteSaleAction::class)->execute($cart->fresh()))
        ->toThrow(GiftcardException::class, 'enough balance');

    expect((string) $giftcard->fresh()->balance->getAmount())->toBe('1.00');
});
