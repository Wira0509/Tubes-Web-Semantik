<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeminiService
{
    private $apiKey;
    private $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    public function __construct()
    {
        $this->apiKey = config('gemini.api_key');

        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API key is not configured');
        }
    }

    /**
     * Chat with conversation memory
     */
    public function chat($message, $filmContext = [], $conversationHistory = [], $recommendedBefore = [])
    {
        try {
            // 🔍 DEBUG LOG
            Log::channel('single')->info('🔍 GEMINI CHAT START', [
                'user_message' => $message,
                'total_films_available' => count($filmContext),
                'history_turns' => count($conversationHistory),
                'already_recommended' => count($recommendedBefore),
                'recommended_ids' => array_slice($recommendedBefore, -10)
            ]);

            if ($this->isRateLimited()) {
                Log::warning('⚠️ RATE LIMITED - Using fallback');
                return $this->getFallbackResponse($message, $filmContext, $recommendedBefore);
            }

            $optimizedContext = $this->optimizeSmartContext($filmContext, $message, $recommendedBefore);
            
            // 🔍 DEBUG: Log optimized context
            Log::info('📊 CONTEXT OPTIMIZATION', [
                'original_count' => count($filmContext),
                'optimized_count' => count($optimizedContext),
                'excluded_count' => count($recommendedBefore),
                'sample_optimized_films' => array_slice(array_map(fn($f) => [
                    'id' => $f['imdb_id'],
                    'title' => $f['title'],
                    'score' => $f['relevance_score'] ?? 0
                ], $optimizedContext), 0, 5)
            ]);
            
            $systemPrompt = $this->buildConversationalPrompt($optimizedContext, $conversationHistory, $recommendedBefore);

            // 🔍 DEBUG: Log prompt size
            Log::info('📝 PROMPT BUILT', [
                'prompt_length' => strlen($systemPrompt),
                'estimated_tokens' => intval(strlen($systemPrompt) / 4)
            ]);

            $response = Http::timeout(60)
                ->retry(2, 100)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->apiUrl . '?key=' . $this->apiKey, [
                    'contents' => [[
                        'parts' => [['text' => $systemPrompt . "\n\nUser: " . $message . "\n\nAsisten:"]]
                    ]],
                    'generationConfig' => [
                        'temperature' => 1.0, // Creative
                        'topK' => 40,
                        'topP' => 0.95,
                        'maxOutputTokens' => 600,
                    ],
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE']
                    ]
                ]);

            if ($response->status() === 429) {
                $this->markRateLimited();
                return $this->getFallbackResponse($message, $filmContext, $recommendedBefore);
            }

            if (!$response->successful()) {
                Log::error('Gemini API Error', ['status' => $response->status()]);
                if ($response->status() === 400) {
                    throw new \Exception('API_KEY_INVALID');
                }
                return $this->getFallbackResponse($message, $filmContext, $recommendedBefore);
            }

            $data = $response->json();

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $text = trim($data['candidates'][0]['content']['parts'][0]['text']);
                
                // 🔍 DEBUG: Log AI response
                Log::info('✅ GEMINI SUCCESS', [
                    'response_length' => strlen($text),
                    'response_preview' => substr($text, 0, 150),
                    'extracted_film_ids' => $this->extractFilmIds($text)
                ]);
                
                return $text;
            }

            Log::warning('⚠️ INVALID RESPONSE STRUCTURE', [
                'data' => $data
            ]);

            return $this->getFallbackResponse($message, $filmContext, $recommendedBefore);

        } catch (\Exception $e) {
            Log::error('❌ GEMINI ERROR', [
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
     * UPGRADE: Context optimization dengan conversation awareness
     */
    private function optimizeSmartContext($allFilms, $userMessage, $recommendedBefore = [])
    {
        Log::info('🔄 CONTEXT OPTIMIZATION', [
            'message' => substr($userMessage, 0, 50),
            'total_available' => count($allFilms)
        ]);

        // 1. Exclude recommended
        $available = array_filter($allFilms, fn($f) => !in_array($f['imdb_id'], $recommendedBefore));
        
        if (empty($available)) {
            $available = $allFilms;
        }

        // 2. SMART KEYWORD SCORING
        $keywords = explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9 ]/', '', $userMessage)));
        $scored = [];
        
        // Detect if user asking for more/other films
        $isFollowUp = preg_match('/(lain|lagi|selain|yang lain|ada lagi|coba|asih|tunjuk)/i', $userMessage);
        
        foreach ($available as $film) {
            $score = 0;
            
            $title = strtolower($film['title'] ?? '');
            $genres = is_array($film['genre']) ? strtolower(implode(' ', $film['genre'])) : strtolower($film['genre'] ?? '');
            $plot = strtolower($film['plot'] ?? '');
            $year = (string)($film['year'] ?? '');
            
            foreach ($keywords as $kw) {
                if (strlen($kw) < 2) continue;
                
                if ($title === $kw) $score += 100;
                if (str_contains($title, $kw)) $score += 50;
                if (str_contains($genres, $kw)) $score += 40;
                if (str_contains($plot, $kw)) $score += 15;
                if ($year === $kw) $score += 20;
            }
            
            // Bonus for high rating
            $score += floatval($film['rating'] ?? 0) * 2;
            
            // If follow-up, prioritize diversity
            if ($isFollowUp) {
                $score += rand(0, 20); // Add randomness
            }
            
            if ($score > 0) {
                $film['relevance_score'] = $score;
                $scored[] = $film;
            }
        }

        // 3. MOOD/GENRE DETECTION
        if (empty($scored)) {
            $moodMap = [
                'sedih|sad|cry|down' => ['Drama', 'Romance'],
                'senang|happy|fun|ceria' => ['Comedy', 'Animation', 'Family'],
                'tegang|horror|scare|takut' => ['Horror', 'Thriller'],
                'action|fight|war|ledakan' => ['Action', 'Adventure'],
                'romantis|love|romance|cinta' => ['Romance', 'Drama'],
                'anak|kids|family|keluarga' => ['Animation', 'Family'],
                'scifi|space|future' => ['Sci-Fi', 'Fantasy'],
                'taktis|spy|heist|pintar' => ['Thriller', 'Crime'],
            ];
            
            $messageLower = strtolower($userMessage);
            $targetGenres = [];
            
            foreach ($moodMap as $pattern => $genres) {
                if (preg_match("/($pattern)/", $messageLower)) {
                    $targetGenres = array_merge($targetGenres, $genres);
                }
            }
            
            if (!empty($targetGenres)) {
                foreach ($available as $film) {
                    $filmGenres = is_array($film['genre']) ? $film['genre'] : [$film['genre']];
                    
                    foreach ($targetGenres as $target) {
                        if (in_array($target, $filmGenres)) {
                            $film['relevance_score'] = 50 + floatval($film['rating'] ?? 0) * 2;
                            $scored[] = $film;
                            break;
                        }
                    }
                }
            }
        }

        // 4. FALLBACK: Top rated + diversity
        if (empty($scored)) {
            $byRating = $available;
            usort($byRating, fn($a,$b) => floatval($b['rating']??0) <=> floatval($a['rating']??0));
            $scored = array_slice($byRating, 0, 40);
            
            // Add random for diversity
            shuffle($available);
            $random = array_slice($available, 0, 20);
            $scored = array_merge($scored, $random);
        }

        // 5. SORT & DEDUPLICATE
        usort($scored, fn($a,$b) => ($b['relevance_score']??0) <=> ($a['relevance_score']??0));
        
        $unique = [];
        foreach ($scored as $film) {
            if (!isset($unique[$film['imdb_id']])) {
                $unique[$film['imdb_id']] = $film;
            }
        }
        
        // Return 60 films (enough untuk conversation)
        $result = array_slice(array_values($unique), 0, 60);

        Log::info('✅ CONTEXT READY', [
            'final_count' => count($result),
            'top_3_titles' => array_map(fn($f) => $f['title'], array_slice($result, 0, 3))
        ]);

        return $result;
    }

    /**
     * UPGRADE: Context-aware conversational prompt
     */
    private function buildConversationalPrompt($filmContext, $conversationHistory, $recommendedBefore)
    {
        $total = count($filmContext);
        $recCount = count($recommendedBefore);
        $turnCount = count($conversationHistory);

        $prompt = "🎬 ASISTEN FILM PINTAR - Kamu teman ngobrol film yang asik\n\n";
        
        $prompt .= "KEPRIBADIAN:\n";
        $prompt .= "- Ramah, santai, kayak teman dekat\n";
        $prompt .= "- Ga buru-buru kasih rekomendasi\n";
        $prompt .= "- Suka tanya balik untuk pahami preferensi user\n";
        $prompt .= "- Kasih rekomendasi bertahap (2-3 film max per turn)\n";
        $prompt .= "- Fokus ke konteks pembicaraan sebelumnya\n\n";
        
        $prompt .= "CARA KERJA:\n";
        $prompt .= "1. BACA HISTORY - Pahami apa yang udah dibahas\n";
        $prompt .= "2. PAHAMI INTENT - User mau apa? Info film? Rekomendasi? Ngobrol?\n";
        $prompt .= "3. TANYA KLARIFIKASI - Kalau belum jelas, tanya dulu\n";
        $prompt .= "4. KASIH REKOMENDASI - Max 2-3 film, dengan alasan\n";
        $prompt .= "5. FOLLOW UP - Tanya pendapat atau mau lanjut?\n\n";
        
        $prompt .= "ATURAN PENTING:\n";
        $prompt .= "❌ JANGAN langsung bombardir banyak film\n";
        $prompt .= "❌ JANGAN rekomendasiin film yang sama\n";
        $prompt .= "❌ JANGAN sebut judul di text (card auto-muncul)\n";
        $prompt .= "✅ TANYA mood/genre dulu kalau user vague\n";
        $prompt .= "✅ Kasih alasan KENAPA film cocok\n";
        $prompt .= "✅ Max 2-3 film per response\n\n";

        // CONVERSATION CONTEXT
        if (!empty($conversationHistory)) {
            $prompt .= "📚 KONTEKS PERCAKAPAN (Baca ini baik-baik!):\n";
            
            $lastTurns = array_slice($conversationHistory, -3);
            foreach ($lastTurns as $i => $turn) {
                $turnNum = $turnCount - count($lastTurns) + $i + 1;
                $userMsg = $turn['user'] ?? '';
                $yourMsg = $turn['assistant'] ?? '';
                $films = $turn['films'] ?? [];
                
                $prompt .= sprintf(
                    "Turn %d:\n  User: \"%s\"\n  Kamu: \"%s\"%s\n\n",
                    $turnNum,
                    substr($userMsg, 0, 80),
                    substr($yourMsg, 0, 80),
                    !empty($films) ? "\n  Films: " . implode(', ', $films) : ""
                );
            }
            
            $prompt .= "👉 PENTING: Lanjutkan percakapan ini dengan natural!\n\n";
        } else {
            $prompt .= "📚 KONTEKS: Ini percakapan pertama dengan user.\n\n";
        }

        // RECOMMENDED FILMS
        if ($recCount > 0) {
            $prompt .= "🚫 FILM YANG UDAH DIREKOMENDASIIN (JANGAN ULANGI):\n";
            $prompt .= implode(', ', array_slice($recommendedBefore, -15)) . "\n\n";
        }

        // FILM DATABASE
        $prompt .= "🎥 DATABASE ({$total} film paling relevan):\n";
        
        foreach (array_slice($filmContext, 0, 50) as $i => $f) {
            $genre = is_array($f['genre']) ? implode('/', array_slice($f['genre'], 0, 2)) : ($f['genre'] ?? '');
            $year = $f['year'] ?? '?';
            $rating = number_format($f['rating'] ?? 0, 1);
            
            $prompt .= sprintf(
                "%d.[%s]%s(%s)★%s|%s\n",
                $i+1,
                $f['imdb_id'],
                substr($f['title'], 0, 18),
                $year,
                $rating,
                substr($genre, 0, 15)
            );
        }

        $prompt .= "\n💬 CONTOH RESPONS YANG BAIK:\n\n";
        
        $prompt .= "❌ SALAH (Langsung bombardir):\n";
        $prompt .= "\"Ini ada banyak film bagus: [FILM:tt1][FILM:tt2][FILM:tt3][FILM:tt4][FILM:tt5]\"\n\n";
        
        $prompt .= "✅ BENAR (Natural & bertahap):\n";
        $prompt .= "User: \"pengen film action\"\n";
        $prompt .= "Kamu: \"Action ya! Mau yang gimana? Ada yang penuh ledakan kayak Fast & Furious, atau yang taktis kayak Mission Impossible?\"\n\n";
        
        $prompt .= "User: \"yang taktis\"\n";
        $prompt .= "Kamu: \"Oke! Coba dua ini deh, keduanya seru banget - film pertama punya stunts gila, yang kedua lebih ke spy thriller: [FILM:tt0120755][FILM:tt0258463]\"\n\n";
        
        $prompt .= "User: \"ada yang lain?\"\n";
        $prompt .= "Kamu: \"Ada! Kalau suka yang tadi, ini juga cocok - film heist yang cerdas: [FILM:tt0468569] Atau mau genre lain?\"\n\n";
        
        $prompt .= "---\n";
        $prompt .= "FORMAT: Jawaban natural (1-2 kalimat) + max 2-3 [FILM:imdb_id]\n";
        
        return $prompt;
    }

    /**
     * Fallback Response Logic
     */
    private function getFallbackResponse($message, $filmContext, $recommendedBefore = [])
    {
        // 🔍 DEBUG LOG
        Log::warning('🔄 USING FALLBACK RESPONSE', [
            'reason' => 'Gemini unavailable or rate limited',
            'available_films' => count($filmContext),
            'excluded_films' => count($recommendedBefore)
        ]);

        // 1. Filter film yang belum direkomendasikan
        $availableFilms = array_filter($filmContext, function($film) use ($recommendedBefore) {
            return !in_array($film['imdb_id'], $recommendedBefore);
        });

        if (empty($availableFilms)) {
            $availableFilms = $filmContext;
        }

        $message = strtolower($message);
        $recommendations = [];
        
        $moodMap = [
            'sedih' => ['genre' => ['Drama'], 'limit' => 3],
            'senang' => ['genre' => ['Comedy', 'Animation'], 'limit' => 3],
            'tegang' => ['genre' => ['Thriller', 'Horror'], 'limit' => 3],
            'romantis' => ['genre' => ['Romance'], 'limit' => 3],
            'action' => ['genre' => ['Action'], 'limit' => 3],
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

        // Default: Top Rated
        if (empty($recommendations)) {
            $sorted = $availableFilms;
            usort($sorted, fn($a, $b) => floatval($b['rating'] ?? 0) <=> floatval($a['rating'] ?? 0));
            $recommendations = array_slice($sorted, 0, 3);
        }

        $responses = [
            "Jaringan AI lagi sibuk, tapi nih aku pilihin manual yang bagus:",
            "Cek film-film keren ini deh:",
            "Rekomendasi spesial buat kamu:"
        ];
        
        $response = $responses[array_rand($responses)] . "\n\n";
        foreach ($recommendations as $film) {
            $response .= "[FILM:" . $film['imdb_id'] . "]";
        }

        Log::info('✅ FALLBACK RESPONSE BUILT', [
            'recommended_count' => count($recommendations),
            'recommended_ids' => array_map(fn($f) => $f['imdb_id'], $recommendations)
        ]);
        
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

        // Hapus ID invalid dari teks response
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