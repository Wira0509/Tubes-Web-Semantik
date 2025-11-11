<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FusekiService; // Gunakan service baru kita
use Illuminate\Pagination\LengthAwarePaginator; // Untuk paginasi manual

class FilmController extends Controller
{
    protected $fuseki;

    // Inject FusekiService
    public function __construct(FusekiService $fuseki)
    {
        $this->fuseki = $fuseki;
    }

    public function search(Request $request)
    {
        // 1. Ambil semua parameter filter
        $searchQuery = $request->input('query');
        $letter = $request->input('letter');
        $year = $request->input('year');
        $type = $request->input('type');
        $rated = $request->input('rated');
        $genre = $request->input('genre');
        $sort = $request->input('sort', 'title_asc');
        $page = $request->input('page', 1);
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // 2. Bangun Klausa WHERE untuk FILTERING
        $filterClause = "
            ?film fm:title ?title .
            ?film fm:type ?typeUri .
            BIND(STRAFTER(STR(?typeUri), '#') AS ?type)
        ";
        
        if ($searchQuery) {
            
            // 1. Bersihkan query pencarian di sisi PHP
            $cleanSearchQuery = strtolower($searchQuery);
            $cleanSearchQuery = str_replace(
                [' ', '-', ':', '_', '4', '1', '0', '3'], 
                ['', '', '', '', 'a', 'i', 'o', 'e'], 
                $cleanSearchQuery
            );

            // 2. Tambahkan pembersihan di sisi SPARQL
            $filterClause .= "
                
                OPTIONAL { ?film fm:year ?yearB }
                BIND(COALESCE(?yearB, '') AS ?year)

                BIND(LCASE(?title) AS ?lcaseTitle)
                BIND(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(?lcaseTitle, ' ', ''), '-', ''), ':', ''), '4', 'a'), '1', 'i'), '0', 'o') AS ?cleanTitle)
                
                OPTIONAL { 
                    ?film fm:plot ?plotB .
                    BIND(LCASE(?plotB) AS ?lcasePlot)
                    BIND(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(?lcasePlot, ' ', ''), '-', ''), ':', ''), '4', 'a'), '1', 'i'), '0', 'o') AS ?cleanPlot_intermediate)
                }
                
                OPTIONAL {
                    ?film fm:actor ?actorUri .
                    BIND(STRAFTER(STR(?actorUri), '#') AS ?actorNameOnly) 
                    BIND(REPLACE(?actorNameOnly, '_', ' ') AS ?actorNameSpaced)
                    BIND(LCASE(?actorNameSpaced) AS ?lcaseActor)
                    BIND(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(?lcaseActor, ' ', ''), '-', ''), ':', ''), '4', 'a'), '1', 'i'), '0', 'o') AS ?cleanActor_intermediate)
                }

                BIND(IF(BOUND(?cleanPlot_intermediate), ?cleanPlot_intermediate, '') AS ?cleanPlot)
                BIND(IF(BOUND(?cleanActor_intermediate), ?cleanActor_intermediate, '') AS ?cleanActor)

                FILTER (
                    CONTAINS(?cleanTitle, '{$cleanSearchQuery}') ||
                    CONTAINS(?cleanPlot, '{$cleanSearchQuery}') ||
                    CONTAINS(LCASE(?year), LCASE('{$searchQuery}')) || 
                    CONTAINS(?cleanActor, '{$cleanSearchQuery}')
                )
            ";
        }
        if ($letter) {
            $filterClause .= " FILTER (STRSTARTS(LCASE(?title), LCASE('{$letter}'))) \n";
        }
        if ($year) {
            $filterClause .= " OPTIONAL { ?film fm:year ?yearF } FILTER (?yearF = '{$year}') \n";
        }
        if ($type) {
            $filterClause .= " FILTER (?type = '{$type}') \n";
        }
        if ($rated) {
            $filterClause .= " OPTIONAL { ?film fm:rated ?ratedF } FILTER (?ratedF = '{$rated}') \n";
        }
        if ($genre) {
            $filterClause .= " OPTIONAL { ?film fm:genre ?genreF } FILTER (CONTAINS(LCASE(?genreF), LCASE('{$genre}'))) \n";
        }

        // 3. Buat kueri untuk TOTAL COUNT
        $countQuery = "SELECT (COUNT(DISTINCT ?film) as ?total) WHERE { {$filterClause} }";
        $total = $this->fuseki->queryValue($countQuery);

        // 4. Bangun kueri utama untuk MENGAMBIL DATA
        $sparqlSelect = "
            SELECT DISTINCT ?film ?title ?year ?rated ?poster ?plot ?rating ?type
                (GROUP_CONCAT(DISTINCT ?actorUri; separator='||') AS ?actors)
                (GROUP_CONCAT(DISTINCT ?directorUri; separator='||') AS ?directors)
        ";

        $subQuery = "
            SELECT DISTINCT ?film ?title ?type
            WHERE { {$filterClause} }
            %ORDER_BY_placeholder%
            LIMIT {$perPage}
            OFFSET {$offset}
        ";

        // 5. Bangun string ORDER BY dinamis
        $orderBy = "";
        switch ($sort) {
            case 'rating_desc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?ratingB", $subQuery);
                // Kita perlu menambahkan OPTIONAL di sini agar ?ratingB ada di dalam subquery
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:rating ?ratingB } ", $subQuery);
                $orderBy = "ORDER BY DESC(IF(COALESCE(?ratingB, '0.0') = 'N/A', 0.0, xsd:float(COALESCE(?ratingB, '0.0'))))"; 
                break;
            case 'rating_asc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?ratingB", $subQuery);
                // Kita perlu menambahkan OPTIONAL di sini agar ?ratingB ada di dalam subquery
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:rating ?ratingB } ", $subQuery);
                $orderBy = "ORDER BY IF(COALESCE(?ratingB, '0.0') = 'N/A', 0.0, xsd:float(COALESCE(?ratingB, '0.0')))"; 
                break;
            case 'year_desc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?yearB", $subQuery);
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:year ?yearB } ", $subQuery);
                $orderBy = "ORDER BY DESC(?yearB)"; 
                break;
            case 'year_asc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?yearB", $subQuery);
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:year ?yearB } ", $subQuery);
                $orderBy = "ORDER BY ?yearB"; 
                break;
            case 'title_desc': 
                $orderBy = "ORDER BY DESC(?title)"; 
                break;
            case 'title_asc': 
            default: 
                $orderBy = "ORDER BY ?title"; 
                break;
        }
        
        $subQuery = str_replace('%ORDER_BY_placeholder%', $orderBy, $subQuery);
        
        // 6. Jalankan kueri data
        $dataQuery = "
            {$sparqlSelect} 
            WHERE {
                { {$subQuery} }
                
                OPTIONAL { ?film fm:year ?yearB }
                OPTIONAL { ?film fm:rated ?ratedB }
                OPTIONAL { ?film fm:poster ?posterB }
                OPTIONAL { ?film fm:plot ?plotB }
                OPTIONAL { ?film fm:rating ?ratingB }
                OPTIONAL { ?film fm:actor ?actorUri . }
                OPTIONAL { ?film fm:director ?directorUri . }

                BIND(COALESCE(?yearB, 'N/A') AS ?year)
                BIND(COALESCE(?ratedB, 'N/A') AS ?rated)
                BIND(COALESCE(?posterB, 'https://placehold.co/100x150/1a1a1a/f5c518?text=No+Poster') AS ?poster)
                BIND(COALESCE(?plotB, 'Plot not available.') AS ?plot)
                BIND(COALESCE(?ratingB, '0.0') AS ?ratingStr)
                BIND(IF(?ratingStr = 'N/A', 0.0, xsd:float(?ratingStr)) AS ?rating)
            }
            GROUP BY ?film ?title ?year ?rated ?poster ?plot ?rating ?type
            {$orderBy}
        ";
        
        $results = $this->fuseki->query($dataQuery);
        
        // 7. Buat Paginator Laravel secara Manual
        $films = new LengthAwarePaginator(
            $results,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // 8. Ambil data untuk dropdown filter
        $years_query = $this->fuseki->query("SELECT DISTINCT ?year WHERE { ?s fm:title ?title ; fm:year ?year . }");
        $types = $this->fuseki->query("SELECT DISTINCT (STRAFTER(STR(?typeUri), '#') AS ?type) WHERE { ?s fm:title ?title ; fm:type ?typeUri . } ORDER BY ?type");
        $ratings_query = $this->fuseki->query("SELECT DISTINCT ?rated WHERE { ?s fm:title ?title ; fm:rated ?rated . }");
        $genres_query = $this->fuseki->query("SELECT DISTINCT ?genre WHERE { ?s fm:title ?title ; fm:genre ?genre . }");

        // 9. Ambil Top Picks
        $topPicks = $this->fuseki->query("
            SELECT ?film ?title ?poster ?rating
            WHERE {
                ?film fm:title ?title .
                OPTIONAL { ?film fm:poster ?posterB }
                OPTIONAL { ?film fm:rating ?ratingB }
                
                BIND(COALESCE(?posterB, 'https://placehold.co/192x288/1a1a1a/f5c518?text=N/A') AS ?poster)
                BIND(COALESCE(?ratingB, '0.0') AS ?ratingStr)
                FILTER(?ratingStr != '0.0' && ?ratingStr != 'N/A')
                BIND(xsd:float(?ratingStr) AS ?rating)
            } 
            ORDER BY DESC(?rating) 
            LIMIT 10
        ");
        
        // 10. Ambil Featured Films
        $featuredFilmIds = $this->fuseki->query("
            SELECT DISTINCT ?film 
            WHERE {
                ?film fm:title ?title .
                ?film fm:plot ?plot .
                ?film fm:poster ?poster .
                FILTER(BOUND(?plot) && BOUND(?poster))
            }
        ");
        
        $featuredFilmList = array_map(fn($r) => $r['film'], $featuredFilmIds);
        shuffle($featuredFilmList);
        $featuredFilms = [];

        if (!empty($featuredFilmList)) {
            $featuredFilms = $this->fuseki->query("
                SELECT ?film ?title ?poster ?plot
                WHERE {
                    VALUES ?film { <" . implode('> <', array_slice($featuredFilmList, 0, 7)) . "> }
                    ?film fm:title ?title .
                    ?film fm:poster ?poster .
                    ?film fm:plot ?plot .
                }
            ");
        }
        
        // 11. Kembalikan View
        
        // Proses daftar TAHUN
        $year_list = array_map(fn($r) => $r['year'], $years_query);
        $year_list = array_filter($year_list, fn($y) => $y !== 'N/A' && $y !== '');
        rsort($year_list);
        
        // Proses daftar RATING
        $rating_list = array_map(fn($r) => $r['rated'], $ratings_query);
        $rating_list = array_filter($rating_list, fn($r) => $r !== 'N/A' && $r !== '');
        sort($rating_list);

        // Proses daftar GENRE
        $genre_list_raw = array_map(fn($r) => $r['genre'], $genres_query);
        $all_genres_flat = [];
        foreach ($genre_list_raw as $genre_string) {
            $genres_array = explode(', ', $genre_string);
            foreach ($genres_array as $g) {
                if (!empty(trim($g))) {
                    $all_genres_flat[] = trim($g);
                }
            }
        }
        $genre_list = array_unique($all_genres_flat);
        sort($genre_list);
        
        return view('search', [
            'films' => $films,
            'topPicks' => $topPicks,
            'featuredFilms' => $featuredFilms,
            'years' => $year_list,
            'types' => array_map(fn($r) => $r['type'], $types),
            'ratings' => $rating_list,
            'genres' => $genre_list,
            'currentFilters' => $request->all(),
            'query' => $searchQuery
        ]);
    }

    public function show($imdb_id)
    {
        $filmUri = "https://www.imdb.com/title/{$imdb_id}/";
        
        $sparqlSelect = "
            SELECT ?title ?year ?rated ?poster ?plot ?rating ?type
                (GROUP_CONCAT(DISTINCT ?actorUri; separator='||') AS ?actors)
                (GROUP_CONCAT(DISTINCT ?directorUri; separator='||') AS ?directors)
                (GROUP_CONCAT(DISTINCT ?genreB; separator=', ') AS ?genres)
        ";
        
        $sparqlWhere = "
            WHERE {
                BIND(<{$filmUri}> AS ?film)
                
                ?film fm:title ?title .
                ?film fm:type ?typeUri .

                OPTIONAL { ?film fm:year ?yearB }
                OPTIONAL { ?film fm:rated ?ratedB }
                OPTIONAL { ?film fm:poster ?posterB }
                OPTIONAL { ?film fm:plot ?plotB }
                OPTIONAL { ?film fm:rating ?ratingB }
                OPTIONAL { ?film fm:genre ?genreB . }
                OPTIONAL { ?film fm:actor ?actorUri . }
                OPTIONAL { ?film fm:director ?directorUri . }

                BIND(STRAFTER(STR(?typeUri), '#') AS ?type)
                BIND(COALESCE(?yearB, 'N/A') AS ?year)
                BIND(COALESCE(?ratedB, 'N/A') AS ?rated)
                BIND(COALESCE(?posterB, 'https://placehold.co/300x450/1a1a1a/f5c518?text=No+Poster') AS ?poster)
                BIND(COALESCE(?plotB, 'Plot not available.') AS ?plot)
                BIND(COALESCE(?ratingB, 'N/A') AS ?ratingStr)
                BIND(IF(?ratingStr = 'N/A', 'N/A', ?ratingStr) AS ?rating)
            }
        ";

        $query = "
            {$sparqlSelect} 
            {$sparqlWhere}
            GROUP BY ?film ?title ?year ?rated ?poster ?plot ?rating ?type
        ";
        
        $results = $this->fuseki->query($query);
        
        if (empty($results)) {
            abort(404);
        }

        $film = $results[0];
        $film['imdb_id'] = $filmUri; 
        
        return view('detail', [
            'film' => $film
        ]);
    }
}

