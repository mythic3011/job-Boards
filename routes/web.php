<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
| Authentication routes (Register, Login, Logout) are automatically
| registered by Laravel Fortify.
|
*/

/*
|--------------------------------------------------------------------------
| Installation Routes
|--------------------------------------------------------------------------
*/
require __DIR__.'/install.php';

/*
|--------------------------------------------------------------------------
| Authentication Routes (Override Fortify defaults)
|--------------------------------------------------------------------------
*/
require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Home Route
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    if (!Schema::hasTable('settings') || !Setting::isSetupCompleted()) {
        return redirect()->route('install.index');
    }
    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Feature-Specific Route Groups
|--------------------------------------------------------------------------
*/
require __DIR__.'/bot.php';
require __DIR__.'/error.php';
require __DIR__.'/images.php';
require __DIR__.'/jobs.php';
require __DIR__.'/profile.php';
require __DIR__.'/admin.php';
