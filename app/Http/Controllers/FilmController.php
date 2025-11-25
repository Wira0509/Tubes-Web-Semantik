<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FusekiService;
use Illuminate\Pagination\LengthAwarePaginator;

class FilmController extends Controller
{
    protected $fuseki;
    protected $dbpedia;

    public function __construct(FusekiService $fuseki, \App\Services\DBpediaService $dbpedia)
    {
        $this->fuseki = $fuseki;
        $this->dbpedia = $dbpedia;
    }

    private function cleanNameFromUri($uri) {
        if (!is_string($uri)) {
            return 'Error:BukanString';
        }
        $fragment = parse_url($uri, PHP_URL_FRAGMENT); 
        if ($fragment) {
            return str_replace('_', ' ', urldecode($fragment));
        }
        return str_replace('_', ' ', basename(urldecode($uri)));
    }

    public function search(Request $request)
    {
        $searchQuery = $request->input('query');
        
        if ($searchQuery && preg_match('/^tt\d+$/i', trim($searchQuery))) {
            $imdbId = strtolower(trim($searchQuery));
            return redirect()->route('film.show', ['imdb_id' => $imdbId]);
        }
        
        $letter = $request->input('letter');
        $year = $request->input('year');
        $type = $request->input('type');
        $rated = $request->input('rated');
        $genre = $request->input('genre');
        $sort = $request->input('sort', 'title_asc');
        $page = $request->input('page', 1);
        $perPage = 42;
        $offset = ($page - 1) * $perPage;

        $filterClause = "
            ?film fm:title ?title .
            ?film fm:type ?typeUri .
            BIND(STRAFTER(STR(?typeUri), '#') AS ?type)
        ";
        
        if ($searchQuery) {
            $cleanSearchQuery = strtolower($searchQuery);
            $cleanSearchQuery = str_replace(
                [' ', '-', ':', '_', '4', '1', '0', '3'], 
                ['', '', '', '', 'a', 'i', 'o', 'e'], 
                $cleanSearchQuery
            );

            $escapedSearchQuery = addslashes($searchQuery);
            $escapedCleanSearchQuery = addslashes($cleanSearchQuery);
            
            $isImdbSearch = preg_match('/tt\d+/i', $searchQuery);

            $filterClause .= "
                
                OPTIONAL { ?film fm:year ?yearB }
                BIND(COALESCE(STR(?yearB), '') AS ?yearStr)

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

                OPTIONAL {
                    ?film fm:director ?directorUri .
                    BIND(STRAFTER(STR(?directorUri), '#') AS ?directorNameOnly) 
                    BIND(REPLACE(?directorNameOnly, '_', ' ') AS ?directorNameSpaced)
                    BIND(LCASE(?directorNameSpaced) AS ?lcaseDirector)
                    BIND(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(?lcaseDirector, ' ', ''), '-', ''), ':', ''), '4', 'a'), '1', 'i'), '0', 'o') AS ?cleanDirector_intermediate)
                }

                OPTIONAL {
                    ?film fm:writer ?writerUri .
                    BIND(STRAFTER(STR(?writerUri), '#') AS ?writerNameOnly) 
                    BIND(REPLACE(?writerNameOnly, '_', ' ') AS ?writerNameSpaced)
                    BIND(LCASE(?writerNameSpaced) AS ?lcaseWriter)
                    BIND(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(?lcaseWriter, ' ', ''), '-', ''), ':', ''), '4', 'a'), '1', 'i'), '0', 'o') AS ?cleanWriter_intermediate)
                }

                OPTIONAL {
                    ?film fm:country ?countryB .
                    BIND(LCASE(?countryB) AS ?lcaseCountry)
                }

                BIND(IF(BOUND(?cleanPlot_intermediate), ?cleanPlot_intermediate, '') AS ?cleanPlot)
                BIND(IF(BOUND(?cleanActor_intermediate), ?cleanActor_intermediate, '') AS ?cleanActor)
                BIND(IF(BOUND(?cleanDirector_intermediate), ?cleanDirector_intermediate, '') AS ?cleanDirector)
                BIND(IF(BOUND(?cleanWriter_intermediate), ?cleanWriter_intermediate, '') AS ?cleanWriter)
                BIND(IF(BOUND(?lcaseCountry), ?lcaseCountry, '') AS ?country)

                FILTER (
                    CONTAINS(?cleanTitle, '{$escapedCleanSearchQuery}') ||
                    CONTAINS(?cleanPlot, '{$escapedCleanSearchQuery}') ||
                    CONTAINS(?yearStr, '{$escapedSearchQuery}') || 
                    CONTAINS(?cleanActor, '{$escapedCleanSearchQuery}') ||
                    CONTAINS(?cleanDirector, '{$escapedCleanSearchQuery}') ||
                    CONTAINS(?cleanWriter, '{$escapedCleanSearchQuery}') ||
                    CONTAINS(?country, LCASE('{$escapedSearchQuery}'))" . 
                    ($isImdbSearch ? " || CONTAINS(LCASE(STR(?film)), LCASE('{$escapedSearchQuery}'))" : "") . "
                )
            ";
        }

        if ($letter) {
            $escapedLetter = addslashes($letter);
            $filterClause .= " FILTER (STRSTARTS(LCASE(?title), LCASE('{$escapedLetter}'))) \n";
        }
        
        if ($year) {
            $escapedYear = addslashes($year);
            $filterClause .= " ?film fm:year ?yearF . FILTER (STR(?yearF) = '{$escapedYear}') \n";
        }
        
        if ($type) {
            $escapedType = addslashes($type);
            $filterClause .= " FILTER (?type = '{$escapedType}') \n";
        }
        
        if ($rated) {
            $escapedRated = addslashes($rated);
            $filterClause .= " ?film fm:rated ?ratedF . FILTER (?ratedF = '{$escapedRated}') \n";
        }
        
        if ($genre) {
            $escapedGenre = addslashes($genre);
            $filterClause .= " ?film fm:genre ?genreF . FILTER (LCASE(?genreF) = LCASE('{$escapedGenre}')) \n";
        }

        $countQuery = "SELECT (COUNT(DISTINCT ?film) as ?total) WHERE { {$filterClause} }";
        $total = $this->fuseki->queryValue($countQuery);
   
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

        $orderBy = "";
        $finalOrderBy = "";

        switch ($sort) {
            case 'rating_desc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?ratingB", $subQuery);
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:imdbRating ?ratingB } ", $subQuery);
                $orderBy = "ORDER BY DESC(IF(COALESCE(?ratingB, '0.0') = 'N/A', 0.0, xsd:float(COALESCE(?ratingB, '0.0'))))"; 
                $finalOrderBy = "ORDER BY DESC(?rating)";
                break;
            case 'rating_asc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?ratingB", $subQuery);
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:imdbRating ?ratingB } ", $subQuery);
                $orderBy = "ORDER BY ASC(IF(COALESCE(?ratingB, '0.0') = 'N/A', 0.0, xsd:float(COALESCE(?ratingB, '0.0'))))"; 
                $finalOrderBy = "ORDER BY ASC(?rating)";
                break;
            case 'year_desc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?yearB", $subQuery);
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:year ?yearB } ", $subQuery);
                $orderBy = "ORDER BY DESC(?yearB)"; 
                $finalOrderBy = "ORDER BY DESC(?year)";
                break;
            case 'year_asc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?yearB", $subQuery);
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:year ?yearB } ", $subQuery);
                $orderBy = "ORDER BY ?yearB"; 
                $finalOrderBy = "ORDER BY ASC(?year)";
                break;
            case 'title_desc': 
                $orderBy = "ORDER BY DESC(?title)"; 
                $finalOrderBy = "ORDER BY DESC(?title)";
                break;
            case 'title_asc': 
            default: 
                $orderBy = "ORDER BY ?title"; 
                $finalOrderBy = "ORDER BY ?title";
                break;
        }
        
        $subQuery = str_replace('%ORDER_BY_placeholder%', $orderBy, $subQuery);
        
        $dataQuery = "
            {$sparqlSelect} 
            WHERE {
                { {$subQuery} }
                
                OPTIONAL { ?film fm:year ?yearB }
                OPTIONAL { ?film fm:rated ?ratedB }
                OPTIONAL { ?film fm:poster ?posterB }
                OPTIONAL { ?film fm:plot ?plotB }
                OPTIONAL { ?film fm:imdbRating ?ratingB } 
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
            {$finalOrderBy}
        ";

        $results = $this->fuseki->query($dataQuery);

        $cleanName = function($uri) {
            if (!is_string($uri)) return '';
            $fragment = parse_url($uri, PHP_URL_FRAGMENT); 
            if ($fragment) {
                return str_replace('_', ' ', urldecode($fragment));
            }
            return str_replace('_', ' ', basename(urldecode($uri)));
        };

        $processedResults = array_map(function($film) use ($cleanName) {
            if (isset($film['actors'])) {
                if (is_string($film['actors'])) {
                    $film['actors_list'] = array_map($cleanName, explode('||', $film['actors']));
                } elseif (is_array($film['actors'])) {
                    $film['actors_list'] = array_map($cleanName, $film['actors']);
                }
            } else {
                $film['actors_list'] = [];
            }
        
            if (isset($film['directors'])) {
                if (is_string($film['directors'])) {
                    $film['directors_list'] = array_map($cleanName, explode('||', $film['directors']));
                } elseif (is_array($film['directors'])) {
                    $film['directors_list'] = array_map($cleanName, $film['directors']);
                }
            } else {
                $film['directors_list'] = [];
            }
            
            return $film;
        }, $results);
        
        $films = new LengthAwarePaginator(
            $processedResults,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $years_query = $this->fuseki->query("SELECT DISTINCT ?year WHERE { ?s fm:title ?title ; fm:year ?year . } ORDER BY ?year");
        
        $types = $this->fuseki->query("SELECT DISTINCT (STRAFTER(STR(?typeUri), '#') AS ?type) WHERE { ?s fm:title ?title ; fm:type ?typeUri . } ORDER BY ?type");
        
        $ratings_query = $this->fuseki->query("SELECT DISTINCT ?rated WHERE { ?s fm:title ?title ; fm:rated ?rated . } ORDER BY ?rated");
        
        $genres_query = $this->fuseki->query("SELECT DISTINCT ?genre WHERE { ?s fm:title ?title ; fm:genre ?genre . } ORDER BY ?genre");

        $topPicks = $this->fuseki->query("
            SELECT ?film ?title ?poster ?rating
            WHERE {
                ?film fm:title ?title .
                OPTIONAL { ?film fm:poster ?posterB }
                OPTIONAL { ?film fm:imdbRating ?ratingB }
                
                BIND(COALESCE(?posterB, 'https://placehold.co/192x288/1a1a1a/f5c518?text=N/A') AS ?poster)
                BIND(COALESCE(?ratingB, '0.0') AS ?ratingStr)
                
                FILTER(?ratingStr != '0.0' && ?ratingStr != 'N/A')
                
                BIND(xsd:float(?ratingStr) AS ?rating)
            } 
            ORDER BY DESC(?rating) 
            LIMIT 10
        ");
        
        $featuredFilmIds = $this->fuseki->query("
            SELECT DISTINCT ?film 
            WHERE {
                ?film fm:title ?title .
                ?film fm:plot ?plot .
                ?film fm:poster ?poster .
                
                FILTER(BOUND(?plot) && BOUND(?poster) && STRLEN(?plot) > 10)
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
        
        $year_list = array_map(fn($r) => (string)$r['year'], $years_query);
        $year_list = array_filter($year_list, fn($y) => $y !== 'N/A' && $y !== '');
        $year_list = array_unique($year_list);
        rsort($year_list);
        
        $rating_list = array_map(fn($r) => $r['rated'], $ratings_query);
        $rating_list = array_filter($rating_list, fn($r) => $r !== 'N/A' && $r !== '');
        $rating_list = array_unique($rating_list);
        sort($rating_list);

        $genre_list_raw = array_map(fn($r) => $r['genre'], $genres_query);
        $genre_list = array_filter($genre_list_raw, fn($g) => !empty(trim($g)));
        $genre_list = array_unique($genre_list);
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

    public function show(Request $request, $imdb_id)
    {
        if (!preg_match('/^tt\d+$/', $imdb_id)) {
            abort(404, 'Invalid IMDB ID format.');
        }

        $filmUri = "https://www.imdb.com/title/{$imdb_id}/";
        
        $sparqlSelect = "
            SELECT 
                ?film ?title ?year ?rated ?poster ?plot ?rating ?type
                ?released ?runtime ?awards ?metascore ?imdbVotes ?boxOffice
                (GROUP_CONCAT(DISTINCT ?actorUri; separator='||') AS ?actors)
                (GROUP_CONCAT(DISTINCT ?directorUri; separator='||') AS ?directors)
                (GROUP_CONCAT(DISTINCT ?writerUri; separator='||') AS ?writers)
                (GROUP_CONCAT(DISTINCT ?genreB; separator=', ') AS ?genres)
                (GROUP_CONCAT(DISTINCT ?languageB; separator=', ') AS ?languages)
                (GROUP_CONCAT(DISTINCT ?countryB; separator=', ') AS ?countries)
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
                OPTIONAL { ?film fm:imdbRating ?ratingB }
                OPTIONAL { ?film fm:genre ?genreB . }
                OPTIONAL { ?film fm:actor ?actorUri . }
                OPTIONAL { ?film fm:director ?directorUri . }
                
                OPTIONAL { ?film fm:released ?releasedB }
                OPTIONAL { ?film fm:runtime ?runtimeB }
                OPTIONAL { ?film fm:awards ?awardsB }
                OPTIONAL { ?film fm:metascore ?metascoreB } 
                OPTIONAL { ?film fm:imdbVotes ?imdbVotesB }
                OPTIONAL { ?film fm:boxOffice ?boxOfficeB }
                OPTIONAL { ?film fm:language ?languageB . }
                OPTIONAL { ?film fm:country ?countryB . }
                OPTIONAL { ?film fm:writer ?writerUri . }

                BIND(STRAFTER(STR(?typeUri), '#') AS ?type)
                BIND(COALESCE(?yearB, 'N/A') AS ?year)
                BIND(COALESCE(?ratedB, 'N/A') AS ?rated)
                BIND(COALESCE(?posterB, 'https://placehold.co/300x450/1a1a1a/f5c518?text=No+Poster') AS ?poster)
                BIND(COALESCE(?plotB, 'Plot not available.') AS ?plot)
                BIND(COALESCE(?ratingB, '0.0') AS ?ratingStr)
                
                BIND(IF(?ratingStr = 'N/A' || ?ratingStr = '0.0', 'N/A', ?ratingStr) AS ?rating)

                BIND(COALESCE(?releasedB, 'N/A') AS ?released)
                BIND(COALESCE(?runtimeB, 'N/A') AS ?runtime)
                BIND(COALESCE(?awardsB, 'N/A') AS ?awards)
                BIND(COALESCE(?metascoreB, 'N/A') AS ?metascore)
                BIND(COALESCE(?imdbVotesB, 'N/A') AS ?imdbVotes)
                BIND(COALESCE(?boxOfficeB, 'N/A') AS ?boxOffice)
            }
        ";

        $query = "
            {$sparqlSelect} 
            {$sparqlWhere}
            GROUP BY 
                ?film ?title ?year ?rated ?poster ?plot ?rating ?type
                ?released ?runtime ?awards ?metascore ?imdbVotes ?boxOffice
        ";
        
        $results = $this->fuseki->query($query);
        
        if (empty($results)) {
            abort(404);
        }

        $film = $results[0];
        
        $film['imdb_id'] = $imdb_id; 
        
        $cleanName = function($uri) {
            if (!is_string($uri)) return '';
            $fragment = parse_url($uri, PHP_URL_FRAGMENT); 
            if ($fragment) {
                return str_replace('_', ' ', urldecode($fragment));
            }
            return str_replace('_', ' ', basename(urldecode($uri)));
        };
        
        if (isset($film['actors'])) {
            $film['actors_list'] = is_string($film['actors']) ? 
                array_map($cleanName, explode('||', $film['actors'])) :
                array_map($cleanName, (array)$film['actors']);
        } else {
            $film['actors_list'] = [];
        }

        if (isset($film['directors'])) {
            $film['directors_list'] = is_string($film['directors']) ?
                array_map($cleanName, explode('||', $film['directors'])) :
                array_map($cleanName, (array)$film['directors']);
        } else {
            $film['directors_list'] = [];
        }

        if (isset($film['writers'])) {
            $film['writers_list'] = is_string($film['writers']) ?
                array_map($cleanName, explode('||', $film['writers'])) :
                array_map($cleanName, (array)$film['writers']);
        } else {
            $film['writers_list'] = [];
        }

        $dbpediaData = $this->dbpedia->getFilmInfo($film['title'], $film['year']);
        $film['dbpedia'] = $dbpediaData;

        $searchQuery = $request->input('query');

        return view('detail', [
            'film' => $film,
            'searchQuery' => $searchQuery
        ]);
    }
}