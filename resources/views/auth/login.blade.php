<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">

        <title>{{ config('app.name', 'Laravel') }} &mdash; Sign in</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body class="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-10 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100 sm:px-6">
        <main class="w-full max-w-md">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xl shadow-slate-900/5 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/20">
                <header class="border-b border-slate-200 px-6 py-5 dark:border-slate-800 sm:px-8">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-emerald-700 text-white dark:bg-emerald-600">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v15H4zM8 5V3h8v2M8 10h8m-8 4h8m-8 3h4" /></svg>
                        </span>
                        <span class="min-w-0 truncate text-sm font-bold text-slate-950 dark:text-white">{{ config('app.name', 'Point of Sale') }}</span>
                    </div>
                    <h1 class="mt-6 text-2xl font-bold text-slate-950 dark:text-white">Sign in</h1>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Use your staff account to continue.</p>
                </header>

                <div class="px-6 py-6 sm:px-8 sm:py-7">
                    @if (session('status'))
                        <div role="status" class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div role="alert" class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                            <div class="flex items-start gap-2.5"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.3 3.9 1.8 18.5A1.7 1.7 0 003.3 21h17.4a1.7 1.7 0 001.5-2.5L13.7 3.9a2 2 0 00-3.4 0z" /></svg><ul class="space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                        </div>
                    @endif

                    <form method="POST" action="{{ url('/login') }}" autocomplete="on" class="space-y-5" x-data="{ showPassword: false }">
                        @csrf

                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Email address</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" @if ($errors->has('email')) aria-invalid="true" @endif class="h-11 w-full rounded-md border border-slate-300 bg-white px-3.5 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Password</label>
                            <div class="relative">
                                <input id="password" type="password" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password" @if ($errors->has('password')) aria-invalid="true" @endif class="h-11 w-full rounded-md border border-slate-300 bg-white px-3.5 pr-12 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <button type="button" @click="showPassword = !showPassword" :aria-label="showPassword ? 'Hide password' : 'Show password'" :title="showPassword ? 'Hide password' : 'Show password'" class="absolute right-1.5 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center rounded text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                                    <svg x-show="!showPassword" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.3-7 9.5-7 9.5 7 9.5 7-3.3 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg x-show="showPassword" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A10.8 10.8 0 0 1 12 5c6.2 0 9.5 7 9.5 7a14.8 14.8 0 0 1-3 3.8M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.3 7 9.5 7c1 0 2-.2 2.8-.5"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <label for="remember" class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-slate-600 dark:text-slate-300">
                                <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600 dark:border-slate-700 dark:bg-slate-950">
                                Remember me
                            </label>
                        </div>

                        <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500 dark:focus:ring-offset-slate-900">
                            Sign in
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>
                        </button>
                    </form>
                </div>
            </section>
            <p class="mt-4 text-center text-xs text-slate-500 dark:text-slate-400">Authorized team members only</p>
        </main>
    </body>
</html>
