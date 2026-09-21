<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('Accountant');
});

it('denies all report pages to a user without reports.view', function () {
    $this->actingAs($this->cashier)->get(route('reports.sales'))->assertForbidden();
    $this->actingAs($this->cashier)->get(route('reports.inventory'))->assertForbidden();
    $this->actingAs($this->cashier)->get(route('reports.shifts'))->assertForbidden();
});

it('lets a Stock Clerk view the inventory report but not sales or shifts', function () {
    $this->actingAs($this->stockClerk)->get(route('reports.inventory'))->assertOk();
    $this->actingAs($this->stockClerk)->get(route('reports.sales'))->assertForbidden();
    $this->actingAs($this->stockClerk)->get(route('reports.shifts'))->assertForbidden();
});

it('lets an Accountant view sales and shift reports but not inventory', function () {
    $this->actingAs($this->accountant)->get(route('reports.sales'))->assertOk();
    $this->actingAs($this->accountant)->get(route('reports.shifts'))->assertOk();
    $this->actingAs($this->accountant)->get(route('reports.inventory'))->assertForbidden();
});

it('lets an admin view all three report pages', function () {
    $this->actingAs($this->admin)->get(route('reports.sales'))->assertOk();
    $this->actingAs($this->admin)->get(route('reports.inventory'))->assertOk();
    $this->actingAs($this->admin)->get(route('reports.shifts'))->assertOk();
});

it('exports the sales report as csv', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.sales.export'));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Number,Date,Type,Customer,Subtotal,Discount,Tax,Total,Cost');
});

it('exports the inventory report as csv', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.inventory.export'));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Date,Item,SKU,Location,"Quantity delta",Reason,"Unit cost"');
});

it('exports the shifts report as csv', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.shifts.export'));

    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Terminal,"Opened by","Opened at","Closed at",Status,"Expected cash","Counted cash",Variance,"Sales total"');
});

it('denies export routes to a user without the report ability', function () {
    $this->actingAs($this->cashier)->get(route('reports.sales.export'))->assertForbidden();
    $this->actingAs($this->stockClerk)->get(route('reports.sales.export'))->assertForbidden();
});

it('exports the sales report as pdf', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.sales.export-pdf'));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('exports the inventory report as pdf', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.inventory.export-pdf'));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('exports the shifts report as pdf', function () {
    $response = $this->actingAs($this->admin)->get(route('reports.shifts.export-pdf'));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('denies pdf export routes to a user without the report ability', function () {
    $this->actingAs($this->cashier)->get(route('reports.sales.export-pdf'))->assertForbidden();
    $this->actingAs($this->stockClerk)->get(route('reports.sales.export-pdf'))->assertForbidden();
});

it('lets a Stock Clerk view items and receivings reports but not categories or suppliers', function () {
    $this->actingAs($this->stockClerk)->get(route('reports.items'))->assertOk();
    $this->actingAs($this->stockClerk)->get(route('reports.receivings'))->assertOk();
    $this->actingAs($this->stockClerk)->get(route('reports.categories'))->assertForbidden();
    $this->actingAs($this->stockClerk)->get(route('reports.suppliers'))->assertForbidden();
});

it('lets an Accountant view suppliers, customers, payments and taxes reports but not items', function () {
    $this->actingAs($this->accountant)->get(route('reports.suppliers'))->assertOk();
    $this->actingAs($this->accountant)->get(route('reports.customers'))->assertOk();
    $this->actingAs($this->accountant)->get(route('reports.payments'))->assertOk();
    $this->actingAs($this->accountant)->get(route('reports.taxes'))->assertOk();
    $this->actingAs($this->accountant)->get(route('reports.items'))->assertForbidden();
});

it('lets an admin view all seven new report pages', function () {
    foreach (['items', 'categories', 'suppliers', 'receivings', 'customers', 'payments', 'taxes'] as $report) {
        $this->actingAs($this->admin)->get(route("reports.{$report}"))->assertOk();
    }
});

it('exports each of the seven new reports as csv and pdf', function () {
    foreach (['items', 'categories', 'suppliers', 'receivings', 'customers', 'payments', 'taxes'] as $report) {
        $csv = $this->actingAs($this->admin)->get(route("reports.{$report}.export"));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $pdf = $this->actingAs($this->admin)->get(route("reports.{$report}.export-pdf"));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
});

it('lets an Accountant view the commission report (reports.employees) but denies a Stock Clerk', function () {
    $this->actingAs($this->accountant)->get(route('reports.commissions'))->assertOk();
    $this->actingAs($this->stockClerk)->get(route('reports.commissions'))->assertForbidden();
    $this->actingAs($this->cashier)->get(route('reports.commissions'))->assertForbidden();
});

it('lets an admin view the commission report and export it as csv', function () {
    $this->actingAs($this->admin)->get(route('reports.commissions'))->assertOk();

    $csv = $this->actingAs($this->admin)->get(route('reports.commissions.export'));
    $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($csv->streamedContent())->toContain('Waiter,Sales,"Total commission","Total tips"');
});

it('denies the commission report export to a user without reports.employees', function () {
    $this->actingAs($this->cashier)->get(route('reports.commissions.export'))->assertForbidden();
});
