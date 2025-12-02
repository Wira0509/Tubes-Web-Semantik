<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DBpediaService
{
    private $endpoint = 'https://dbpedia.org/sparql';

    private function query($sparql)
    {
        $url = $this->endpoint . '?' . http_build_query([
            'query' => $sparql,
            'format' => 'json'
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) TetengFilm/1.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/sparql-results+json'
        ]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            Log::warning('DBpedia CURL error: ' . curl_error($ch));
            curl_close($ch);
            return [];
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            Log::warning("DBpedia HTTP error: {$httpCode}");
            return [];
        }

        $data = json_decode($response, true);
        
        if (!isset($data['results']['bindings'])) {
            return [];
        }

        return array_map(function($row) {
            $result = [];
            foreach ($row as $key => $value) {
                $result[$key] = $value['value'] ?? null;
            }
            return $result;
        }, $data['results']['bindings']);
    }

    public function getFilmInfo($title, $year = null, $type = 'movie')
    {
        $cleanTitle = str_replace(' ', '_', $title);
        
        // Build possible URIs based on type
        $possibleUris = [
            "http://dbpedia.org/resource/{$cleanTitle}",
        ];

        if ($type === 'series' || strtolower($type) === 'series') {
            // For TV series
            $possibleUris[] = "http://dbpedia.org/resource/{$cleanTitle}_(TV_series)";
            $possibleUris[] = "http://dbpedia.org/resource/{$cleanTitle}_(TV_Series)";
            if ($year && $year !== 'N/A') {
                $possibleUris[] = "http://dbpedia.org/resource/{$cleanTitle}_({$year}_TV_series)";
            }
        } else {
            // For movies
            $possibleUris[] = "http://dbpedia.org/resource/{$cleanTitle}_(film)";
            if ($year && $year !== 'N/A') {
                $possibleUris[] = "http://dbpedia.org/resource/{$cleanTitle}_({$year}_film)";
            }
        }

        $filmData = [
            'boxOffice' => null,
            'wikipediaUrl' => null
        ];

        foreach ($possibleUris as $uri) {
            // Query for both Film and TelevisionShow types
            $sparql = "
                PREFIX dbo: <http://dbpedia.org/ontology/>
                PREFIX foaf: <http://xmlns.com/foaf/0.1/>
                
                SELECT ?boxOffice ?wikiPage ?type
                WHERE {
                    {
                        <{$uri}> a dbo:Film .
                        BIND('film' AS ?type)
                    }
                    UNION
                    {
                        <{$uri}> a dbo:TelevisionShow .
                        BIND('series' AS ?type)
                    }
                    OPTIONAL { <{$uri}> dbo:gross ?boxOffice }
                    OPTIONAL { <{$uri}> foaf:isPrimaryTopicOf ?wikiPage }
                }
                LIMIT 1
            ";
            
            $results = $this->query($sparql);

            if (!empty($results)) {
                $result = $results[0];
                
                // Set Wikipedia URL if found
                if (!empty($result['wikiPage'])) {
                    $filmData['wikipediaUrl'] = $result['wikiPage'];
                }
                
                if (!empty($result['boxOffice'])) {
                    $filmData['boxOffice'] = $this->formatCurrency($result['boxOffice']);
                }
                
                // If we found the page, use this URI
                if (!empty($result['wikiPage']) || !empty($result['boxOffice']) || !empty($result['type'])) {
                    // Extract Wikipedia title from DBpedia URI
                    if (empty($filmData['wikipediaUrl'])) {
                        $filmData['wikipediaUrl'] = str_replace(
                            'http://dbpedia.org/resource/',
                            'https://en.wikipedia.org/wiki/',
                            $uri
                        );
                    }
                    break;
                }
            }
        }

        return $filmData;
    }

    /**
     * Get the correct Wikipedia URL for a film
     */
    public function getWikipediaUrl($title, $year = null)
    {
        $filmInfo = $this->getFilmInfo($title, $year);
        return $filmInfo['wikipediaUrl'];
    }

    private function formatCurrency($value)
    {
        Log::info('DBpedia raw boxOffice value: ' . $value);
        
        if (!is_numeric($value)) {
            $cleanValue = preg_replace('/[^0-9.]/', '', $value);
            if (is_numeric($cleanValue)) {
                $value = floatval($cleanValue);
            } else {
                return $value;
            }
        }

        $numericValue = floatval($value);
        
        if ($numericValue > 0 && $numericValue < 10000) {
            $numericValue = $numericValue * 1000000;
        }
        
        return '$' . number_format($numericValue, 0, '.', ',');
    }
}
