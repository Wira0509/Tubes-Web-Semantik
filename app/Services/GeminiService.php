<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private $apiKey;
    private $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');
        
        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured');
        }
    }

    public function chat($message, $filmContext = [])
    {
        try {
            $systemPrompt = $this->buildSystemPrompt($filmContext);
            
            Log::info('Calling Gemini API', [
                'message_length' => strlen($message),
                'context_count' => count($filmContext),
                'prompt_length' => strlen($systemPrompt)
            ]);
            
            $response = Http::timeout(60)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '?key=' . $this->apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\nUser: " . $message]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 1.0,  // Max creativity
                    'topK' => 64,
                    'topP' => 0.95,
                    'maxOutputTokens' => 8192,  // Max output
                ]
            ]);

            if (!$response->successful()) {
                Log::error('Gemini API Failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Gemini API request failed: ' . $response->body());
            }

            $data = $response->json();
            
            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                Log::error('Invalid Gemini Response Structure', ['data' => $data]);
                throw new \Exception('Invalid Gemini API response structure');
            }

            $text = $data['candidates'][0]['content']['parts'][0]['text'];
            Log::info('Gemini Response Success', ['response_length' => strlen($text)]);
            
            return $text;
            
        } catch (\Exception $e) {
            Log::error('GeminiService Error', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    private function buildSystemPrompt($filmContext)
    {
        $prompt = "Kamu Asisten TetengFilm. Database: " . count($filmContext) . " film.\n\n";
        
        // Compact format - lebih ringkas
        $prompt .= "FILM:\n";
        foreach ($filmContext as $idx => $film) {
            $genres = is_array($film['genre']) ? implode('/', $film['genre']) : $film['genre'];
            $prompt .= sprintf(
                "%d.[%s]%s(%s)⭐%s|%s\n",
                $idx + 1,
                $film['imdb_id'],
                substr($film['title'], 0, 30),
                $film['year'] ?? '?',
                $film['rating'] ?? '?',
                $genres ?? '?'
            );
        }
        
        $prompt .= "\nATURAN:\n";
        $prompt .= "1. Tanya balik jika perlu\n";
        $prompt .= "2. Rekomendasi 2-3 film BERBEDA tiap kali\n";
        $prompt .= "3. HANYA dari list di atas\n";
        $prompt .= "4. Tulis natural tanpa markdown\n";
        $prompt .= "5. Format: [FILM:imdb_id]\n";
        $prompt .= "6. Variasi genre & tahun\n\n";
        
        $prompt .= "Contoh:\nUser: sedih\nKamu: Mau yang relate atau nghibur?\n\n";
        
        return $prompt;
    }

    /**
     * Group films by genre untuk memudahkan AI pahami diversity
     */
    private function groupFilmsByGenre($films)
    {
        $groups = [];
        
        foreach ($films as $film) {
            $genres = is_array($film['genre']) ? $film['genre'] : [$film['genre']];
            
            foreach ($genres as $genre) {
                if (empty($genre)) continue;
                
                if (!isset($groups[$genre])) {
                    $groups[$genre] = [];
                }
                $groups[$genre][] = $film;
            }
        }
        
        return $groups;
    }

    public function extractFilmIds($response)
    {
        preg_match_all('/\[FILM:(tt\d+)\]/', $response, $matches);
        return $matches[1] ?? [];
    }

    public function validateResponse($response, $validImdbIds)
    {
        $extractedIds = $this->extractFilmIds($response);
        
        $validIds = array_filter($extractedIds, function($id) use ($validImdbIds) {
            return in_array($id, $validImdbIds);
        });
        
        if (count($extractedIds) !== count($validIds)) {
            foreach ($extractedIds as $id) {
                if (!in_array($id, $validImdbIds)) {
                    $response = str_replace("[FILM:$id]", "", $response);
                }
            }
        }
        
        return [
            'response' => $response,
            'valid_ids' => array_values($validIds)
        ];
    }
}