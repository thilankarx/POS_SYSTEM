<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Sales\Actions\VoidSaleAction;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Sale;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class VoidSale extends Component
{
    public Sale $sale;

    public string $reason = '';

    public function mount(Sale $sale): void
    {
        Gate::authorize('void', $sale);

        $this->sale = $sale;

        // Thrown, not redirected, this would hit bootstrap/app.php's global
        // CheckoutException JSON renderer -- registered unconditionally, it
        // fires for a normal browser page load exactly as it would for an
        // API request, so a stale bookmark or double-opened tab would show
        // the visitor raw {"message": ...} JSON instead of a page. mount()
        // is a page load, not an action handler; redirect back with a flash
        // message the way save() already does below, rather than throwing.
        if ($sale->status !== Sale::STATUS_COMPLETED) {
            session()->flash('error', CheckoutException::saleNotVoidable($sale->status)->getMessage());
            $this->redirectRoute('sales.show', $sale);

            return;
        }

        $this->sale = $sale->load(['lines', 'payments.method']);
    }

    public function save(): void
    {
        Gate::authorize('void', $this->sale);

        $validated = $this->validate([
            'reason' => ['required', 'string', 'min:3'],
        ]);

        try {
            app(VoidSaleAction::class)->execute(
                sale: $this->sale,
                reason: $validated['reason'],
                user: auth()->user(),
            );
        } catch (CheckoutException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        session()->flash('status', 'Sale voided.');
        $this->redirectRoute('sales.show', $this->sale);
    }

    public function render()
    {
        return view('livewire.sales.void-sale');
    }
}
