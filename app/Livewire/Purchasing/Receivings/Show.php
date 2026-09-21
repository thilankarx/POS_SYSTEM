<?php

declare(strict_types=1);

namespace App\Livewire\Purchasing\Receivings;

use App\Domain\Purchasing\Models\Receiving;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Receiving $receiving;

    public function mount(Receiving $receiving): void
    {
        Gate::authorize('view', $receiving);

        $this->receiving = $receiving->load([
            'lines.item',
            'lines.stockLot',
            'lines.purchaseOrderLine',
            'purchaseOrder',
            'supplier',
            'stockLocation',
            'transferToLocation',
            'user',
        ]);
    }

    public function render()
    {
        return view('livewire.purchasing.receivings.show');
    }
}
