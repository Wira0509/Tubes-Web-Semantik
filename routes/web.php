<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilmController;

Route::get('/', [FilmController::class, 'search'])->name('film.search');
Route::get('/film/{imdb_id}', [FilmController::class, 'show'])->name('film.show');
Route::post('/chatbot/recommend', [FilmController::class, 'recommendChat'])->name('chatbot.recommend');