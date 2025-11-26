# 🎬 TetengFilm - Semantic Web Film Database

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel" alt="Laravel">
  <img src="https://img.shields.io/badge/SPARQL-1.1-E10098?style=for-the-badge" alt="SPARQL">
  <img src="https://img.shields.io/badge/Apache_Jena_Fuseki-4.x-00ADD8?style=for-the-badge" alt="Fuseki">
  <img src="https://img.shields.io/badge/RDF-Semantic_Web-orange?style=for-the-badge" alt="RDF">
</p>

> **TetengFilm** adalah aplikasi web pencarian dan eksplorasi film berbasis **Semantic Web** menggunakan teknologi **SPARQL**, **RDF**, dan **Apache Jena Fuseki**. Proyek ini mengintegrasikan data film Marvel & DC dari triplestore lokal dengan knowledge graph publik **DBpedia**.

---

## 📋 Daftar Isi

- [Fitur Utama](#-fitur-utama)
- [Teknologi](#-teknologi)
- [Arsitektur SPARQL](#-arsitektur-sparql)
- [Query SPARQL](#-query-sparql)
- [Instalasi](#-instalasi)
- [Struktur Proyek](#-struktur-proyek)
- [API Endpoints](#-api-endpoints)
- [Kontribusi](#-kontribusi)
- [Lisensi](#-lisensi)

---

## ✨ Fitur Utama

- 🔍 **Semantic Search** - Pencarian fuzzy dengan normalisasi teks otomatis
- 🎯 **Multi-Filter** - Filter berdasarkan tahun, genre, rating, tipe film
- ⭐ **Top Picks** - Daftar film dengan rating IMDb tertinggi
- 🎨 **Featured Films** - Carousel film unggulan
- 📊 **Detailed Information** - Informasi lengkap: cast, crew, awards, budget
- 🌐 **Federated Query** - Integrasi dengan DBpedia untuk data tambahan
- 📱 **Responsive Design** - UI modern dengan Tailwind CSS
- ⚡ **High Performance** - Pagination dan caching optimized

---

## 🛠 Teknologi

### Backend
- **Laravel 11.x** - PHP Framework
- **Apache Jena Fuseki** - RDF Triplestore
- **EasyRDF** - PHP library untuk SPARQL client
- **SPARQL 1.1** - Query language untuk RDF

### Frontend
- **Tailwind CSS** - Utility-first CSS framework
- **Alpine.js** - Lightweight JavaScript framework
- **Blade Templates** - Laravel templating engine

### Semantic Web
- **RDF/XML** - Format data semantic
- **DBpedia** - Public knowledge graph
- **Custom Ontology** - Film & Person ontology

---

## 🏗 Arsitektur SPARQL

### Namespace & Prefixes

```sparql
PREFIX fm: <http://www.example.com/film#>
PREFIX person: <http://www.example.com/person#>
PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
PREFIX dbo: <http://dbpedia.org/ontology/>
```

### Data Sources

1. **Local Fuseki Triplestore**
   - Dataset: Film Marvel & DC
   - Endpoint: `http://localhost:3030/filmDataset`
   - Data: `film_marvel_dc.rdf`

2. **DBpedia SPARQL Endpoint**
   - Endpoint: `https://dbpedia.org/sparql`
   - Data: Budget, box office, production info

---

## 🔎 Query SPARQL

### 1. **Pencarian Film dengan Multi-Filter**

Query ini mendukung pencarian fuzzy, filter tahun, genre, rating, dan sorting dinamis.

```sparql
SELECT DISTINCT ?film ?title ?year ?rated ?poster ?plot ?rating ?type
    (GROUP_CONCAT(DISTINCT ?actorUri; separator='||') AS ?actors)
    (GROUP_CONCAT(DISTINCT ?directorUri; separator='||') AS ?directors)
WHERE {
    ?film fm:title ?title .
    ?film fm:type ?typeUri .
    BIND(STRAFTER(STR(?typeUri), '#') AS ?type)
    
    # Optional fields dengan default values
    OPTIONAL { ?film fm:year ?yearB }
    OPTIONAL { ?film fm:rated ?ratedB }
    OPTIONAL { ?film fm:poster ?posterB }
    OPTIONAL { ?film fm:plot ?plotB }
    OPTIONAL { ?film fm:imdbRating ?ratingB }
    OPTIONAL { ?film fm:actor ?actorUri }
    OPTIONAL { ?film fm:director ?directorUri }
    
    # Fuzzy search - normalisasi teks
    BIND(LCASE(?title) AS ?lcaseTitle)
    BIND(REPLACE(REPLACE(REPLACE(?lcaseTitle, ' ', ''), '4', 'a'), '1', 'i') AS ?cleanTitle)
    
    # Filter berdasarkan query
    FILTER(CONTAINS(?cleanTitle, 'ironman'))
    
    # Default values dengan COALESCE
    BIND(COALESCE(?yearB, 'N/A') AS ?year)
    BIND(COALESCE(?ratedB, 'N/A') AS ?rated)
    BIND(COALESCE(?posterB, 'https://placehold.co/100x150') AS ?poster)
    BIND(COALESCE(?plotB, 'Plot not available.') AS ?plot)
    BIND(IF(?ratingB = 'N/A', 0.0, xsd:float(?ratingB)) AS ?rating)
}
GROUP BY ?film ?title ?year ?rated ?poster ?plot ?rating ?type
ORDER BY DESC(?rating)
LIMIT 42
```

**Fitur Query:**
- ✅ Fuzzy search dengan normalisasi (4→a, 1→i, 0→o, 3→e)
- ✅ Multi-field search (title, plot, actor, director, writer, country)
- ✅ GROUP_CONCAT untuk aggregate multiple values
- ✅ Type casting dengan xsd:float untuk sorting numerik
- ✅ OPTIONAL untuk handle missing data
- ✅ Pagination dengan LIMIT & OFFSET

---

### 2. **Detail Film Lengkap**

Query untuk mengambil semua informasi detail dari satu film spesifik.

```sparql
SELECT 
    ?film ?title ?year ?rated ?poster ?plot ?rating ?type
    ?released ?runtime ?awards ?metascore ?imdbVotes ?boxOffice
    (GROUP_CONCAT(DISTINCT ?actorUri; separator='||') AS ?actors)
    (GROUP_CONCAT(DISTINCT ?directorUri; separator='||') AS ?directors)
    (GROUP_CONCAT(DISTINCT ?writerUri; separator='||') AS ?writers)
    (GROUP_CONCAT(DISTINCT ?genreB; separator=', ') AS ?genres)
    (GROUP_CONCAT(DISTINCT ?languageB; separator=', ') AS ?languages)
    (GROUP_CONCAT(DISTINCT ?countryB; separator=', ') AS ?countries)
WHERE {
    BIND(<https://www.imdb.com/title/tt0371746/> AS ?film)
    
    ?film fm:title ?title .
    ?film fm:type ?typeUri .
    
    # All optional fields
    OPTIONAL { ?film fm:year ?yearB }
    OPTIONAL { ?film fm:rated ?ratedB }
    OPTIONAL { ?film fm:poster ?posterB }
    OPTIONAL { ?film fm:plot ?plotB }
    OPTIONAL { ?film fm:imdbRating ?ratingB }
    OPTIONAL { ?film fm:genre ?genreB }
    OPTIONAL { ?film fm:actor ?actorUri }
    OPTIONAL { ?film fm:director ?directorUri }
    OPTIONAL { ?film fm:writer ?writerUri }
    OPTIONAL { ?film fm:released ?releasedB }
    OPTIONAL { ?film fm:runtime ?runtimeB }
    OPTIONAL { ?film fm:awards ?awardsB }
    OPTIONAL { ?film fm:metascore ?metascoreB }
    OPTIONAL { ?film fm:imdbVotes ?imdbVotesB }
    OPTIONAL { ?film fm:boxOffice ?boxOfficeB }
    OPTIONAL { ?film fm:language ?languageB }
    OPTIONAL { ?film fm:country ?countryB }
    
    # Bindings dengan default values
    BIND(STRAFTER(STR(?typeUri), '#') AS ?type)
    BIND(COALESCE(?yearB, 'N/A') AS ?year)
    BIND(COALESCE(?ratedB, 'N/A') AS ?rated)
    BIND(COALESCE(?posterB, 'https://placehold.co/300x450') AS ?poster)
    BIND(COALESCE(?plotB, 'Plot not available.') AS ?plot)
    BIND(COALESCE(?ratingB, '0.0') AS ?rating)
    BIND(COALESCE(?releasedB, 'N/A') AS ?released)
    BIND(COALESCE(?runtimeB, 'N/A') AS ?runtime)
    BIND(COALESCE(?awardsB, 'N/A') AS ?awards)
    BIND(COALESCE(?metascoreB, 'N/A') AS ?metascore)
    BIND(COALESCE(?imdbVotesB, 'N/A') AS ?imdbVotes)
    BIND(COALESCE(?boxOfficeB, 'N/A') AS ?boxOffice)
}
GROUP BY ?film ?title ?year ?rated ?poster ?plot ?rating ?type
         ?released ?runtime ?awards ?metascore ?imdbVotes ?boxOffice
```

**Data yang Diambil:**
- Info Dasar: title, year, rated, poster, plot, rating
- Cast & Crew: actors, directors, writers
- Production: genres, languages, countries
- Statistics: metascore, imdbVotes, awards, boxOffice

---

### 3. **Top Picks - Film Rating Tertinggi**

```sparql
SELECT ?film ?title ?poster ?rating
WHERE {
    ?film fm:title ?title .
    OPTIONAL { ?film fm:poster ?posterB }
    OPTIONAL { ?film fm:imdbRating ?ratingB }
    
    BIND(COALESCE(?posterB, 'https://placehold.co/192x288') AS ?poster)
    BIND(COALESCE(?ratingB, '0.0') AS ?ratingStr)
    
    FILTER(?ratingStr != '0.0' && ?ratingStr != 'N/A')
    
    BIND(xsd:float(?ratingStr) AS ?rating)
} 
ORDER BY DESC(?rating) 
LIMIT 10
```

**Fungsi**: Menampilkan 10 film dengan rating IMDb tertinggi di homepage.

---

### 4. **Statistics Queries - Filter Dropdown**

#### Years Available
```sparql
SELECT DISTINCT ?year 
WHERE { 
    ?s fm:title ?title ; fm:year ?year 
} 
ORDER BY ?year
```

#### Film Types
```sparql
SELECT DISTINCT (STRAFTER(STR(?typeUri), '#') AS ?type) 
WHERE { 
    ?s fm:title ?title ; fm:type ?typeUri 
} 
ORDER BY ?type
```

#### Ratings Available
```sparql
SELECT DISTINCT ?rated 
WHERE { 
    ?s fm:title ?title ; fm:rated ?rated 
} 
ORDER BY ?rated
```

#### Genres Available
```sparql
SELECT DISTINCT ?genre 
WHERE { 
    ?s fm:title ?title ; fm:genre ?genre 
} 
ORDER BY ?genre
```

**Fungsi**: Mengambil daftar unik untuk populate dropdown filter.

---

### 5. **DBpedia Integration - Budget Information**

Query ke endpoint eksternal untuk data tambahan.

```sparql
PREFIX dbo: <http://dbpedia.org/ontology/>

SELECT ?budget
WHERE {
    <http://dbpedia.org/resource/Iron_Man_(film)> a dbo:Film .
    OPTIONAL { 
        <http://dbpedia.org/resource/Iron_Man_(film)> dbo:budget ?budget 
    }
}
LIMIT 1
```

**Strategi**: Mencoba multiple URI patterns untuk meningkatkan success rate.

---

## 🚀 Instalasi

### Prerequisites

- PHP >= 8.2
- Composer
- Node.js & NPM
- Apache Jena Fuseki 4.x

### Steps

1. **Clone Repository**
```bash
git clone https://github.com/Wira0509/Tubes-Web-Semantik.git
cd Tubes-Web-Semantik
```

2. **Install Dependencies**
```bash
composer install
npm install
```

3. **Environment Setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure Fuseki Endpoint**
Edit `.env`:
```env
FUSEKI_ENDPOINT=http://localhost:3030/filmDataset/query
```

5. **Start Fuseki Server**
```bash
cd /path/to/fuseki
./fuseki-server --update --mem /filmDataset
```

6. **Load RDF Data**
```bash
# Via Fuseki UI: http://localhost:3030
# Upload: database/seeders/film_marvel_dc.rdf
```

7. **Start Development Server**
```bash
npm run dev
php artisan serve
```

8. **Access Application**
```
http://localhost:8000
```

---

## 📁 Struktur Proyek

```
Tubes-Web-Semantik/
├── app/
│   ├── Http/Controllers/
│   │   └── FilmController.php          # Main controller dengan SPARQL queries
│   ├── Services/
│   │   ├── FusekiService.php           # Service untuk Fuseki triplestore
│   │   └── DBpediaService.php          # Service untuk DBpedia integration
│   └── Models/
├── database/
│   └── seeders/
│       └── film_marvel_dc.rdf          # RDF dataset
├── resources/
│   ├── views/
│   │   ├── search.blade.php            # Homepage dengan search
│   │   └── detail.blade.php            # Film detail page
│   └── css/
├── routes/
│   └── web.php                         # Route definitions
└── README.md
```

---

## 🌐 API Endpoints

### Web Routes

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/` | Homepage dengan search & filters |
| GET | `/film/{imdb_id}` | Detail film berdasarkan IMDb ID |

### Query Parameters (Search)

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `query` | string | Search query | `?query=iron+man` |
| `letter` | string | Filter by first letter | `?letter=A` |
| `year` | string | Filter by year | `?year=2008` |
| `type` | string | Filter by type | `?type=movie` |
| `rated` | string | Filter by rating | `?rated=PG-13` |
| `genre` | string | Filter by genre | `?genre=Action` |
| `sort` | string | Sort order | `?sort=rating_desc` |
| `page` | int | Page number | `?page=2` |

### Sort Options

- `title_asc` - Title A-Z
- `title_desc` - Title Z-A
- `year_asc` - Year ascending
- `year_desc` - Year descending
- `rating_asc` - Rating low to high
- `rating_desc` - Rating high to low

---

## 🎯 Keunggulan Semantic Web

### Dibandingkan Database Relasional

| Feature | SQL Database | SPARQL/RDF |
|---------|-------------|------------|
| Data Model | Fixed schema (tables) | Flexible graph |
| Relationships | Foreign keys | Direct links (triples) |
| Schema Changes | Complex migrations | Add properties freely |
| Federated Queries | Complex joins across DBs | Native federation |
| Semantic Meaning | Limited | Rich ontologies |
| Inference | Manual/triggers | Built-in reasoning |

### Contoh Kasus: Mencari "Iron Man 4"

**SQL Approach:**
```sql
SELECT * FROM films 
WHERE (title LIKE '%iron%' AND title LIKE '%man%' AND title LIKE '%4%')
   OR (title LIKE '%ironman%' AND title LIKE '%4%')
```
❌ Tidak match jika user typo: "ironman4", "lron man 4", "iron-man IV"

**SPARQL Approach:**
```sparql
BIND(REPLACE(REPLACE(LCASE(?title), ' ', ''), '4', 'a') AS ?clean)
FILTER(CONTAINS(?clean, 'ironmana'))
```
✅ Match: "Iron Man 4", "IronMan 4", "iron-man 4", "lron Man a"

---

## 📊 Performance Optimizations

1. **Subquery Pattern** - Filtering sebelum JOIN untuk reduce hasil
2. **Pagination** - LIMIT/OFFSET untuk load data secara bertahap
3. **OPTIONAL Optimization** - Hanya query field yang dibutuhkan
4. **Caching** - Laravel cache untuk query results
5. **Indexed Properties** - Fuseki indexing untuk title, year, rating

---

## 🔧 Development

### Add New SPARQL Query

Edit `app/Services/FusekiService.php`:

```php
public function customQuery($params) {
    $sparql = "
        SELECT ?film ?title
        WHERE {
            ?film fm:title ?title .
            FILTER(CONTAINS(?title, '{$params['search']}'))
        }
        LIMIT 10
    ";
    
    return $this->query($sparql);
}
```

### Add New Filter

Edit `app/Http/Controllers/FilmController.php`:

```php
if ($language) {
    $escapedLanguage = addslashes($language);
    $filterClause .= " ?film fm:language ?lang . 
                       FILTER(?lang = '{$escapedLanguage}') \n";
}
```

---

## 🤝 Kontribusi

Kontribusi sangat diterima! Silakan:

1. Fork repository
2. Buat branch fitur (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

---

## 📝 Lisensi

Proyek ini menggunakan lisensi MIT. Lihat file `LICENSE` untuk detail.

---

## 👥 Tim Pengembang

- **Wira0509** - [@Wira0509](https://github.com/Wira0509)

---

## 📚 Referensi

- [SPARQL 1.1 Specification](https://www.w3.org/TR/sparql11-query/)
- [Apache Jena Fuseki Documentation](https://jena.apache.org/documentation/fuseki2/)
- [EasyRDF Documentation](https://www.easyrdf.org/)
- [DBpedia SPARQL Endpoint](https://wiki.dbpedia.org/OnlineAccess)
- [RDF 1.1 Primer](https://www.w3.org/TR/rdf11-primer/)

---

<p align="center">
  <strong>Built with ❤️ using Semantic Web Technologies</strong>
</p>

<p align="center">
  <sub>Tugas Besar Web Semantik - 2025</sub>
</p>

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
