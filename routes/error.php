<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Error Routes
|--------------------------------------------------------------------------
|
| Generic application error page. Only accessible via redirect (not directly).
|
*/

Route::get('/error', function () {
    if (! session()->has('error_message')) {
        abort(404);
    }

    return view('errors.app-error');
})->name('error.page');

Route::get('/install-gone', function () {
    if (! session()->has('install_gone')) {
        abort(404);
    }

    return view('errors.install-gone');
})->name('install.gone');
