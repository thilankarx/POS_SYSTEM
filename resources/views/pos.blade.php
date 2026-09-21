<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
        <meta name="theme-color" content="#0f172a">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/icon-192.png">

        <title>{{ config('app.name') }} Register</title>

        @vite(['resources/css/pos.css', 'resources/js/pos/main.js'])
    </head>
    <body class="h-full bg-slate-950 text-slate-100">
        <div id="app"></div>
    </body>
</html>
