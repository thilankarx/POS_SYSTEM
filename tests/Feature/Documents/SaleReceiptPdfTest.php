<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Documents\SaleReceiptPdf;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use App\Settings\BusinessProfileSettings;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->cash = PaymentMethod::where('code', 'cash')->firstOrFail();
    $this->shift = openShiftFor($this->user, $this->terminal);
});

function receiptPdfCart(array $overrides = []): Cart
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

function receiptPdfLine(Cart $cart): CartLine
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

it('renders a POS receipt PDF for a completed sale', function () {
    $cart = receiptPdfCart();
    receiptPdfLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $pdf = app(SaleReceiptPdf::class)->build($sale);

    expect($pdf->output())->toStartWith('%PDF-');
});

it('renders an invoice heading for TYPE_INVOICE sales with a bill-to block', function () {
    $cart = receiptPdfCart(['sale_type' => Sale::TYPE_INVOICE]);
    receiptPdfLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $html = view('pdf.sales.receipt', ['sale' => $sale, 'business' => app(BusinessProfileSettings::class)])->render();

    expect($html)->toContain('Invoice')->not->toContain('Receipt #');
});

it('renders a status badge for a voided sale', function () {
    $cart = receiptPdfCart();
    receiptPdfLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());
    $sale->update(['status' => Sale::STATUS_VOIDED]);

    $html = view('pdf.sales.receipt', ['sale' => $sale, 'business' => app(BusinessProfileSettings::class)])->render();

    expect($html)->toContain('Voided');
});

it('renders a zero-payment quote sale without error', function () {
    $cart = receiptPdfCart(['sale_type' => Sale::TYPE_QUOTE]);
    receiptPdfLine($cart);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    expect($sale->payments)->toBeEmpty();

    $html = view('pdf.sales.receipt', ['sale' => $sale, 'business' => app(BusinessProfileSettings::class)])->render();

    expect($html)->toContain('No payment due');
});

it('renders a barcode of the sale number on the receipt', function () {
    $cart = receiptPdfCart();
    receiptPdfLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $html = view('pdf.sales.receipt', ['sale' => $sale, 'business' => app(BusinessProfileSettings::class)])->render();

    expect($html)->toContain('data:image/svg+xml;base64,')
        ->and($html)->toContain($sale->number);
});

it('prints the sold-as-of snapshot, not the live catalog item', function () {
    $cart = receiptPdfCart();
    $line = receiptPdfLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $item->update(['name' => 'Renamed after sale']);

    $html = view('pdf.sales.receipt', ['sale' => $sale->fresh(['lines.item']), 'business' => app(BusinessProfileSettings::class)])->render();

    expect($html)->not->toContain('Renamed after sale');
});

it('shows the configured business profile name, address and phone', function () {
    $settings = app(BusinessProfileSettings::class);
    $settings->store_name = 'Acme Hardware';
    $settings->address = '123 Main St';
    $settings->phone = '555-0100';
    $settings->save();

    $cart = receiptPdfCart();
    receiptPdfLine($cart);
    CartPayment::create(['cart_id' => $cart->id, 'payment_method_id' => $this->cash->id, 'amount' => '1.38']);
    $sale = app(CompleteSaleAction::class)->execute($cart->fresh());

    $html = view('pdf.sales.receipt', ['sale' => $sale, 'business' => app(BusinessProfileSettings::class)])->render();

    expect($html)->toContain('Acme Hardware')
        ->and($html)->toContain('123 Main St')
        ->and($html)->toContain('555-0100');
});
