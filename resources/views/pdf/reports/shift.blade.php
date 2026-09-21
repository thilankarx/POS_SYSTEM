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

    <h2>Shifts</h2>
    <table>
        <thead>
            <tr>
                <th>Terminal</th>
                <th>Opened by</th>
                <th>Opened at</th>
                <th>Closed at</th>
                <th>Status</th>
                <th class="text-right">Expected cash</th>
                <th class="text-right">Counted cash</th>
                <th class="text-right">Variance</th>
                <th class="text-right">Sales total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $shift)
                <tr>
                    <td>{{ $shift->terminal?->name }}</td>
                    <td>{{ $shift->openedBy?->name }}</td>
                    <td>{{ $shift->opened_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $shift->closed_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $shift->status }}</td>
                    <td class="text-right">{{ $shift->expected_cash }}</td>
                    <td class="text-right">{{ $shift->counted_cash }}</td>
                    <td class="text-right">{{ $shift->cash_variance }}</td>
                    <td class="text-right">{{ \App\Support\Money\Money::of($shift->sales_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
