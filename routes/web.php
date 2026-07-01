<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/jogos');

Route::livewire('/jogos', 'pages::jogos')->name('jogos');
Route::livewire('/classificacao', 'pages::classificacao')->name('classificacao');
