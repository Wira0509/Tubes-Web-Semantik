<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FilmController;
use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Http;

Route::get('/', [FilmController::class, 'search'])->name('film.search');
Route::get('/film/{imdb_id}', [FilmController::class, 'show'])->name('film.show');

// Chatbot dengan rate limiting (20 request per menit)
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/chatbot/recommend', [ChatbotController::class, 'recommend'])->name('chatbot.recommend');
});

// Route untuk clear cache (hanya untuk admin)
Route::post('/admin/chatbot/clear-cache', [ChatbotController::class, 'clearCache'])
    ->name('chatbot.clear-cache');

// =================== ROUTE DEBUGGING ===================

Route::get('/test-gemini-simple', function () {
    try {
        $apiKey = config('gemini.api_key');
        
        if (empty($apiKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'API Key tidak ditemukan di .env',
                'hint' => 'Pastikan GEMINI_API_KEY sudah di set di file .env'
            ]);
        }
        
        // Update: gunakan gemini-2.5-flash (stable version)
        $response = Http::timeout(30)->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey,
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Halo, katakan "Halo dari Gemini!" dalam bahasa Indonesia']
                        ]
                    ]
                ]
            ]
        );
        
        if (!$response->successful()) {
            return response()->json([
                'status' => 'error',
                'http_status' => $response->status(),
                'message' => 'Gemini API Error',
                'body' => $response->json(),
                'hint' => $response->status() == 400 ? 'API Key mungkin salah atau tidak valid' : 'Cek koneksi internet'
            ], 500);
        }
        
        $data = $response->json();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Gemini API bekerja dengan baik!',
            'response' => $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response',
            'full_data' => $data
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
});

// Tambahkan route untuk list available models
Route::get('/test-list-models', function () {
    try {
        $apiKey = config('gemini.api_key');
        
        $response = Http::timeout(30)->get(
            'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey
        );
        
        if (!$response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to list models',
                'body' => $response->json()
            ], 500);
        }
        
        $models = $response->json();
        
        // Filter hanya model yang support generateContent
        $generativeModels = array_filter($models['models'] ?? [], function($model) {
            return in_array('generateContent', $model['supportedGenerationMethods'] ?? []);
        });
        
        return response()->json([
            'status' => 'success',
            'available_models' => array_map(fn($m) => $m['name'], $generativeModels),
            'full_data' => $models
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/test-rdf', function () {
    try {
        $rdfService = app(\App\Services\RDFService::class);
        $films = $rdfService->getAllFilms();
        return response()->json([
            'status' => 'success',
            'count' => count($films),
            'sample' => array_slice($films, 0, 3)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

Route::get('/test-env', function () {
    return response()->json([
        'GEMINI_API_KEY_SET' => !empty(env('GEMINI_API_KEY')),
        'GEMINI_API_KEY_LENGTH' => strlen(env('GEMINI_API_KEY') ?? ''),
        'GEMINI_API_KEY_PREFIX' => substr(env('GEMINI_API_KEY') ?? '', 0, 10) . '...',
        'CONFIG_GEMINI' => config('gemini.api_key') ? 'SET' : 'NOT SET',
        'APP_DEBUG' => config('app.debug')
    ]);
});

// Test chatbot secara manual
Route::get('/test-chatbot', function () {
    try {
        $controller = app(ChatbotController::class);
        $request = new \Illuminate\Http\Request();
        $request->merge(['message' => 'Aku lagi bosan nih, rekomendasikan film dong']);
        
        $response = $controller->recommend($request);
        
        return $response;
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});