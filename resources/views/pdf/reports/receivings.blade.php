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

    <h2>Receivings</h2>
    <table>
        <thead>
            <tr>
                <th>Number</th>
                <th>Supplier</th>
                <th>Location</th>
                <th>Type</th>
                <th class="text-right">Total</th>
                <th>Received at</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $receiving)
                <tr>
                    <td>{{ $receiving->number }}</td>
                    <td>{{ $receiving->supplier?->company_name }}</td>
                    <td>{{ $receiving->stockLocation?->name }}</td>
                    <td>{{ $receiving->type }}</td>
                    <td class="text-right">{{ $receiving->total }}</td>
                    <td>{{ $receiving->received_at->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
