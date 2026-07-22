<?php

use App\Livewire\Pos\PosApp;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/pos', PosApp::class)->name('pos');
