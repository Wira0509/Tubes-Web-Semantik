<div x-data="chatbot()" class="fixed bottom-6 right-6 z-50 flex flex-col items-end">
    
    <div x-show="isOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-10 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-10 scale-95"
         class="bg-imdb-gray border border-gray-700 w-80 md:w-96 rounded-lg shadow-2xl mb-4 overflow-hidden flex flex-col"
         style="display: none; height: 500px;"> <div class="bg-imdb-yellow p-3 flex justify-between items-center text-black flex-shrink-0">
            <h3 class="font-bold flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" />
                </svg>
                Asisten TetengFilm
            </h3>
            <button @click="isOpen = false" class="hover:bg-yellow-600 rounded p-1 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-grow p-4 overflow-y-auto bg-imdb-dark space-y-3" x-ref="chatContainer">
            <div class="flex justify-start">
                <div class="bg-gray-700 text-white p-3 rounded-lg rounded-tl-none max-w-[85%] text-sm shadow-md">
                    Halo! Pilih mood kamu di bawah ini, nanti aku sarankan film yang cocok! 👇
                </div>
            </div>

            <template x-for="(msg, index) in messages" :key="index">
                <div :class="msg.sender === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="msg.sender === 'user' ? 'bg-imdb-yellow text-black rounded-tr-none' : 'bg-gray-700 text-white rounded-tl-none'"
                         class="p-3 rounded-lg max-w-[85%] text-sm shadow-md">
                        <p x-text="msg.text"></p>
                        
                        <template x-if="msg.films && msg.films.length > 0">
                            <div class="mt-3 space-y-2">
                                <template x-for="film in msg.films" :key="film.imdb_id">
                                    <a :href="'/film/' + film.imdb_id" class="flex items-center gap-2 bg-black/20 p-2 rounded hover:bg-black/40 transition block border border-white/10">
                                        <img :src="film.poster" class="w-10 h-14 object-cover rounded shadow-sm">
                                        <div class="overflow-hidden">
                                            <div class="font-bold truncate text-xs" x-text="film.title"></div>
                                            <div class="text-xs opacity-75 mt-0.5 flex items-center gap-1">
                                                <svg class="w-3 h-3 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                                <span x-text="film.rating"></span>
                                            </div>
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
            
            <div x-show="isLoading" class="flex justify-start">
                <div class="bg-gray-700 p-3 rounded-lg rounded-tl-none shadow-md">
                    <div class="flex space-x-1">
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="p-3 bg-imdb-gray border-t border-gray-700 flex-shrink-0">
            <p class="text-xs text-gray-400 mb-2 text-center">Bagaimana perasaanmu saat ini?</p>
            <div class="grid grid-cols-2 gap-2">
                <template x-for="mood in moodOptions" :key="mood.value">
                    <button @click="sendMood(mood)" 
                            :disabled="isLoading"
                            class="bg-imdb-light-gray hover:bg-imdb-yellow hover:text-black text-white text-sm py-2 px-3 rounded-md transition-colors duration-200 flex items-center justify-center gap-2 border border-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-text="mood.icon"></span>
                        <span x-text="mood.label" class="font-medium"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <button @click="isOpen = !isOpen" 
            class="bg-imdb-yellow text-black p-4 rounded-full shadow-[0_0_15px_rgba(245,197,24,0.5)] hover:bg-yellow-400 transition-transform transform hover:scale-110 flex items-center justify-center border-2 border-white/20 z-50">
        <svg x-show="!isOpen" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        </svg>
        <svg x-show="isOpen" style="display: none;" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
    </button>
</div>

<script>
    function chatbot() {
        return {
            isOpen: false,
            messages: [],
            isLoading: false,
            moodOptions: [
                { label: 'Senang', value: 'senang', icon: '😄' },
                { label: 'Sedih', value: 'sedih', icon: '😢' },
                { label: 'Bosan', value: 'bosan', icon: '😴' },
                { label: 'Tegang', value: 'takut', icon: '😱' },
                { label: 'Romantis', value: 'romantis', icon: '😍' },
                { label: 'Acak Aja', value: 'acak', icon: '🎲' } 
            ],
            
            init() {
                this.$watch('messages', () => {
                    this.$nextTick(() => {
                        this.$refs.chatContainer.scrollTop = this.$refs.chatContainer.scrollHeight;
                    });
                });
            },

            sendMood(mood) {
                const userText = "Aku lagi " + mood.label.toLowerCase();
                this.messages.push({ sender: 'user', text: userText });
                
                this.isLoading = true;

                fetch('{{ route("chatbot.recommend") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message: mood.value }) 
                })
                .then(response => response.json())
                .then(data => {
                    setTimeout(() => {
                        this.isLoading = false;
                        this.messages.push({ 
                            sender: 'bot', 
                            text: data.message,
                            films: data.films 
                        });
                    }, 2000); 
                })
                .catch(error => {
                    this.isLoading = false;
                    this.messages.push({ sender: 'bot', text: 'Maaf, lagi gangguan nih. Coba lagi nanti ya!' });
                    console.error('Error:', error);
                });
            }
        }
    }
</script>