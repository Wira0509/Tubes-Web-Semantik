<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GeminiService;
use App\Services\RDFService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        
        Log::info('Chatbot Request Received', ['message' => $message]);
        
        try {
            $allFilms = $this->rdfService->getAllFilms();
            
            Log::info('Films fetched from RDF', ['count' => count($allFilms)]);
            
            if (empty($allFilms)) {
                Log::warning('No films found in RDF');
                return response()->json([
                    'message' => 'Maaf, database film sedang kosong.',
                    'films' => []
                ]);
            }
            
            // Sort by rating
            usort($allFilms, function($a, $b) {
                return floatval($b['rating'] ?? 0) <=> floatval($a['rating'] ?? 0);
            });
            
            // Ambil 50 top rated + 50 random untuk diversity
            $topFilms = array_slice($allFilms, 0, 50);
            $randomFilms = [];
            
            $remaining = array_slice($allFilms, 50);
            if (count($remaining) > 0) {
                shuffle($remaining);
                $randomFilms = array_slice($remaining, 0, 50);
            }
            
            $selectedFilms = array_merge($topFilms, $randomFilms);
            $limitedFilms = array_slice($selectedFilms, 0, 100);
            
            $filmContext = array_map(function($film) {
                return [
                    'imdb_id' => $film['imdb_id'],
                    'title' => $film['title'],
                    'year' => $film['year'] ?? '',
                    'genre' => is_array($film['genre']) ? implode(', ', array_slice($film['genre'], 0, 3)) : $film['genre'],
                    'rating' => $film['rating'] ?? '',
                    'plot' => isset($film['plot']) ? substr($film['plot'], 0, 60) : ''
                ];
            }, $limitedFilms);
            
            Log::info('Film context prepared', ['total_films' => count($filmContext)]);
            
            // Dapatkan response dari Gemini
            $aiResponse = $this->geminiService->chat($message, $filmContext);
            
            Log::info('Gemini Response Received', ['length' => strlen($aiResponse)]);
            
            // Validasi response
            $validImdbIds = array_column($filmContext, 'imdb_id');
            $validated = $this->geminiService->validateResponse($aiResponse, $validImdbIds);
            
            // Ambil detail film yang direkomendasikan
            $recommendedFilms = [];
            foreach ($validated['valid_ids'] as $imdbId) {
                foreach ($allFilms as $film) {
                    if ($film['imdb_id'] === $imdbId) {
                        $recommendedFilms[] = [
                            'imdb_id' => $film['imdb_id'],
                            'title' => $film['title'],
                            'poster' => $film['poster'] ?? '/images/no-poster.jpg',
                            'rating' => $film['rating'] ?? 'N/A',
                            'year' => $film['year'] ?? 'N/A'
                        ];
                        break;
                    }
                }
            }
            
            // Bersihkan response
            $cleanResponse = preg_replace('/\[FILM:tt\d+\]/', '', $validated['response']);
            $cleanResponse = $this->sanitizeResponse($cleanResponse, $filmContext);
            $cleanResponse = $this->cleanMarkdown($cleanResponse);
            
            return response()->json([
                'message' => trim($cleanResponse),
                'films' => $recommendedFilms
            ]);
            
        } catch (\Exception $e) {
            Log::error('Chatbot Error Details', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 500)
            ]);
            
            return response()->json([
                'message' => 'Maaf, ada kendala teknis. Coba lagi ya! 😊',
                'films' => [],
                'error_debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    private function sanitizeResponse($response, $filmContext)
    {
        $filmTitles = array_column($filmContext, 'title');
        
        $commonLeaks = [
            'Inception', 'Interstellar', 'Avatar', 'Titanic', 'The Godfather',
            'The Shawshank Redemption', 'Pulp Fiction', 'The Dark Knight',
            'Fight Club', 'Forrest Gump', 'The Matrix', 'Goodfellas'
        ];
        
        foreach ($commonLeaks as $leak) {
            if (!in_array($leak, $filmTitles)) {
                $response = preg_replace("/\b$leak\b/i", "[film tidak tersedia]", $response);
            }
        }
        
        return $response;
    }

    private function cleanMarkdown($text)
    {
        $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
        $text = preg_replace('/__(.+?)__/', '$1', $text);
        $text = preg_replace('/\*(.+?)\*/', '$1', $text);
        $text = preg_replace('/_(.+?)_/', '$1', $text);
        $text = preg_replace('/`(.+?)`/', '$1', $text);
        $text = preg_replace('/^\s*[\*\-\+]\s+/m', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        return trim($text);
    }

    public function clearCache()
    {
        Cache::forget('film_context');
        return response()->json(['message' => 'Cache cleared successfully']);
    }
}
