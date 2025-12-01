<!DOCTYPE html>
<html lang="id" prefix="og: http://ogp.me/ns# video: http://ogp.me/ns/video# schema: http://schema.org/" vocab="http://schema.org/" typeof="Movie">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $film['title'] }} - TetengFilm</title>
    
    <meta property="og:title" content="{{ $film['title'] }} ({{ $film['year'] }})">
    <meta property="og:type" content="video.movie">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $film['poster'] }}">
    <meta property="og:description" content="{{ Str::limit($film['plot'], 200) }}">
    <meta property="og:site_name" content="TetengFilm">
    <meta property="og:locale" content="id_ID">
    
    @if($film['year'] !== 'N/A')
    <meta property="video:release_date" content="{{ $film['year'] }}">
    @endif
    
    @if(isset($film['actors_list']) && !empty($film['actors_list']))
        @foreach(array_slice($film['actors_list'], 0, 3) as $actor)
    <meta property="video:actor" content="{{ $actor }}">
        @endforeach
    @endif
    
    @if(isset($film['directors_list']) && !empty($film['directors_list']))
        @foreach($film['directors_list'] as $director)
    <meta property="video:director" content="{{ $director }}">
        @endforeach
    @endif
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $film['title'] }} ({{ $film['year'] }})">
    <meta name="twitter:description" content="{{ Str::limit($film['plot'], 200) }}">
    <meta name="twitter:image" content="{{ $film['poster'] }}">
    
    @php
        $wikipediaTitle = str_replace(' ', '_', $film['title']);
        $imdbId = last(explode('/', rtrim($film['film'], '/')));
    @endphp
    <meta property="og:see_also" content="https://en.wikipedia.org/wiki/{{ $wikipediaTitle }}">
    <meta property="og:see_also" content="https://www.imdb.com/title/{{ $imdbId }}/">
    <link rel="alternate" type="text/html" href="https://en.wikipedia.org/wiki/{{ $wikipediaTitle }}" title="Wikipedia Article">
    
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
</head>
<body class="bg-imdb-dark text-white font-sans">

    <header class="sticky top-0 z-30 bg-imdb-gray border-b border-imdb-light-gray">
        <div class="container mx-auto max-w-7xl px-4 md:px-8 py-3 flex items-center gap-4">
            
            <a href="{{ route('film.search') }}" class="text-3xl font-bold text-imdb-yellow hidden md:block">
                TetengFilm
            </a>

            <a href="{{ route('film.search', request()->only(['query', 'letter', 'type', 'year', 'rated', 'genre', 'sort', 'page'])) }}" 
               class="p-2 text-white hover:text-imdb-yellow transition-colors"
               title="Kembali ke Hasil Pencarian">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span class="sr-only">Kembali</span>
            </a>

            <form action="{{ route('film.search') }}" method="GET" class="w-full md:w-[32rem] ml-auto"
                  x-data="{ isSubmitting: false }"
                  @submit="isSubmitting = true; sessionStorage.setItem('scrollToResults', 'true')">
                
                @foreach(request()->except(['query', 'letter', 'page']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach

                <div class="relative w-full text-gray-800 focus-within:text-gray-600">
                    
                    <input type="text" 
                           name="query" 
                           placeholder="Cari film lain..."
                           value="{{ $searchQuery ?? request('query') ?? '' }}"
                           class="w-full py-3 pl-4 pr-12 bg-white border-none rounded-md text-lg text-black focus:outline-none focus:ring-2 focus:ring-imdb-yellow placeholder-gray-500">
                    
                    <button type="submit" 
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 p-2 bg-transparent hover:text-imdb-yellow transition-colors disabled:cursor-not-allowed"
                            :disabled="isSubmitting">
                
                        <svg x-show="!isSubmitting" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <path fill-rule="evenodd" d="M10.5 3.75a6.75 6.75 0 100 13.5 6.75 6.75 0 000-13.5zM2.25 10.5a8.25 8.25 0 1114.59 5.28l4.69 4.69a.75.75 0 11-1.06 1.06l-4.69-4.69A8.25 8.25 0 012.25 10.5z" clip-rule="evenodd" />
                        </svg>

                        <svg x-show="isSubmitting" style="display: none;" class="animate-spin h-6 w-6 text-imdb-yellow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </header>

    <main class="container mx-auto max-w-7xl p-4 md:p-8">
        
        <div class="flex flex-col md:flex-row gap-8">
            
            <div class="flex-shrink-0 w-full md:w-1/3 lg:w-1/4">
                <img src="{{ $film['poster'] }}" alt="{{ $film['title'] }} Poster" property="image" class="w-full rounded-lg shadow-2xl border-4 border-imdb-gray">
                
                @if($film['rating'] !== 'N/A' && $film['rating'] != 0)
                <div class="mt-6 p-4 bg-imdb-gray rounded-lg text-center" property="aggregateRating" typeof="AggregateRating">
                    <div class="text-sm text-gray-300 uppercase tracking-wide">IMDb Rating</div>
                    <div class="flex items-center justify-center gap-2 mt-1">
                        <svg class="w-8 h-8 text-imdb-yellow" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        <span class="text-3xl font-bold text-white" property="ratingValue">{{ $film['rating'] }}</span>
                        <span class="text-gray-400">/<span property="bestRating">10</span></span>
                    </div>
                    @if($film['imdbVotes'] !== 'N/A')
                    <div class="text-xs text-gray-400 mt-2">
                        (<span property="ratingCount">{{ $film['imdbVotes'] }}</span> suara)
                    </div>
                    @endif
                </div>
                @endif
                
                @if($film['metascore'] !== 'N/A' && $film['metascore'] != 0)
                <div class="mt-4 p-4 bg-imdb-gray rounded-lg text-center">
                    <div class="text-sm text-gray-300 uppercase tracking-wide">Metascore</div>
                    <div class="flex items-center justify-center gap-2 mt-1">
                        <span class="text-3xl font-bold text-green-400">{{ $film['metascore'] }}</span>
                    </div>
                </div>
                @endif
                
                @php
                    $wikipediaTitle = str_replace(' ', '_', $film['title']);
                @endphp
                <div class="mt-6">
                    <a href="https://en.wikipedia.org/wiki/{{ $wikipediaTitle }}" target="_blank" class="flex items-center justify-center gap-2 w-full px-4 py-3 bg-gray-700 text-white font-semibold rounded-lg hover:bg-gray-600 transition-colors border border-gray-600">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.09 13.119c-.936 1.932-2.217 4.548-2.853 5.728-.616 1.074-1.127.931-1.532.029-1.406-3.321-4.293-9.144-5.651-12.409-.251-.601-.441-.987-.619-1.139-.181-.15-.554-.24-1.122-.271C.103 5.033 0 4.997 0 4.838v-.748c0-.159.103-.195.307-.226 1.078-.129 2.599-.246 4.018-.246.926 0 2.164.117 3.09.246.204.031.307.067.307.226v.748c0 .159-.103.195-.307.226-.449.031-.857.074-1.189.141-.333.068-.411.227-.267.495 1.096 2.036 2.831 5.924 3.814 8.217.375.888.681 1.61.962 2.166.271-.545.583-1.262.962-2.166.912-2.174 2.456-5.888 3.468-7.946.182-.354.103-.555-.23-.623-.307-.062-.706-.104-1.155-.135-.204-.031-.307-.067-.307-.226v-.748c0-.159.103-.195.307-.226.937-.129 2.106-.246 3.032-.246 1.292 0 2.429.117 3.506.246.204.031.307.067.307.226v.748c0 .159-.103.195-.307.226-.473.031-.918.104-1.293.226-.373.123-.65.486-.946 1.122-1.104 2.558-3.295 7.574-4.788 10.711-.494 1.021-.883 1.788-1.168 2.301-.285.512-.569.683-.854.512-.286-.17-.569-.512-.854-1.024-.286-.511-.674-1.278-1.168-2.3z"/>
                        </svg>
                        Baca di Wikipedia
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </div>

            <div class="flex-grow">
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-2" property="name">{{ $film['title'] }}</h1>
                
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-300 mb-6">
                    <span class="border border-gray-500 px-2 py-0.5 rounded" property="contentRating">{{ $film['rated'] }}</span>
                    <span property="datePublished">{{ $film['year'] }}</span>
                    <span class="capitalize">{{ ucfirst($film['type']) }}</span>
                    <span property="duration">{{ $film['runtime'] ?? 'N/A' }}</span>
                </div>

                @if(isset($film['genres']) && $film['genres'])
                <div class="flex flex-wrap gap-2 mb-6">
                    @foreach(explode(', ', $film['genres']) as $genre)
                        <span class="px-3 py-1 bg-imdb-gray hover:bg-imdb-light-gray rounded-full text-sm text-white border border-gray-600 transition-colors cursor-default" property="genre">
                            {{ $genre }}
                        </span>
                    @endforeach
                </div>
                @endif

                <div class="mb-8">
                    <h3 class="text-lg font-bold text-imdb-yellow mb-2 border-l-4 border-imdb-yellow pl-3">Sinopsis</h3>
                    <p class="text-lg text-gray-200 leading-relaxed" property="description">
                        {{ $film['plot'] }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <div class="mb-4">
                            <span class="text-gray-300 block text-sm mb-1">Sutradara</span>
                            <div class="text-white font-medium text-lg">
                                @if(!empty($film['directors_list']))
                                    @foreach($film['directors_list'] as $director)
                                        <span property="director" typeof="Person">
                                            <span property="name" class="text-imdb-yellow">{{ $director }}</span>
                                        </span>@if(!$loop->last), @endif
                                    @endforeach
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <span class="text-gray-300 block text-sm mb-1">Penulis</span>
                            <div class="text-white font-medium">
                                @if(!empty($film['writers_list']))
                                    @foreach($film['writers_list'] as $writer)
                                        <span property="author" typeof="Person">
                                            <span property="name" class="text-imdb-yellow">{{ $writer }}</span>
                                        </span>@if(!$loop->last), @endif
                                    @endforeach
                                @else
                                    -
                                @endif
                            </div>
                        </div>

                        <div class="mb-4">
                            <span class="text-gray-300 block text-sm mb-1">Pemeran Utama</span>
                            <div class="text-white font-medium">
                                @if(!empty($film['actors_list']))
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach($film['actors_list'] as $actor)
                                            <li class="hover:text-imdb-yellow transition-colors cursor-pointer">
                                                <span property="actor" typeof="Person">
                                                    <span property="name">{{ $actor }}</span>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="bg-imdb-gray p-6 rounded-lg border border-gray-700">
                        <h4 class="text-white font-bold mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-imdb-yellow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Detail Produksi
                        </h4>
                        <div class="space-y-3 text-sm">
                            @if($film['released'] !== 'N/A')
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-300">Rilis</span>
                                <span>{{ $film['released'] }}</span>
                            </div>
                            @endif
                            
                            @php
                                $displayBoxOffice = $film['dbpedia']['boxOffice'] ?? $film['boxOffice'] ?? 'N/A';
                                $isFromDbpedia = !empty($film['dbpedia']['boxOffice']);
                                $isMovie = strtolower($film['type']) === 'movie';
                            @endphp
                            
                            @if($displayBoxOffice !== 'N/A' && $isMovie)
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-300">Box Office @if($isFromDbpedia)<span class="text-xs text-blue-400">(DBpedia)</span>@endif</span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $displayBoxOffice }}
                                </span>
                            </div>
                            @endif
                            
                            @if(!empty($film['dbpedia']['budget']) && $isMovie)
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-300">Budget <span class="text-xs text-blue-300">(DBpedia)</span></span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $film['dbpedia']['budget'] }}
                                </span>
                            </div>

                            <div class="flex justify-between border-b border-gray-700 pb-2 bg-gray-800 bg-opacity-50 p-2 rounded mt-2">
                                <div>
                                    <span class="block text-gray-300 font-bold">Analisis Profit</span>
                                    <span class="text-[10px] text-yellow-500 tracking-wider uppercase">(Semantik Data)</span>
                                </div>
                                <div class="text-right">
                                    <span class="block font-bold {{ $film['profit_class'] ?? 'text-gray-400' }} text-base">
                                        {{ $film['profit_status'] ?? 'N/A' }}
                                    </span>
                                    <span class="text-[10px] text-gray-500">BoxOffice - Budget</span>
                                </div>
                            </div>
                            @endif
                            
                            @if($film['awards'] !== 'N/A')
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-300">Penghargaan</span>
                                <span class="text-right max-w-[60%]">{{ $film['awards'] }}</span>
                            </div>
                            @endif
                            
                            @if(!empty($film['languages']))
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-300">Bahasa</span>
                                <span property="inLanguage">{{ $film['languages'] }}</span>
                            </div>
                            @endif
                            
                            @if(!empty($film['countries']))
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-300">Negara</span>
                                <span property="countryOfOrigin">{{ $film['countries'] }}</span>
                            </div>
                            @endif
                            
                            @php($imdbId = last(explode('/', rtrim($film['film'], '/'))))
                            <div class="flex justify-between">
                                <span class="text-gray-300">Halaman IMDb</span>
                                <a href="https://www.imdb.com/title/{{ $imdbId }}/" target="_blank" class="text-imdb-yellow hover:underline flex items-center gap-1">
                                    {{ $imdbId }}
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-12">
                    <h3 class="text-2xl font-bold text-white mb-4 flex items-center gap-2">
                        <svg class="w-8 h-8 text-imdb-yellow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        Visualisasi Knowledge Graph
                    </h3>
                    <p class="text-gray-400 mb-6">
                        Visualisasi interaktif relasi semantik antara film, aktor, dan sutradara. 
                        <span class="text-imdb-yellow font-semibold">Coba tarik node-node di bawah ini!</span>
                    </p>
                    <div id="semantic-network" class="w-full h-[500px] bg-gray-900 rounded-xl border border-gray-700 shadow-inner"></div>
                </div>

            </div>
        </div>

    </main>

    <footer class="border-t border-imdb-light-gray mt-12 py-8 bg-imdb-gray">
        <div class="container mx-auto max-w-6xl px-4 text-center text-gray-500">
            <p>&copy; {{ date('Y') }} TetengFilm. Data film dari Fuseki (OMDb) & DBpedia (Wikipedia).</p>
            <p class="text-xs mt-2">
                Integrasi: <span class="text-blue-400">DBpedia SPARQL Endpoint</span> | 
                <span class="text-yellow-400">Open Graph Protocol</span> | 
                <span class="text-green-400">Schema.org RDFa</span>
            </p>
        </div>
    </footer>

    @include('partials.chatbot')

    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if(isset($graphData))
            const graphData = {!! $graphData !!};
            
            const container = document.getElementById('semantic-network');
            
            const data = {
                nodes: new vis.DataSet(graphData.nodes),
                edges: new vis.DataSet(graphData.edges)
            };
            
            const options = {
                nodes: {
                    shape: 'dot',
                    size: 20,
                    font: {
                        size: 14,
                        color: '#ffffff',
                        face: 'sans-serif'
                    },
                    borderWidth: 2,
                    shadow: true
                },
                edges: {
                    width: 2,
                    color: { color: '#6b7280', highlight: '#f5c518' },
                    smooth: {
                        type: 'continuous'
                    },
                    font: {
                        color: '#e5e7eb',
                        size: 10,
                        align: 'middle'
                    }
                },
                groups: {
                    mainFilm: {
                        color: { background: '#f5c518', border: '#b4860b' },
                        size: 35,
                        font: { size: 18, color: '#000000', face: 'bold' }
                    },
                    actor: {
                        color: { background: '#3b82f6', border: '#1d4ed8' },
                        size: 20
                    },
                    director: {
                        color: { background: '#ef4444', border: '#b91c1c' },
                        size: 20
                    },
                    relatedFilm: {
                        color: { background: '#10b981', border: '#047857' },
                        size: 15,
                        shape: 'diamond'
                    }
                },
                physics: {
                    stabilization: false,
                    barnesHut: {
                        gravitationalConstant: -2000,
                        springConstant: 0.04,
                        springLength: 150
                    }
                },
                interaction: {
                    hover: true,
                    tooltipDelay: 200
                }
            };
            
            const network = new vis.Network(container, data, options);
            
            network.on("doubleClick", function (params) {
                if (params.nodes.length === 1) {
                    const nodeId = params.nodes[0];
                    const node = data.nodes.get(nodeId);
                    if (node.group === 'relatedFilm') {
                        window.location.href = "{{ route('film.search') }}?query=" + encodeURIComponent(node.title.split(': ')[1]);
                    }
                }
            });
            @endif
        });
    </script>

</body>
</html>