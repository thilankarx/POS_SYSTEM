@extends('pdf.layout')

@section('page-margin', '10mm')

@php
    $isInvoice = $sale->sale_type === \App\Domain\Sales\Models\Sale::TYPE_INVOICE;
    $isQuote = $sale->sale_type === \App\Domain\Sales\Models\Sale::TYPE_QUOTE;
    $documentLabel = $isInvoice ? 'Invoice' : ($isQuote ? 'Quote' : 'Receipt');
    $documentNumber = $isInvoice ? $sale->invoice_number : ($isQuote ? $sale->quote_number : $sale->number);
    $isCompleted = $sale->status === \App\Domain\Sales\Models\Sale::STATUS_COMPLETED;
    $barcodeSvg = base64_encode(
        (new \Picqer\Barcode\BarcodeGeneratorSVG())->getBarcode(
            $sale->number,
            \Picqer\Barcode\BarcodeGeneratorSVG::TYPE_CODE_128,
            1.5,
            40
        )
    );
@endphp

@section('title', $documentLabel.' '.$documentNumber)

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
                @if ($business->receipt_header)
                    <div class="muted">{{ $business->receipt_header }}</div>
                @endif
                <div class="muted">{{ $documentLabel }} #{{ $documentNumber }}</div>
                <div class="muted">{{ $sale->sold_at?->copy()->timezone(config('pos.timezone'))->format('Y-m-d H:i') }}</div>
                @if ($sale->stockLocation)
                    <div class="muted">{{ $sale->stockLocation->name }}</div>
                @endif
            </td>
            <td class="text-right">
                @unless ($isCompleted)
                    <span class="badge">{{ ucfirst(str_replace('_', ' ', $sale->status)) }}</span>
                @endunless
                @if ($sale->user)
                    <div class="muted" style="margin-top: 4px;">Served by {{ $sale->user->name }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if ($isInvoice && $sale->customer)
        <h2>Bill to</h2>
        <div>{{ $sale->customer->company_name ?: $sale->customer->person?->full_name }}</div>
        @if ($sale->customer->person?->email)
            <div class="muted">{{ $sale->customer->person->email }}</div>
        @endif
        @if ($sale->customer->person?->phone)
            <div class="muted">{{ $sale->customer->person->phone }}</div>
        @endif
    @endif

    <h2>Items</h2>
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit price</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->lines as $line)
                <tr>
                    <td>{{ $line->item_name }}</td>
                    <td>{{ $line->sku }}</td>
                    <td class="text-right">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}</td>
                    <td class="text-right">{{ $line->unit_price }}</td>
                    <td class="text-right">{{ $line->discount_amount }}</td>
                    <td class="text-right">{{ $line->line_tax }}</td>
                    <td class="text-right">{{ $line->line_total }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 12px;">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                @if ($sale->taxes->isNotEmpty())
                    <h2>Tax breakdown</h2>
                    <table>
                        @foreach ($sale->taxes as $tax)
                            <tr>
                                <td>{{ $tax->name }} ({{ rtrim(rtrim((string) $tax->rate, '0'), '.') }}%)</td>
                                <td class="text-right">{{ $tax->tax_amount }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                <h2>Payments</h2>
                @if ($sale->payments->isEmpty())
                    <div class="muted">No payment due ({{ $sale->sale_type }})</div>
                @else
                    <table>
                        @foreach ($sale->payments as $payment)
                            <tr>
                                <td>{{ $payment->method?->name }}</td>
                                <td class="text-right">{{ $payment->amount }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </td>
            <td style="width: 40%; vertical-align: top;">
                <table>
                    {{-- $sale->subtotal is stored net of every line discount (the
                    basis CartPricer needs for total = subtotal + tax to hold in
                    both tax modes); add the discount back for display so this
                    line, minus the Discount line below it, reconciles to Total
                    the way a customer reading top-to-bottom expects. --}}
                    <tr><td>Subtotal</td><td class="text-right">{{ $sale->subtotal->plus($sale->discount_total) }}</td></tr>
                    <tr><td>Discount</td><td class="text-right">-{{ $sale->discount_total }}</td></tr>
                    <tr><td>Tax</td><td class="text-right">{{ $sale->tax_total }}</td></tr>
                    @if (! $sale->rounding_adjustment->isZero())
                        <tr><td>Rounding</td><td class="text-right">{{ $sale->rounding_adjustment }}</td></tr>
                    @endif
                    <tr class="total"><td>Total</td><td class="text-right">{{ $sale->total }}</td></tr>
                    @if (! $sale->tip_amount->isZero())
                        <tr><td>Tip</td><td class="text-right">{{ $sale->tip_amount }}</td></tr>
                        <tr class="total"><td>Grand Total</td><td class="text-right">{{ $sale->total->plus($sale->tip_amount) }}</td></tr>
                    @endif
                    @if ($sale->payments->isNotEmpty())
                        <tr><td>Paid</td><td class="text-right">{{ $sale->paid_total }}</td></tr>
                        <tr><td>Change</td><td class="text-right">{{ $sale->change_given }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div style="margin-top: 24px; text-align: center;">
        <img src="data:image/svg+xml;base64,{{ $barcodeSvg }}" style="height: 40px;">
        <div class="muted">{{ $sale->number }}</div>
    </div>

    <div class="muted" style="margin-top: 12px; text-align: center;">
        @if ($business->receipt_footer)
            {{ $business->receipt_footer }} &middot;
        @endif
        Printed {{ now()->format('Y-m-d H:i') }}
    </div>
@endsection
