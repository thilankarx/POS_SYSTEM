<?php

declare(strict_types=1);

namespace App\Livewire\Catalog\ItemKits;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemKit;
use App\Support\Money\Money;
use App\Support\Money\Rules\ValidDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?ItemKit $itemKit = null;

    public string $kit_number = '';

    public string $name = '';

    public string $description = '';

    public string $discount_value = '0';

    public string $discount_type = 'percent';

    public string $price_option = 'kit';

    public string $print_option = 'all';

    /** @var array<int, array{item_id: ?int, quantity: string}> */
    public array $items = [];

    public function mount(?ItemKit $itemKit = null): void
    {
        $this->itemKit = $itemKit;

        if ($itemKit !== null) {
            Gate::authorize('update', $itemKit);

            $this->kit_number = $itemKit->kit_number;
            $this->name = $itemKit->name;
            $this->description = (string) $itemKit->description;
            $this->discount_value = (string) $itemKit->discount_value;
            $this->discount_type = $itemKit->discount_type;
            $this->price_option = $itemKit->price_option;
            $this->print_option = $itemKit->print_option;
            $this->items = $itemKit->items->map(fn (Item $item) => [
                'item_id' => $item->id,
                'quantity' => (string) $item->pivot->quantity,
            ])->all();
        } else {
            Gate::authorize('create', ItemKit::class);
            $this->addItem();
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['item_id' => null, 'quantity' => '1'];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function rules(): array
    {
        return [
            'kit_number' => ['required', 'string', 'max:255', Rule::unique('item_kits', 'kit_number')->ignore($this->itemKit?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_value' => ['required', new ValidDecimal],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'price_option' => ['required', Rule::in(['kit', 'components', 'both'])],
            'print_option' => ['required', Rule::in(['all', 'kit_only'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', Rule::exists('items', 'id')],
            'items.*.quantity' => ['required', new ValidDecimal, 'gt:0'],
        ];
    }

    public function save(): void
    {
        $this->withValidator(function ($validator) {
            $validator->after(function ($validator) {
                $itemIds = array_column($this->items, 'item_id');

                if (count($itemIds) !== count(array_unique($itemIds))) {
                    $validator->errors()->add('items', 'A kit cannot contain the same item more than once.');
                }
            });
        });

        $validated = $this->validate();
        $items = $validated['items'];
        unset($validated['items']);

        if ($this->itemKit !== null) {
            Gate::authorize('update', $this->itemKit);
            $this->itemKit->update($validated);
        } else {
            Gate::authorize('create', ItemKit::class);
            $this->itemKit = ItemKit::create($validated);
        }

        $this->itemKit->items()->sync(collect($items)->mapWithKeys(
            fn (array $row, int $index) => [$row['item_id'] => ['quantity' => $row['quantity'], 'sequence' => $index]]
        ));

        session()->flash('status', 'Item kit saved.');
        $this->redirectRoute('item-kits.index');
    }

    public function render(): View
    {
        $availableItems = Item::active()
            ->withHasMultiplePrices()
            ->withCurrentPrice()
            ->orderBy('name')
            ->get();

        return view('livewire.catalog.item-kits.form', [
            'availableItems' => $availableItems,
            'preview' => $this->preview($availableItems),
        ]);
    }

    /**
     * @param  Collection<int, Item>  $availableItems
     * @return array{componentTotal: \Brick\Money\Money, discount: \Brick\Money\Money, kitTotal: \Brick\Money\Money, selectedCount: int, unpricedCount: int, multiplePriceCount: int}
     */
    private function preview($availableItems): array
    {
        $componentTotal = Money::zero();
        $selectedCount = 0;
        $unpricedCount = 0;
        $multiplePriceCount = 0;

        foreach ($this->items as $row) {
            if ($row['item_id'] === null || $row['quantity'] === '' || ! is_numeric($row['quantity'])) {
                continue;
            }

            $item = $availableItems->firstWhere('id', (int) $row['item_id']);

            if ($item === null) {
                continue;
            }

            $selectedCount++;
            $unpricedCount += $item->current_price === null ? 1 : 0;
            $multiplePriceCount += $item->has_multiple_prices ? 1 : 0;

            $price = $item->current_price !== null ? Money::of($item->current_price) : Money::zero();
            $componentTotal = $componentTotal->plus($price->multipliedBy($row['quantity'], RoundingMode::HalfUp));
        }

        $discount = Money::zero();

        if ($this->price_option !== 'components' && is_numeric($this->discount_value)) {
            $discount = $this->discount_type === 'percent'
                ? Money::percentageOf($componentTotal, $this->discount_value)
                : Money::of($this->discount_value);

            if ($discount->isGreaterThan($componentTotal)) {
                $discount = $componentTotal;
            }
        }

        return [
            'componentTotal' => $componentTotal,
            'discount' => $discount,
            'kitTotal' => $componentTotal->minus($discount),
            'selectedCount' => $selectedCount,
            'unpricedCount' => $unpricedCount,
            'multiplePriceCount' => $multiplePriceCount,
        ];
    }
}
