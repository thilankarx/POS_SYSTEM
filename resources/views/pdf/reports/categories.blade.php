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

    <h2>Categories</h2>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-right">Qty sold</th>
                <th class="text-right">Revenue</th>
                <th class="text-right">Cost</th>
                <th class="text-right">Margin</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->category_name }}</td>
                    <td class="text-right">{{ $row->quantity }}</td>
                    <td class="text-right">{{ $row->line_total }}</td>
                    <td class="text-right">{{ $row->cost_price }}</td>
                    <td class="text-right">{{ $row->line_total->minus($row->cost_price) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
