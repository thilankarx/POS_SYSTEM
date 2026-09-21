<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Sales\Models\Sale;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Sale $sale;

    public function mount(Sale $sale): void
    {
        Gate::authorize('view', $sale);

        $this->sale = $sale->load([
            'lines.item',
            'taxes',
            'payments.method',
            'customer.person',
            'user',
            'terminal',
            'stockLocation',
            'returnsSale',
            'returns.returnReason',
            'voidedBy',
        ]);
    }

    public function render()
    {
        return view('livewire.sales.show');
    }
}
