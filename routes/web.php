<?php

use App\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

// Paginas del panel (Inertia): Laravel elige la pagina Vue y le pasa los datos como props.
Route::controller(PanelController::class)->group(function () {
    Route::get('/', 'inicio')->name('inicio');
    Route::get('/ofertas/{sitio}/{dia?}', 'ofertas')->name('ofertas')->where('dia', '\d{4}-\d{2}-\d{2}');
    Route::get('/comparar/{competidor?}/{dia?}', 'comparar')->name('comparar')->where('dia', '\d{4}-\d{2}-\d{2}');
});
