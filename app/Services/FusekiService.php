<?php

namespace App\Services;

use EasyRdf\Sparql\Client;
use EasyRdf\RdfNamespace;

class FusekiService
{
    protected $client;
    protected $prefixes;

    public function __construct()
    {
        // ============================================================
        // 🔧 RDF Namespace Registration
        // ============================================================
        // Map prefixes to full URIs for easy querying
        RdfNamespace::set('fm', 'http://www.example.com/film#');          // 🎬 Film properties
        RdfNamespace::set('person', 'http://www.example.com/person#');    // 👤 People
        RdfNamespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');  // 📋 RDF standard

        // ============================================================
        // 🔗 SPARQL Client Setup
        // ============================================================
        // Connect to Apache Fuseki: localhost:3030/tubeswsfilm/query
        $this->client = new Client(env('FUSEKI_ENDPOINT'));

        // ============================================================
        // 📝 Auto-Inject Prefixes
        // ============================================================
        // Shorthand: fm:title vs <http://www.example.com/film#title>
        $this->prefixes = "
            PREFIX fm: <http://www.example.com/film#>
            PREFIX person: <http://www.example.com/person#>
            PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
        ";
    }

    /**
     * Helper untuk mengekstrak nama dari URI
     * 
     * URI Format: http://example.com/person#Christopher_Nolan
     * Step 1: Extract "Christopher_Nolan" (bagian setelah #)
     * Step 2: Replace underscore dengan spasi -> "Christopher Nolan"
     * 
     * Example:
     * Input:  "http://example.com/person#Robert_Downey_Jr."
     * Output: "Robert Downey Jr."
     */
    private function cleanName($uri)
    {
        // Pertama, dapatkan bagian setelah '#' (fragment)
        $name = str_contains($uri, '#') ? substr($uri, strpos($uri, '#') + 1) : $uri;
        // Ganti '_' dengan spasi untuk readability
        return str_replace('_', ' ', $name);
    }

    /**
     * Menjalankan kueri SPARQL dan memformat hasilnya menjadi array PHP yang bersih.
     * 
     * SPARQL Query Execution Flow:
     * 1. Auto-inject prefixes ke query
     * 2. Execute query ke Fuseki endpoint
     * 3. Convert EasyRdf objects ke PHP array
     * 4. Clean URI-based names (person#John_Doe -> John Doe)
     */
    public function query($sparqlQuery)
    {
        // ============================================================
        // Execute SPARQL Query
        // ============================================================
        // Auto-inject prefixes di awal query
        $results = $this->client->query($this->prefixes . $sparqlQuery);
        $data = [];

        // ============================================================
        // Process Results
        // ============================================================
        foreach ($results as $row) {
            $rowData = [];
            foreach ($row as $key => $value) {
                // Konversi dari objek EasyRdf ke string PHP biasa
                $rowData[$key] = (string) $value;
            }
            
            // Bersihkan data gabungan (actors/directors)
            // Split string "actor1||actor2||actor3" jadi array
            if (isset($rowData['actors'])) {
                $rowData['actors'] = array_map([$this, 'cleanName'], explode('||', $rowData['actors']));
            }
            if (isset($rowData['directors'])) {
                $rowData['directors'] = array_map([$this, 'cleanName'], explode('||', $rowData['directors']));
            }

            $data[] = $rowData;
        }
        return $data;
    }

    /**
     * Menjalankan kueri untuk mendapatkan satu nilai (seperti COUNT)
     */
    public function queryValue($sparqlQuery)
    {
        $results = $this->query($sparqlQuery);
        if (empty($results)) {
            return 0;
        }
        // Ambil nilai dari kolom pertama di baris pertama
        return (int) array_values($results[0])[0];
    }
}
