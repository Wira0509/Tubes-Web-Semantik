<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DBpediaService
{
    private $endpoint = 'https://dbpedia.org/sparql';

    /**
     * Query DBpedia SPARQL endpoint
     */
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

        // Transform hasil ke format yang lebih mudah digunakan
        return array_map(function($row) {
            $result = [];
            foreach ($row as $key => $value) {
                $result[$key] = $value['value'] ?? null;
            }
            return $result;
        }, $data['results']['bindings']);
    }

    /**
     * Ambil budget film dari DBpedia berdasarkan judul
     */
    public function getFilmInfo($title, $year = null)
    {
        // Bersihkan judul untuk pencarian DBpedia
        $cleanTitle = str_replace(' ', '_', $title);
        
        // Coba beberapa kemungkinan URI DBpedia
        $possibleUris = [
            "http://dbpedia.org/resource/{$cleanTitle}",
            "http://dbpedia.org/resource/{$cleanTitle}_(film)",
        ];

        // Jika ada tahun, tambahkan variasi dengan tahun
        if ($year && $year !== 'N/A') {
            $possibleUris[] = "http://dbpedia.org/resource/{$cleanTitle}_({$year}_film)";
        }

        $filmData = [
            'budget' => null
        ];

        foreach ($possibleUris as $uri) {
            // Query dengan OPTIONAL agar lebih fleksibel
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
                    break; // Keluar dari loop jika berhasil
                }
            }
        }

        return $filmData;
    }

    /**
     * Format angka menjadi currency
     */
    private function formatCurrency($value)
    {
        // Log raw value untuk debugging
        Log::info('DBpedia raw budget value: ' . $value);
        
        // Jika sudah dalam format string dengan simbol, kembalikan apa adanya
        if (!is_numeric($value)) {
            // Coba ekstrak angka dari string
            $cleanValue = preg_replace('/[^0-9.]/', '', $value);
            if (is_numeric($cleanValue)) {
                $value = floatval($cleanValue);
            } else {
                return $value; // Kembalikan apa adanya jika tidak bisa diparse
            }
        }

        // Konversi ke float (handle scientific notation)
        $numericValue = floatval($value);
        
        // Jika nilai terlalu kecil (< 10000), kemungkinan dalam juta dollar
        // DBpedia kadang menyimpan budget dalam juta untuk menghemat space
        if ($numericValue > 0 && $numericValue < 10000) {
            $numericValue = $numericValue * 1000000; // Konversi juta ke dollar
        }
        
        // Format sebagai USD dengan koma sebagai thousand separator
        return '$' . number_format($numericValue, 0, '.', ',');
    }
}
