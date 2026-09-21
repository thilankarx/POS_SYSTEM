<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Livewire\Purchasing\SupplierInvoices\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();

    $this->overdueInvoice = SupplierInvoice::create([
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'INV-PAGE-OVERDUE',
        'invoice_date' => now()->subDays(10)->toDateString(),
        'due_date' => now()->subDay()->toDateString(),
        'total' => '100.00',
        'paid_total' => '20.00',
        'status' => SupplierInvoice::STATUS_PARTIALLY_PAID,
    ]);

    $this->disputedInvoice = SupplierInvoice::create([
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'INV-PAGE-DISPUTED',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(3)->toDateString(),
        'total' => '50.00',
        'status' => SupplierInvoice::STATUS_DISPUTED,
    ]);
});

it('shows supplier invoices as a searchable accounts-payable queue', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('Outstanding')
        ->assertSee('130.00')
        ->assertSee('Payment progress')
        ->assertSee('1 day overdue')
        ->assertSet("paymentAmount.{$this->overdueInvoice->id}", '80.00')
        ->set('search', 'INV-PAGE-OVERDUE')
        ->assertSee($this->overdueInvoice->invoice_number)
        ->assertDontSee($this->disputedInvoice->invoice_number)
        ->call('clearFilters')
        ->set('status', SupplierInvoice::STATUS_DISPUTED)
        ->assertSee($this->disputedInvoice->invoice_number)
        ->assertDontSee($this->overdueInvoice->invoice_number);
});

it('rejects a zero supplier-invoice payment with a visible field error', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set("paymentAmount.{$this->overdueInvoice->id}", '0')
        ->call('recordPayment', $this->overdueInvoice->id)
        ->assertHasErrors("paymentAmount.{$this->overdueInvoice->id}");

    expect((string) $this->overdueInvoice->fresh()->paid_total->getAmount())->toBe('20.00');
});
