@extends('pdf.layout')

@section('page-margin', '15mm')

@section('title', 'Supplier Invoice ' . $invoice->invoice_number)

@section('content')
    <table>
        <tr>
            <td>
                @if ($business->logo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->path($business->logo_path) }}" style="max-height: 40px; margin-bottom: 6px;">
                @endif
                <h1>{{ $business->store_name }}</h1>
                @if ($business->address)
                    <div class="muted">{{ $business->address }}</div>
                @endif
                @if ($business->phone)
                    <div class="muted">{{ $business->phone }}</div>
                @endif
                <div class="muted">Supplier Invoice #{{ $invoice->invoice_number }}</div>
                <div class="muted">Invoice date: {{ $invoice->invoice_date?->format('Y-m-d') }}</div>
                @if ($invoice->due_date)
                    <div class="muted">Due: {{ $invoice->due_date->format('Y-m-d') }}</div>
                @endif
                @if ($invoice->purchaseOrder)
                    <div class="muted">Purchase order: {{ $invoice->purchaseOrder->number }}</div>
                @endif
            </td>
            <td class="text-right">
                <span class="badge">{{ ucfirst(str_replace('_', ' ', $invoice->status)) }}</span>
            </td>
        </tr>
    </table>

    <h2>Supplier</h2>
    <div>{{ $invoice->supplier?->company_name ?: $invoice->supplier?->person?->full_name }}</div>
    @if ($invoice->supplier?->person?->email)
        <div class="muted">{{ $invoice->supplier->person->email }}</div>
    @endif
    @if ($invoice->supplier?->person?->phone)
        <div class="muted">{{ $invoice->supplier->person->phone }}</div>
    @endif

    @if ($invoice->status === \App\Domain\Purchasing\Models\SupplierInvoice::STATUS_DISPUTED)
        <h2>Discrepancy</h2>
        <table>
            <tr><td>Invoiced</td><td class="text-right">{{ $invoice->total }}</td></tr>
            <tr><td>Received</td><td class="text-right">{{ $invoice->receivedTotal() }}</td></tr>
        </table>
    @endif

    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%; vertical-align: top;">
                <table>
                    <tr class="total"><td>Total</td><td class="text-right">{{ $invoice->total }}</td></tr>
                    <tr><td>Paid</td><td class="text-right">{{ $invoice->paid_total }}</td></tr>
                    <tr><td>Balance due</td><td class="text-right">{{ $invoice->total->minus($invoice->paid_total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="muted" style="margin-top: 24px; text-align: center;">
        Printed {{ now()->format('Y-m-d H:i') }}
    </div>
@endsection
