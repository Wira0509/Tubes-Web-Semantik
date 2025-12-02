<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
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
}
