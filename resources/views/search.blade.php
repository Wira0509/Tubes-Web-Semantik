<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TetengFilm: Peringkat, Saran, dan Tempat Mengetahui Film & Acara TV Terbaik</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="//unpkg.com/alpinejs" defer></script>
    <script>
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
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Style kustom untuk dropdown filter */
        select.filter-dropdown {
            background-color: #1a1a1a;
            border: 1px solid #333;
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%2Lxmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22%23aaa%22%3E%3Cpath%20d%3D%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%20%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1.25em 1.25em;
            padding-right: 2rem; /* Ruang untuk ikon panah */
        }
        
        /* Helper untuk line-clamp (pembatasan teks) */
        .line-clamp-2 { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .line-clamp-3 { overflow: hidden; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
    </style>
</head>

<body class="bg-imdb-dark text-white font-sans" x-data="{ isFilterOpen: false }" @keydown.escape.window="isFilterOpen = false">

    <header class="sticky top-0 z-30 flex items-center gap-4 p-3 bg-imdb-gray border-b border-imdb-light-gray">
        
        <a href="{{ route('film.search') }}" class="text-3xl font-bold text-imdb-yellow hidden md:block ml-2">
            TetengFilm
        </a>

        <button @click="isFilterOpen = true" class="p-2 text-white hover:text-imdb-yellow transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span class="sr-only">Buka Filter</span>
        </button>

        <form action="{{ route('film.search') }}" method="GET" class="flex w-full md:w-[32rem] ml-auto"
              x-data="{ isSubmitting: false }"
              @submit="isSubmitting = true">
            
            @foreach(request()->except(['query', 'letter', 'page']) as $key => $value)
                @if(is_array($value))
                    @foreach($value as $v)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach

            <input type="text" 
                   name="query" 
                   placeholder="Cari judul, plot, tahun, atau aktor..."
                   value="{{ $query ?? '' }}"
                   class="flex-grow p-3 bg-white border-0 rounded-l-md text-lg text-black focus:outline-none focus:ring-2 focus:ring-imdb-yellow focus:border-transparent">
            
            <button type="submit" 
                    class="p-3 bg-imdb-yellow text-black font-bold text-lg rounded-r-md hover:bg-yellow-300
                           disabled:bg-gray-500 disabled:cursor-not-allowed"
            :disabled="isSubmitting">
        
                <svg x-show="!isSubmitting" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>

                <svg x-show="isSubmitting" style="display: none;" class="animate-spin h-6 w-6 text-black" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        </form>
    </header>

    <aside x-show="isFilterOpen" class="fixed inset-0 z-40 overflow-hidden" style="display: none;">
        <div x-show="isFilterOpen" 
             x-transition:enter="ease-in-out duration-500"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-500"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-black bg-opacity-75 transition-opacity" 
             @click="isFilterOpen = false">
        </div>

        <div class="fixed inset-y-0 left-0 max-w-full flex"
             x-show="isFilterOpen"
             x-transition:enter="transform transition ease-in-out duration-500"
             x-transition:enter-start="-translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in-out duration-500"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="-translate-x-full"
             @click.away="isFilterOpen = false">
            
            <div class="w-screen max-w-md bg-imdb-dark text-white p-6 overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-imdb-yellow">Filter & Urutkan</h2>
                    <button @click="isFilterOpen = false" class="text-gray-400 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Filter Judul (A-Z)</h3>
                    <div class="flex flex-wrap justify-center gap-1.5">
                        @php($currentLetter = request('letter'))
                        @foreach(range('A', 'Z') as $char)
                            <a href="{{ route('film.search', array_merge(request()->except(['query', 'page']), ['letter' => $char])) }}"
                               class="w-8 h-8 flex items-center justify-center rounded text-sm font-bold
                                      {{ $currentLetter == $char ? 'bg-imdb-yellow text-black' : 'bg-imdb-gray hover:bg-imdb-light-gray' }}">
                                {{ $char }}
                            </a>
                        @endforeach
                         <a href="{{ route('film.search', array_merge(request()->except(['query', 'page']), ['letter' => ''])) }}"
                            class="w-8 h-8 flex items-center justify-center rounded text-lg font-bold
                                   {{ !$currentLetter ? 'bg-imdb-yellow text-black' : 'bg-imdb-gray hover:bg-imdb-light-gray' }}">
                            #
                         </a>
                    </div>
                </div>
                
                <form action="{{ route('film.search') }}" method="GET" class="space-y-4"
                      x-data="{ isSubmitting: false }"
                      @submit="isSubmitting = true">
                    
                    <input type="hidden" name="query" value="{{ $currentFilters['query'] ?? '' }}">
                    <input type="hidden" name="letter" value="{{ $currentFilters['letter'] ?? '' }}">

                    <div>
                        <label for="type_filter" class="block text-sm font-medium text-gray-300 mb-1">Tipe</label>
                        <select name="type" id="type_filter" class="filter-dropdown w-full">
                            <option value="">Semua Tipe</option>
                            @foreach($types as $type)
                                <option value="{{ $type }}" {{ (request('type') == $type) ? 'selected' : '' }}>
                                    {{ ucfirst($type) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="year_filter" class="block text-sm font-medium text-gray-300 mb-1">Tahun</label>
                        <select name="year" id="year_filter" class="filter-dropdown w-full">
                            <option value="">Semua Tahun</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ (request('year') == $year) ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="rated_filter" class="block text-sm font-medium text-gray-300 mb-1">Rating Usia</label>
                        <select name="rated" id="rated_filter" class="filter-dropdown w-full">
                            <option value="">Semua Rating</option>
                            @foreach($ratings as $rated)
                                <option value="{{ $rated }}" {{ (request('rated') == $rated) ? 'selected' : '' }}>
                                    {{ $rated }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label for="genre_filter" class="block text-sm font-medium text-gray-300 mb-1">Genre</label>
                        <select name="genre" id="genre_filter" class="filter-dropdown w-full">
                            <option value="">Semua Genre</option>
                            @foreach($genres as $genre)
                                <option value="{{ $genre }}" {{ (request('genre') == $genre) ? 'selected' : '' }}>
                                    {{ $genre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="sort_filter" class="block text-sm font-medium text-gray-300 mb-1">Urutkan</label>
                        <select name="sort" id="sort_filter" class="filter-dropdown w-full">
                            <option value="title_asc" {{ (request('sort') == 'title_asc') ? 'selected' : '' }}>Judul (A-Z)</option>
                            <option value="title_desc" {{ (request('sort') == 'title_desc') ? 'selected' : '' }}>Judul (Z-A)</option>
                            <option value="rating_desc" {{ (request('sort') == 'rating_desc') ? 'selected' : '' }}>Rating (Tertinggi)</option>
                            <option value="rating_asc" {{ (request('sort') == 'rating_asc') ? 'selected' : '' }}>Rating (Terendah)</option>
                            <option value="year_desc" {{ (request('sort') == 'year_desc') ? 'selected' : '' }}>Tahun (Terbaru)</option>
                            <option value="year_asc" {{ (request('sort') == 'year_asc') ? 'selected' : '' }}>Tahun (Terlama)</option>
                        </select>
                    </div>
                    
                    <div class="flex gap-4 pt-4">
                        <button type="submit" 
                                class="flex-1 px-4 py-2 text-center font-bold text-black bg-imdb-yellow rounded-md hover:bg-yellow-300
                                       disabled:bg-gray-500 disabled:cursor-not-allowed"
                                :disabled="isSubmitting">
                            <span x-show="!isSubmitting">Terapkan Filter</span>
                            <span x-show="isSubmitting" style="display: none;">Memfilter...</span>
                        </button>
                        <a href="{{ route('film.search', ['query' => $currentFilters['query'] ?? '']) }}" 
                           class="flex-1 px-4 py-2 text-sm text-center text-white bg-imdb-gray hover:bg-imdb-light-gray rounded-md">
                            Reset Filter
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </aside>
    
    <main class="container mx-auto max-w-7xl p-4 md:p-8">
        
        @if(!empty($featuredFilms))
        <section 
            class="mb-12 rounded-lg overflow-hidden relative" 
            x-data="{
                activeSlide: 0,
                slideCount: {{ count($featuredFilms) }},
                autoplay: null,
                startAutoplay() {
                    this.autoplay = setInterval(() => {
                        this.activeSlide = (this.activeSlide + 1) % this.slideCount;
                    }, 5000);
                },
                stopAutoplay() {
                    clearInterval(this.autoplay);
                },
                next() {
                    this.activeSlide = (this.activeSlide + 1) % this.slideCount;
                    this.stopAutoplay();
                    this.startAutoplay();
                },
                prev() {
                    this.activeSlide = (this.activeSlide - 1 + this.slideCount) % this.slideCount;
                    this.stopAutoplay();
                    this.startAutoplay();
                }
            }"
            x-init="startAutoplay()"
        >
            <div class="relative w-full h-[400px] overflow-hidden">
                @foreach($featuredFilms as $index => $featuredFilm)
                    @php($featuredId = last(explode('/', rtrim($featuredFilm['film'], '/'))))
                    
                    <div 
                        x-show="activeSlide === {{ $index }}" 
                        x-transition:enter="transition-opacity ease-in-out duration-1000"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity ease-in-out duration-1000"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="absolute inset-0 w-full h-full"
                        style="background-image: url('{{ $featuredFilm['poster'] }}'); background-size: cover; background-position: center; filter: blur(24px) brightness(0.2); transform: scale(1.2);"
                    ></div>

                    <div 
                        x-show="activeSlide === {{ $index }}"
                        x-transition:enter="transition-opacity ease-in-out duration-1000 delay-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity ease-in-out duration-1000"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="absolute inset-0 p-6 md:p-12 flex flex-col md:flex-row items-center gap-6 md:gap-8"
                    >
                        <a href="{{ route('film.show', $featuredId) }}" class="w-48 flex-shrink-0">
                            <img src="{{ $featuredFilm['poster'] }}" alt="{{ $featuredFilm['title'] }} Poster" class="w-full h-72 object-cover rounded-lg shadow-lg hover:opacity-80 transition-opacity duration-200">
                        </a>
                        <div class="flex flex-col justify-center text-white text-center md:text-left">
                            <h2 class="text-3xl font-bold mb-3">{{ $featuredFilm['title'] }}</h2>
                            <p class="text-gray-300 text-lg mb-6 line-clamp-3">
                                {{ \Illuminate\Support\Str::limit($featuredFilm['plot'], 150) }}
                            </p>
                            <a href="{{ route('film.show', $featuredId) }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-imdb-yellow text-black font-bold rounded-md w-auto md:w-max hover:bg-yellow-300 transition-colors mx-auto md:mx-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" /></svg>
Lihat Detail
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <button @click="prev()" class="absolute top-1/2 -translate-y-1/2 left-4 z-10 bg-black/40 hover:bg-black/70 p-2 rounded-full text-white cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </button>
            <button @click="next()" class="absolute top-1/2 -translate-y-1/2 right-4 z-10 bg-black/40 hover:bg-black/70 p-2 rounded-full text-white cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
        </section>
        @endif
        
        <section 
            class="mb-12 relative" 
            x-data="{ 
                scroller: null, atStart: true, atEnd: false,
                checkScroll() {
                    if (!this.scroller) return;
                    this.atStart = this.scroller.scrollLeft <= 0;
                    this.atEnd = this.scroller.scrollLeft + this.scroller.clientWidth >= this.scroller.scrollWidth - 1;
                }
            }"
            x-init=" scroller = $refs.scroller; $nextTick(() => checkScroll()); window.addEventListener('resize', () => checkScroll()); "
        >
            <h3 class="text-2xl font-bold text-white mb-4">What to Watch: <span class="text-imdb-yellow">Top Picks</span></h3>
            
            <button @click="scroller.scrollBy({ left: -scroller.clientWidth / 2, behavior: 'smooth' })" x-show="!atStart" x-transition class="absolute top-1/2 -translate-y-1/2 left-0 z-10 bg-black/60 hover:bg-black/80 p-2 rounded-full text-white cursor-pointer mt-4" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            </button>

            <div class="flex overflow-x-auto space-x-4 pb-4 no-scrollbar scroll-smooth" x-ref="scroller" @scroll.debounce.100ms="checkScroll()">
                @foreach($topPicks as $pick)
                    @php($imdbId = last(explode('/', rtrim($pick['film'], '/'))))
                    <a href="{{ route('film.show', $imdbId) }}" class="block w-48 flex-shrink-0 group scroll-snap-start">
                        <div class="relative">
                            <img src="{{ $pick['poster'] }}" alt="{{ $pick['title'] }} Poster" class="w-full h-72 object-cover rounded-lg shadow-lg group-hover:opacity-80 transition-opacity duration-200">
                        </div>
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

            <button @click="scroller.scrollBy({ left: scroller.clientWidth / 2, behavior: 'smooth' })" x-show="!atEnd" x-transition class="absolute top-1/2 -translate-y-1/2 right-0 z-10 bg-black/60 hover:bg-black/80 p-2 rounded-full text-white cursor-pointer mt-4" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            </button>
        </section>
        
        <h1 class="text-2xl font-bold text-white mb-6 border-l-4 border-imdb-yellow pl-3">
            @if (request('query'))
                Hasil Pencarian untuk "{{ request('query') }}"
            @elseif (request()->hasAny(['letter', 'year', 'type', 'rated', 'genre']))
                Hasil Filter
            @else
                Semua Film
            @endif
        </h1>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            
            @forelse ($films as $film)
                @php($imdbId = last(explode('/', rtrim($film['film'], '/'))))
                
                <div class="block bg-imdb-gray rounded-lg shadow-md overflow-hidden 
                          transform transition-all duration-300 ease-in-out 
                          hover:shadow-xl hover:shadow-imdb-yellow/10 group">
                    
                    <a href="{{ route('film.show', $imdbId) }}" class="relative block">
                        <img src="{{ $film['poster'] }}" alt="{{ $film['title'] }} Poster" 
                             class="w-full h-64 sm:h-72 md:h-80 object-cover">
                        
                        <div class="absolute top-0 left-0 w-8 h-10 
                                    bg-black bg-opacity-60 
                                    opacity-0 group-hover:opacity-100 
                                    transition-opacity duration-300
                                    flex items-center justify-center
                                    rounded-br-lg cursor-pointer
                                    text-imdb-yellow hover:bg-opacity-80">
                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0111.186 0z" />
                            </svg>
                        </div>
                    </a>
                    
                    <div class="p-3">
                        <div class="flex items-center mb-1">
                            <span class="text-imdb-yellow font-bold text-sm">★</span>
                            <span class="text-white font-semibold text-sm ml-1.5">{{ isset($film['rating']) && $film['rating'] !== '' ? $film['rating'] : 'N/A' }}</span>
                        </div>

                        <a href="{{ route('film.show', $imdbId) }}" title="{{ $film['title'] }}">
                            <h3 class="text-md font-semibold text-white group-hover:underline truncate">
                                {{ $film['title'] }}
                            </h3>
                        </a>

                        <button type="button" class="mt-3 w-full text-center px-2 py-1.5 hover:bg-imdb-light-gray rounded-md text-sm text-white font-semibold transition-colors flex items-center justify-center gap-1 group/trailer">
                            <svg class="w-4 h-4 text-white group-hover/trailer:text-imdb-yellow transition-colors" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
                            </svg>
                            <span class="group-hover/trailer:text-imdb-yellow transition-colors">Trailer</span>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-2 sm:col-span-3 md:col-span-4 lg:col-span-6 bg-imdb-gray p-8 md:p-16 rounded-lg text-center flex flex-col items-center justify-center min-h-[300px]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-2xl font-bold text-white mb-2">Tidak Ada Hasil</h3>
                    @if (request()->hasAny(['query', 'letter', 'year', 'type', 'rated', 'genre']))
                        <p class="text-center text-gray-400 text-lg max-w-md">
                            Tidak ada film yang cocok dengan pencarian atau filter Anda. Coba reset filter Anda.
                        </p>
                        <a href="{{ route('film.search') }}" 
                           class="mt-6 px-5 py-2 text-center font-bold text-black bg-imdb-yellow rounded-md hover:bg-yellow-300">
                            Reset Semua Filter
                        </a>
                    @else
                        <p class="text-center text-gray-400 text-lg">Mulai pencarian atau gunakan filter untuk menemukan film.</p>
                    @endif
                </div>
            @endforelse
        </div>

        <div class="mt-12">
            {{ $films->links() }}
        </div>
    </main>

</body>
</html>