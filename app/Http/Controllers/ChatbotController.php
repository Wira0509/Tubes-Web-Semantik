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
        
        Log::channel('single')->info("🚀 REQUEST [{$requestId}]", ['message' => $message]);
        
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
            
            // Detect intent with semantic analysis
            $intent = $this->detectIntent($message, $conversationHistory);
            
            Log::info("🎯 INTENT [{$requestId}]", $intent);
            
            // Smart film query handling
            if ($intent['type'] === 'film_query' || preg_match('/(ada|punya|tau).+(film|pilem)/i', $message)) {
                $filmCheck = $this->detectFilmQuery($message, $allFilms);
                
                if ($filmCheck['is_film_query'] && !empty($filmCheck['found_film'])) {
                    $film = $filmCheck['found_film'];
                    $allMatches = $filmCheck['all_matches'] ?? [$film];
                    
                    // Smart response based on match confidence
                    if ($film['match_score'] >= 95 || count($allMatches) == 1) {
                        // High confidence - direct answer
                        $responseText = sprintf(
                            "Ada! %s (%s) ★%s/10\n\n%s\n\nMau aku ceritain lebih detail atau cari yang mirip?",
                            $film['title'],
                            $film['year'] ?? '?',
                            $film['rating'] ?? '?',
                            !empty($film['plot']) ? substr($film['plot'], 0, 150) . '...' : 'Film ' . (is_array($film['genre']) ? implode(', ', array_slice($film['genre'], 0, 2)) : $film['genre'])
                        );
                    } elseif (count($allMatches) > 1) {
                        // Multiple matches - ask clarification
                        $titles = array_map(fn($f) => '• ' . $f['title'] . ' (' . ($f['year'] ?? '?') . ')', array_slice($allMatches, 0, 3));
                        $responseText = sprintf(
                            "Hmm, ada beberapa film mirip '%s':\n\n%s\n\nYang mana nih? Aku tunjukin yang pertama dulu ya:",
                            $filmCheck['search_term'],
                            implode("\n", $titles)
                        );
                    } else {
                        // Fuzzy match
                        $responseText = sprintf(
                            "Kamu maksud '%s' (%s) ya? ★%s\n\n%s",
                            $film['title'],
                            $film['year'] ?? '?',
                            $film['rating'] ?? '?',
                            !empty($film['plot']) ? substr($film['plot'], 0, 120) . '...' : ''
                        );
                    }
                    
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
                        'intent' => 'film_query_found'
                    ];
                    
                    Session::put('chatbot_history', array_slice($conversationHistory, -10));
                    
                    return response()->json([
                        'message' => $responseText,
                        'films' => $recommendedFilms
                    ]);
                } elseif ($filmCheck['is_film_query']) {
                    // Film not found
                    $responseText = sprintf(
                        "Hmm, aku cari '%s' tapi nggak ketemu di database kami 😅\n\nMau aku cariin yang mirip? Atau cerita genre apa yang kamu suka?",
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
            
            // Enhanced film context
            $filmContext = array_map(function($film) {
                return [
                    'imdb_id' => $film['imdb_id'],
                    'title' => $film['title'],
                    'year' => $film['year'] ?? '',
                    'genre' => is_array($film['genre']) ? $film['genre'] : [$film['genre']],
                    'rating' => $film['rating'] ?? '',
                    'plot' => isset($film['plot']) ? substr($film['plot'], 0, 300) : '',
                    'director' => $film['director'] ?? '',
                    'cast' => isset($film['cast']) ? (is_array($film['cast']) ? implode(', ', array_slice($film['cast'], 0, 8)) : $film['cast']) : '',
                    'country' => isset($film['country']) && is_array($film['country']) ? implode(', ', $film['country']) : ''
                ];
            }, $allFilms);
            
            // Get AI response with intent
            $aiResponse = $this->geminiService->chat(
                $message, 
                $filmContext, 
                $conversationHistory,
                $recommendedBefore,
                $intent
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
            
            // Save conversation with topic tracking
            $conversationHistory[] = [
                'user' => $message,
                'assistant' => $cleanResponse,
                'films' => $validated['valid_ids'] ?? [],
                'intent' => $intent['type'],
                'timestamp' => now()->toIso8601String()
            ];
            
            // Increase memory from 10 to 15 turns
            if (count($conversationHistory) > 15) {
                $conversationHistory = array_slice($conversationHistory, -15);
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
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'message' => $e->getMessage(),
                'films' => []
            ], 500);
        }
    }

    /**
     * Advanced film query detection dengan fuzzy matching
     */
    private function detectFilmQuery($message, $allFilms)
    {
        $messageLower = strtolower($message);
        
        $patterns = [
            'ada (film |pilem )?(.+?)\??$',
            'punya (film |pilem )?(.+?)\??$',
            'tau (film |pilem )?(.+?)\??$',
            'kenal (film |pilem )?(.+?)\??$',
            'film (.+?) ada( ga| gak| nggak| tidak)?\??$',
            'ada (gak |ga )?(.+?)\??$',
            '(.+?) ada( ga| gak| nggak)?\??$'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match("/$pattern/i", $messageLower, $matches)) {
                $searchTerm = trim(end($matches));
                
                $stopWords = ['film', 'pilem', 'itu', 'ini', 'nya', 'yang', 'ada', 'ga', 'gak', 'nggak'];
                $searchTerms = explode(' ', $searchTerm);
                
                // PERBAIKAN: Menggunakan array_filter (PHP), bukan array.filter (JS)
                $searchTerms = array_filter($searchTerms, fn($w) => !in_array($w, $stopWords) && strlen($w) > 2);
                
                $cleanSearchTerm = implode(' ', $searchTerms);
                
                if (empty($cleanSearchTerm)) {
                    continue;
                }
                
                Log::info('Film query detected', ['search_term' => $cleanSearchTerm]);
                
                $bestMatches = $this->fuzzySearchFilms($cleanSearchTerm, $allFilms);
                
                if (!empty($bestMatches)) {
                    return [
                        'is_film_query' => true,
                        'found_film' => $bestMatches[0],
                        'all_matches' => $bestMatches,
                        'search_term' => $cleanSearchTerm,
                        'match_type' => $bestMatches[0]['match_type'] ?? 'exact'
                    ];
                }
                
                return [
                    'is_film_query' => true,
                    'found_film' => null,
                    'search_term' => $cleanSearchTerm
                ];
            }
        }
        
        return ['is_film_query' => false];
    }

    private function fuzzySearchFilms($searchTerm, $allFilms)
    {
        $matches = [];
        $searchLower = strtolower($searchTerm);
        
        foreach ($allFilms as $film) {
            $titleLower = strtolower($film['title']);
            $similarity = 0;
            
            // 1. Exact match
            if ($titleLower === $searchLower) {
                $film['match_score'] = 100;
                $film['match_type'] = 'exact';
                $matches[] = $film;
                continue;
            }
            
            // 2. Contains match
            if (str_contains($titleLower, $searchLower)) {
                $film['match_score'] = 90;
                $film['match_type'] = 'contains';
                $matches[] = $film;
                continue;
            }
            
            // 3. Word-by-word match
            $searchWords = explode(' ', $searchLower);
            $titleWords = explode(' ', $titleLower);
            $wordMatches = 0;
            
            foreach ($searchWords as $searchWord) {
                foreach ($titleWords as $titleWord) {
                    if ($searchWord === $titleWord) {
                        $wordMatches += 2;
                    } elseif (str_contains($titleWord, $searchWord) || str_contains($searchWord, $titleWord)) {
                        $wordMatches += 1;
                    }
                }
            }
            
            if ($wordMatches > 0) {
                $film['match_score'] = min(85, 50 + ($wordMatches * 10));
                $film['match_type'] = 'partial';
                $matches[] = $film;
                continue;
            }
            
            // 4. Levenshtein distance (typo tolerance)
            similar_text($titleLower, $searchLower, $similarity);
            
            if ($similarity > 60) {
                $film['match_score'] = (int)$similarity;
                $film['match_type'] = 'fuzzy';
                $matches[] = $film;
            }
        }
        
        usort($matches, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        
        return array_slice($matches, 0, 3);
    }

    private function detectIntent($message, $conversationHistory)
    {
        $messageLower = strtolower($message);
        
        $lastIntent = !empty($conversationHistory) ? ($conversationHistory[count($conversationHistory)-1]['intent'] ?? 'none') : 'none';
        
        // Pattern 1: Greeting
        if (preg_match('/^(halo|hai|hi|hello|hey|hy)\b/i', $messageLower)) {
            return ['type' => 'greeting', 'confidence' => 0.95];
        }
        
        // Pattern 2: Film query
        if (preg_match('/(ada|punya|tau|kenal|cari|tahu)\s+(film|pilem|movie)/i', $messageLower)) {
            return ['type' => 'film_query', 'confidence' => 0.90];
        }
        
        // Pattern 3: Direct film mention
        if (preg_match('/\b(toy story|inception|avatar|matrix|joker|titanic|avengers|spider|batman|superman|ironman)\b/i', $messageLower)) {
            return ['type' => 'film_query', 'confidence' => 0.95];
        }
        
        // Pattern 4: Follow-up
        if (preg_match('/(yang lain|ada lagi|selain|coba|asih|tunjuk|lanjut|lagi dong|masih ada)/i', $messageLower)) {
            if (in_array($lastIntent, ['film_query_found', 'recommendation_request', 'mood_preference'])) {
                return ['type' => 'follow_up', 'confidence' => 0.92, 'context' => 'continuing_recommendation'];
            }
            return ['type' => 'follow_up', 'confidence' => 0.75];
        }
        
        // Pattern 5: Mood/preference
        $moodGenrePatterns = [
            'sedih|sad|cry|galau' => ['type' => 'mood_preference', 'mood' => 'sad', 'confidence' => 0.88],
            'senang|happy|fun|ceria|ketawa' => ['type' => 'mood_preference', 'mood' => 'happy', 'confidence' => 0.88],
            'tegang|horror|scare|takut|horor' => ['type' => 'mood_preference', 'mood' => 'tense', 'confidence' => 0.88],
            'action|fight|war|ledakan|tembak' => ['type' => 'mood_preference', 'mood' => 'action', 'confidence' => 0.88],
            'romantis|love|romance|cinta' => ['type' => 'mood_preference', 'mood' => 'romance', 'confidence' => 0.88],
        ];
        
        foreach ($moodGenrePatterns as $pattern => $result) {
            if (preg_match("/($pattern)/i", $messageLower)) {
                return $result;
            }
        }
        
        // Pattern 6: Recommendation request
        if (preg_match('/(rekomen|saran|cari|pilih|bagus|keren|oke|mantap)/i', $messageLower)) {
            if ($lastIntent === 'greeting') {
                return ['type' => 'recommendation_request', 'confidence' => 0.60, 'need_clarification' => true];
            }
            return ['type' => 'recommendation_request', 'confidence' => 0.75];
        }
        
        // Pattern 7: Question about film details
        if (preg_match('/(tentang|cerita|sinopsis|siapa|kapan|tahun|sutradara|director|pemain|cast)/i', $messageLower)) {
            return ['type' => 'film_info_request', 'confidence' => 0.82];
        }
        
        // Pattern 8: Confirmation/agreement
        if (preg_match('/^(ya|iya|yup|betul|benar|ok|oke|siap|boleh|mau)\b/i', $messageLower)) {
            if (in_array($lastIntent, ['film_query_found', 'film_not_found'])) {
                return ['type' => 'confirmation', 'confidence' => 0.90, 'confirming' => $lastIntent];
            }
            return ['type' => 'confirmation', 'confidence' => 0.70];
        }
        
        return ['type' => 'general', 'confidence' => 0.50];
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