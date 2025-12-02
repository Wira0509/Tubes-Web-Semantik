<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeminiService
{
    private $apiKey;
    // Menggunakan Gemini 2.0 Flash sesuai request
    private $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');

        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured');
        }
    }

    /**
     * Chat with intent awareness
     */
    public function chat($message, $filmContext = [], $conversationHistory = [], $recommendedBefore = [], $intent = null)
    {
        try {
            Log::info('🔍 GEMINI CHAT START', [
                'message_preview' => substr($message, 0, 50),
                'intent' => $intent['type'] ?? 'unknown',
            ]);

            if ($this->isRateLimited()) {
                Log::warning('⚠️ Rate limited locally, using fallback');
                return $this->getFallbackResponse($message, $filmContext, $recommendedBefore);
            }

            $optimizedContext = $this->optimizeSmartContext($filmContext, $message, $recommendedBefore);
            $systemPrompt = $this->buildConversationalPrompt($optimizedContext, $conversationHistory, $recommendedBefore);

            Log::info('📝 PROMPT BUILT', [
                'prompt_length' => strlen($systemPrompt),
                'context_count' => count($optimizedContext)
            ]);

            // UPGRADE: Increase output tokens for complete responses
            $response = Http::timeout(60)
                ->retry(2, 100)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->apiUrl . '?key=' . $this->apiKey, [
                    'contents' => [[
                        'parts' => [['text' => $systemPrompt . "\n\nUser: " . $message . "\n\nAsisten:"]]
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.7, // LOWER for more focused reasoning
                        'topK' => 40,
                        'topP' => 0.9,
                        'maxOutputTokens' => 2000, // INCREASE from 1500
                        'stopSequences' => ["\n\nUser:", "\n\n---"]
                    ],
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
                    ]
                ]);

            // 6. Handle Response Errors
            if ($response->status() === 429) {
                $this->markRateLimited();
                Log::warning('⚠️ Google Rate Limit Hit (429)');
                return $this->getFallbackResponse($message, $filmContext, $recommendedBefore);
            }

            if (!$response->successful()) {
                Log::error('❌ Gemini API Failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                if ($response->status() === 400) {
                    throw new \Exception('API_KEY_INVALID');
                }
                return $this->getFallbackResponse($message, $filmContext, $recommendedBefore);
            }

            // 7. Extract Text
            $data = $response->json();

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $text = trim($data['candidates'][0]['content']['parts'][0]['text']);
                
                Log::info('✅ GEMINI SUCCESS', [
                    'response_length' => strlen($text)
                ]);
                
                return $text;
            }

            Log::warning('⚠️ Invalid Response Structure', ['data' => $data]);
            return $this->getFallbackResponse($message, $filmContext, $recommendedBefore);

        } catch (\Exception $e) {
            Log::error('❌ GEMINI EXCEPTION', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            if (strpos($e->getMessage(), 'API_KEY_INVALID') !== false) {
                throw new \Exception($this->getUserFriendlyError('API_KEY_INVALID'));
            }
            
            return $this->getFallbackResponse($message, $filmContext, $recommendedBefore);
        }
    }

    /**
     * UPGRADE: Plot-aware context optimization
     */
    private function optimizeSmartContext($allFilms, $userMessage, $recommendedBefore = [])
    {
        Log::info('🔄 INTELLIGENT CONTEXT SEARCH', [
            'message' => substr($userMessage, 0, 50),
            'total_db' => count($allFilms)
        ]);

        // 1. Exclude recommended
        $available = array_filter($allFilms, fn($f) => !in_array($f['imdb_id'], $recommendedBefore));
        if (empty($available)) $available = $allFilms;

        // 2. Extract semantic keywords
        $keywords = $this->extractSemanticKeywords($userMessage);
        
        Log::info('🔍 KEYWORDS EXTRACTED', $keywords);
        
        $scored = [];
        
        foreach ($available as $film) {
            $score = 0;
            
            $title = strtolower($film['title'] ?? '');
            $genres = is_array($film['genre']) ? strtolower(implode(' ', $film['genre'])) : strtolower($film['genre'] ?? '');
            $plot = strtolower($film['plot'] ?? '');
            $director = strtolower($film['director'] ?? '');
            $cast = is_array($film['cast']) ? strtolower(implode(' ', $film['cast'])) : strtolower($film['cast'] ?? '');
            
            // HIGH PRIORITY: Character/Theme matches in plot
            foreach ($keywords['high_priority'] as $kw) {
                if (str_contains($title, $kw)) $score += 150; // Title match = highest
                if (str_contains($plot, $kw)) $score += 100; // Plot match = very high (Woody/Buzz in Toy Story plot)
                if (str_contains($genres, $kw)) $score += 80;
                if (str_contains($cast, $kw)) $score += 40;
            }
            
            // MEDIUM PRIORITY: Mood matches
            foreach ($keywords['medium_priority'] as $kw) {
                if (str_contains($title, $kw)) $score += 50;
                if (str_contains($genres, $kw)) $score += 40;
                if (str_contains($plot, $kw)) $score += 20;
            }

            // LOW PRIORITY: General words
            foreach ($keywords['low_priority'] as $kw) {
                if (str_contains($title, $kw)) $score += 20;
                if (str_contains($plot, $kw)) $score += 15;
                if (str_contains($genres, $kw)) $score += 10;
            }
            
            // Bonus: High rating
            $score += floatval($film['rating'] ?? 0) * 3;
            
            if ($score > 0) {
                $film['relevance_score'] = $score;
                $scored[] = $film;
            }
        }

        // Fallback if no matches
        if (empty($scored)) {
            Log::warning('No keyword matches, using top rated');
            $byRating = $available;
            usort($byRating, fn($a,$b) => floatval($b['rating']??0) <=> floatval($a['rating']??0));
            $scored = array_slice($byRating, 0, 50);
        }

        // Sort by score
        usort($scored, fn($a,$b) => ($b['relevance_score']??0) <=> ($a['relevance_score']??0));
        
        // Deduplicate
        $unique = [];
        foreach ($scored as $film) {
            if (!isset($unique[$film['imdb_id']])) {
                $unique[$film['imdb_id']] = $film;
            }
        }
        
        // Return top 100
        $result = array_slice(array_values($unique), 0, 100);

        Log::info('✅ CONTEXT OPTIMIZED', [
            'result_count' => count($result),
            'top_match' => $result[0]['title'] ?? 'none',
            'top_score' => $result[0]['relevance_score'] ?? 0
        ]);

        return $result;
    }

    /**
     * UPGRADE: Enhanced semantic keyword extraction dengan character detection
     */
    private function extractSemanticKeywords($message)
    {
        $messageLower = strtolower(preg_replace('/[^a-zA-Z0-9 ]/', '', $message));
        $words = explode(' ', $messageLower);
            
        $highPriority = [];
        $mediumPriority = [];
        $lowPriority = [];
        
        // UPGRADE: Character/Theme keywords
        $characterKeywords = ['koboi', 'cowboy', 'astronot', 'astronaut', 'superhero', 'princess', 'robot', 'alien', 'zombie', 'wizard', 'pirate', 'detective'];
        $genreKeywords = ['action', 'comedy', 'drama', 'horror', 'thriller', 'romance', 'scifi', 'fantasy', 'animation', 'cartoon', 'kartun', 'anime', 'family'];
        $typeKeywords = ['mobil', 'car', 'racing', 'balap', 'war', 'perang', 'space', 'monster', 'dinosaur', 'dragon'];
        $moodKeywords = ['sedih', 'senang', 'happy', 'sad', 'scary', 'funny', 'tegang', 'romantic', 'epic', 'dark', 'suram', 'ceria'];
        $stopWords = ['aku', 'saya', 'mau', 'pengen', 'dong', 'nih', 'yang', 'nya', 'aja', 'deh', 'gak', 'nggak', 'film', 'movie', 'nonton', 'ada', 'dia', 'punya', 'kawan', 'teman'];

        foreach ($words as $word) {
            if (strlen($word) < 3 || in_array($word, $stopWords)) continue;
            
            // HIGH: Characters & Genres
            if (in_array($word, $characterKeywords) || in_array($word, $genreKeywords) || in_array($word, $typeKeywords)) {
                $highPriority[] = $word;
            }
            // MEDIUM: Moods
            elseif (in_array($word, $moodKeywords)) {
                $mediumPriority[] = $word;
            }
            // LOW: Other descriptive words
            else {
                $lowPriority[] = $word;
            }
        }

        return [
            'high_priority' => array_unique($highPriority),
            'medium_priority' => array_unique($mediumPriority),
            'low_priority' => array_unique($lowPriority),
        ];
    }

    /**
     * ULTIMATE UPGRADE: Natural Language Understanding Prompt
     */
    private function buildConversationalPrompt($filmContext, $conversationHistory, $recommendedBefore)
    {
        $total = count($filmContext);
        
        $prompt = "🎬 YOU ARE: Expert Film Consultant with HUMAN-LEVEL UNDERSTANDING\n\n";
        
        $prompt .= "CORE CAPABILITIES:\n";
        $prompt .= "1. 🧠 REASONING: You can DEDUCE films from descriptions\n";
        $prompt .= "   Example: 'kartun koboi + astronot' → Think: Toy Story!\n";
        $prompt .= "2. 🔍 SEARCH: Match keywords to film plot/characters\n";
        $prompt .= "3. 💬 NATURAL: Talk like a knowledgeable friend, NOT a robot\n";
        $prompt .= "4. ✅ COMPLETE: ALWAYS finish your sentences properly\n\n";
        
        $prompt .= "RESPONSE GUIDELINES:\n";
        $prompt .= "❌ BAD: 'Kamu suka atau .' ← INCOMPLETE!\n";
        $prompt .= "✅ GOOD: 'Oh! Maksudnya Toy Story ya? Koboi Woody & Buzz Lightyear si astronot! [FILM:tt0114709]'\n";
        $prompt .= "✅ GOOD: 'Hmm, aku ga nemu film yang pas. Bisa kasih detail lebih?'\n\n";
        
        $prompt .= "HOW TO THINK:\n";
        $prompt .= "Step 1: READ user description carefully\n";
        $prompt .= "Step 2: MATCH keywords to film database below\n";
        $prompt .= "Step 3: REASON: 'Oh, kartun + koboi + astronot = likely Toy Story!'\n";
        $prompt .= "Step 4: RESPOND naturally + give [FILM:id]\n\n";
        
        $prompt .= "CONVERSATION CONTEXT:\n";
        $prompt .= "Available films in database: {$total} (most relevant to user query)\n";
        $prompt .= "Format: [FILM:imdb_id] to recommend (max 2-3)\n";
        $prompt .= "NEVER mention film titles in plain text (auto-show as cards)\n\n";

        // Enhanced conversation history
        if (!empty($conversationHistory)) {
            $prompt .= "📚 RECENT CONVERSATION (Use this to understand context!):\n";
            $lastTurns = array_slice($conversationHistory, -4);
            
            foreach ($lastTurns as $i => $turn) {
                $userMsg = $turn['user'] ?? '';
                $yourMsg = $turn['assistant'] ?? '';
                
                $prompt .= sprintf(
                    "Turn %d:\n  User: \"%s\"\n  You: \"%s\"\n\n",
                    $i + 1,
                    substr($userMsg, 0, 120),
                    substr($yourMsg, 0, 120)
                );
            }
        }

        // Films to avoid
        if (!empty($recommendedBefore)) {
            $prompt .= "🚫 ALREADY RECOMMENDED (Don't repeat): " . implode(', ', array_slice($recommendedBefore, -15)) . "\n\n";
        }

        // CRITICAL: Film database with PLOT DESCRIPTIONS
        $prompt .= "🎥 FILM DATABASE ({$total} films - Search by title/plot/characters):\n\n";
        
        foreach (array_slice($filmContext, 0, 100) as $i => $f) {
            $genre = is_array($f['genre']) ? implode(', ', array_slice($f['genre'], 0, 2)) : ($f['genre'] ?? '?');
            $year = $f['year'] ?? '?';
            $rating = number_format($f['rating'] ?? 0, 1);
            
            // UPGRADE: Include plot for better matching
            $plot = !empty($f['plot']) ? ' | Plot: ' . substr($f['plot'], 0, 80) . '...' : '';
            $director = !empty($f['director']) ? ' | Dir: ' . $f['director'] : '';
            
            $prompt .= sprintf(
                "%d. ID:%s | %s (%s) ★%s | %s%s%s\n",
                $i + 1,
                $f['imdb_id'],
                $f['title'],
                $year,
                $rating,
                $genre,
                $director,
                $plot
            );
        }

        $prompt .= "\n🎯 EXAMPLES OF INTELLIGENT RESPONSES:\n\n";
        
        $prompt .= "Example 1 - Description Match:\n";
        $prompt .= "User: 'kartun koboi, punya kawan astronot'\n";
        $prompt .= "You: 'Oh! Itu Toy Story ya! Cerita tentang Woody si koboi dan Buzz Lightyear si astronot mainan. Film klasik Pixar yang seru banget! [FILM:tt0114709]'\n\n";
        
        $prompt .= "Example 2 - Clarification Needed:\n";
        $prompt .= "User: 'film yang seru'\n";
        $prompt .= "You: 'Seru itu luas ya! Maksudnya action yang banyak ledakan, thriller yang bikin tegang, atau comedy yang bikin ketawa? Biar aku cariin yang pas.'\n\n";
        
        $prompt .= "Example 3 - Not Found:\n";
        $prompt .= "User: 'film tentang dinosaurus merah'\n";
        $prompt .= "You: 'Hmm, aku cari di database tapi belum nemu film tentang dinosaurus merah spesifik. Mungkin maksudnya Jurassic Park atau film dinosaurus lain? Atau ada detail tambahan?'\n\n";
        
        $prompt .= "⚠️ CRITICAL RULES:\n";
        $prompt .= "1. ALWAYS complete your sentences (end with . ! ?)\n";
        $prompt .= "2. REASON first, then recommend\n";
        $prompt .= "3. If unsure, ASK for clarification (but be specific)\n";
        $prompt .= "4. Match descriptions to plot/characters in database\n";
        $prompt .= "5. Be conversational, not robotic\n\n";
        
        $prompt .= "NOW RESPOND TO USER:\n";
        
        return $prompt;
    }

    /**
     * Fallback Logic
     */
    private function getFallbackResponse($message, $filmContext, $recommendedBefore = [])
    {
        // Filter used films
        $availableFilms = array_filter($filmContext, function($film) use ($recommendedBefore) {
            return !in_array($film['imdb_id'], $recommendedBefore);
        });

        if (empty($availableFilms)) $availableFilms = $filmContext;

        $message = strtolower($message);
        $recommendations = [];
        
        $moodMap = [
            'sedih' => ['genre' => ['Drama'], 'limit' => 3],
            'senang' => ['genre' => ['Comedy', 'Animation'], 'limit' => 3],
            'tegang' => ['genre' => ['Thriller', 'Horror'], 'limit' => 3],
            'action' => ['genre' => ['Action', 'Adventure'], 'limit' => 3],
            'romantis' => ['genre' => ['Romance'], 'limit' => 3],
            'acak' => ['random' => true, 'limit' => 3]
        ];

        $matched = false;
        foreach ($moodMap as $keyword => $config) {
            if (strpos($message, $keyword) !== false) {
                $matched = true;
                if (isset($config['random'])) {
                    shuffle($availableFilms);
                    $recommendations = array_slice($availableFilms, 0, $config['limit']);
                } else {
                    foreach ($availableFilms as $film) {
                        if (count($recommendations) >= $config['limit']) break;
                        $filmGenres = is_array($film['genre']) ? $film['genre'] : [$film['genre']];
                        foreach ($config['genre'] as $targetGenre) {
                            if (in_array($targetGenre, $filmGenres)) {
                                $recommendations[] = $film;
                                break;
                            }
                        }
                    }
                }
                break;
            }
        }

        if (empty($recommendations)) {
            $sorted = $availableFilms;
            usort($sorted, fn($a, $b) => floatval($b['rating'] ?? 0) <=> floatval($a['rating'] ?? 0));
            $recommendations = array_slice($sorted, 0, 3);
        }

        $responses = [
            "Lagi agak sibuk nih jaringannya, tapi coba cek film keren ini:",
            "Ini rekomendasi spesial pilihan manual buat kamu:",
            "Kayaknya kamu bakal suka film-film ini:"
        ];
        
        $response = $responses[array_rand($responses)] . "\n\n";
        foreach ($recommendations as $film) {
            $response .= "[FILM:" . $film['imdb_id'] . "]";
        }
        
        return $response;
    }

    private function isRateLimited()
    {
        return Cache::has('gemini_rate_limited');
    }

    private function markRateLimited()
    {
        Cache::put('gemini_rate_limited', true, 60);
    }

    private function getUserFriendlyError($technicalError)
    {
        return 'Sistem sedang sibuk. Coba lagi sebentar ya!';
    }

    public function extractFilmIds($response)
    {
        preg_match_all('/\[FILM:(tt\d+)\]/', $response, $matches);
        return $matches[1] ?? [];
    }

    public function validateResponse($response, $validImdbIds)
    {
        $extractedIds = $this->extractFilmIds($response);
        $validIds = array_filter($extractedIds, fn($id) => in_array($id, $validImdbIds));

        foreach ($extractedIds as $id) {
            if (!in_array($id, $validIds)) {
                $response = str_replace("[FILM:$id]", "", $response);
            }
        }

        return [
            'response' => $response,
            'valid_ids' => array_values($validIds)
        ];
    }
}