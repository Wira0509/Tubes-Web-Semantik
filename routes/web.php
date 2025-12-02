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

Route::post('/admin/chatbot/clear-cache', [ChatbotController::class, 'clearCache'])
    ->name('chatbot.clear-cache');

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

// UPGRADE: Test endpoint dengan detail error
Route::get('/test-gemini-diagnosis', function () {
    $apiKey = config('gemini.api_key');
    
    $diagnosis = [
        'step1_env_check' => [
            'key_exists' => !empty($apiKey),
            'key_length' => strlen($apiKey ?? ''),
            'key_prefix' => substr($apiKey ?? '', 0, 10) . '...',
            'key_suffix' => '...' . substr($apiKey ?? '', -5),
        ]
    ];
    
    if (empty($apiKey)) {
        return response()->json([
            'status' => '🔴 FATAL ERROR',
            'message' => 'GEMINI_API_KEY tidak ditemukan di .env',
            'solution' => [
                '1. Buka .env file',
                '2. Tambahkan: GEMINI_API_KEY=YOUR_KEY_HERE',
                '3. Generate key di: https://aistudio.google.com/app/apikey',
                '4. Restart server: php artisan serve'
            ],
            'diagnosis' => $diagnosis
        ], 500);
    }
    
    try {
        $diagnosis['step2_api_test'] = 'Testing Gemini API...';
        
        $response = Http::timeout(30)->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=' . $apiKey,
            [
                'contents' => [[
                    'parts' => [['text' => 'Say "API Working!" in 2 words']]
                ]]
            ]
        );
        
        $diagnosis['step3_response'] = [
            'http_status' => $response->status(),
            'success' => $response->successful()
        ];
        
        if ($response->status() === 400) {
            $errorBody = $response->json();
            
            return response()->json([
                'status' => '🔴 API KEY INVALID',
                'message' => 'API Key sudah EXPIRED atau SALAH!',
                'error_detail' => $errorBody['error']['message'] ?? 'Unknown error',
                'solution' => [
                    '⚡ URGENT: Generate API Key BARU',
                    '1. Buka: https://aistudio.google.com/app/apikey',
                    '2. Klik "Create API Key" atau "Get API Key"',
                    '3. Copy key baru (mulai dengan AIza...)',
                    '4. Ganti di .env: GEMINI_API_KEY=KEY_BARU',
                    '5. Run: php artisan config:clear',
                    '6. Restart server'
                ],
                'diagnosis' => $diagnosis,
                'old_key' => substr($apiKey, 0, 15) . '...(EXPIRED)'
            ], 400);
        }
        
        if ($response->status() === 429) {
            return response()->json([
                'status' => '⚠️ RATE LIMIT',
                'message' => 'Terlalu banyak request. Tunggu 1 menit.',
                'diagnosis' => $diagnosis
            ], 429);
        }
        
        if (!$response->successful()) {
            return response()->json([
                'status' => '🔴 API ERROR',
                'message' => 'Gemini API error: ' . $response->status(),
                'body' => $response->json(),
                'diagnosis' => $diagnosis
            ], 500);
        }
        
        $data = $response->json();
        $aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';
        
        return response()->json([
            'status' => '✅ SUCCESS',
            'message' => 'API Key VALID dan bekerja!',
            'ai_response' => $aiText,
            'diagnosis' => $diagnosis,
            'next_step' => 'Chatbot siap digunakan! Test di /test-chatbot'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => '🔴 EXCEPTION',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'diagnosis' => $diagnosis
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

// Add rate limit checker
Route::get('/test-rate-limit', function () {
    $rateLimited = Cache::has('gemini_rate_limited');
    $ttl = Cache::get('gemini_rate_limited') ? Cache::get('gemini_rate_limited', 0) : 0;
    
    return response()->json([
        'rate_limited' => $rateLimited,
        'expires_in_seconds' => $rateLimited ? (60 - (time() % 60)) : 0,
        'status' => $rateLimited ? '🔴 Rate limited - using fallback' : '✅ API available',
        'clear_cache_url' => url('/admin/chatbot/clear-cache')
    ]);
});

// 🔍 DEBUG ROUTE: Inspect Chatbot State
Route::get('/debug-chatbot-state', function () {
    $history = Session::get('chatbot_history', []);
    $recommended = Session::get('chatbot_recommended_films', []);
    
    return response()->json([
        '📚 Conversation History' => [
            'count' => count($history),
            'last_5_turns' => array_map(function($turn) {
                return [
                    'user' => $turn['user'] ?? 'N/A',
                    'assistant' => substr($turn['assistant'] ?? 'N/A', 0, 100) . '...',
                    'films' => $turn['films'] ?? []
                ];
            }, array_slice($history, -5))
        ],
        '🎬 Recommended Films' => [
            'total_count' => count($recommended),
            'film_ids' => $recommended,
            'last_10' => array_slice($recommended, -10)
        ],
        '⚙️ Cache Status' => [
            'rdf_cache_exists' => Cache::has('rdf_all_films_v4'),
            'rate_limit_active' => Cache::has('gemini_rate_limited')
        ],
        '🔧 Actions' => [
            'clear_session' => url('/admin/chatbot/clear-cache'),
            'test_chatbot' => url('/test-chatbot-detailed')
        ]
    ]);
});

// 🔬 DETAILED CHATBOT TEST - Step by Step
Route::get('/test-chatbot-detailed', function () {
    try {
        $message = request('message', 'Halo mok, ada film bagus?');
        
        $rdfService = app(\App\Services\RDFService::class);
        $geminiService = app(\App\Services\GeminiService::class);
        
        // Step 1: Get films
        $allFilms = $rdfService->getAllFilms();
        
        $debug = [
            'step1_rdf_data' => [
                'total_films' => count($allFilms),
                'sample_films' => array_slice(array_map(function($f) {
                    return [
                        'id' => $f['imdb_id'],
                        'title' => $f['title'],
                        'rating' => $f['rating'] ?? 'N/A'
                    ];
                }, $allFilms), 0, 5)
            ]
        ];
        
        // Step 2: Get session state
        $history = Session::get('chatbot_history', []);
        $recommended = Session::get('chatbot_recommended_films', []);
        
        $debug['step2_session_state'] = [
            'history_count' => count($history),
            'recommended_count' => count($recommended),
            'recommended_ids' => array_slice($recommended, -10)
        ];
        
        // Step 3: Format context
        $filmContext = array_map(function($film) {
            return [
                'imdb_id' => $film['imdb_id'],
                'title' => $film['title'],
                'year' => $film['year'] ?? '',
                'genre' => is_array($film['genre']) ? $film['genre'] : [$film['genre']],
                'rating' => $film['rating'] ?? '',
                'plot' => isset($film['plot']) ? substr($film['plot'], 0, 150) : ''
            ];
        }, $allFilms);
        
        $debug['step3_context_prepared'] = [
            'total_context' => count($filmContext)
        ];
        
        // Step 4: Call Gemini
        $startTime = microtime(true);
        $aiResponse = $geminiService->chat($message, $filmContext, $history, $recommended);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        $debug['step4_gemini_response'] = [
            'duration_ms' => $duration,
            'raw_response' => $aiResponse,
            'response_length' => strlen($aiResponse)
        ];
        
        // Step 5: Extract films
        $extractedIds = $geminiService->extractFilmIds($aiResponse);
        
        $debug['step5_extracted_films'] = [
            'found_ids' => $extractedIds,
            'count' => count($extractedIds)
        ];
        
        // Step 6: Validate
        $allImdbIds = array_column($allFilms, 'imdb_id');
        $validated = $geminiService->validateResponse($aiResponse, $allImdbIds);
        
        $debug['step6_validation'] = [
            'valid_ids' => $validated['valid_ids'],
            'cleaned_response' => $validated['response']
        ];
        
        // Step 7: Get film details
        $recommendedFilms = [];
        foreach ($validated['valid_ids'] as $imdbId) {
            foreach ($allFilms as $film) {
                if ($film['imdb_id'] === $imdbId) {
                    $recommendedFilms[] = [
                        'imdb_id' => $film['imdb_id'],
                        'title' => $film['title'],
                        'rating' => $film['rating'] ?? 'N/A',
                        'year' => $film['year'] ?? 'N/A'
                    ];
                    break;
                }
            }
        }
        
        $debug['step7_final_films'] = $recommendedFilms;
        
        // Step 8: Check logs
        $logFile = storage_path('logs/laravel.log');
        $lastLogs = [];
        if (file_exists($logFile)) {
            $logs = file($logFile);
            $lastLogs = array_slice($logs, -20);
        }
        
        $debug['step8_recent_logs'] = array_map('trim', $lastLogs);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Detailed debug complete',
            'user_message' => $message,
            'debug_steps' => $debug,
            '🚨 POTENTIAL_ISSUES' => [
                'same_films_recommended' => count(array_intersect($extractedIds, $recommended)) > 0 ? '⚠️ YES - AI recommending already suggested films!' : '✅ NO',
                'no_films_extracted' => empty($extractedIds) ? '⚠️ YES - No [FILM:xxx] found in response' : '✅ NO',
                'rate_limited' => Cache::has('gemini_rate_limited') ? '⚠️ YES - Using fallback' : '✅ NO',
                'empty_context' => empty($filmContext) ? '⚠️ YES - No films in database' : '✅ NO'
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// 🧪 TEST GEMINI RAW - No history, no filter
Route::get('/test-gemini-raw', function () {
    try {
        $apiKey = config('gemini.api_key');
        $message = request('message', 'Rekomendasiin film action dong');
        
        $prompt = "Kamu asisten film. User bilang: '{$message}'\nKasih 3 rekomendasi format: [FILM:tt1234567]\nFilm: Breaking Bad [FILM:tt0903747], The Wire [FILM:tt0306414], Narcos [FILM:tt2707408]";
        
        $response = Http::timeout(30)->post(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey,
            [
                'contents' => [[
                    'parts' => [['text' => $prompt]]
                ]],
                'generationConfig' => [
                    'temperature' => 1.0,
                    'maxOutputTokens' => 500
                ]
            ]
        );
        
        if (!$response->successful()) {
            return response()->json([
                'error' => 'Gemini failed',
                'status' => $response->status(),
                'body' => $response->json()
            ], 500);
        }
        
        $data = $response->json();
        $aiText = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'No response';
        
        return response()->json([
            'status' => 'success',
            'user_message' => $message,
            'prompt_sent' => $prompt,
            'ai_response' => $aiText,
            'full_data' => $data
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::get('/debug-models', function () {
    $apiKey = config('gemini.api_key'); // Pastikan ini mengambil key yang benar
    
    $response = Http::get("https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}");
    
    return $response->json();
});

// 📊 DATABASE STATS - Show AI knowledge coverage
Route::get('/database-stats', function () {
    try {
        $rdfService = app(\App\Services\RDFService::class);
        $stats = $rdfService->getStats();
        
        return response()->json([
            '📊 Database Statistics' => $stats,
            '🤖 AI Coverage' => [
                'can_answer' => 'YES - All ' . $stats['total_films'] . ' films',
                'search_capability' => 'Keyword matching across title, genre, director, cast, plot',
                'recommendation_engine' => 'Smart scoring + genre/mood detection',
                'auto_scale' => 'Will auto-adapt when new films added to RDF'
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});