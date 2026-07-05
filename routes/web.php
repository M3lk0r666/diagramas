<?php

use Illuminate\Support\Facades\Route;

// Landing page pública
Route::get('/', function () {
    return view('welcome');
});

// Alias 'dashboard' que usan breadcrumbs internos → redirige al dashboard real en /admin/
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->to('/admin/dashboard');
    })->name('dashboard');
});
