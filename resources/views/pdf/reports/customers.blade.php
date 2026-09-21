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

    <h2>Customers</h2>
    <table>
        <thead>
            <tr>
                <th>Customer</th>
                <th class="text-right">Sales</th>
                <th class="text-right">Total spend</th>
                <th>Last purchase</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->customer_name }}</td>
                    <td class="text-right">{{ $row->sale_count }}</td>
                    <td class="text-right">{{ $row->total }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row->last_purchase_at)->format('Y-m-d') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
