<div class="custom-container py-6 lg:py-10">
    <style>
        .search-main-input {
            background-color: transparent !important;
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            caret-color: #dc2626 !important;
        }
        .search-main-input::placeholder {
            color: #9ca3af !important;
            -webkit-text-fill-color: #9ca3af !important;
        }
        .search-main-input:-webkit-autofill,
        .search-main-input:-webkit-autofill:hover,
        .search-main-input:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff !important;
            -webkit-box-shadow: 0 0 0px 1000px #111827 inset !important;
            transition: background-color 5000s ease-in-out 0s !important;
        }

        /* Barra de rolagem vermelha e elegante para os gêneros */
        .genre-scroll-bar {
            scrollbar-width: thin !important;
            scrollbar-color: #dc2626 #111827 !important;
        }
        .genre-scroll-bar::-webkit-scrollbar {
            height: 6px !important;
        }
        .genre-scroll-bar::-webkit-scrollbar-track {
            background: #111827 !important;
            border-radius: 9999px !important;
        }
        .genre-scroll-bar::-webkit-scrollbar-thumb {
            background-color: #dc2626 !important;
            border-radius: 9999px !important;
        }
        .genre-scroll-bar::-webkit-scrollbar-thumb:hover {
            background-color: #ef4444 !important;
        }
    </style>

    <!-- Cabeçalho Integrado da Pesquisa (Sem quadros isolados) -->
    <div class="mb-8">
        <h1 class="text-2xl sm:text-4xl font-extrabold text-white tracking-tight">
            Encontre Filmes, Séries e Animes
        </h1>
        <p class="text-sm sm:text-base text-gray-400 mt-2">
            Busque pelo título, nome original ou explore por tipo e categorias.
        </p>

        <!-- Campo de Busca Principal (Ícone perfeitamente alinhado em Flexbox) -->
        <div class="mt-6 max-w-3xl">
            <div class="flex items-center w-full rounded-2xl px-4 py-3 sm:py-3.5 transition shadow-lg focus-within:ring-2 focus-within:ring-red-500/30"
                 style="background-color: #111827 !important; border: 1px solid #374151 !important;">
                
                <!-- Ícone de Busca em posição natural e fixa -->
                <svg class="w-5 h-5 text-gray-400 shrink-0 mr-3.5 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>

                <!-- Input transparente sem bordas conflitantes -->
                <input
                    type="search"
                    wire:model.live.debounce.500ms="q"
                    placeholder="Digite o nome do filme ou série..."
                    class="search-main-input w-full bg-transparent border-0 outline-none text-white text-sm sm:text-base p-0 focus:ring-0"
                    style="border: none !important; box-shadow: none !important; outline: none !important;"
                    autocomplete="off"
                    autofocus
                />

                <!-- Controles do Input (Limpar e Spinner de Carregamento) -->
                <div class="flex items-center gap-2 ml-2 shrink-0">
                    @if(!empty($q))
                        <button
                            type="button"
                            wire:click="clearQuery"
                            class="text-gray-400 hover:text-white p-1 rounded-full hover:bg-gray-800 transition cursor-pointer"
                            title="Limpar pesquisa">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    @endif

                    <div wire:loading wire:target="q" class="p-1" style="color: #dc2626 !important;">
                        <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros e Controles Integrada (Sem bordas brancas ou quadros artificiais) -->
    <div class="space-y-4 mb-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <!-- Filtro de Tipo (Todos / Filmes / Séries) -->
            <div class="inline-flex p-1 rounded-xl" style="background-color: #111827 !important; border: 1px solid #1f2937 !important;">
                <button
                    type="button"
                    wire:click="setType('')"
                    class="px-4 py-2 rounded-lg text-xs sm:text-sm font-medium transition cursor-pointer {{ empty($type) ? 'text-white shadow-sm' : 'text-gray-400 hover:text-white' }}"
                    @if(empty($type)) style="background-color: #dc2626 !important; color: #ffffff !important;" @endif>
                    Todos
                </button>
                <button
                    type="button"
                    wire:click="setType('movie')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs sm:text-sm font-medium transition cursor-pointer {{ $type === 'movie' ? 'text-white shadow-sm' : 'text-gray-400 hover:text-white' }}"
                    @if($type === 'movie') style="background-color: #dc2626 !important; color: #ffffff !important;" @endif>
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                    </svg>
                    <span>Filmes</span>
                </button>
                <button
                    type="button"
                    wire:click="setType('tv')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs sm:text-sm font-medium transition cursor-pointer {{ $type === 'tv' ? 'text-white shadow-sm' : 'text-gray-400 hover:text-white' }}"
                    @if($type === 'tv') style="background-color: #dc2626 !important; color: #ffffff !important;" @endif>
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="2" y="7" width="20" height="15" rx="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 2l-5 5-5-5"/>
                    </svg>
                    <span>Séries</span>
                </button>
            </div>

            <!-- Ordenação e Limpeza -->
            <div class="flex items-center gap-3 ml-auto">
                <!-- Seletor Nativo do Site (Custom Dropdown Alpine.js) -->
                <div class="relative" x-data="{ openSort: false }">
                    <button
                        type="button"
                        @click="openSort = !openSort"
                        class="inline-flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-medium text-gray-200 hover:text-white transition shadow-sm cursor-pointer min-w-[175px]"
                        style="background-color: #111827 !important; border: 1px solid #1f2937 !important;">
                        <span class="truncate font-medium">
                            @if($sort === 'vote_average')
                                Melhor avaliados (IMDb)
                            @elseif($sort === 'view')
                                Mais populares
                            @elseif($sort === 'release_date')
                                Data de lançamento
                            @elseif($sort === 'title')
                                Título (A-Z)
                            @else
                                Mais recentes
                            @endif
                        </span>
                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-200" :class="openSort ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div
                        class="origin-top-right z-50 absolute right-0 top-full mt-2 rounded-2xl shadow-2xl py-1.5 w-56 text-sm flex flex-col gap-1 p-1.5"
                        style="background-color: #111827 !important; border: 1px solid #1f2937 !important; display: none;"
                        x-show="openSort"
                        @click.outside="openSort = false"
                        @keydown.escape.window="openSort = false"
                        x-transition:enter="transition ease-out duration-150 transform"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-100 transform"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95">

                        <button
                            type="button"
                            wire:click="setSort('created_at')"
                            @click="openSort = false"
                            class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition flex items-center justify-between cursor-pointer {{ $sort === 'created_at' ? 'text-white shadow-sm' : 'text-gray-300 hover:text-white hover:bg-gray-800/80' }}"
                            @if($sort === 'created_at') style="background-color: #dc2626 !important; color: #ffffff !important;" @endif>
                            <span>Mais recentes</span>
                            @if($sort === 'created_at')
                                <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </button>

                        <button
                            type="button"
                            wire:click="setSort('vote_average')"
                            @click="openSort = false"
                            class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition flex items-center justify-between cursor-pointer {{ $sort === 'vote_average' ? 'text-white shadow-sm' : 'text-gray-300 hover:text-white hover:bg-gray-800/80' }}"
                            @if($sort === 'vote_average') style="background-color: #dc2626 !important; color: #ffffff !important;" @endif>
                            <span>Melhor avaliados (IMDb)</span>
                            @if($sort === 'vote_average')
                                <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </button>

                        <button
                            type="button"
                            wire:click="setSort('view')"
                            @click="openSort = false"
                            class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition flex items-center justify-between cursor-pointer {{ $sort === 'view' ? 'text-white shadow-sm' : 'text-gray-300 hover:text-white hover:bg-gray-800/80' }}"
                            @if($sort === 'view') style="background-color: #dc2626 !important; color: #ffffff !important;" @endif>
                            <span>Mais populares</span>
                            @if($sort === 'view')
                                <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </button>

                        <button
                            type="button"
                            wire:click="setSort('release_date')"
                            @click="openSort = false"
                            class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition flex items-center justify-between cursor-pointer {{ $sort === 'release_date' ? 'text-white shadow-sm' : 'text-gray-300 hover:text-white hover:bg-gray-800/80' }}"
                            @if($sort === 'release_date') style="background-color: #dc2626 !important; color: #ffffff !important;" @endif>
                            <span>Data de lançamento</span>
                            @if($sort === 'release_date')
                                <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </button>

                        <button
                            type="button"
                            wire:click="setSort('title')"
                            @click="openSort = false"
                            class="w-full text-left px-3.5 py-2.5 rounded-xl text-xs sm:text-sm font-medium transition flex items-center justify-between cursor-pointer {{ $sort === 'title' ? 'text-white shadow-sm' : 'text-gray-300 hover:text-white hover:bg-gray-800/80' }}"
                            @if($sort === 'title') style="background-color: #dc2626 !important; color: #ffffff !important;" @endif>
                            <span>Título (A-Z)</span>
                            @if($sort === 'title')
                                <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </button>
                    </div>
                </div>

                @if(!empty($type) || !empty($genre) || !empty($q))
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="text-xs text-gray-400 hover:text-red-400 px-3.5 py-2.5 rounded-xl transition flex items-center gap-1.5 cursor-pointer"
                        style="background-color: #111827 !important; border: 1px solid #1f2937 !important;"
                        title="Limpar todos os filtros">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span>Limpar</span>
                    </button>
                @endif
            </div>
        </div>

        <!-- Filtros de Gênero Integrados (Linha de chips com barra de rolagem vermelha) -->
        @if(count($genres) > 0)
            <div class="genre-scroll-bar flex items-center gap-2 overflow-x-auto pb-3 pt-1">
                <button
                    type="button"
                    wire:click="setGenre('')"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-medium transition cursor-pointer {{ empty($genre) ? 'text-white shadow-sm' : 'text-gray-400 hover:text-white' }}"
                    style="{{ empty($genre) ? 'background-color: #dc2626 !important; color: #ffffff !important;' : 'background-color: #111827 !important; border: 1px solid #1f2937 !important;' }}">
                    Todos os Gêneros
                </button>
                @foreach($genres as $g)
                    <button
                        type="button"
                        wire:click="setGenre('{{ $g->id }}')"
                        class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-medium transition cursor-pointer {{ (string) $genre === (string) $g->id ? 'text-white shadow-sm' : 'text-gray-400 hover:text-white' }}"
                        style="{{ (string) $genre === (string) $g->id ? 'background-color: #dc2626 !important; color: #ffffff !important;' : 'background-color: #111827 !important; border: 1px solid #1f2937 !important;' }}">
                        {{ $g->title }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Indicador de Status da Busca Integrado -->
    <div class="mb-6 flex items-center justify-between">
        @if($hasSearch)
            <h2 class="text-base sm:text-lg font-semibold text-gray-200 flex items-center gap-2">
                <span>Resultados para:</span>
                <span class="font-bold" style="color: #ef4444 !important;">"{{ $q }}"</span>
                @if($posts->count() > 0)
                    <span class="text-xs text-gray-300 px-2.5 py-1 rounded-full ml-2" style="background-color: #111827 !important; border: 1px solid #1f2937 !important;">
                        Página {{ $posts->currentPage() }}
                    </span>
                @endif
            </h2>
        @else
            <h2 class="text-base sm:text-lg font-semibold text-gray-200 flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z" />
                </svg>
                <span>Títulos Populares em Destaque</span>
            </h2>
        @endif
    </div>

    <!-- Grid de Resultados -->
    @if($posts->count() > 0)
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 2xl:grid-cols-8 gap-4 sm:gap-6">
            @foreach($posts as $post)
                <x-ui.post
                    :listing="$post"
                    :title="$post->title"
                    :image="$post->imageurl"
                    :vote="$post->vote_average"
                    :genres="$post->genres"
                />
            @endforeach
        </div>

        <!-- Paginação Livewire -->
        <div class="flex justify-center items-center gap-3 mt-10 mb-6">
            @if(!$posts->onFirstPage())
                <button
                    type="button"
                    wire:click="previousPage"
                    class="py-2.5 px-5 inline-flex justify-center items-center gap-2 rounded-full font-medium text-gray-300 shadow-sm hover:text-white transition text-sm cursor-pointer"
                    style="background-color: #111827 !important; border: 1px solid #1f2937 !important;">
                    <x-ui.icon name="left" class="w-3.5 h-3.5" fill="currentColor" />
                    <span>Anterior</span>
                </button>
            @endif

            @if($posts->hasMorePages())
                <button
                    type="button"
                    wire:click="nextPage"
                    class="py-2.5 px-5 inline-flex justify-center items-center gap-2 rounded-full font-medium text-gray-300 shadow-sm hover:text-white transition text-sm cursor-pointer"
                    style="background-color: #111827 !important; border: 1px solid #1f2937 !important;">
                    <span>Próximo</span>
                    <x-ui.icon name="right" class="w-3.5 h-3.5" fill="currentColor" />
                </button>
            @endif
        </div>

    @else
        <!-- Estado Vazio: Nenhum resultado encontrado -->
        <div class="rounded-3xl p-8 sm:p-12 text-center max-w-2xl mx-auto my-6"
             style="background-color: #111827 !important; border: 1px solid #1f2937 !important;">
            <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-full flex items-center justify-center mx-auto mb-4"
                 style="background-color: rgba(220, 38, 38, 0.15) !important; color: #ef4444 !important; border: 1px solid rgba(220, 38, 38, 0.3) !important;">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-white mb-2">
                Nenhum título encontrado para "{{ $q }}"
            </h3>
            <p class="text-sm text-gray-400 mb-6 max-w-md mx-auto">
                Tente verificar se o nome foi digitado corretamente ou faça uma busca mais ampla usando menos palavras.
            </p>
            <div class="flex justify-center gap-3">
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="px-5 py-2.5 text-white rounded-xl text-sm font-medium transition shadow-lg cursor-pointer"
                    style="background-color: #dc2626 !important;">
                    Limpar pesquisa e filtros
                </button>
            </div>
        </div>

        <!-- Sugestões de títulos recomendados quando não houver resultado -->
        @if(count($recommendations) > 0)
            <div class="mt-12">
                <div class="flex items-center gap-2 mb-6">
                    <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/>
                    </svg>
                    <h3 class="text-lg font-bold text-white">Títulos em alta que você pode gostar:</h3>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 2xl:grid-cols-8 gap-4 sm:gap-6">
                    @foreach($recommendations as $rec)
                        <x-ui.post
                            :listing="$rec"
                            :title="$rec->title"
                            :image="$rec->imageurl"
                            :vote="$rec->vote_average"
                            :genres="$rec->genres"
                        />
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
