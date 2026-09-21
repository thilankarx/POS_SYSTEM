<?php

declare(strict_types=1);

namespace App\Livewire\Purchasing\SupplierInvoices;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Purchasing\Actions\RecordSupplierInvoicePaymentAction;
use App\Domain\Purchasing\Actions\ResolveSupplierInvoiceDisputeAction;
use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\Receiving;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Support\Logging\DomainLog;
use App\Support\Money\Money as MoneySupport;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $supplier = '';

    #[Url(except: '')]
    public string $timing = '';

    #[Url(except: 'invoice_date')]
    public string $sort = 'invoice_date';

    /** @var array<int, string> */
    public array $paymentAmount = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSupplier(): void
    {
        $this->resetPage();
    }

    public function updatingTiming(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'supplier', 'timing', 'sort']);
        $this->sort = 'invoice_date';
        $this->resetPage();
    }

    public function recordPayment(int $id): void
    {
        $invoice = SupplierInvoice::findOrFail($id);

        Gate::authorize('update', $invoice);

        $this->validate([
            "paymentAmount.{$id}" => ['required', new ValidMoneyAmount, 'gt:0'],
        ]);

        $amount = $this->paymentAmount[$id];

        try {
            app(RecordSupplierInvoicePaymentAction::class)->execute($invoice, $amount);
        } catch (PurchasingException $e) {
            DomainLog::refused($e, ['supplier_invoice_id' => $id]);
            $this->addError("payment.{$id}", $e->getMessage());

            return;
        }

        unset($this->paymentAmount[$id]);
        $this->resetErrorBag(["paymentAmount.{$id}", "payment.{$id}"]);
        session()->flash('status', 'Payment recorded.');
    }

    public function resolveDispute(int $id): void
    {
        $invoice = SupplierInvoice::findOrFail($id);

        Gate::authorize('approve', $invoice);

        try {
            app(ResolveSupplierInvoiceDisputeAction::class)->execute($invoice);
        } catch (PurchasingException $e) {
            DomainLog::refused($e, ['supplier_invoice_id' => $id]);
            $this->addError("dispute.{$id}", $e->getMessage());

            return;
        }

        session()->flash('status', 'Discrepancy accepted; invoice can now be paid.');
    }

    public function render()
    {
        Gate::authorize('viewAny', SupplierInvoice::class);

        $query = SupplierInvoice::query()
            ->with(['supplier', 'purchaseOrder'])
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);

                $query->where(function ($query) use ($search) {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($query) => $query->where('company_name', 'like', "%{$search}%"))
                        ->orWhereHas('purchaseOrder', fn ($query) => $query->where('number', 'like', "%{$search}%"));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->supplier !== '', fn ($q) => $q->where('supplier_id', $this->supplier))
            ->when($this->timing === 'overdue', fn ($q) => $q
                ->where('status', '!=', SupplierInvoice::STATUS_PAID)
                ->whereDate('due_date', '<', today()))
            ->when($this->timing === 'due_soon', fn ($q) => $q
                ->where('status', '!=', SupplierInvoice::STATUS_PAID)
                ->whereBetween('due_date', [today(), today()->addDays(7)]))
            ->when($this->timing === 'unscheduled', fn ($q) => $q->whereNull('due_date'));

        match ($this->sort) {
            'due_date' => $query->orderByRaw('due_date IS NULL, due_date ASC')->latest('id'),
            'balance' => $query->orderByRaw('(total - paid_total) DESC')->latest('id'),
            default => $query->latest('invoice_date')->latest('id'),
        };

        $supplierInvoices = $query->paginate(20);

        foreach ($supplierInvoices as $invoice) {
            if (! in_array($invoice->status, [SupplierInvoice::STATUS_PAID, SupplierInvoice::STATUS_DISPUTED], true)
                && ! array_key_exists($invoice->id, $this->paymentAmount)) {
                $this->paymentAmount[$invoice->id] = (string) $invoice->total->minus($invoice->paid_total)->getAmount();
            }
        }

        $purchaseOrderIds = $supplierInvoices->getCollection()
            ->where('status', SupplierInvoice::STATUS_DISPUTED)
            ->pluck('purchase_order_id')
            ->filter()
            ->unique();
        $receiptSums = Receiving::query()
            ->whereIn('purchase_order_id', $purchaseOrderIds)
            ->where('type', Receiving::TYPE_RECEIPT)
            ->selectRaw('purchase_order_id, SUM(total) as aggregate')
            ->groupBy('purchase_order_id')
            ->pluck('aggregate', 'purchase_order_id');
        $receivedTotals = $supplierInvoices->getCollection()
            ->where('status', SupplierInvoice::STATUS_DISPUTED)
            ->mapWithKeys(fn (SupplierInvoice $invoice) => [
                $invoice->id => MoneySupport::of((string) ($receiptSums[$invoice->purchase_order_id] ?? '0')),
            ]);

        $outstanding = SupplierInvoice::query()
            ->where('status', '!=', SupplierInvoice::STATUS_PAID)
            ->selectRaw('COALESCE(SUM(total - paid_total), 0) as aggregate')
            ->value('aggregate');

        $summary = [
            'outstanding' => MoneySupport::of((string) $outstanding),
            'overdue' => SupplierInvoice::query()
                ->where('status', '!=', SupplierInvoice::STATUS_PAID)
                ->whereDate('due_date', '<', today())
                ->count(),
            'dueSoon' => SupplierInvoice::query()
                ->where('status', '!=', SupplierInvoice::STATUS_PAID)
                ->whereBetween('due_date', [today(), today()->addDays(7)])
                ->count(),
            'disputed' => SupplierInvoice::where('status', SupplierInvoice::STATUS_DISPUTED)->count(),
            'total' => SupplierInvoice::count(),
        ];

        return view('livewire.purchasing.supplier-invoices.index', [
            'supplierInvoices' => $supplierInvoices,
            'suppliers' => Supplier::orderBy('company_name')->get(['id', 'company_name']),
            'receivedTotals' => $receivedTotals,
            'summary' => $summary,
        ]);
    }
}
