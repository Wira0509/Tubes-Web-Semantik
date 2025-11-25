<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FusekiService; // Gunakan service baru kita
use Illuminate\Pagination\LengthAwarePaginator; // Untuk paginasi manual

class FilmController extends Controller
{
    protected $fuseki;
    protected $dbpedia;

    // Inject FusekiService and DBpediaService
    public function __construct(FusekiService $fuseki, \App\Services\DBpediaService $dbpedia)
    {
        $this->fuseki = $fuseki;
        $this->dbpedia = $dbpedia;
    }

    /**
     * Helper function untuk membersihkan nama dari URI
     * (Contoh: .../person#Matt_Damon -> "Matt Damon")
     */
    private function cleanNameFromUri($uri) {
        if (!is_string($uri)) {
            return 'Error:BukanString';
        }
        // 1. Coba ambil bagian setelah tanda # (fragment)
        $fragment = parse_url($uri, PHP_URL_FRAGMENT); 
        if ($fragment) {
            // 2. Jika ada, bersihkan (Matt_Damon -> Matt Damon)
            return str_replace('_', ' ', urldecode($fragment));
        }
        // 3. Jika tidak ada #, gunakan cara lama sebagai cadangan (mengambil bagian terakhir)
        return str_replace('_', ' ', basename(urldecode($uri)));
    }


    /**
     * Menampilkan daftar film yang bisa difilter, di-search, dan di-paginate.
     */
    public function search(Request $request)
    {
        // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        // === PERBAIKAN: Hapus definisi $prefixes. ===
        // Biarkan FusekiService yang menangani prefix.
        // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        // $prefixes = " ... "; // (Baris ini dihapus)

        // 1. Ambil semua parameter filter
        $searchQuery = $request->input('query');
        
        // ============================================================
        // 🎯 IMDb ID DETECTION - Direct Redirect to Detail Page
        // ============================================================
        // Jika user search dengan IMDb ID format (tt + angka), redirect langsung ke detail
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

        // 2. Bangun Klausa WHERE untuk FILTERING
        // Ini adalah properti wajib minimum yang harus dimiliki film
        $filterClause = "
            ?film fm:title ?title .
            ?film fm:type ?typeUri .
            BIND(STRAFTER(STR(?typeUri), '#') AS ?type)
        ";
        
        // ==================================================================
        // === PERBAIKAN LOGIKA FILTER: Menghapus 'OPTIONAL' ===
        // ==================================================================
        
        if ($searchQuery) {
            
            // 1. Bersihkan query pencarian di sisi PHP
            $cleanSearchQuery = strtolower($searchQuery);
            $cleanSearchQuery = str_replace(
                [' ', '-', ':', '_', '4', '1', '0', '3'], 
                ['', '', '', '', 'a', 'i', 'o', 'e'], 
                $cleanSearchQuery
            );

            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            // === PERBAIKAN KEAMANAN (SPARQL INJECTION) ===
            // Kita harus meng-escape tanda kutip ' dan "
            // addslashes() akan mengubah ' menjadi \'
            // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            $escapedSearchQuery = addslashes($searchQuery);
            $escapedCleanSearchQuery = addslashes($cleanSearchQuery);
            
            // 🎬 Deteksi jika search query mengandung format IMDb ID (tt + angka)
            $isImdbSearch = preg_match('/tt\d+/i', $searchQuery);

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

                BIND(IF(BOUND(?cleanPlot_intermediate), ?cleanPlot_intermediate, '') AS ?cleanPlot)
                BIND(IF(BOUND(?cleanActor_intermediate), ?cleanActor_intermediate, '') AS ?cleanActor)
                BIND(IF(BOUND(?cleanDirector_intermediate), ?cleanDirector_intermediate, '') AS ?cleanDirector)
                BIND(IF(BOUND(?cleanWriter_intermediate), ?cleanWriter_intermediate, '') AS ?cleanWriter)

                FILTER (
                    CONTAINS(?cleanTitle, '{$escapedCleanSearchQuery}') ||
                    CONTAINS(?cleanPlot, '{$escapedCleanSearchQuery}') ||
                    CONTAINS(LCASE(?year), LCASE('{$escapedSearchQuery}')) || 
                    CONTAINS(?cleanActor, '{$escapedCleanSearchQuery}') ||
                    CONTAINS(?cleanDirector, '{$escapedCleanSearchQuery}') ||
                    CONTAINS(?cleanWriter, '{$escapedCleanSearchQuery}')" . 
                    ($isImdbSearch ? " || CONTAINS(LCASE(STR(?film)), LCASE('{$escapedSearchQuery}'))" : "") . "
                )
            ";
        }
        
        // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        // === PERBAIKAN KEAMANAN (SPARQL INJECTION) PADA SEMUA FILTER ===
        // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!

        // Jika filter surat dipilih, tambahkan filternya
        if ($letter) {
            $escapedLetter = addslashes($letter);
            $filterClause .= " FILTER (STRSTARTS(LCASE(?title), LCASE('{$escapedLetter}'))) \n";
        }
        
        // Jika filter tahun dipilih, film HARUS memiliki tahun itu
        if ($year) {
            $escapedYear = addslashes($year);
            $filterClause .= " ?film fm:year ?yearF . FILTER (STR(?yearF) = '{$escapedYear}') \n";
        }
        
        // Jika filter tipe dipilih, gunakan BIND yang sudah ada
        if ($type) {
            $escapedType = addslashes($type);
            $filterClause .= " FILTER (?type = '{$escapedType}') \n";
        }
        
        // Jika filter rating usia dipilih, film HARUS memiliki rating itu
        if ($rated) {
            $escapedRated = addslashes($rated);
            $filterClause .= " ?film fm:rated ?ratedF . FILTER (?ratedF = '{$escapedRated}') \n";
        }
        
        // Jika filter genre dipilih, film HARUS memiliki genre itu
        if ($genre) {
            $escapedGenre = addslashes($genre);
            $filterClause .= " ?film fm:genre ?genreF . FILTER (LCASE(?genreF) = LCASE('{$escapedGenre}')) \n";
        }
        
        // ==================================================================
        // === AKHIR DARI PERBAIKAN LOGIKA FILTER ===
        // ==================================================================


        // ============================================================
        // QUERY 1: COUNT QUERY - Menghitung total film untuk pagination
        // ============================================================
        // Tujuan: Mendapatkan jumlah total film yang sesuai dengan filter
        // Output: Integer tunggal (contoh: 4875)
        // Digunakan untuk: Membuat pagination yang akurat
        $countQuery = "SELECT (COUNT(DISTINCT ?film) as ?total) WHERE { {$filterClause} }";
        // COUNT(DISTINCT ?film): Hitung film unik, hindari duplikasi
        // Menggunakan filterClause yang sama dengan main query
        $total = $this->fuseki->queryValue($countQuery);

        // ============================================================
        // QUERY 2 & 3: TWO-STAGE QUERY PATTERN (Optimasi Performance)
        // ============================================================
        // Teknik: Pisah query jadi 2 tahap untuk speedup 60x
        // Stage 1 (Subquery): Filter + Sort -> ambil 42 ID saja
        // Stage 2 (Outer): Load detail lengkap hanya untuk 42 film
        
        // 4. Bangun kueri utama untuk MENGAMBIL DATA
        $sparqlSelect = "
            SELECT DISTINCT ?film ?title ?year ?rated ?poster ?plot ?rating ?type
                (GROUP_CONCAT(DISTINCT ?actorUri; separator='||') AS ?actors)
                (GROUP_CONCAT(DISTINCT ?directorUri; separator='||') AS ?directors)
        ";
        // GROUP_CONCAT: Gabungkan multiple actors/directors jadi satu string
        // separator='||': Pemisah untuk split di PHP nanti
        // Contoh result: "Robert_Downey_Jr.||Gwyneth_Paltrow||Terrence_Howard"

        // ============================================================
        // SUBQUERY (Stage 1): Filter dan ambil ID film saja
        // ============================================================
        $subQuery = "
            SELECT DISTINCT ?film ?title ?type
            WHERE { {$filterClause} }
            %ORDER_BY_placeholder%
            LIMIT {$perPage}
            OFFSET {$offset}
        ";
        // DISTINCT: Hindari duplikasi film
        // Hanya ambil 3 properties (film, title, type) -> SANGAT CEPAT
        // LIMIT: Ambil 42 film per halaman
        // OFFSET: Skip film sebelumnya untuk pagination
        //   Hal 1: OFFSET 0 (skip 0)
        //   Hal 2: OFFSET 42 (skip 42)
        //   Hal 3: OFFSET 84 (skip 84)

        // 5. Bangun string ORDER BY dinamis
        $orderBy = "";
        $finalOrderBy = ""; // Variabel baru untuk kueri akhir

        switch ($sort) {
            case 'rating_desc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?ratingB", $subQuery);
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:imdbRating ?ratingB } ", $subQuery);
                // Untuk SubQuery (menggunakan ?ratingB)
                $orderBy = "ORDER BY DESC(IF(COALESCE(?ratingB, '0.0') = 'N/A', 0.0, xsd:float(COALESCE(?ratingB, '0.0'))))"; 
                // Untuk Kueri Akhir (menggunakan ?rating)
                $finalOrderBy = "ORDER BY DESC(?rating)";
                break;
            case 'rating_asc': 
                $subQuery = str_replace("?title ?type", "?title ?type ?ratingB", $subQuery);
                $subQuery = str_replace("WHERE {", "WHERE { OPTIONAL { ?film fm:imdbRating ?ratingB } ", $subQuery);
                // Untuk SubQuery (menggunakan ?ratingB)
                $orderBy = "ORDER BY ASC(IF(COALESCE(?ratingB, '0.0') = 'N/A', 0.0, xsd:float(COALESCE(?ratingB, '0.0'))))"; 
                // Untuk Kueri Akhir (menggunakan ?rating)
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
                $finalOrderBy = "ORDER BY ASC(?year)"; // ASC ditambahkan untuk konsistensi
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
        
        // ============================================================
        // MAIN DATA QUERY (Stage 2): Load detail untuk 42 film
        // ============================================================
        // 6. Jalankan kueri data (Hapus {$prefixes})
        $dataQuery = "
            {$sparqlSelect} 
            WHERE {
                { {$subQuery} }
                
                # OPTIONAL: Properti tidak wajib ada (film bisa tanpa data ini)
                OPTIONAL { ?film fm:year ?yearB }
                OPTIONAL { ?film fm:rated ?ratedB }
                OPTIONAL { ?film fm:poster ?posterB }
                OPTIONAL { ?film fm:plot ?plotB }
                OPTIONAL { ?film fm:imdbRating ?ratingB } 
                OPTIONAL { ?film fm:actor ?actorUri . }
                OPTIONAL { ?film fm:director ?directorUri . }

                # BIND + COALESCE: Set default value jika data kosong
                BIND(COALESCE(?yearB, 'N/A') AS ?year)
                BIND(COALESCE(?ratedB, 'N/A') AS ?rated)
                BIND(COALESCE(?posterB, 'https://placehold.co/100x150/1a1a1a/f5c518?text=No+Poster') AS ?poster)
                BIND(COALESCE(?plotB, 'Plot not available.') AS ?plot)
                BIND(COALESCE(?ratingB, '0.0') AS ?ratingStr)
                
                # xsd:float: Convert string ke float untuk sorting numerik yang benar
                # Tanpa casting: sort alfabetis (SALAH)
                # Dengan casting: sort numerik (BENAR)
                BIND(IF(?ratingStr = 'N/A', 0.0, xsd:float(?ratingStr)) AS ?rating)
            }
            # GROUP BY: Wajib karena pakai GROUP_CONCAT (aggregation)
            GROUP BY ?film ?title ?year ?rated ?poster ?plot ?rating ?type
            {$finalOrderBy}
        ";
        // Performance: Load hanya 42 film x 10 properties = 420 triples (CEPAT!)
        // vs load 5000 film x 10 properties = 50,000 triples (LAMBAT!)
        
        $results = $this->fuseki->query($dataQuery);

        // Fungsi helper untuk membersihkan nama
        $cleanName = function($uri) {
            if (!is_string($uri)) return '';
            $fragment = parse_url($uri, PHP_URL_FRAGMENT); 
            if ($fragment) {
                return str_replace('_', ' ', urldecode($fragment));
            }
            return str_replace('_', ' ', basename(urldecode($uri)));
        };

        // Memproses hasil untuk mengubah string '||' menjadi array nama
        $processedResults = array_map(function($film) use ($cleanName) {
            // Tangani Aktor
            if (isset($film['actors'])) {
                if (is_string($film['actors'])) {
                    $film['actors_list'] = array_map($cleanName, explode('||', $film['actors']));
                } elseif (is_array($film['actors'])) { // Jika hanya ada 1 aktor, FusekiSvc mungkin mengembalikan array
                    $film['actors_list'] = array_map($cleanName, $film['actors']);
                }
            } else {
                $film['actors_list'] = [];
            }
        
            // Tangani Sutradara
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
        
        // 7. Buat Paginator Laravel secara Manual
        $films = new LengthAwarePaginator(
            $processedResults, // Gunakan hasil yang sudah diproses
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // ============================================================
        // QUERY 4-7: DROPDOWN FILTER QUERIES
        // ============================================================
        // Tujuan: Auto-populate dropdown options dari data yang ada
        // 8. Ambil data untuk dropdown filter (Hapus {$prefixes})
        
        // QUERY 4: Years Dropdown
        // DISTINCT: Hilangkan duplikasi (2008 muncul sekali saja)
        // Semicolon (;): Shorthand untuk subject sama (?s fm:title DAN ?s fm:year)
        $years_query = $this->fuseki->query("SELECT DISTINCT ?year WHERE { ?s fm:title ?title ; fm:year ?year . } ORDER BY ?year");
        
        // QUERY 5: Types Dropdown
        // STRAFTER: Extract string setelah '#' dari URI
        // Input: http://example.com/film#movie -> Output: movie
        $types = $this->fuseki->query("SELECT DISTINCT (STRAFTER(STR(?typeUri), '#') AS ?type) WHERE { ?s fm:title ?title ; fm:type ?typeUri . } ORDER BY ?type");
        
        // QUERY 6: Ratings Dropdown (G, PG, PG-13, R, NC-17)
        $ratings_query = $this->fuseki->query("SELECT DISTINCT ?rated WHERE { ?s fm:title ?title ; fm:rated ?rated . } ORDER BY ?rated");
        
        // QUERY 7: Genres Dropdown (Action, Drama, Sci-Fi, dll)
        $genres_query = $this->fuseki->query("SELECT DISTINCT ?genre WHERE { ?s fm:title ?title ; fm:genre ?genre . } ORDER BY ?genre");

        // ============================================================
        // QUERY 8: TOP PICKS - 10 Film Rating Tertinggi
        // ============================================================
        // Tujuan: Tampilkan 10 film dengan rating IMDb terbaik
        // Output: Array of 10 objects dengan poster dan rating
        // 9. Ambil Top Picks (Hapus {$prefixes})
        $topPicks = $this->fuseki->query("
            SELECT ?film ?title ?poster ?rating
            WHERE {
                ?film fm:title ?title .
                OPTIONAL { ?film fm:poster ?posterB }
                OPTIONAL { ?film fm:imdbRating ?ratingB }
                
                # COALESCE: Default value jika kosong
                BIND(COALESCE(?posterB, 'https://placehold.co/192x288/1a1a1a/f5c518?text=N/A') AS ?poster)
                BIND(COALESCE(?ratingB, '0.0') AS ?ratingStr)
                
                # FILTER: Buang film tanpa rating valid
                # && = AND operator (kedua kondisi harus true)
                FILTER(?ratingStr != '0.0' && ?ratingStr != 'N/A')
                
                # xsd:float: Cast ke float untuk sort numerik
                BIND(xsd:float(?ratingStr) AS ?rating)
            } 
            # ORDER BY DESC: Sort dari tinggi ke rendah (9.3, 9.2, 9.0, ...)
            ORDER BY DESC(?rating) 
            # LIMIT 10: Ambil 10 film teratas saja
            LIMIT 10
        ");
        // Result: The Shawshank Redemption (9.3), The Godfather (9.2), dll
        
        // ============================================================
        // QUERY 9: FEATURED FILMS IDs - Kandidat untuk Carousel
        // ============================================================
        // Tujuan: Cari film yang cocok untuk carousel (punya plot dan poster)
        // Output: ~800 film URIs yang eligible
        // 10. Ambil Featured Films (Hapus {$prefixes})
        $featuredFilmIds = $this->fuseki->query("
            SELECT DISTINCT ?film 
            WHERE {
                # Film HARUS punya title, plot, DAN poster (tidak pakai OPTIONAL)
                ?film fm:title ?title .
                ?film fm:plot ?plot .
                ?film fm:poster ?poster .
                
                # BOUND: Check apakah variabel punya value
                # STRLEN: Hitung panjang string, minimal 10 karakter
                # Tujuan: Buang plot kosong seperti 'N/A' atau ''
                FILTER(BOUND(?plot) && BOUND(?poster) && STRLEN(?plot) > 10)
            }
        ");
        // Result: Array of film URIs yang bisa masuk carousel
        
        $featuredFilmList = array_map(fn($r) => $r['film'], $featuredFilmIds);
        shuffle($featuredFilmList);
        $featuredFilms = [];

        if (!empty($featuredFilmList)) {
            // ============================================================
            // QUERY 10: FEATURED FILMS DETAILS - Data Lengkap Carousel
            // ============================================================
            // Tujuan: Ambil detail 7 film random untuk slideshow
            // Teknik: VALUES clause (seperti WHERE IN di SQL)
            $featuredFilms = $this->fuseki->query("
                SELECT ?film ?title ?poster ?plot
                WHERE {
                    # VALUES: Query specific URIs (SANGAT CEPAT ~50ms)
                    # Tidak perlu scan database, langsung lookup 7 film
                    VALUES ?film { <" . implode('> <', array_slice($featuredFilmList, 0, 7)) . "> }
                    ?film fm:title ?title .
                    ?film fm:poster ?poster .
                    ?film fm:plot ?plot .
                }
            ");
            // Result: 7 film dengan poster + plot untuk carousel
        }
        
        // 11. Kembalikan View
        
        // Proses daftar TAHUN
        $year_list = array_map(fn($r) => (string)$r['year'], $years_query);
        $year_list = array_filter($year_list, fn($y) => $y !== 'N/A' && $y !== '');
        $year_list = array_unique($year_list);
        rsort($year_list); // Urutkan dari terbaru ke terlama
        
        // Proses daftar RATING
        $rating_list = array_map(fn($r) => $r['rated'], $ratings_query);
        $rating_list = array_filter($rating_list, fn($r) => $r !== 'N/A' && $r !== '');
        $rating_list = array_unique($rating_list);
        sort($rating_list);

        // Proses daftar GENRE
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

    /**
     * Menampilkan halaman detail untuk satu film.
     */
    public function show($imdb_id)
    {
        // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        // === PERBAIKAN KEAMANAN & STABILITAS (TAMBAHAN) ===
        // Pastikan $imdb_id adalah format yang valid (tt + angka)
        // Ini mencegah SPARQL Injection/Syntax Error di BIND
        // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        if (!preg_match('/^tt\d+$/', $imdb_id)) {
            abort(404, 'Invalid IMDB ID format.');
        }

        // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        // === PERBAIKAN: Hapus definisi $prefixes. ===
        // Biarkan FusekiService yang menangani prefix.
        // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        // $prefixes = " ... "; // (Baris ini dihapus)

        // Construct film URI dari IMDb ID parameter
        $filmUri = "https://www.imdb.com/title/{$imdb_id}/";
        
        // ============================================================
        // QUERY 11: FILM DETAIL - 22 Properties untuk Halaman Detail
        // ============================================================
        // Tujuan: Ambil SEMUA data lengkap untuk 1 film
        // Output: 1 object dengan 22 properties
        
        // Kueri SELECT yang diperbarui untuk mengambil LEBIH BANYAK data
        // PERBAIKAN: Menambahkan ?film ke SELECT list
        $sparqlSelect = "
            SELECT 
                ?film ?title ?year ?rated ?poster ?plot ?rating ?type
                ?released ?runtime ?awards ?metascore ?imdbVotes ?boxOffice
                # GROUP_CONCAT dengan separator berbeda:
                # '||' untuk split di PHP jadi array
                # ', ' untuk langsung display sebagai string
                (GROUP_CONCAT(DISTINCT ?actorUri; separator='||') AS ?actors)
                (GROUP_CONCAT(DISTINCT ?directorUri; separator='||') AS ?directors)
                (GROUP_CONCAT(DISTINCT ?writerUri; separator='||') AS ?writers)
                (GROUP_CONCAT(DISTINCT ?genreB; separator=', ') AS ?genres)
                (GROUP_CONCAT(DISTINCT ?languageB; separator=', ') AS ?languages)
                (GROUP_CONCAT(DISTINCT ?countryB; separator=', ') AS ?countries)
        ";
        
        // Kueri WHERE yang diperbarui dengan LEBIH BANYAK OPTIONAL
        $sparqlWhere = "
            WHERE {
                # BIND: Set film spesifik dari URL parameter
                # Tidak perlu FILTER, langsung query 1 film exact
                BIND(<{$filmUri}> AS ?film)
                
                # Properties WAJIB (film harus punya ini)
                ?film fm:title ?title .
                ?film fm:type ?typeUri .

                # OPTIONAL: Properti tidak wajib (film bisa tanpa data ini)
                # Jika tidak ada, tidak error, hanya NULL
                OPTIONAL { ?film fm:year ?yearB }
                OPTIONAL { ?film fm:rated ?ratedB }
                OPTIONAL { ?film fm:poster ?posterB }
                OPTIONAL { ?film fm:plot ?plotB }
                OPTIONAL { ?film fm:imdbRating ?ratingB }
                OPTIONAL { ?film fm:genre ?genreB . }
                OPTIONAL { ?film fm:actor ?actorUri . }
                OPTIONAL { ?film fm:director ?directorUri . }
                
                # Properti TAMBAHAN untuk detail produksi
                OPTIONAL { ?film fm:released ?releasedB }
                OPTIONAL { ?film fm:runtime ?runtimeB }
                OPTIONAL { ?film fm:awards ?awardsB }
                
                # !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                # === PERBAIKAN TYPO  ===
                # !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
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
                
                # !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                # === PERBAIKAN BUG SINTAKS SPARQL ===
                # Mengganti 'OR' dengan operator '||'
                # !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
                BIND(IF(?ratingStr = 'N/A' || ?ratingStr = '0.0', 'N/A', ?ratingStr) AS ?rating)

                # BIND untuk properti TAMBAHAN
                BIND(COALESCE(?releasedB, 'N/A') AS ?released)
                BIND(COALESCE(?runtimeB, 'N/A') AS ?runtime)
                BIND(COALESCE(?awardsB, 'N/A') AS ?awards)
                BIND(COALESCE(?metascoreB, 'N/A') AS ?metascore)
                BIND(COALESCE(?imdbVotesB, 'N/A') AS ?imdbVotes)
                BIND(COALESCE(?boxOfficeB, 'N/A') AS ?boxOffice)
            }
        ";

        // Kueri GROUP BY yang diperbarui (Hapus {$prefixes})
        $query = "
            {$sparqlSelect} 
            {$sparqlWhere}
            # GROUP BY: WAJIB karena pakai GROUP_CONCAT (aggregation)
            # List semua variabel yang TIDAK di-agregasi
            GROUP BY 
                ?film ?title ?year ?rated ?poster ?plot ?rating ?type
                ?released ?runtime ?awards ?metascore ?imdbVotes ?boxOffice
        ";
        // Performance: Query 1 film spesifik (CEPAT ~180ms)
        
        $results = $this->fuseki->query($query);
        
        if (empty($results)) {
            abort(404);
        }

        $film = $results[0];
        
        // Menambahkan IMDB ID secara manual untuk tautan
        $film['imdb_id'] = $imdb_id; 
        
        // Fungsi helper untuk membersihkan nama
        $cleanName = function($uri) {
            if (!is_string($uri)) return '';
            $fragment = parse_url($uri, PHP_URL_FRAGMENT); 
            if ($fragment) {
                return str_replace('_', ' ', urldecode($fragment));
            }
            return str_replace('_', ' ', basename(urldecode($uri)));
        };
        
        // PERBAIKAN: Memeriksa apakah string atau array sebelum explode
        // Tangani Aktor
        if (isset($film['actors'])) {
            $film['actors_list'] = is_string($film['actors']) ? 
                array_map($cleanName, explode('||', $film['actors'])) :
                array_map($cleanName, (array)$film['actors']);
        } else {
            $film['actors_list'] = [];
        }

        // Tangani Sutradara
        if (isset($film['directors'])) {
            $film['directors_list'] = is_string($film['directors']) ?
                array_map($cleanName, explode('||', $film['directors'])) :
                array_map($cleanName, (array)$film['directors']);
        } else {
            $film['directors_list'] = [];
        }

        // Tangani Penulis
        if (isset($film['writers'])) {
            $film['writers_list'] = is_string($film['writers']) ?
                array_map($cleanName, explode('||', $film['writers'])) :
                array_map($cleanName, (array)$film['writers']);
        } else {
            $film['writers_list'] = [];
        }

        // Ambil budget dari DBpedia
        $dbpediaData = $this->dbpedia->getFilmInfo($film['title'], $film['year']);
        $film['dbpedia'] = $dbpediaData;

        return view('detail', [
            'film' => $film
        ]);
    }
}