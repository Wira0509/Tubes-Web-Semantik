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

    public function getFilmInfo($title, $year = null)
    {
        $cleanTitle = str_replace(' ', '_', $title);
        
        $possibleUris = [
            "http://dbpedia.org/resource/{$cleanTitle}",
            "http://dbpedia.org/resource/{$cleanTitle}_(film)",
        ];

        if ($year && $year !== 'N/A') {
            $possibleUris[] = "http://dbpedia.org/resource/{$cleanTitle}_({$year}_film)";
        }

        $filmData = [
            'budget' => null
        ];

        foreach ($possibleUris as $uri) {
            $sparql = "
                PREFIX dbo: <http://dbpedia.org/ontology/>
                
                SELECT ?budget
                WHERE {
                    <{$uri}> a dbo:Film .
                    OPTIONAL { <{$uri}> dbo:budget ?budget }
                }
                LIMIT 1
            ";
            
            $results = $this->query($sparql);

            if (!empty($results)) {
                $result = $results[0];
                
                if (!empty($result['budget'])) {
                    $filmData['budget'] = $this->formatCurrency($result['budget']);
                    break;
                }
            }
        }

        return $filmData;
    }

    private function formatCurrency($value)
    {
        Log::info('DBpedia raw budget value: ' . $value);
        
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
