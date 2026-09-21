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

    <h2>Suppliers</h2>
    <table>
        <thead>
            <tr>
                <th>Supplier</th>
                <th class="text-right">Receivings</th>
                <th class="text-right">Total spend</th>
                <th class="text-right">Avg receiving</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->company_name }}</td>
                    <td class="text-right">{{ $row->receiving_count }}</td>
                    <td class="text-right">{{ $row->total }}</td>
                    <td class="text-right">{{ number_format((float) $row->avg_receiving, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
