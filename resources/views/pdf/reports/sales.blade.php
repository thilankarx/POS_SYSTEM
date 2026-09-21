@extends('pdf.layout')

@section('page-margin', '15mm 12mm')

@section('title', $reportTitle)

@section('content')
    <table>
        <tr>
            <td>
                <h1>{{ $reportTitle }}</h1>
                <div class="muted">{{ $from->format('Y-m-d') }} &ndash; {{ $to->format('Y-m-d') }}</div>
            </td>
            <td class="text-right muted">Generated {{ now()->format('Y-m-d H:i') }}</td>
        </tr>
    </table>

    <table style="margin-top: 12px;">
        <tr>
            <td>Sales</td>
            <td>Total</td>
            <td>Discount</td>
            <td>Tax</td>
            <td>Margin</td>
        </tr>
        <tr class="total">
            <td>{{ $summary->saleCount }}</td>
            <td>{{ $summary->total }}</td>
            <td>{{ $summary->discountTotal }}</td>
            <td>{{ $summary->taxTotal }}</td>
            <td>{{ $summary->margin() }}</td>
        </tr>
    </table>

    <h2>Sales</h2>
    <table>
        <thead>
            <tr>
                <th>Number</th>
                <th>Date</th>
                <th>Type</th>
                <th>Customer</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $sale)
                <tr>
                    <td>{{ $sale->number }}</td>
                    <td>{{ $sale->sold_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $sale->sale_type }}</td>
                    <td>{{ $sale->customer?->company_name }}</td>
                    <td class="text-right">{{ $sale->subtotal }}</td>
                    <td class="text-right">{{ $sale->discount_total }}</td>
                    <td class="text-right">{{ $sale->tax_total }}</td>
                    <td class="text-right">{{ $sale->total }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
