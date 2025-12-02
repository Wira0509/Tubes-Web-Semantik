<div x-data="chatbot()">
    <!-- Chatbot Box - Draggable & Resizable -->
    <div x-show="isOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-10 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-10 scale-95"
         class="fixed bg-imdb-gray border border-gray-700 rounded-lg shadow-2xl overflow-hidden flex flex-col z-50"
         :style="`left: ${position.x}px; top: ${position.y}px; width: ${size.width}px; height: ${size.height}px;`"
         style="display: none;">
        
        <!-- Header - Draggable -->
        <div @mousedown="startDrag($event)" 
             class="bg-imdb-yellow p-3 flex justify-between items-center text-black flex-shrink-0 cursor-move select-none">
            <h3 class="font-bold flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z"
                        clip-rule="evenodd" />
                </svg>
                Asisten TetengFilm
            </h3>
            <button @click="isOpen = false" class="hover:bg-yellow-600 rounded p-1 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-grow p-4 overflow-y-auto bg-imdb-dark space-y-3" x-ref="chatContainer">
            <div class="flex justify-start">
                <div class="bg-gray-700 text-white p-3 rounded-lg rounded-tl-none max-w-[85%] text-sm shadow-md">
                    Halo! Aku asisten TetengFilm. Tanya aku tentang film atau ceritakan mood kamu, nanti aku bantu carikan film yang cocok! 🎬
                </div>
            </div>

            <template x-for="(msg, index) in messages" :key="index">
                <div :class="msg.sender === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="msg.sender === 'user' ? 'bg-imdb-yellow text-black rounded-tr-none' : 'bg-gray-700 text-white rounded-tl-none'"
                        class="p-3 rounded-lg max-w-[85%] text-sm shadow-md">
                        <p class="whitespace-pre-line" x-text="msg.text"></p>

                        <!-- Film Recommendations as Buttons -->
                        <template x-if="msg.films && msg.films.length > 0">
                            <div class="mt-3 grid grid-cols-1 gap-2">
                                <template x-for="film in msg.films" :key="film.imdb_id">
                                    <a :href="'/film/' + film.imdb_id" 
                                       target="_blank"
                                       class="flex items-center gap-2 bg-imdb-yellow/10 hover:bg-imdb-yellow/20 p-2 rounded-md transition-all duration-200 border border-imdb-yellow/30 hover:border-imdb-yellow group">
                                        <img :src="film.poster" 
                                             :alt="film.title"
                                             class="w-12 h-16 object-cover rounded shadow-sm flex-shrink-0">
                                        <div class="flex-1 overflow-hidden">
                                            <div class="font-bold text-white group-hover:text-imdb-yellow text-xs truncate" 
                                                 x-text="film.title"></div>
                                            <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-2">
                                                <span x-text="film.year"></span>
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3 h-3 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                    </svg>
                                                    <span x-text="film.rating" class="font-semibold text-white"></span>
                                                </span>
                                            </div>
                                        </div>
                                        <svg class="w-5 h-5 text-gray-400 group-hover:text-imdb-yellow transition-colors flex-shrink-0" 
                                             fill="none" 
                                             stroke="currentColor" 
                                             viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
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
            <div class="flex gap-2 mb-2">
                <input type="text" 
                       x-model="userInput"
                       @keyup.enter="sendMessage()"
                       :disabled="isLoading"
                       placeholder="Ketik pesan..."
                       class="flex-1 bg-imdb-light-gray text-white px-3 py-2 rounded-md border border-gray-600 focus:outline-none focus:border-imdb-yellow text-sm disabled:opacity-50">
                <button @click="sendMessage()" 
                        :disabled="isLoading || !userInput.trim()"
                        class="bg-imdb-yellow text-black px-4 py-2 rounded-md hover:bg-yellow-400 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                    </svg>
                </button>
            </div>
            <p class="text-xs text-gray-400 text-center">atau pilih mood:</p>
            <div class="grid grid-cols-3 gap-2 mt-2">
                <template x-for="mood in moodOptions" :key="mood.value">
                    <button @click="sendMood(mood)" 
                            :disabled="isLoading"
                            class="bg-imdb-light-gray hover:bg-imdb-yellow hover:text-black text-white text-xs py-1.5 px-2 rounded-md transition-colors duration-200 flex items-center justify-center gap-1 border border-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-text="mood.icon" class="text-sm"></span>
                        <span x-text="mood.label" class="font-medium"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Resize Handle -->
        <div @mousedown="startResize($event)" 
             class="absolute bottom-0 right-0 w-6 h-6 cursor-nwse-resize flex items-center justify-center group">
            <svg class="w-4 h-4 text-gray-400 group-hover:text-imdb-yellow transition-colors rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
            </svg>
        </div>
    </div>

    <!-- Toggle Button - FIXED POSITION -->
    <button @click="toggleChat()" 
        class="fixed bottom-6 right-6 bg-imdb-yellow text-black p-4 rounded-full shadow-[0_0_15px_rgba(245,197,24,0.5)] hover:bg-yellow-400 transition-transform transform hover:scale-110 flex items-center justify-center border-2 border-white/20 z-50">
        <svg x-show="!isOpen" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
        </svg>
        <svg x-show="isOpen" style="display: none;" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none"
            viewBox="0 0 24 24" stroke="currentColor">
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
            userInput: '',
            // Position dari kanan bawah, default di dekat button
            position: { x: window.innerWidth - 450, y: 100 },
            size: { width: 384, height: 500 },
            dragging: false,
            resizing: false,
            dragStart: { x: 0, y: 0 },
            moodOptions: [
                { label: 'Senang', value: 'Aku lagi senang nih', icon: '😄' },
                { label: 'Sedih', value: 'Aku lagi sedih', icon: '😢' },
                { label: 'Bosan', value: 'Aku lagi bosan', icon: '😴' },
                { label: 'Tegang', value: 'Aku pengen film yang bikin tegang', icon: '😱' },
                { label: 'Romantis', value: 'Pengen nonton yang romantis', icon: '😍' },
                { label: 'Acak', value: 'Kasih rekomendasi film acak dong', icon: '🎲' } 
            ],
            
            init() {
                this.$watch('messages', () => {
                    this.$nextTick(() => {
                        this.$refs.chatContainer.scrollTop = this.$refs.chatContainer.scrollHeight;
                    });
                });

                // Event listeners untuk drag dan resize
                document.addEventListener('mousemove', (e) => this.handleMouseMove(e));
                document.addEventListener('mouseup', () => this.stopDragResize());
                
                // Set initial position
                this.updateInitialPosition();
                window.addEventListener('resize', () => this.updateInitialPosition());
            },

            updateInitialPosition() {
                this.position = {
                    x: Math.max(20, window.innerWidth - this.size.width - 100),
                    y: Math.max(20, window.innerHeight - this.size.height - 100)
                };
            },

            toggleChat() {
                this.isOpen = !this.isOpen;
                if (this.isOpen) {
                    // Pastikan posisi tidak keluar layar saat dibuka
                    this.position.x = Math.min(this.position.x, window.innerWidth - this.size.width - 20);
                    this.position.y = Math.min(this.position.y, window.innerHeight - this.size.height - 20);
                }
            },

            startDrag(e) {
                this.dragging = true;
                this.dragStart = {
                    x: e.clientX - this.position.x,
                    y: e.clientY - this.position.y
                };
            },

            startResize(e) {
                e.stopPropagation();
                this.resizing = true;
                this.dragStart = {
                    x: e.clientX,
                    y: e.clientY,
                    width: this.size.width,
                    height: this.size.height
                };
            },

            handleMouseMove(e) {
                if (this.dragging) {
                    const newX = e.clientX - this.dragStart.x;
                    const newY = e.clientY - this.dragStart.y;
                    
                    this.position = {
                        x: Math.max(0, Math.min(newX, window.innerWidth - this.size.width)),
                        y: Math.max(0, Math.min(newY, window.innerHeight - this.size.height))
                    };
                } else if (this.resizing) {
                    const deltaX = e.clientX - this.dragStart.x;
                    const deltaY = e.clientY - this.dragStart.y;
                    
                    this.size = {
                        width: Math.max(320, Math.min(800, this.dragStart.width + deltaX)),
                        height: Math.max(400, Math.min(900, this.dragStart.height + deltaY))
                    };
                }
            },

            stopDragResize() {
                this.dragging = false;
                this.resizing = false;
            },

            sendMessage() {
                if (!this.userInput.trim()) return;
                
                const message = this.userInput;
                this.messages.push({ sender: 'user', text: message });
                this.userInput = '';
                
                this.fetchResponse(message);
            },

            sendMood(mood) {
                this.messages.push({ sender: 'user', text: mood.value });
                this.fetchResponse(mood.value);
            },

            fetchResponse(message) {
                this.isLoading = true;

                fetch('{{ route("chatbot.recommend") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ message: message }) 
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    
                    if (!response.ok) {
                        return response.json().then(data => {
                            console.error('Error response:', data);
                            
                            // Show detailed error in development
                            let errorMsg = data.message || 'Maaf, lagi gangguan nih. Coba lagi ya! 😊';
                            if (data.error_debug) {
                                errorMsg += '\n\nDebug: ' + data.error_debug;
                            }
                            
                            throw new Error(errorMsg);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    this.isLoading = false;
                    
                    console.log('Success response:', data);
                    
                    if (!data.message) {
                        throw new Error('Invalid response from server');
                    }
                    
                    this.messages.push({ 
                        sender: 'bot', 
                        text: data.message,
                        films: data.films || []
                    });
                })
                .catch(error => {
                    this.isLoading = false;
                    console.error('Full error:', error);
                    
                    this.messages.push({ 
                        sender: 'bot', 
                        text: error.message || 'Maaf, lagi gangguan nih. Coba lagi ya! 😊'
                    });
                });
            }
        }
    }
</script>