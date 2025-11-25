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
     * 
     * QUERY 12: DBPEDIA BUDGET - External SPARQL Endpoint
     * Tujuan: Ambil data budget dari Wikipedia/DBpedia
     * Endpoint: https://dbpedia.org/sparql
     * Performance: 2-3 detik (external API lambat)
     */
    public function getFilmInfo($title, $year = null)
    {
        // ============================================================
        // STEP 1: Construct Wikipedia URI
        // ============================================================
        // Wikipedia naming convention: spasi -> underscore
        // "Iron Man" -> "Iron_Man"
        $cleanTitle = str_replace(' ', '_', $title);
        
        // Coba 3 kemungkinan URI DBpedia
        // Wikipedia kadang pakai suffix berbeda
        $possibleUris = [
            "http://dbpedia.org/resource/{$cleanTitle}",                    // Base URI
            "http://dbpedia.org/resource/{$cleanTitle}_(film)",             // With (film)
        ];

        // Jika ada tahun, tambahkan variasi dengan tahun
        // Contoh: Iron_Man_(2008_film)
        if ($year && $year !== 'N/A') {
            $possibleUris[] = "http://dbpedia.org/resource/{$cleanTitle}_({$year}_film)";
        }

        $filmData = [
            'budget' => null
        ];

        // ============================================================
        // STEP 2: Try Each URI dengan SPARQL Query
        // ============================================================
        foreach ($possibleUris as $uri) {
            // SPARQL Query ke DBpedia endpoint
            $sparql = "
                PREFIX dbo: <http://dbpedia.org/ontology/>
                
                SELECT ?budget
                WHERE {
                    # Type Check: Pastikan resource adalah Film
                    # 'a' = shorthand untuk rdf:type
                    # Jika bukan film (misal: person, place), query gagal
                    <{$uri}> a dbo:Film .
                    
                    # OPTIONAL: Budget tidak wajib ada
                    # Jika tidak ada, return hasil tapi ?budget = null
                    OPTIONAL { <{$uri}> dbo:budget ?budget }
                }
                # LIMIT 1: Ambil 1 hasil saja (cukup)
                LIMIT 1
            ";
            
            // Execute SPARQL query via CURL
            $results = $this->query($sparql);

            // Jika dapat hasil dan budget ada
            if (!empty($results)) {
                $result = $results[0];
                
                if (!empty($result['budget'])) {
                    // Format currency (handle 3 format berbeda)
                    $filmData['budget'] = $this->formatCurrency($result['budget']);
                    break; // Success! Keluar dari loop
                }
            }
            // Jika gagal, coba URI berikutnya
        }

        return $filmData;
    }

    /**
     * Format angka menjadi currency
     * 
     * DBpedia menyimpan budget dalam 3 FORMAT BERBEDA:
     * 1. Full number: 220000000 -> $220,000,000
     * 2. Scientific notation: 2.2E8 -> $220,000,000
     * 3. Millions: 220.0 (dalam juta) -> $220,000,000
     */
    private function formatCurrency($value)
    {
        // Log raw value untuk debugging
        Log::info('DBpedia raw budget value: ' . $value);
        
        // ============================================================
        // STEP 1: Handle Non-Numeric String
        // ============================================================
        if (!is_numeric($value)) {
            // Coba ekstrak angka dari string
            $cleanValue = preg_replace('/[^0-9.]/', '', $value);
            if (is_numeric($cleanValue)) {
                $value = floatval($cleanValue);
            } else {
                return $value; // Kembalikan apa adanya jika tidak bisa diparse
            }
        }

        // ============================================================
        // STEP 2: Convert ke Float (Handle Scientific Notation)
        // ============================================================
        // floatval() otomatis convert "2.2E8" -> 220000000.0
        $numericValue = floatval($value);
        
        // ============================================================
        // STEP 3: Detect Million Format
        // ============================================================
        // DBpedia kadang simpan budget dalam juta (220.0 = 220 million)
        // Jika nilai < 10000, kemungkinan dalam juta
        if ($numericValue > 0 && $numericValue < 10000) {
            $numericValue = $numericValue * 1000000; // Convert juta ke dollar
        }
        
        // ============================================================
        // STEP 4: Format dengan Comma Separator
        // ============================================================
        // number_format(value, decimals, decimal_separator, thousands_separator)
        return '$' . number_format($numericValue, 0, '.', ',');
        // Example: 220000000 -> "$220,000,000"
    }
}
