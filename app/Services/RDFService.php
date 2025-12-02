<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RDFService
{
    private $endpoint;

    public function __construct()
    {
        $this->endpoint = env('FUSEKI_ENDPOINT', 'http://localhost:3030/tubeswsfilm/query');
    }

    /**
     * Get all films from RDF database
     */
    public function getAllFilms()
    {
        try {
            // Query yang lebih spesifik berdasarkan output test-rdf
            $query = '
                SELECT DISTINCT ?subject ?predicate ?object
                WHERE {
                    ?subject ?predicate ?object .
                    FILTER(CONTAINS(STR(?subject), "imdb.com/title/"))
                }
            ';

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/sparql-results+json'
                ])
                ->asForm()
                ->post($this->endpoint, [
                    'query' => $query
                ]);

            if (!$response->successful()) {
                Log::error('Fuseki Query Failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();
            
            Log::info('Fuseki Response', [
                'bindings_count' => count($data['results']['bindings'] ?? [])
            ]);

            $films = $this->transformResults($data['results']['bindings'] ?? []);
            
            Log::info('Films transformed', [
                'count' => count($films),
                'sample' => array_slice($films, 0, 2)
            ]);

            return $films;

        } catch (\Exception $e) {
            Log::error('RDFService Error', [
                'method' => 'getAllFilms',
                'error' => $e->getMessage(),
                'endpoint' => $this->endpoint
            ]);
            return [];
        }
    }

    /**
     * Get single film by IMDb ID
     */
    public function getFilmByImdbId($imdbId)
    {
        try {
            $query = '
                SELECT ?subject ?predicate ?object
                WHERE {
                    ?subject ?predicate ?object .
                    FILTER(CONTAINS(STR(?object), "' . $imdbId . '"))
                }
                LIMIT 50
            ';

            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/sparql-results+json'
                ])
                ->asForm()
                ->post($this->endpoint, [
                    'query' => $query
                ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $films = $this->transformResults($data['results']['bindings'] ?? []);
            
            return $films[0] ?? null;

        } catch (\Exception $e) {
            Log::error('RDFService Error', [
                'method' => 'getFilmByImdbId',
                'imdb_id' => $imdbId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Transform SPARQL results to film array
     */
    private function transformResults($bindings)
    {
        if (empty($bindings)) {
            return [];
        }

        // Group by subject (film URI)
        $filmsMap = [];

        foreach ($bindings as $binding) {
            $subject = $binding['subject']['value'] ?? null;
            $predicate = $binding['predicate']['value'] ?? null;
            $object = $binding['object']['value'] ?? null;

            if (!$subject) continue;

            if (!isset($filmsMap[$subject])) {
                $filmsMap[$subject] = [
                    'uri' => $subject,
                    'imdb_id' => $this->extractImdbIdFromUri($subject),
                    'title' => 'Unknown',
                    'year' => null,
                    'rating' => null,
                    'plot' => null,
                    'poster' => null,
                    'genre' => [],
                ];
            }

            // Map predicates to film properties
            $predicateName = $this->getPredicateName($predicate);
            
            switch ($predicateName) {
                case 'title':
                case 'name':
                case 'label':
                    $filmsMap[$subject]['title'] = trim($object);
                    break;
                case 'year':
                case 'releaseYear':
                case 'datePublished':
                    $filmsMap[$subject]['year'] = trim($object);
                    break;
                case 'rating':
                case 'imdbRating':
                    $filmsMap[$subject]['rating'] = trim($object);
                    break;
                case 'plot':
                case 'description':
                case 'abstract':
                    $filmsMap[$subject]['plot'] = trim($object);
                    break;
                case 'poster':
                case 'image':
                    $filmsMap[$subject]['poster'] = trim($object);
                    break;
                case 'genre':
                    $genre = trim($object);
                    if (!in_array($genre, $filmsMap[$subject]['genre'])) {
                        $filmsMap[$subject]['genre'][] = $genre;
                    }
                    break;
                case 'imdbID':
                case 'identifier':
                    $filmsMap[$subject]['imdb_id'] = trim($object);
                    break;
            }
        }

        // Filter: harus punya imdb_id dan title
        $films = array_filter($filmsMap, function($film) {
            return !empty($film['imdb_id']) && !empty($film['title']) && $film['title'] !== 'Unknown';
        });

        return array_values($films);
    }

    /**
     * Extract predicate name from URI
     */
    private function getPredicateName($predicateUri)
    {
        if (strpos($predicateUri, '#') !== false) {
            return substr($predicateUri, strrpos($predicateUri, '#') + 1);
        }
        return substr($predicateUri, strrpos($predicateUri, '/') + 1);
    }

    /**
     * Extract IMDb ID from URI (https://www.imdb.com/title/tt0004972/)
     */
    private function extractImdbIdFromUri($uri)
    {
        if (preg_match('/tt\d{7,}/', $uri, $matches)) {
            return $matches[0];
        }
        return null;
    }

    /**
     * Test connection to Fuseki
     */
    public function testConnection()
    {
        try {
            $query = 'SELECT (COUNT(*) as ?count) WHERE { ?s ?p ?o }';
            
            $response = Http::timeout(10)
                ->asForm()
                ->post($this->endpoint, [
                    'query' => $query
                ]);

            return $response->successful();

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * UPGRADE: Smart search in RDF data
     * Returns films matching keywords with relevance score
     */
    public function searchFilms($query, $limit = 100)
    {
        $allFilms = $this->getAllFilms();
        
        if (empty($query)) {
            // No query, return top rated
            usort($allFilms, fn($a, $b) => floatval($b['rating'] ?? 0) <=> floatval($a['rating'] ?? 0));
            return array_slice($allFilms, 0, $limit);
        }
        
        $keywords = explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9 ]/', '', $query)));
        $scored = [];
        
        foreach ($allFilms as $film) {
            $score = 0;
            
            // Searchable fields
            $title = strtolower($film['title'] ?? '');
            $genres = is_array($film['genre']) ? strtolower(implode(' ', $film['genre'])) : strtolower($film['genre'] ?? '');
            $director = strtolower($film['director'] ?? '');
            $cast = strtolower($film['cast'] ?? '');
            $plot = strtolower($film['plot'] ?? '');
            $year = (string)($film['year'] ?? '');
            
            foreach ($keywords as $kw) {
                if (strlen($kw) < 2) continue;
                
                // Exact match in title = highest score
                if ($title === $kw) $score += 100;
                if (str_contains($title, $kw)) $score += 50;
                
                // Genre match
                if (str_contains($genres, $kw)) $score += 30;
                
                // Director/Cast match
                if (str_contains($director, $kw)) $score += 25;
                if (str_contains($cast, $kw)) $score += 20;
                
                // Plot match
                if (str_contains($plot, $kw)) $score += 10;
                
                // Year match
                if ($year === $kw) $score += 15;
            }
            
            if ($score > 0) {
                $film['search_score'] = $score;
                $scored[] = $film;
            }
        }
        
        // Sort by relevance
        usort($scored, fn($a, $b) => $b['search_score'] <=> $a['search_score']);
        
        // If no results, return top rated
        if (empty($scored)) {
            Log::info('No search results, returning top rated films');
            usort($allFilms, fn($a, $b) => floatval($b['rating'] ?? 0) <=> floatval($a['rating'] ?? 0));
            return array_slice($allFilms, 0, $limit);
        }
        
        return array_slice($scored, 0, $limit);
    }

    /**
     * Check if specific film exists in database
     */
    public function filmExists($titleOrId)
    {
        $allFilms = $this->getAllFilms();
        $query = strtolower($titleOrId);
        
        foreach ($allFilms as $film) {
            if (strtolower($film['imdb_id']) === $query) {
                return $film;
            }
            
            if (str_contains(strtolower($film['title']), $query)) {
                return $film;
            }
        }
        
        return null;
    }

    /**
     * Get statistics about database
     */
    public function getStats()
    {
        $films = $this->getAllFilms();
        
        $genres = [];
        $years = [];
        
        foreach ($films as $film) {
            // Count genres
            $filmGenres = is_array($film['genre']) ? $film['genre'] : [$film['genre']];
            foreach ($filmGenres as $g) {
                if (empty($g)) continue;
                $genres[$g] = ($genres[$g] ?? 0) + 1;
            }
            
            // Count years
            $year = $film['year'] ?? 'Unknown';
            $years[$year] = ($years[$year] ?? 0) + 1;
        }
        
        arsort($genres);
        arsort($years);
        
        return [
            'total_films' => count($films),
            'total_genres' => count($genres),
            'top_genres' => array_slice($genres, 0, 10, true),
            'year_range' => [
                'min' => min(array_keys(array_filter($years, fn($k) => $k !== 'Unknown', ARRAY_FILTER_USE_KEY))),
                'max' => max(array_keys(array_filter($years, fn($k) => $k !== 'Unknown', ARRAY_FILTER_USE_KEY)))
            ],
            'films_per_decade' => $this->groupByDecade($years)
        ];
    }

    private function groupByDecade($years)
    {
        $decades = [];
        foreach ($years as $year => $count) {
            if ($year === 'Unknown') continue;
            $decade = floor($year / 10) * 10;
            $decades[$decade . 's'] = ($decades[$decade . 's'] ?? 0) + $count;
        }
        ksort($decades);
        return $decades;
    }
}
