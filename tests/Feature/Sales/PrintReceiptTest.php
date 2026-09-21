<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Actions\PrintReceiptAction;
use App\Domain\Sales\Exceptions\PrintingException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use App\Domain\Sales\Support\PrintConnectorFactory;
use App\Settings\BusinessProfileSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mike42\Escpos\Printer;
use Tests\Support\FakePrintConnectorFactory;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->card = PaymentMethod::where('code', 'card')->firstOrFail();
    $this->shift = openShiftFor($this->user, $this->terminal);
});

function printReceiptCart(array $overrides = []): Cart
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

function printReceiptLine(Cart $cart): CartLine
{
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    return CartLine::create([
        'cart_id' => $cart->id,
        'line_number' => 1,
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '1',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
    ]);
}

it('prints a cash sale and pulses the drawer', function () {
    $this->terminal->update([
        'printer_connector' => Terminal::CONNECTOR_NETWORK,
        'receipt_printer' => '127.0.0.1:9100',
        'printer_paper_width' => 80,
    ]);

    $cart = printReceiptCart();
    printReceiptLine($cart);
    CartPayment::create([
        'cart_id' => $cart->id,
        'payment_method_id' => $this->cash->id,
        'amount' => '1.38',
        'tendered' => '5.00',
    ]);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $fake = new FakePrintConnectorFactory;
    $this->app->instance(PrintConnectorFactory::class, $fake);

    app(PrintReceiptAction::class)->execute($sale->fresh());

    $captured = $fake->connector->captured;
    expect($captured)->toContain(Printer::ESC.'p') // drawer pulse
        ->and($captured)->toContain($sale->number)
        ->and($captured)->toContain(str_repeat('-', 48))
        ->and($captured)->toContain($sale->currency.' '.$sale->total->getAmount())
        ->and($captured)->toContain('PRODUCT')
        ->and($captured)->toContain('PRICE')
        ->and($captured)->toContain('QTY')
        ->and($captured)->toContain('AMOUNT')
        ->and($captured)->toContain(
            str_pad('PRODUCT', 20)
            .str_pad('PRICE', 9, ' ', STR_PAD_LEFT)
            .str_pad('QTY', 6, ' ', STR_PAD_LEFT)
            .str_pad('AMOUNT', 13, ' ', STR_PAD_LEFT)
        )
        ->and($captured)->toContain('CASH TENDERED')
        ->and($captured)->toContain('LKR 5.00')
        ->and($captured)->toContain('CHANGE DUE')
        ->and($captured)->toContain('LKR 3.62')
        ->and($captured)->toContain('NO OF ITEMS: 1')
        ->and($captured)->toContain('NO OF PCS: 1')
        ->and($captured)->toContain('AMT Solutions (Pvt) Ltd')
        ->and($captured)->toContain("www.amtsolutions.lk  +94 77 341 1861\n")
        ->and($captured)->toContain(Printer::ESC.'e'.chr(2))
        ->and($captured)->toContain(Printer::GS.'V'.chr(Printer::CUT_FULL).chr(1))
        ->and($captured)->not->toContain(Printer::ESC.'d'.chr(2));
});

it('prints a card-only sale without pulsing the drawer', function () {
    $this->terminal->update([
        'printer_connector' => Terminal::CONNECTOR_NETWORK,
        'receipt_printer' => '127.0.0.1:9100',
        'printer_paper_width' => 58,
    ]);

    $cart = printReceiptCart();
    printReceiptLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->card->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $fake = new FakePrintConnectorFactory;
    $this->app->instance(PrintConnectorFactory::class, $fake);

    app(PrintReceiptAction::class)->execute($sale->fresh());

    expect($fake->connector->captured)->not->toContain(Printer::ESC.'p')
        ->and($fake->connector->captured)->toContain(str_repeat('-', 32))
        ->and($fake->connector->captured)->not->toContain(str_repeat('-', 48));
});

it('prints the configured store name and receipt header/footer, not the app name', function () {
    $this->terminal->update(['printer_connector' => Terminal::CONNECTOR_NETWORK, 'receipt_printer' => '127.0.0.1:9100']);

    $business = app(BusinessProfileSettings::class);
    $business->store_name = 'Ada Hardware Co';
    $business->receipt_header = 'Open 7 days a week';
    $business->receipt_footer = 'Returns within 30 days';
    $business->save();

    $cart = printReceiptCart();
    printReceiptLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $fake = new FakePrintConnectorFactory;
    $this->app->instance(PrintConnectorFactory::class, $fake);

    app(PrintReceiptAction::class)->execute($sale->fresh());

    $captured = $fake->connector->captured;
    expect($captured)->toContain('Ada Hardware Co')
        ->and($captured)->toContain('Open 7 days a week')
        ->and($captured)->toContain('Returns within 30 days')
        ->and($captured)->not->toContain(config('app.name'));
});

it('prints the configured business logo on a thermal receipt', function () {
    Storage::fake('public');
    Storage::disk('public')->put(
        'branding/logo.png',
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
    );

    $business = app(BusinessProfileSettings::class);
    $business->logo_path = 'branding/logo.png';
    $business->save();
    $this->terminal->update(['printer_connector' => Terminal::CONNECTOR_NETWORK, 'receipt_printer' => '127.0.0.1:9100']);

    $cart = printReceiptCart();
    printReceiptLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $fake = new FakePrintConnectorFactory;
    $this->app->instance(PrintConnectorFactory::class, $fake);

    app(PrintReceiptAction::class)->execute($sale->fresh());

    expect($fake->connector->captured)->toContain(Printer::GS.'v0');
});

it('prints the sale time in the configured business timezone', function () {
    config()->set('pos.timezone', 'Asia/Colombo');
    $this->terminal->update(['printer_connector' => Terminal::CONNECTOR_NETWORK, 'receipt_printer' => '127.0.0.1:9100']);

    $cart = printReceiptCart();
    printReceiptLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->card->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $sale->update(['sold_at' => '2026-09-03 04:30:00']);

    $fake = new FakePrintConnectorFactory;
    $this->app->instance(PrintConnectorFactory::class, $fake);

    app(PrintReceiptAction::class)->execute($sale->fresh());

    expect($fake->connector->captured)->toContain('2026-09-03 10:00');
});

it('refuses to print when the terminal has no printer configured', function () {
    $cart = printReceiptCart();
    printReceiptLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect(fn () => app(PrintReceiptAction::class)->execute($sale->fresh()))
        ->toThrow(PrintingException::class, 'no receipt printer configured');
});
