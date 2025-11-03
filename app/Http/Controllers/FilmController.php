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
            $filterClause .= "
                OPTIONAL { ?film fm:plot ?plotB }
                OPTIONAL { ?film fm:year ?yearB }
                BIND(COALESCE(?plotB, '') AS ?plot)
                BIND(COALESCE(?yearB, '') AS ?year)
                FILTER (
                    CONTAINS(LCASE(?title), LCASE('{$searchQuery}')) ||
                    CONTAINS(LCASE(?plot), LCASE('{$searchQuery}')) ||
                    CONTAINS(LCASE(?year), LCASE('{$searchQuery}'))
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

        // 3. Buat kueri untuk TOTAL COUNT
        $countQuery = "SELECT (COUNT(DISTINCT ?film) as ?total) WHERE { {$filterClause} }";
        $total = $this->fuseki->queryValue($countQuery);

        // 4. Bangun kueri utama untuk MENGAMBIL DATA
        $sparqlSelect = "
            SELECT DISTINCT ?film ?title ?year ?rated ?poster ?plot ?rating ?type
                (GROUP_CONCAT(DISTINCT ?actorUri; separator='||') AS ?actors)
                (GROUP_CONCAT(DISTINCT ?directorUri; separator='||') AS ?directors)
        ";
        
        $sparqlWhere = "
            { 
              SELECT DISTINCT ?film ?title ?type
              WHERE { {$filterClause} }
              %ORDER_BY_placeholder%
              LIMIT {$perPage}
              OFFSET {$offset}
            }
            
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
            BIND(xsd:float(?ratingStr) AS ?rating)
        ";

        // 5. Bangun string ORDER BY dinamis
        $orderBy = "";
        $orderByVar = "?title"; // default
        switch ($sort) {
            case 'rating_desc': $orderBy = "ORDER BY DESC(?rating)"; $orderByVar="?rating"; break;
            case 'rating_asc': $orderBy = "ORDER BY ?rating"; $orderByVar="?rating"; break;
            case 'year_desc': $orderBy = "ORDER BY DESC(?year)"; $orderByVar="?year"; break;
            case 'year_asc': $orderBy = "ORDER BY ?year"; $orderByVar="?year"; break;
            case 'title_desc': $orderBy = "ORDER BY DESC(?title)"; $orderByVar="?title"; break;
            case 'title_asc': default: $orderBy = "ORDER BY ?title"; $orderByVar="?title"; break;
        }
        
        $dataQuery = str_replace('%ORDER_BY_placeholder%', $orderBy, $sparqlWhere);

        // 6. Jalankan kueri data
        $dataQuery = "
            {$sparqlSelect} 
            WHERE { {$dataQuery} }
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
        
        // 8. Ambil data untuk dropdown filter (Kueri yang Lebih Aman)
        $years_query = $this->fuseki->query("SELECT DISTINCT ?year WHERE { ?s fm:title ?title ; fm:year ?year . }");
        $types = $this->fuseki->query("SELECT DISTINCT (STRAFTER(STR(?typeUri), '#') AS ?type) WHERE { ?s fm:title ?title ; fm:type ?typeUri . } ORDER BY ?type");
        $ratings_query = $this->fuseki->query("SELECT DISTINCT ?rated WHERE { ?s fm:title ?title ; fm:rated ?rated . }");

        // 9. Ambil Top Picks (Kueri ini sudah aman)
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

        // =================================================================
        // --- INI ADALAH BAGIAN YANG DIPERBARUI UNTUK RANDOMISASI ---
        // =================================================================
        
        // 9b. Ambil SEMUA film yang layak untuk di-feature
        $all_films_query = $this->fuseki->query("
            SELECT DISTINCT ?film
            WHERE {
                ?film fm:title ?title .
                ?film fm:poster ?poster .
                ?film fm:plot ?plot .
                FILTER(BOUND(?poster) && BOUND(?plot))
            }
        ");
        
        $all_film_uris = array_map(fn($f) => $f['film'], $all_films_query);
        shuffle($all_film_uris); // Acak daftar film menggunakan PHP
        
        $random_uris = array_slice($all_film_uris, 0, 7); // Ambil 7 film acak
        
        $featuredFilms = []; // Default array kosong
        
        if (!empty($random_uris)) {
            // Bangun klausa VALUES untuk kueri SPARQL
            $values_clause = "VALUES ?film { \n";
            foreach ($random_uris as $uri) {
                $values_clause .= "    <{$uri}> \n";
            }
            $values_clause .= "}";

            // 9c. Ambil detail untuk 7 film acak tersebut
            $featured_query = $this->fuseki->query("
                SELECT ?film ?title ?poster ?plot
                WHERE {
                    {$values_clause}
                    
                    ?film fm:title ?title .
                    OPTIONAL { ?film fm:poster ?posterB }
                    OPTIONAL { ?film fm:plot ?plotB }
                    
                    BIND(COALESCE(?posterB, 'https://placehold.co/300x450/1a1a1a/f5c518?text=N/A') AS ?poster)
                    BIND(COALESCE(?plotB, 'Plot not available.') AS ?plot)
                }
            ");
            $featuredFilms = $featured_query;
        }
        
        // =================================================================
        
        // 10. Kembalikan View
        
        // 10a. Ekstrak, filter N/A, dan urutkan TAHUN menggunakan PHP
        $year_list = array_map(fn($r) => $r['year'], $years_query);
        $year_list = array_filter($year_list, fn($y) => $y !== 'N/A' && $y !== '');
        rsort($year_list); // Urutkan descending (2023, 2022, ...)
        
        // 10b. Ekstrak, filter N/A, dan urutkan RATING menggunakan PHP
        $rating_list = array_map(fn($r) => $r['rated'], $ratings_query);
        $rating_list = array_filter($rating_list, fn($r) => $r !== 'N/A' && $r !== '');
        sort($rating_list); // Urutkan ascending (PG, PG-13, R)
        
        return view('search', [
            'films' => $films,
            'topPicks' => $topPicks,
            'featuredFilms' => $featuredFilms, // Ganti dari $featuredFilm (singular)
            'years' => $year_list,
            'types' => array_map(fn($r) => $r['type'], $types),
            'ratings' => $rating_list,
            'currentFilters' => $request->all(),
            'query' => $searchQuery
        ]);
    }

    public function show($imdb_id)
    {
        // ID adalah bagian dari URI, jadi kita bangun URI lengkapnya
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
                BIND(COALESCE(?ratingB, 'N/A') AS ?rating)
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

