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

    <h2>Payments</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Sale #</th>
                <th>Method</th>
                <th class="text-right">Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $payment)
                <tr>
                    <td>{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $payment->sale?->number }}</td>
                    <td>{{ $payment->method?->name }}</td>
                    <td class="text-right">{{ $payment->amount }}</td>
                    <td>{{ $payment->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
