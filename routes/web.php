<?php

use Illuminate\Support\Facades\Route;

/* Route::get('/', function () {
    return view('welcome');
}); */
Route::redirect('/','/admin');


Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Alias: mantiene el nombre 'dashboard' que usan los breadcrumbs de las vistas existentes
    // El dashboard real está en /admin/ vía admin.php → DashboardController
    Route::get('/dashboard', function () {
        return redirect()->route('dashboard');
    })->name('dashboard');
});
