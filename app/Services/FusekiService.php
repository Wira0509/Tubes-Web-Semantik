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
        // Daftarkan namespace dari file RDF Anda
        RdfNamespace::set('fm', 'http://www.example.com/film#');
        RdfNamespace::set('person', 'http://www.example.com/person#');
        RdfNamespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

        // Buat SPARQL client yang mengarah ke endpoint di .env
        $this->client = new Client(env('FUSEKI_ENDPOINT'));

        // Simpan string prefix untuk digunakan di semua kueri
        $this->prefixes = "
            PREFIX fm: <http://www.example.com/film#>
            PREFIX person: <http://www.example.com/person#>
            PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
        ";
    }

    /**
     * Helper untuk mengekstrak nama dari URI (e.g. ...#John_Favreau -> John Favreau)
     */
    private function cleanName($uri)
    {
        // Pertama, dapatkan bagian setelah '#'
        $name = str_contains($uri, '#') ? substr($uri, strpos($uri, '#') + 1) : $uri;
        // Ganti '_' dengan spasi
        return str_replace('_', ' ', $name);
    }

    /**
     * Menjalankan kueri SPARQL dan memformat hasilnya menjadi array PHP yang bersih.
     */
    public function query($sparqlQuery)
    {
        $results = $this->client->query($this->prefixes . $sparqlQuery);
        $data = [];

        foreach ($results as $row) {
            $rowData = [];
            foreach ($row as $key => $value) {
                // Konversi dari objek EasyRdf ke string PHP biasa
                $rowData[$key] = (string) $value;
            }
            
            // Bersihkan data gabungan (actors/directors)
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
