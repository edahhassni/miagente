<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Chat;


// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::view('dashboard', 'dashboard')->name('dashboard');
//     Route::get('/chat', Chat::class)->name('chat');
// });

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('chat')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function() {
        return redirect()->route('chat');
    })->name('dashboard');

    Route::get('/chat', function() {
        return view('chat');
    })->name('chat');
});
require __DIR__.'/settings.php';
