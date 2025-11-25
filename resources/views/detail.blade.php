<!DOCTYPE html>
<html lang="id" prefix="og: http://ogp.me/ns# video: http://ogp.me/ns/video# schema: http://schema.org/" vocab="http://schema.org/" typeof="Movie">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $film['title'] }} - TetengFilm</title>
    
    {{-- Open Graph Protocol Meta Tags --}}
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
    
    {{-- Twitter Card Meta Tags --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $film['title'] }} ({{ $film['year'] }})">
    <meta name="twitter:description" content="{{ Str::limit($film['plot'], 200) }}">
    <meta name="twitter:image" content="{{ $film['poster'] }}">
    
    {{-- Wikipedia Reference Link --}}
    @php
        $wikipediaTitle = str_replace(' ', '_', $film['title']);
        $imdbId = last(explode('/', rtrim($film['film'], '/')));
    @endphp
    <meta property="og:see_also" content="https://en.wikipedia.org/wiki/{{ $wikipediaTitle }}">
    <meta property="og:see_also" content="https://www.imdb.com/title/{{ $imdbId }}/">
    <link rel="alternate" type="text/html" href="https://en.wikipedia.org/wiki/{{ $wikipediaTitle }}" title="Wikipedia Article">
    
    <script src="https://cdn.tailwindcss.com"></script>
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

    <!-- Navbar Sederhana -->
    <nav class="bg-imdb-gray border-b border-imdb-light-gray p-4 sticky top-0 z-50">
        <div class="container mx-auto max-w-6xl flex items-center justify-between">
            <a href="{{ route('film.search', request()->only(['query', 'letter', 'type', 'year', 'rated', 'genre', 'sort', 'page'])) }}" class="text-2xl font-bold text-imdb-yellow">
                TetengFilm
            </a>
            <a href="{{ route('film.search', request()->only(['query', 'letter', 'type', 'year', 'rated', 'genre', 'sort', 'page'])) }}" class="text-sm text-gray-300 hover:text-white transition-colors">
                &larr; Kembali ke Pencarian
            </a>
        </div>
    </nav>

    <!-- Konten Utama -->
    <main class="container mx-auto max-w-6xl p-4 md:p-8">
        
        <!-- Header Film -->
        <div class="flex flex-col md:flex-row gap-8">
            
            <!-- Poster -->
            <div class="flex-shrink-0 w-full md:w-1/3 lg:w-1/4">
                <img src="{{ $film['poster'] }}" alt="{{ $film['title'] }} Poster" property="image" class="w-full rounded-lg shadow-2xl border-4 border-imdb-gray">
                
                @if($film['rating'] !== 'N/A' && $film['rating'] != 0)
                <div class="mt-6 p-4 bg-imdb-gray rounded-lg text-center" property="aggregateRating" typeof="AggregateRating">
                    <div class="text-sm text-gray-400 uppercase tracking-wide">IMDb Rating</div>
                    <div class="flex items-center justify-center gap-2 mt-1">
                        <svg class="w-8 h-8 text-imdb-yellow" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        <span class="text-3xl font-bold text-white" property="ratingValue">{{ $film['rating'] }}</span>
                        <span class="text-gray-500">/<span property="bestRating">10</span></span>
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
                    <div class="text-sm text-gray-400 uppercase tracking-wide">Metascore</div>
                    <div class="flex items-center justify-center gap-2 mt-1">
                        <span class="text-3xl font-bold text-green-500">{{ $film['metascore'] }}</span>
                    </div>
                </div>
                @endif
                
                {{-- External Links --}}
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

            <!-- Detail Info -->
            <div class="flex-grow">
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-2" property="name">{{ $film['title'] }}</h1>
                
                <div class="flex flex-wrap items-center gap-4 text-sm text-gray-400 mb-6">
                    <span class="border border-gray-600 px-2 py-0.5 rounded" property="contentRating">{{ $film['rated'] }}</span>
                    <span property="datePublished">{{ $film['year'] }}</span>
                    <span class="capitalize">{{ ucfirst($film['type']) }}</span>
                    <span property="duration">{{ $film['runtime'] ?? 'N/A' }}</span>
                </div>

                <!-- Genre Tags -->
                @if(isset($film['genres']) && $film['genres'])
                <div class="flex flex-wrap gap-2 mb-6">
                    @foreach(explode(', ', $film['genres']) as $genre)
                        <span class="px-3 py-1 bg-imdb-gray hover:bg-imdb-light-gray rounded-full text-sm text-white border border-gray-700 transition-colors cursor-default" property="genre">
                            {{ $genre }}
                        </span>
                    @endforeach
                </div>
                @endif

                <!-- Plot -->
                <div class="mb-8">
                    <h3 class="text-lg font-bold text-imdb-yellow mb-2 border-l-4 border-imdb-yellow pl-3">Sinopsis</h3>
                    <p class="text-lg text-gray-300 leading-relaxed" property="description">
                        {{ $film['plot'] }}
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Cast & Crew -->
                    <div>
                        <div class="mb-4">
                            <span class="text-gray-400 block text-sm mb-1">Sutradara</span>
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
                            <span class="text-gray-400 block text-sm mb-1">Penulis</span>
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
                            <span class="text-gray-400 block text-sm mb-1">Pemeran Utama</span>
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

                    <!-- Info Tambahan -->
                    <div class="bg-imdb-gray p-6 rounded-lg border border-gray-800">
                        <h4 class="text-white font-bold mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-imdb-yellow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Detail Produksi
                        </h4>
                        <div class="space-y-3 text-sm">
                            @if($film['released'] !== 'N/A')
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-400">Rilis</span>
                                <span>{{ $film['released'] }}</span>
                            </div>
                            @endif
                            
                            @if($film['boxOffice'] !== 'N/A')
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-400">Box Office</span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clip-rule="evenodd"/>
                                    </svg>
                                    @php
                                        // Format box office dengan koma
                                        $boxOffice = $film['boxOffice'];
                                        // Ekstrak angka dari string (misal: $623575910)
                                        if (preg_match('/[\d.]+/', $boxOffice, $matches)) {
                                            $number = floatval($matches[0]);
                                            $formatted = '$' . number_format($number, 0, '.', ',');
                                            $boxOffice = str_replace($matches[0], ltrim($formatted, '$'), $boxOffice);
                                        }
                                    @endphp
                                    {{ $boxOffice }}
                                </span>
                            </div>
                            @endif
                            
                            @if(!empty($film['dbpedia']['budget']))
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-400">Budget <span class="text-xs text-blue-400">(DBpedia)</span></span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $film['dbpedia']['budget'] }}
                                </span>
                            </div>
                            @endif
                            
                            @if($film['awards'] !== 'N/A')
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-400">Penghargaan</span>
                                <span class="text-right max-w-[60%]">{{ $film['awards'] }}</span>
                            </div>
                            @endif
                            
                            @if(!empty($film['languages']))
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-400">Bahasa</span>
                                <span property="inLanguage">{{ $film['languages'] }}</span>
                            </div>
                            @endif
                            
                            @if(!empty($film['countries']))
                            <div class="flex justify-between border-b border-gray-700 pb-2">
                                <span class="text-gray-400">Negara</span>
                                <span property="countryOfOrigin">{{ $film['countries'] }}</span>
                            </div>
                            @endif
                            
                            @php($imdbId = last(explode('/', rtrim($film['film'], '/'))))
                            <div class="flex justify-between">
                                <span class="text-gray-400">Halaman IMDb</span>
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

</body>
</html>