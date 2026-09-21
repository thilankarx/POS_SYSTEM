<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Sales\Actions\RefundSaleAction;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\PaymentMethod;
use App\Domain\Sales\Models\ReturnReason;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleLine;
use App\Support\Money\Rules\ValidDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Refund extends Component
{
    public Sale $sale;

    /** @var array<int, string> keyed by sale_line id */
    public array $quantities = [];

    public ?int $return_reason_id = null;

    public ?int $refund_payment_method_id = null;

    public function mount(Sale $sale): void
    {
        Gate::authorize('refund', $sale);

        $this->sale = $sale;

        // See VoidSale::mount()'s comment: thrown here, this would hit the
        // global CheckoutException JSON renderer on what is a normal browser
        // page load, not an action handler -- redirect with a flash message
        // instead of throwing.
        if (! in_array($sale->status, [Sale::STATUS_COMPLETED, Sale::STATUS_PARTIALLY_REFUNDED], true)) {
            session()->flash('error', CheckoutException::saleNotRefundable($sale->status)->getMessage());
            $this->redirectRoute('sales.show', $sale);

            return;
        }

        $this->sale = $sale->load(['lines.item', 'payments.method']);

        foreach ($this->returnableLines() as $line) {
            $this->quantities[$line->id] = '0';
        }

        $methodIds = $this->sale->payments->pluck('payment_method_id')->unique();
        $this->refund_payment_method_id = $methodIds->count() === 1 ? $methodIds->first() : null;
    }

    /** @return Collection<int, SaleLine> */
    public function returnableLines()
    {
        return $this->sale->lines->filter(fn (SaleLine $line) => bccomp($line->remainingReturnable(), '0', 3) > 0);
    }

    public function save(): void
    {
        Gate::authorize('refund', $this->sale);

        $validated = $this->validate([
            'quantities' => ['array'],
            'quantities.*' => ['nullable', new ValidDecimal],
            'return_reason_id' => ['required', 'exists:return_reasons,id'],
            'refund_payment_method_id' => ['required', 'exists:payment_methods,id'],
        ]);

        $lineQuantities = array_filter(
            $validated['quantities'],
            fn (string $quantity) => bccomp($quantity, '0', 3) > 0,
        );

        try {
            $returnSale = app(RefundSaleAction::class)->execute(
                sale: $this->sale,
                lineQuantities: $lineQuantities,
                returnReasonId: $validated['return_reason_id'],
                refundPaymentMethodId: $validated['refund_payment_method_id'],
                user: auth()->user(),
            );
        } catch (CheckoutException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        session()->flash('status', 'Return processed.');
        $this->redirectRoute('sales.show', $returnSale->returnsSale);
    }

    public function render()
    {
        return view('livewire.sales.refund', [
            'returnReasons' => ReturnReason::where('is_active', true)->orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::whereIn('id', $this->sale->payments->pluck('payment_method_id'))->get(),
        ]);
    }
}
