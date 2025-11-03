<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TetengFilm: Peringkat, Saran, dan Tempat Mengetahui Film & Acara TV Terbaik</title>
    <!-- Memuat Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Memuat Alpine.js untuk tombol geser -->
    <script src="//unpkg.com/alpinejs" defer></script>
    <script>
        // Konfigurasi dark mode untuk Tailwind (opsional tapi disarankan)
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'imdb-yellow': '#f5c518',
                        'imdb-dark': '#121212',
                        'imdb-gray': '#1a1a1a',
                        'imdb-light-gray': '#222222',
                    }
                }
            }
        }
    </script>
    <style>
        /* CSS tambahan untuk menyembunyikan scrollbar horizontal */
        .no-scrollbar::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        .no-scrollbar {
            -ms-overflow-style: none;  /* IE dan Edge */
            scrollbar-width: none;  /* Firefox */
        }
        /* Style kustom untuk dropdown filter */
        select.filter-dropdown {
            background-color: #1a1a1a;
            border: 1px solid #333;
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22%23aaa%22%3E%3Cpath%20d%3D%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%20%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1.25em 1.25em;
            padding-right: 2rem; /* Ruang untuk ikon panah */
        }
        
        /* Helper untuk line-clamp (pembatasan teks) */
        .line-clamp-2 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }
        .line-clamp-3 {
            overflow: hidden;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }
    </style>
</head>
<body class="bg-imdb-dark text-white font-sans">

    <div class="container mx-auto max-w-4xl p-4 md:p-8">
        
        <header class="text-center mb-8">
            <h1 class="text-4xl font-bold text-imdb-yellow">TetengFilm</h1>
        </header>

        <!-- ============================================ -->
        <!-- =========== BAGIAN FEATURED (CAROUSEL SLIDE) =========== -->
        <!-- ============================================ -->
        @if(!empty($featuredFilms))
            <section 
                class="mb-12 relative rounded-lg overflow-hidden" 
                x-data="{ 
                    activeSlide: 0, 
                    totalSlides: {{ count($featuredFilms) }},
                    autoplay: null,
                    startAutoplay() {
                        this.autoplay = setInterval(() => { 
                            this.activeSlide = (this.activeSlide + 1) % this.totalSlides 
                        }, 5000); // Ganti setiap 5 detik
                    },
                    stopAutoplay() {
                        clearInterval(this.autoplay);
                    },
                    next() {
                        this.activeSlide = (this.activeSlide + 1) % this.totalSlides;
                        this.stopAutoplay();
                    },
                    prev() {
                        this.activeSlide = (this.activeSlide - 1 + this.totalSlides) % this.totalSlides;
                        this.stopAutoplay();
                    }
                }"
                x-init="startAutoplay()"
                @mouseenter="stopAutoplay()"
                @mouseleave="startAutoplay()"
            >
                <!-- Wadah untuk semua slide (bergerak) -->
                <div 
                    class="flex transition-transform duration-700 ease-in-out" 
                    :style="`transform: translateX(-${activeSlide * 100}%)`"
                >
                    @foreach($featuredFilms as $index => $featuredFilm)
                        @php($featuredId = last(explode('/', rtrim($featuredFilm['film'], '/'))))
                        
                        <!-- SATU SLIDE (Sekarang flex-shrink-0) -->
                        <div class="w-full flex-shrink-0 relative">
                            <!-- Background Blur (terpisah) -->
                            <div class="absolute inset-0 transform scale-125"
                                 style="background-image: url('{{ $featuredFilm['poster'] }}'); 
                                        background-size: cover; 
                                        background-position: center; 
                                        filter: blur(12px) brightness(0.3);">
                            </div>

                            <!-- Konten Jelas (di atas blur) -->
                            <div class="relative z-10 flex flex-col md:flex-row gap-6 md:gap-8 h-80 p-6 md:p-8">
                                <!-- Kiri: Poster Jelas -->
                                <a href="{{ route('film.show', $featuredId) }}" class="w-48 h-full flex-shrink-0 mx-auto md:mx-0">
                                    <img src="{{ $featuredFilm['poster'] }}" alt="{{ $featuredFilm['title'] }} Poster" 
                                         class="w-full h-full object-cover rounded-lg shadow-lg hover:opacity-80 transition-opacity duration-200">
                                </a>
                                <!-- Kanan: Detail -->
                                <div class="flex flex-col justify-center text-white text-center md:text-left">
                                    <h2 class="text-3xl font-bold mb-3">{{ $featuredFilm['title'] }}</h2>
                                    <p class="text-gray-300 text-lg mb-6 line-clamp-3">
                                        {{ \Illuminate\Support\Str::limit($featuredFilm['plot'], 150) }}
                                    </p>
                                    <a href="{{ route('film.show', $featuredId) }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-imdb-yellow text-black font-bold rounded-md w-auto md:w-max hover:bg-yellow-300 transition-colors mx-auto md:mx-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                                        </svg>
                                        Lihat Detail
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div> <!-- Akhir wadah slides -->

                <!-- Tombol Geser Kiri -->
                <button 
                    @click="prev()"
                    class="absolute top-1/2 -translate-y-1/2 left-0 z-10 bg-black/60 hover:bg-black/80 p-2 rounded-full text-white cursor-pointer"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>

                <!-- Tombol Geser Kanan -->
                <button 
                    @click="next()"
                    class="absolute top-1/2 -translate-y-1/2 right-0 z-10 bg-black/60 hover:bg-black/80 p-2 rounded-full text-white cursor-pointer"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </section>
        @endif
        <!-- ============================================ -->
        <!-- =========== AKHIR BAGIAN FEATURED =========== -->
        <!-- ============================================ -->


        <!-- =========== BAGIAN "WHAT TO WATCH" =========== -->
        <section 
            class="mb-12 relative" 
            x-data="{ 
                scroller: null,
                atStart: true, 
                atEnd: false,
                checkScroll() {
                    if (!this.scroller) return; // Penjaga jika scroller belum siap
                    this.atStart = this.scroller.scrollLeft <= 0;
                    this.atEnd = this.scroller.scrollLeft + this.scroller.clientWidth >= this.scroller.scrollWidth - 1;
                }
            }"
            x-init="
                scroller = $refs.scroller;
                $nextTick(() => checkScroll()); // Periksa setelah DOM di-render
                window.addEventListener('resize', () => checkScroll());
            "
        >
            <h3 class="text-2xl font-bold text-white mb-4">What to Watch: <span class="text-imdb-yellow">Top Rating</span></h3>
            
            <!-- Tombol Geser Kiri -->
            <button 
                @click="scroller.scrollBy({ left: -scroller.clientWidth / 2, behavior: 'smooth' })"
                x-show="!atStart"
                x-transition
                class="absolute top-1/2 -translate-y-1/2 left-0 z-10 bg-black/60 hover:bg-black/80 p-2 rounded-full text-white cursor-pointer mt-4"
                style="display: none;"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Horizontal Scroller -->
            <div 
                class="flex overflow-x-auto space-x-4 pb-4 no-scrollbar scroll-smooth" 
                x-ref="scroller" 
                @scroll.debounce.100ms="checkScroll()"
            >
                @foreach($topPicks as $pick)
                    {{-- Ubah 'tt123' dari URI --}}
                    @php($imdbId = last(explode('/', rtrim($pick['film'], '/'))))
                    <a href="{{ route('film.show', $imdbId) }}" class="block w-48 flex-shrink-0 group scroll-snap-start">
                        <!-- Poster Card -->
                        <div class="relative">
                            <img src="{{ $pick['poster'] }}" alt="{{ $pick['title'] }} Poster" 
                                 class="w-full h-72 object-cover rounded-lg shadow-lg group-hover:opacity-80 transition-opacity duration-200">
                            
                        </div>
                        <!-- Detail Card -->
                        <div class="mt-3">
                            <div class="flex items-center">
                                <span class="text-imdb-yellow font-bold">★</span>
                                <span class="text-white font-semibold ml-1.5">{{ $pick['rating'] }}</span>
                            </div>
                            <h4 class="text-white font-semibold mt-1 truncate group-hover:text-imdb-yellow transition-colors" title="{{ $pick['title'] }}">
                                {{ $pick['title'] }}
                            </h4>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- Tombol Geser Kanan -->
            <button 
                @click="scroller.scrollBy({ left: scroller.clientWidth / 2, behavior: 'smooth' })"
                x-show="!atEnd"
                x-transition
                class="absolute top-1/2 -translate-y-1/2 right-0 z-10 bg-black/60 hover:bg-black/80 p-2 rounded-full text-white cursor-pointer mt-4"
                style="display: none;"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </section>
        
        <!-- Form Pencarian Utama -->
        <form action="{{ route('film.search') }}" method="GET" class="flex mb-4">
            <input type="text" 
                   name="query" 
                   placeholder="Cari judul film, plot, atau tahun..."
                   value="{{ $query ?? '' }}"
                   class="flex-grow p-3 bg-imdb-gray border border-gray-700 rounded-l-md text-lg text-white focus:outline-none focus:ring-2 focus:ring-imdb-yellow focus:border-transparent">
            <button type="submit" 
                    class="p-3 bg-imdb-yellow text-black font-bold text-lg rounded-r-md hover:bg-yellow-300">
                Cari
            </button>
        </form>

        <!-- =========== BAGIAN FILTER BARU =========== -->

        <!-- Filter Abjad -->
        <div class="flex flex-wrap justify-center gap-1.5 mb-6">
            @php($currentLetter = request('letter'))
            @foreach(range('A', 'Z') as $char)
                <a href="{{ route('film.search', array_merge(request()->all(), ['letter' => $char, 'page' => 1])) }}"
                   class="w-8 h-8 flex items-center justify-center rounded text-sm font-bold
                           {{ $currentLetter == $char 
                                ? 'bg-imdb-yellow text-black' 
                                : 'bg-imdb-gray hover:bg-imdb-light-gray' }}">
                    {{ $char }}
                </a>
            @endforeach
             <a href="{{ route('film.search', array_merge(request()->all(), ['letter' => '', 'page' => 1])) }}"
                class="w-8 h-8 flex items-center justify-center rounded text-lg font-bold
                        {{ !$currentLetter 
                            ? 'bg-imdb-yellow text-black' 
                            : 'bg-imdb-gray hover:bg-imdb-light-gray' }}">
                 #
             </a>
        </div>
        
        <!-- Filter Dropdown -->
        <form action="{{ route('film.search') }}" method="GET" class="flex flex-wrap items-center gap-4 mb-8">
            <!-- Hidden input untuk menyimpan filter lain yang aktif -->
            <input type="hidden" name="query" value="{{ $currentFilters['query'] ?? '' }}">
            <input type="hidden" name="letter" value="{{ $currentFilters['letter'] ?? '' }}">

            <!-- Dropdown Tipe -->
            <select name="type" onchange="this.form.submit()" class="filter-dropdown">
                <option value="">Semua Tipe</option>
                @foreach($types as $type)
                    <option value="{{ $type }}" {{ (request('type') == $type) ? 'selected' : '' }}>
                        {{ ucfirst($type) }}
                    </option>
                @endforeach
            </select>

            <!-- Dropdown Tahun -->
            <select name="year" onchange="this.form.submit()" class="filter-dropdown">
                <option value="">Semua Tahun</option>
                @foreach($years as $year)
                    <option value="{{ $year }}" {{ (request('year') == $year) ? 'selected' : '' }}>
                        {{ $year }}
                    </option>
                @endforeach
            </select>
            
            <!-- BARU: Dropdown Rating -->
            <select name="rated" onchange="this.form.submit()" class="filter-dropdown">
                <option value="">Semua Rating</option>
                @foreach($ratings as $rated)
                    <option value="{{ $rated }}" {{ (request('rated') == $rated) ? 'selected' : '' }}>
                        {{ $rated }}
                    </option>
                @endforeach
            </select>

            <!-- Dropdown Urutkan -->
            <select name="sort" onchange="this.form.submit()" class="filter-dropdown">
                <option value="title_asc" {{ (request('sort') == 'title_asc') ? 'selected' : '' }}>Judul (A-Z)</option>
                <option value="title_desc" {{ (request('sort') == 'title_desc') ? 'selected' : '' }}>Judul (Z-A)</option>
                <option value="rating_desc" {{ (request('sort') == 'rating_desc') ? 'selected' : '' }}>Rating (Tertinggi)</option>
                <option value="rating_asc" {{ (request('sort') == 'rating_asc') ? 'selected' : '' }}>Rating (Terendah)</option>
                <option value="year_desc" {{ (request('sort') == 'year_desc') ? 'selected' : '' }}>Tahun (Terbaru)</option>
                <option value="year_asc" {{ (request('sort') == 'year_asc') ? 'selected' : '' }}>Tahun (Terlama)</option>
            </select>

            <!-- Tombol Reset -->
            <a href="{{ route('film.search', ['query' => $currentFilters['query'] ?? '']) }}" 
               class="px-4 py-2 text-sm text-center text-white bg-imdb-gray hover:bg-imdb-light-gray rounded-md">
                Reset Filter
            </a>
        </form>
        <!-- ============================================ -->


        <!-- Hasil Pencarian -->
        <div class="space-y-6">
            @forelse ($films as $film)
                @php($imdbId = last(explode('/', rtrim($film['film'], '/'))))
                <a href="{{ route('film.show', $imdbId) }}" class="block bg-imdb-gray rounded-lg shadow-md overflow-hidden flex transform transition-all duration-200 hover:bg-gray-800 hover:scale-101">
                    <!-- Poster -->
                    <img src="{{ $film['poster'] }}" alt="{{ $film['title'] }} Poster" class="w-24 md:w-32 h-36 md:h-48 object-cover flex-shrink-0">
                    
                    <!-- Details -->
                    <div class="p-4 flex-grow">
                        <h3 class="text-xl md:text-2xl font-bold text-white">{{ $film['title'] }}</h3>
                        <p class="text-sm text-gray-400 mt-1">
                            {{ $film['year'] }} &bull; {{ ucfirst($film['type']) }}
                        </p>
                        <p class="text-sm text-gray-400 mt-2 hidden md:block">
                            @if(is_array($film['actors']))
                                {{-- Mengambil 3 aktor pertama untuk preview --}}
                                @foreach($film['actors'] as $actor)
                                    {{ $actor }}
                                    @if(!$loop->last && $loop->index < 2)
                                        , 
                                    @endif
                                    @if($loop->index == 2)
                                        ...
                                        @break
                                    @endif
                                @endforeach
                            @endif
                        </p>
                    </div>
                    
                    <!-- Rating -->
                    <div class="p-4 flex-shrink-0 flex items-center justify-center min-w-[80px]">
                        <div class="text-center">
                            <span class="text-2xl text-imdb-yellow font-bold">★</span>
                            <span class="text-xl font-bold ml-1">{{ $film['rating'] }}</span>
                        </div>
                    </div>
                </a>
            @empty
                @if (request()->hasAny(['query', 'letter', 'year', 'type', 'rated']))
                    <p class="text-center text-gray-400 text-lg">Tidak ada hasil yang cocok dengan filter Anda.</p>
                @else
                    <p class="text-center text-gray-400 text-lg">Mulai pencarian atau gunakan filter.</p>
                @endif
            @endforelse
        </div>

        <!-- Link Paginasi -->
        <div class="mt-12">
            <!-- Memperbarui 'appends' untuk menyertakan SEMUA filter saat ini -->
            {{ $films->links() }}
        </div>
    </div>

</body>
</html>

