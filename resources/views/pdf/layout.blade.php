<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title', config('app.name'))</title>
    <style>
        @page {
            margin: @yield('page-margin', '15mm 15mm');
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1b1b18;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .muted {
            color: #6b7280;
        }

        .rule {
            border-bottom: 1px solid #e5e7eb;
        }

        .text-right {
            text-align: right;
        }

        .total {
            color: #059669;
            font-weight: bold;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            color: #ffffff;
            background: #dc2626;
            font-size: 10px;
            text-transform: uppercase;
        }

        th {
            text-align: left;
            padding: 4px 6px;
            border-bottom: 1px solid #1b1b18;
            font-size: 10px;
            text-transform: uppercase;
            color: #6b7280;
        }

        td {
            padding: 4px 6px;
            border-bottom: 1px solid #e5e7eb;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }

        h2 {
            font-size: 13px;
            margin: 16px 0 6px;
            color: #6b7280;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
