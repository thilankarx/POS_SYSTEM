<?php

use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

// The auth middleware on /dashboard sends a guest on to /login itself, so
// this needs no auth check of its own.
Route::redirect('/', '/dashboard');

Route::get('/dashboard', Dashboard::class)
    ->middleware(['auth'])
    ->name('dashboard');

Route::middleware(['auth'])->group(base_path('routes/backoffice.php'));

Route::view('/pos', 'pos')->name('pos');
