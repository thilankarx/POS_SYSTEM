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

    <h2>Taxes</h2>
    <table>
        <thead>
            <tr>
                <th>Tax</th>
                <th class="text-right">Rate</th>
                <th class="text-right">Taxable amount</th>
                <th class="text-right">Tax collected</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->name }}</td>
                    <td class="text-right">{{ $row->rate }}%</td>
                    <td class="text-right">{{ $row->taxable_amount }}</td>
                    <td class="text-right">{{ $row->tax_amount }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
