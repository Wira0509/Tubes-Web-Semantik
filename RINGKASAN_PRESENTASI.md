# 📽️ Ringkasan Presentasi - IMDB Clone Project

## 🎯 **1. OVERVIEW (1 slide)**
- **Nama Proyek**: IMDB Clone
- **Framework**: Laravel 12
- **Database**: Apache Fuseki (RDF/SPARQL)
- **External API**: DBpedia
- **Fitur**: Pencarian, Filter, Detail Film, Top Picks, Carousel

---

## 🎬 **2. FILMCONTROLLER - Struktur (1 slide)**

### **Properties:**
- `$fuseki`: Instance FusekiService (query lokal)
- `$dbpedia`: Instance DBpediaService (query external)

### **Methods:**
1. `__construct()` - Dependency Injection
2. `cleanNameFromUri()` - Helper untuk bersihkan URI
3. `search()` - Halaman pencarian film
4. `show()` - Halaman detail film

---

## 🔍 **3. METHOD `search()` - Alur Kerja (3 slides)**

### **Slide 1: Input Processing**
- Ambil parameter dari request (query, year, genre, dll)
- **IMDb ID Detection**: Jika user search dengan format `tt1234567`, langsung redirect ke detail
- **Fuzzy Search**: Normalisasi query (hapus spasi, replace angka dengan huruf)

### **Slide 2: Query Building**
- **Filter Clause**: Bangun WHERE clause dinamis berdasarkan filter
- **Security**: `addslashes()` untuk prevent SPARQL Injection
- **Multi-field Search**: Cari di 6 field (title, plot, year, actor, director, writer)

### **Slide 3: Two-Stage Query Pattern**
- **Stage 1 (Subquery)**: Filter + Sort → ambil 42 ID saja (CEPAT)
- **Stage 2 (Main Query)**: Load detail lengkap hanya untuk 42 film
- **Performance**: 60x lebih cepat daripada load semua film sekaligus

---

## 📊 **4. QUERIES YANG DIGUNAKAN (1 slide)**

1. **COUNT Query**: Hitung total film untuk pagination
2. **Data Query**: Ambil 42 film dengan detail lengkap
3. **Dropdown Queries**: Years, Types, Ratings, Genres
4. **Top Picks Query**: 10 film rating tertinggi
5. **Featured Films Query**: Film untuk carousel (punya plot + poster)

---

## 🎯 **5. METHOD `show()` - Detail Film (1 slide)**

- **Validasi**: Cek format IMDb ID dengan regex
- **Query**: Ambil 22 properties lengkap untuk 1 film
- **Processing**: Convert URI ke nama bersih (actors, directors, writers)
- **DBpedia Integration**: Ambil budget dari external API

---

## 🔧 **6. FUSEKISERVICE - Penjelasan (2 slides)**

### **Slide 1: Setup**
- **Constructor**: Register RDF namespace, connect ke Fuseki endpoint
- **Prefixes**: Auto-inject ke semua query (fm:, person:, rdf:, dll)

### **Slide 2: Methods**
- **`query()`**: Execute SPARQL query, convert EasyRdf ke PHP array
- **`queryValue()`**: Untuk query COUNT (return integer)
- **`cleanName()`**: Helper untuk bersihkan URI

---

## 🌐 **7. DBPEDIASERVICE - Penjelasan (2 slides)**

### **Slide 1: Setup & Query Method**
- **Endpoint**: `https://dbpedia.org/sparql`
- **`query()`**: HTTP request via CURL, transform JSON ke PHP array
- **Error Handling**: Timeout 15 detik, logging untuk debugging

### **Slide 2: Get Film Info**
- **`getFilmInfo()`**: 
  - Construct 3 variasi URI (karena Wikipedia naming tidak konsisten)
  - Try each URI dengan SPARQL query
  - Return budget jika ditemukan
- **`formatCurrency()`**: 
  - Handle 3 format berbeda (full number, scientific notation, millions)
  - Format dengan comma separator: `$220,000,000`

---

## 🚀 **8. OPTIMASI PERFORMANCE (1 slide)**

1. ✅ **Two-Stage Query**: Pisah query jadi 2 tahap (60x speedup)
2. ✅ **LIMIT & OFFSET**: Pagination di level SPARQL
3. ✅ **DISTINCT**: Hindari duplikasi
4. ✅ **VALUES Clause**: Query specific URIs (sangat cepat)
5. ✅ **OPTIONAL**: Hanya load property yang ada

---

## 🔒 **9. KEAMANAN (1 slide)**

1. ✅ **SPARQL Injection Prevention**: `addslashes()` untuk escape input
2. ✅ **Input Validation**: Regex untuk validasi IMDb ID
3. ✅ **Error Handling**: Try-catch dan logging
4. ✅ **Type Checking**: Validasi tipe data

---

## 📈 **10. STATISTIK & METRIK (1 slide)**

- **Total Queries per Request**: 10 queries (search page)
- **Performance**: 
  - Search page: ~200ms (dengan optimasi)
  - Detail page: ~180ms
  - DBpedia query: 2-3 detik (external API)
- **Data Volume**: 
  - 42 film per halaman
  - 22 properties per film detail

---

## 🎯 **11. KESIMPULAN (1 slide)**

**Keunggulan Proyek:**
- ✅ Architecture yang scalable (service layer separation)
- ✅ Performance optimized (two-stage query)
- ✅ Security hardened (injection prevention)
- ✅ User-friendly (fuzzy search, multiple filters)
- ✅ Integrasi external API (DBpedia)

**Teknologi:**
- Laravel 12 (Framework)
- Apache Fuseki (RDF Database)
- SPARQL (Query Language)
- DBpedia (External Data Source)

---

## 📝 **CATATAN UNTUK PRESENTASI**

### **Poin Penting yang Harus Ditekankan:**
1. **Two-Stage Query Pattern** - Optimasi utama yang membuat aplikasi cepat
2. **SPARQL Injection Prevention** - Keamanan sangat penting
3. **Service Layer Separation** - Architecture yang baik untuk maintainability
4. **Fuzzy Search** - User experience yang lebih baik
5. **DBpedia Integration** - Menunjukkan kemampuan integrasi external API

### **Demo Flow:**
1. Tampilkan halaman search dengan filter
2. Tunjukkan pencarian dengan fuzzy search
3. Tampilkan halaman detail dengan data lengkap
4. Jelaskan query yang dijalankan di background

### **Q&A Preparation:**
- **Kenapa pakai RDF/SPARQL?** - Untuk semantic web, data terstruktur, query fleksibel
- **Kenapa pakai DBpedia?** - Data tambahan yang tidak ada di database lokal
- **Bagaimana handle error?** - Try-catch, logging, default values
- **Bagaimana scalability?** - Service layer, caching bisa ditambahkan

---

**Total Slides: ~11 slides**
**Durasi Presentasi: ~15-20 menit**

