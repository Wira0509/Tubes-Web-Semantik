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
                <span class="border-l border-gray-600 pl-4">{{ $film['rated'] }}</span>
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
                <div class="bg-imdb-gray rounded-lg p-4 flex items-center space-x-4 mb-6">
                    <span class="text-4xl text-imdb-yellow font-bold">★</span>
                    <div>
                        <span class="text-2xl font-bold">{{ $film['rating'] }}</span>
                        <span class="text-gray-400">/ 10</span>
                    </div>
                </div>

                <!-- Plot -->
                <p class="text-lg text-gray-300 leading-relaxed mb-8">
                    {{ $film['plot'] }}
                </p>

                <!-- Genre, Sutradara, Aktor, Link IMDb -->
                <div class="space-y-4">
                    
                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Genre</strong>
                        <div class="col-span-3">
                            {{ $film['genres'] }}
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Sutradara</strong>
                        <div class="col-span-3">
                            {{-- $film['directors'] sudah berupa array bersih --}}
                            @foreach($film['directors'] as $director)
                                <a href="#" class="text-imdb-yellow hover:underline">{{ $director }}</a>
                                @if(!$loop->last), @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-4 gap-4">
                        <strong class="col-span-1 text-gray-400">Aktor</strong>
                        <div class="col-span-3">
                            {{-- $film['actors'] sudah berupa array bersih --}}
                            @foreach($film['actors'] as $actor)
                                <a href="#" class="text-imdb-yellow hover:underline">{{ $actor }}</a>
                                @if(!$loop->last), @endif
                            @endforeach
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-4 gap-4 pt-4 border-t border-gray-700">
                        <strong class="col-span-1 text-gray-400">Halaman IMDb</strong>
                        <div class="col-span-3">
                            <a href="{{ $film['imdb_id'] }}" target="_blank" class="text-imdb-yellow hover:underline truncate">
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

