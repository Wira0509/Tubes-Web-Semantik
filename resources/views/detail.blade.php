<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Kita gunakan $film['title'] karena data adalah array --}}
    <title>{{ $film['title'] }} ({{ $film['year'] }}) - Detail Film</title>
    <!-- Memuat Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'imdb-yellow': '#f5c518',
                        'imdb-dark': '#121212',
                        'imdb-gray': '#1a1a1a',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-imdb-dark text-white font-sans">

    <div class="container mx-auto max-w-4xl p-4 md:p-8">

        <!-- Tombol Kembali -->
        <a href="{{ url()->previous() }}" class="inline-block mb-8 text-imdb-yellow hover:text-yellow-300 transition-colors">
            &laquo; Kembali ke Hasil
        </a>

        <!-- Header: Judul, Tahun, Rating Usia, Tipe -->
        <header class="mb-8 border-b border-gray-700 pb-6">
            <h1 class="text-4xl md:text-5xl font-bold text-white">{{ $film['title'] }}</h1>
            <div class="flex items-center space-x-4 mt-2 text-gray-400 text-lg">
                <span>{{ $film['year'] }}</span>
                @if($film['rated'] !== 'N/A')
                    <span class="border-l border-gray-600 pl-4">{{ $film['rated'] }}</span>
                @endif
                {{-- Tampilkan Runtime jika ada --}}
                @if($film['runtime'] !== 'N/A' && $film['runtime'] != 0)
                    <span class="border-l border-gray-600 pl-4">{{ $film['runtime'] }} menit</span>
                @endif
                <span class="border-l border-gray-600 pl-4 font-bold text-imdb-yellow capitalize">{{ $film['type'] }}</span>
            </div>
        </header>

        <main class="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-12">

            <!-- Kolom Kiri: Poster -->
            <aside class="md:col-span-1">
                <img src="{{ $film['poster'] }}" alt="{{ $film['title'] }} Poster" class="w-full rounded-lg shadow-lg">
            </aside>

            <!-- Kolom Kanan: Rating, Plot, Kru -->
            <section class="md:col-span-2">

                <!-- Blok Rating -->
                @if($film['rating'] !== 'N/A' && $film['rating'] != 0)
                <div class="bg-imdb-gray rounded-lg p-4 flex items-center space-x-4 mb-6">
                    <span class="text-4xl text-imdb-yellow font-bold">★</span>
                    <div>
                        <span class="text-2xl font-bold">{{ $film['rating'] }}</span>
                        <span class="text-gray-400">/ 10</span>
                        @if($film['imdbVotes'] !== 'N/A')
                            <span class="text-gray-400 ml-2">({{ $film['imdbVotes'] }} suara)</span>
                        @endif
                    </div>
                    @if($film['metascore'] !== 'N/A' && $film['metascore'] != 0)
                    <div class="border-l border-gray-600 pl-4 ml-4">
                        <span class="text-2xl font-bold">{{ $film['metascore'] }}</span>
                        <span class="text-gray-400">Metascore</span>
                    </div>
                    @endif
                </div>
                @endif


                <!-- Plot -->
                <p class="text-lg text-gray-300 leading-relaxed mb-8">
                    {{ $film['plot'] }}
                </p>

                <!-- Detail Info -->
                <div class="space-y-4">
                    
                    @if(!empty($film['genres']))
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Genre</strong>
                        <div class="col-span-3">
                            {{ $film['genres'] }}
                        </div>
                    </div>
                    @endif

                    @if(!empty($film['directors_list']))
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Sutradara</strong>
                        <div class="col-span-3">
                            {{-- PERBAIKAN: Gunakan 'directors_list' --}}
                            @foreach($film['directors_list'] as $director)
                                <a href="#" class="text-imdb-yellow hover:underline">{{ $director }}</a>
                                @if(!$loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if(!empty($film['writers_list']))
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Penulis</strong>
                        <div class="col-span-3">
                            {{-- BARU: Menampilkan penulis --}}
                            @foreach($film['writers_list'] as $writer)
                                <a href="#" class="text-imdb-yellow hover:underline">{{ $writer }}</a>
                                @if(!$loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if(!empty($film['actors_list']))
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Aktor</strong>
                        <div class="col-span-3">
                            {{-- PERBAIKAN: Gunakan 'actors_list' --}}
                            @foreach($film['actors_list'] as $actor)
                                <a href="#" class="text-imdb-yellow hover:underline">{{ $actor }}</a>
                                @if(!$loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if($film['released'] !== 'N/A')
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Rilis</strong>
                        <div class="col-span-3">
                            {{ $film['released'] }}
                        </div>
                    </div>
                    @endif

                    @if(!empty($film['languages']))
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Bahasa</strong>
                        <div class="col-span-3">
                            {{ $film['languages'] }}
                        </div>
                    </div>
                    @endif

                    @if(!empty($film['countries']))
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Negara</strong>
                        <div class="col-span-3">
                            {{ $film['countries'] }}
                        </div>
                    </div>
                    @endif

                    @if($film['awards'] !== 'N/A')
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Penghargaan</strong>
                        <div class="col-span-3">
                            {{ $film['awards'] }}
                        </div>
                    </div>
                    @endif

                    @if($film['boxOffice'] !== 'N/A' && $film['boxOffice'] != 0)
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Box Office</strong>
                        <div class="col-span-3">
                            ${{ number_format(floatval($film['boxOffice']), 0, ',', '.') }}
                        </div>
                    </div>
                    @endif
                    
                    <div class="grid grid-cols-4 gap-4 pt-4 border-t border-gray-700">
                        <strong class="col-span-1 text-gray-400">Halaman IMDb</strong>
                        <div class="col-span-3">
                            {{-- PERBAIKAN: href menggunakan $film['film'] (URI lengkap), teks menggunakan $film['imdb_id'] (ID) --}}
                            <a href="{{ $film['film'] }}" target="_blank" class="text-imdb-yellow hover:underline truncate">
                                {{ $film['imdb_id'] }}
                            </a>
                        </div>
                    </div>

                </div>

            </section>
        </main>
    </div>

</body>
</html>