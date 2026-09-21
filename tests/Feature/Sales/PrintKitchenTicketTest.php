<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Support\KitchenPrinterConnectorFactory;
use App\Domain\Sales\Actions\PrintKitchenTicketAction;
use App\Domain\Sales\Actions\SendCartToKitchenAction;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Exceptions\KitchenPrintingException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Domain\Sales\Models\DinnerTable;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Support\Str;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Tests\Support\FakeKitchenPrinterConnectorFactory;

beforeEach(function () {
    $this->seed();
    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->terminal = Terminal::where('code', 'T1')->firstOrFail();
    $this->shift = openShiftFor($this->user, $this->terminal);
    $this->table = DinnerTable::create([
        'name' => 'Patio A',
        'stock_location_id' => $this->location->id,
        'seats' => 4,
        'status' => DinnerTable::STATUS_OCCUPIED,
    ]);
});

function kitchenCart(): Cart
{
    return Cart::create([
        'client_uuid' => (string) Str::uuid(),
        'terminal_id' => test()->terminal->id,
        'shift_id' => test()->shift->id,
        'stock_location_id' => test()->location->id,
        'user_id' => test()->user->id,
        'dinner_table_id' => test()->table->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Cart::STATUS_ACTIVE,
    ]);
}

function kitchenLine(Cart $cart, string $sku, ?string $description = null): CartLine
{
    $item = Item::where('sku', $sku)->firstOrFail();

    return $cart->lines()->create([
        'line_number' => $cart->nextLineNumber(),
        'item_id' => $item->id,
        'stock_location_id' => $cart->stock_location_id,
        'quantity' => '2',
        'unit_price' => demoPriceFor($item),
        'cost_price' => demoPriceFor($item, 'cost_price'),
        'description' => $description,
    ]);
}

it('prints quantity and item name for every unsent line', function () {
    $this->location->update(['kitchen_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'kitchen_printer' => '127.0.0.1:9100']);
    $cart = kitchenCart();
    kitchenLine($cart, 'BEV-COLA-330', 'No ice');

    $fake = new FakeKitchenPrinterConnectorFactory;
    $this->app->instance(KitchenPrinterConnectorFactory::class, $fake);

    app(SendCartToKitchenAction::class)->execute($cart->fresh());

    $captured = $fake->connector->captured;
    expect($captured)->toContain('Patio A')
        ->and($captured)->toContain('2 x Cola 330ml')
        ->and($captured)->toContain('No ice');
});

it('marks sent lines and excludes them from a second ticket', function () {
    $this->location->update(['kitchen_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'kitchen_printer' => '127.0.0.1:9100']);
    $cart = kitchenCart();
    $first = kitchenLine($cart, 'BEV-COLA-330');

    $fake = new FakeKitchenPrinterConnectorFactory;
    $this->app->instance(KitchenPrinterConnectorFactory::class, $fake);

    app(SendCartToKitchenAction::class)->execute($cart->fresh());

    expect($first->fresh()->kitchen_sent_at)->not->toBeNull();

    $second = kitchenLine($cart, 'BAK-BREAD-WHT');

    app(SendCartToKitchenAction::class)->execute($cart->fresh());

    // RetainedMemoryPrintConnector::captured holds only the most recent
    // print job (finalize() replaces it, doesn't append) -- so the second
    // ticket's buffer proves the exclusion by *not* containing the
    // already-sent Cola line at all, only the new one.
    $captured = $fake->connector->captured;
    expect($captured)->not->toContain('Cola')
        ->and($captured)->toContain('x White Loaf')
        ->and($second->fresh()->kitchen_sent_at)->not->toBeNull();
});

it('marks the line sent without printing when no kitchen printer is configured', function () {
    // The digital kitchen display and the physical printer are independent
    // outlets for the same ticket -- a kitchen running the screen alone,
    // with no printer at all, must work identically to one with both.
    $cart = kitchenCart();
    $line = kitchenLine($cart, 'BEV-COLA-330');

    $result = app(SendCartToKitchenAction::class)->execute($cart->fresh());

    expect($result)->toBeNull()
        ->and($line->fresh()->kitchen_sent_at)->not->toBeNull();
});

it('marks the line sent and reports the failure when a configured printer is unreachable', function () {
    $this->location->update(['kitchen_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'kitchen_printer' => '127.0.0.1:9100']);
    $cart = kitchenCart();
    $line = kitchenLine($cart, 'BEV-COLA-330');

    $this->app->instance(KitchenPrinterConnectorFactory::class, new class extends KitchenPrinterConnectorFactory
    {
        public function resolve(StockLocation $location): PrintConnector
        {
            throw KitchenPrintingException::connectionFailed('timed out');
        }
    });

    $result = app(SendCartToKitchenAction::class)->execute($cart->fresh());

    expect($result)->toContain('timed out')
        ->and($line->fresh()->kitchen_sent_at)->not->toBeNull();
});

it('refuses when every line has already been sent', function () {
    $this->location->update(['kitchen_printer_connector' => StockLocation::CONNECTOR_NETWORK, 'kitchen_printer' => '127.0.0.1:9100']);
    $cart = kitchenCart();
    kitchenLine($cart, 'BEV-COLA-330');

    $fake = new FakeKitchenPrinterConnectorFactory;
    $this->app->instance(KitchenPrinterConnectorFactory::class, $fake);

    app(SendCartToKitchenAction::class)->execute($cart->fresh());

    expect(fn () => app(SendCartToKitchenAction::class)->execute($cart->fresh()))
        ->toThrow(CheckoutException::class, 'already been sent to the kitchen');
});

it('PrintKitchenTicketAction itself still refuses an unconfigured printer', function () {
    // This is the lower-level action in isolation, called directly rather
    // than through SendCartToKitchenAction -- it has no opinion on whether
    // a printer is optional, that decision belongs to the caller.
    $cart = kitchenCart();
    kitchenLine($cart, 'BEV-COLA-330');

    expect(fn () => app(PrintKitchenTicketAction::class)->execute($cart->fresh(), $cart->fresh()->lines))
        ->toThrow(KitchenPrintingException::class, 'no kitchen printer configured');
});
