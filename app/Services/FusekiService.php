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
        RdfNamespace::set('fm', 'http://www.example.com/film#');
        RdfNamespace::set('person', 'http://www.example.com/person#');
        RdfNamespace::set('rdf', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#');

        $this->client = new Client(env('FUSEKI_ENDPOINT'));

        $this->prefixes = "
            PREFIX fm: <http://www.example.com/film#>
            PREFIX person: <http://www.example.com/person#>
            PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
            PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
        ";
    }

    private function cleanName($uri)
    {
        $name = str_contains($uri, '#') ? substr($uri, strpos($uri, '#') + 1) : $uri;
        return str_replace('_', ' ', $name);
    }

    public function query($sparqlQuery)
    {
        $results = $this->client->query($this->prefixes . $sparqlQuery);
        $data = [];

        foreach ($results as $row) {
            $rowData = [];
            foreach ($row as $key => $value) {
                $rowData[$key] = (string) $value;
            }
            
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

    public function queryValue($sparqlQuery)
    {
        $results = $this->query($sparqlQuery);
        if (empty($results)) {
            return 0;
        }
        return (int) array_values($results[0])[0];
    }
}
