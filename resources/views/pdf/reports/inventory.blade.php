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

    <h2>Movements</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Item</th>
                <th>SKU</th>
                <th>Location</th>
                <th class="text-right">Quantity delta</th>
                <th>Reason</th>
                <th class="text-right">Unit cost</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $movement)
                <tr>
                    <td>{{ $movement->occurred_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $movement->item?->name }}</td>
                    <td>{{ $movement->item?->sku }}</td>
                    <td>{{ $movement->stockLocation?->name }}</td>
                    <td class="text-right">{{ $movement->quantity_delta }}</td>
                    <td>{{ $movement->reason }}</td>
                    <td class="text-right">{{ $movement->unit_cost }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
