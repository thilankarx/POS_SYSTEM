<?php

declare(strict_types=1);

namespace App\Livewire\Purchasing\SupplierInvoices;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Purchasing\Actions\MatchSupplierInvoiceAction;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Receiving;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Support\Money\Money;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?SupplierInvoice $supplierInvoice = null;

    public ?int $supplier_id = null;

    public ?int $purchase_order_id = null;

    public string $invoice_number = '';

    public string $invoice_date = '';

    public ?string $due_date = null;

    public string $total = '0.00';

    public function mount(?SupplierInvoice $supplierInvoice = null): void
    {
        $this->supplierInvoice = $supplierInvoice;

        if ($supplierInvoice !== null) {
            Gate::authorize('update', $supplierInvoice);

            $this->supplier_id = $supplierInvoice->supplier_id;
            $this->purchase_order_id = $supplierInvoice->purchase_order_id;
            $this->invoice_number = $supplierInvoice->invoice_number;
            $this->invoice_date = $supplierInvoice->invoice_date->toDateString();
            $this->due_date = $supplierInvoice->due_date?->toDateString();
            $this->total = (string) $supplierInvoice->total->getAmount();
        } else {
            Gate::authorize('create', SupplierInvoice::class);
            $this->invoice_date = now()->toDateString();

            $purchaseOrderId = request()->integer('purchase_order');
            $purchaseOrder = $purchaseOrderId > 0 ? PurchaseOrder::find($purchaseOrderId) : null;

            if ($purchaseOrder !== null) {
                $this->supplier_id = $purchaseOrder->supplier_id;
                $this->purchase_order_id = $purchaseOrder->id;
            }
        }
    }

    public function updatedSupplierId(): void
    {
        if ($this->purchase_order_id !== null && ! PurchaseOrder::query()
            ->whereKey($this->purchase_order_id)
            ->where('supplier_id', $this->supplier_id)
            ->exists()) {
            $this->purchase_order_id = null;
        }

        $this->resetValidation(['supplier_id', 'purchase_order_id']);
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')],
            'purchase_order_id' => [
                'nullable',
                Rule::exists('purchase_orders', 'id')->where('supplier_id', $this->supplier_id),
            ],
            'invoice_number' => [
                'required', 'string', 'max:64',
                Rule::unique('supplier_invoices', 'invoice_number')
                    ->where('supplier_id', $this->supplier_id)
                    ->ignore($this->supplierInvoice?->id),
            ],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'total' => ['required', new ValidMoneyAmount, 'gt:0'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->supplierInvoice !== null) {
            Gate::authorize('update', $this->supplierInvoice);
            $this->supplierInvoice->update($validated);
        } else {
            Gate::authorize('create', SupplierInvoice::class);
            $this->supplierInvoice = SupplierInvoice::create($validated + ['status' => SupplierInvoice::STATUS_OPEN]);
        }

        app(MatchSupplierInvoiceAction::class)->execute($this->supplierInvoice);

        session()->flash('status', 'Supplier invoice saved.');
        $this->redirectRoute('supplier-invoices.index');
    }

    public function render()
    {
        $suppliers = Supplier::with('person')->orderBy('company_name')->get();
        $purchaseOrders = PurchaseOrder::query()
            ->with('stockLocation')
            ->withSum([
                'receivings as received_total' => fn ($query) => $query->where('type', Receiving::TYPE_RECEIPT),
            ], 'total')
            ->when($this->supplier_id, fn ($query) => $query->where('supplier_id', $this->supplier_id))
            ->orderByDesc('id')
            ->get();
        $selectedPurchaseOrder = $purchaseOrders->firstWhere('id', $this->purchase_order_id);
        $receivedTotal = (string) ($selectedPurchaseOrder?->received_total ?? '0');
        $validTotal = preg_match('/^\d{1,15}(\.\d{1,2})?$/', trim($this->total)) === 1
            && bccomp($this->total, '0', 2) > 0;
        $variance = $selectedPurchaseOrder !== null && $validTotal
            ? bcsub($this->total, $receivedTotal, 2)
            : null;
        $absoluteVariance = $variance !== null && bccomp($variance, '0', 2) < 0
            ? bcmul($variance, '-1', 2)
            : $variance;

        return view('livewire.purchasing.supplier-invoices.form', [
            'suppliers' => $suppliers,
            'purchaseOrders' => $purchaseOrders,
            'selectedSupplier' => $suppliers->firstWhere('id', $this->supplier_id),
            'selectedPurchaseOrder' => $selectedPurchaseOrder,
            'receivedTotal' => $receivedTotal,
            'variance' => $variance,
            'matchesReceipts' => $absoluteVariance !== null
                && bccomp($absoluteVariance, (string) config('pos.purchasing.match_tolerance', '0.01'), 2) <= 0,
            'currency' => Money::currency(),
        ]);
    }
}
