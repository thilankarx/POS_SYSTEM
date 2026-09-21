<?php

declare(strict_types=1);

namespace App\Livewire\Sales\DinnerTables;

use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\DinnerTable;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?DinnerTable $dinnerTable = null;

    public string $name = '';

    public ?int $stock_location_id = null;

    public int $seats = 0;

    public function mount(?DinnerTable $dinnerTable = null): void
    {
        $this->dinnerTable = $dinnerTable;

        if ($dinnerTable !== null) {
            Gate::authorize('update', $dinnerTable);

            $this->name = $dinnerTable->name;
            $this->stock_location_id = $dinnerTable->stock_location_id;
            $this->seats = $dinnerTable->seats;
        } else {
            Gate::authorize('create', DinnerTable::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'stock_location_id' => ['required', 'exists:stock_locations,id'],
            'seats' => ['required', 'integer', 'min:0', 'max:255'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->dinnerTable !== null) {
            Gate::authorize('update', $this->dinnerTable);
            $this->dinnerTable->update($validated);
        } else {
            Gate::authorize('create', DinnerTable::class);
            $validated['status'] = DinnerTable::STATUS_AVAILABLE;
            $this->dinnerTable = DinnerTable::create($validated);
        }

        session()->flash('status', 'Dinner table saved.');
        $this->redirectRoute('dinner-tables.index');
    }

    public function render()
    {
        return view('livewire.sales.dinner-tables.form', [
            'stockLocations' => StockLocation::orderBy('name')->get(),
        ]);
    }
}
