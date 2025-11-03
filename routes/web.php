<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilmController; // Pastikan ini di-import

Route::get('/', [FilmController::class, 'search'])->name('film.search');

// Ubah {film} menjadi {imdb_id} agar kita bisa menangkap 'tt0371746'
Route::get('/film/{imdb_id}', [FilmController::class, 'show'])->name('film.show');
