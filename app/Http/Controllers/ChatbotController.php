<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GeminiService;
use App\Services\RDFService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class ChatbotController extends Controller
{
    protected $geminiService;
    protected $rdfService;

    public function __construct(GeminiService $geminiService, RDFService $rdfService)
    {
        $this->geminiService = $geminiService;
        $this->rdfService = $rdfService;
    }

    public function recommend(Request $request)
    {
        $message = $request->input('message');
        $requestId = uniqid('chat_');
        
        Log::channel('single')->info("🚀 CHATBOT REQUEST [{$requestId}]", [
            'message' => $message
        ]);
        
        try {
            $allFilms = Cache::remember('rdf_all_films_v5', 3600, function () {
                return $this->rdfService->getAllFilms();
            });
            
            if (empty($allFilms)) {
                return response()->json([
                    'message' => 'Database film belum ready. Coba lagi ya!',
                    'films' => []
                ]);
            }
            
            $conversationHistory = Session::get('chatbot_history', []);
            $recommendedBefore = Session::get('chatbot_recommended_films', []);
            
            Log::info("📊 SESSION [{$requestId}]", [
                'turns' => count($conversationHistory),
                'recommended_count' => count($recommendedBefore)
            ]);
            
            // UPGRADE: Detect conversation intent
            $intent = $this->detectIntent($message, $conversationHistory);
            
            Log::info("🎯 INTENT DETECTED [{$requestId}]", $intent);
            
            // Handle specific film query
            if ($intent['type'] === 'film_query') {
                $filmCheck = $this->detectFilmQuery($message, $allFilms);
                
                if ($filmCheck['is_film_query'] && $filmCheck['found_film']) {
                    $film = $filmCheck['found_film'];
                    
                    $responseText = sprintf(
                        "Ada! %s (%s) ★%s. %s Mau aku kasih info lebih atau cari yang mirip?",
                        $film['title'],
                        $film['year'] ?? '?',
                        $film['rating'] ?? '?',
                        is_array($film['genre']) ? implode(', ', array_slice($film['genre'], 0, 2)) . '.' : ''
                    );
                    
                    $recommendedFilms = [[
                        'imdb_id' => $film['imdb_id'],
                        'title' => $film['title'],
                        'poster' => $film['poster'] ?? '/images/no-poster.jpg',
                        'rating' => $film['rating'] ?? 'N/A',
                        'year' => $film['year'] ?? 'N/A',
                        'genre' => is_array($film['genre']) ? implode(', ', array_slice($film['genre'], 0, 2)) : $film['genre']
                    ]];
                    
                    $conversationHistory[] = [
                        'user' => $message,
                        'assistant' => $responseText,
                        'films' => [$film['imdb_id']],
                        'intent' => 'film_query'
                    ];
                    
                    Session::put('chatbot_history', array_slice($conversationHistory, -10));
                    
                    return response()->json([
                        'message' => $responseText,
                        'films' => $recommendedFilms
                    ]);
                } elseif ($filmCheck['is_film_query'] && !$filmCheck['found_film']) {
                    // Film not found
                    $responseText = sprintf(
                        "Hmm, kayaknya %s nggak ada di database kami deh. Mau aku cariin yang mirip atau genre lain?",
                        $filmCheck['search_term']
                    );
                    
                    $conversationHistory[] = [
                        'user' => $message,
                        'assistant' => $responseText,
                        'films' => [],
                        'intent' => 'film_not_found'
                    ];
                    
                    Session::put('chatbot_history', array_slice($conversationHistory, -10));
                    
                    return response()->json([
                        'message' => $responseText,
                        'films' => []
                    ]);
                }
            }
            
            // Handle greeting
            if ($intent['type'] === 'greeting') {
                $responses = [
                    "Halo! 👋 Lagi cari film apa nih? Atau mau cerita mood kamu gimana?",
                    "Hai! Seneng bisa bantu. Pengen nonton film tapi bingung pilih yang mana?",
                    "Halo! Ada yang bisa aku bantu? Mau rekomendasi film atau tanya-tanya dulu?"
                ];
                
                $responseText = $responses[array_rand($responses)];
                
                $conversationHistory[] = [
                    'user' => $message,
                    'assistant' => $responseText,
                    'films' => [],
                    'intent' => 'greeting'
                ];
                
                Session::put('chatbot_history', array_slice($conversationHistory, -10));
                
                return response()->json([
                    'message' => $responseText,
                    'films' => []
                ]);
            }
            
            // Handle main conversation with AI
            $filmContext = array_map(function($film) {
                return [
                    'imdb_id' => $film['imdb_id'],
                    'title' => $film['title'],
                    'year' => $film['year'] ?? '',
                    'genre' => is_array($film['genre']) ? $film['genre'] : [$film['genre']],
                    'rating' => $film['rating'] ?? '',
                    'plot' => isset($film['plot']) ? substr($film['plot'], 0, 200) : '',
                ];
            }, $allFilms);
            
            // Get AI response
            $aiResponse = $this->geminiService->chat(
                $message, 
                $filmContext, 
                $conversationHistory,
                $recommendedBefore
            );
            
            // Extract & validate
            $allImdbIds = array_column($allFilms, 'imdb_id');
            $extractedIds = $this->geminiService->extractFilmIds($aiResponse);
            
            $recommendedFilms = [];
            
            if (!empty($extractedIds)) {
                $validated = $this->geminiService->validateResponse($aiResponse, $allImdbIds);
                
                foreach ($validated['valid_ids'] as $imdbId) {
                    foreach ($allFilms as $film) {
                        if ($film['imdb_id'] === $imdbId) {
                            $recommendedFilms[] = [
                                'imdb_id' => $film['imdb_id'],
                                'title' => $film['title'],
                                'poster' => $film['poster'] ?? '/images/no-poster.jpg',
                                'rating' => $film['rating'] ?? 'N/A',
                                'year' => $film['year'] ?? 'N/A',
                                'genre' => is_array($film['genre']) ? implode(', ', array_slice($film['genre'], 0, 2)) : $film['genre']
                            ];
                            break;
                        }
                    }
                }
                
                $cleanResponse = preg_replace('/\[FILM:tt\d+\]/', '', $validated['response']);
            } else {
                $cleanResponse = $aiResponse;
                $validated = ['valid_ids' => []];
            }
            
            $cleanResponse = preg_replace('/\s+/', ' ', $cleanResponse);
            $cleanResponse = trim($cleanResponse);
            
            // Save conversation with intent
            $conversationHistory[] = [
                'user' => $message,
                'assistant' => $cleanResponse,
                'films' => $validated['valid_ids'] ?? [],
                'intent' => $intent['type']
            ];
            
            if (count($conversationHistory) > 10) {
                $conversationHistory = array_slice($conversationHistory, -10);
            }
            
            if (!empty($validated['valid_ids'])) {
                $recommendedBefore = array_unique(array_merge($recommendedBefore, $validated['valid_ids']));
                
                if (count($recommendedBefore) > 30) {
                    $recommendedBefore = array_slice($recommendedBefore, -30);
                }
            }
            
            Session::put('chatbot_history', $conversationHistory);
            Session::put('chatbot_recommended_films', $recommendedBefore);
            
            Log::info("✅ COMPLETE [{$requestId}]", [
                'films' => count($recommendedFilms),
                'intent' => $intent['type']
            ]);
            
            return response()->json([
                'message' => $cleanResponse,
                'films' => $recommendedFilms
            ]);
            
        } catch (\Exception $e) {
            Log::error("❌ ERROR [{$requestId}]", [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'message' => $e->getMessage(),
                'films' => []
            ], 500);
        }
    }

    /**
     * UPGRADE: Detect conversation intent
     */
    private function detectIntent($message, $conversationHistory)
    {
        $messageLower = strtolower($message);
        
        // Greeting patterns
        if (preg_match('/(^halo|^hai|^hi|^hello|^hy|^hey)/i', $messageLower)) {
            return ['type' => 'greeting', 'confidence' => 0.9];
        }
        
        // Film query patterns
        if (preg_match('/(ada (film )?|punya (film )?|tau (film )?|film .+ ada)/i', $messageLower)) {
            return ['type' => 'film_query', 'confidence' => 0.85];
        }
        
        // Follow-up request (asking for more)
        if (preg_match('/(yang lain|ada lagi|selain|coba (asih|tunjuk)|lanjut)/i', $messageLower)) {
            return ['type' => 'follow_up', 'confidence' => 0.8];
        }
        
        // Mood/preference
        if (preg_match('/(lagi |pengen |mau |suka |mood )/i', $messageLower)) {
            return ['type' => 'mood_preference', 'confidence' => 0.75];
        }
        
        // Generic recommendation request
        if (preg_match('/(rekomen|saran|cari|pilih|bagus)/i', $messageLower)) {
            return ['type' => 'recommendation_request', 'confidence' => 0.7];
        }
        
        // Default
        return ['type' => 'general', 'confidence' => 0.5];
    }

    /**
     * UPGRADE: Detect if user asking about specific film
     */
    private function detectFilmQuery($message, $allFilms)
    {
        $messageLower = strtolower($message);
        
        // Common question patterns
        $patterns = [
            'ada (film )?(.+?)\??$',
            'punya (film )?(.+?)\??$',
            'tau (film )?(.+?)\??$',
            'kenal (film )?(.+?)\??$',
            'film (.+?) ada( ga| gak| nggak| tidak)?\??$'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match("/$pattern/i", $messageLower, $matches)) {
                $searchTerm = trim(end($matches));
                
                // Search in database
                foreach ($allFilms as $film) {
                    $titleLower = strtolower($film['title']);
                    
                    if (str_contains($titleLower, $searchTerm) || $titleLower === $searchTerm) {
                        return [
                            'is_film_query' => true,
                            'found_film' => $film,
                            'search_term' => $searchTerm
                        ];
                    }
                }
                
                return [
                    'is_film_query' => true,
                    'found_film' => null,
                    'search_term' => $searchTerm
                ];
            }
        }
        
        return ['is_film_query' => false];
    }

    public function clearCache()
    {
        Cache::forget('rdf_all_films_v5');
        Cache::forget('gemini_rate_limited');
        Session::forget('chatbot_history');
        Session::forget('chatbot_recommended_films');
        Cache::flush();
        
        return response()->json(['message' => 'Cache & history cleared successfully']);
    }
}
