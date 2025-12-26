<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
|
*/

Route::prefix('/pattern-library')->group(function () {
    Route::view('/', 'pattern-library.pattern-library')
        ->name('pattern-library');

    Route::view('/prose', 'pattern-library.prose')
        ->name('prose');

    Route::view('/disclosure', 'pattern-library.disclosure')
        ->name('disclosure');

    Route::view('/accordion', 'pattern-library.accordion')
        ->name('accordion');
});
