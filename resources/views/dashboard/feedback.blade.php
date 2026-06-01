@extends('layouts.app')
@section('title', 'Feedback')

@section('content')
<div class="w-full px-3 my-2" x-data="feedbackForm()" x-cloak>

    {{-- ── Cabeçalho ─────────────────────────────────────────────────────────── --}}
    <div class="flex items-center gap-2 mb-5">
        <i class="fas fa-comment-dots text-santacasa-100 text-lg"></i>
        <h1 class="text-2xl font-medium text-santacasa-100">Feedback</h1>
    </div>

    {{-- ── Formulário ─────────────────────────────────────────────────────────── --}}
    <div x-show="!submitted"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <div class="max-w-4xl mx-auto">

            <div>
                <form @submit.prevent="submit" class="space-y-4">

                    {{-- Setor --}}
                    <div class="shadow sm:rounded-md bg-white overflow-visible">
                        <div class="px-4 py-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                Setor / Hospital <span class="text-red-500">*</span>
                            </label>
                            <div class="relative" @click.outside="sectorOpen = false">
                                <div class="relative">
                                    <input type="text"
                                           x-model="sectorQuery"
                                           @input.debounce.300ms="sectorSearch()"
                                           @focus="sectorSearch()"
                                           @keydown.escape="sectorOpen = false"
                                           @keydown.arrow-down.prevent="sectorHighlightNext()"
                                           @keydown.arrow-up.prevent="sectorHighlightPrev()"
                                           @keydown.enter.prevent="sectorSelectHighlighted()"
                                           placeholder="Digite o nome do setor ou hospital..."
                                           autocomplete="off"
                                           class="w-full border border-gray-300 rounded px-3 py-2 pr-8 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D] transition"
                                           :class="errors.sector ? 'border-red-400 bg-red-50' : (sectorSelected ? 'border-[#004D9D]/50 bg-[#004D9D]/5' : '')">
                                    <div class="absolute inset-y-0 right-2 flex items-center pointer-events-none">
                                        <template x-if="sectorLoading">
                                            <svg class="animate-spin w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                            </svg>
                                        </template>
                                        <template x-if="!sectorLoading && sectorSelected">
                                            <button type="button" @click="sectorClear()" class="pointer-events-auto text-gray-400 hover:text-gray-600 transition-colors">
                                                <i class="fas fa-times-circle fa-xs"></i>
                                            </button>
                                        </template>
                                        <template x-if="!sectorLoading && !sectorSelected">
                                            <i class="fas fa-search fa-xs text-gray-300"></i>
                                        </template>
                                    </div>
                                </div>

                                {{-- Dropdown --}}
                                <div x-show="sectorOpen && sectorResults.length > 0"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     class="absolute z-[9999] w-full mt-1 bg-white border border-gray-200 rounded shadow-lg max-h-56 overflow-y-auto">
                                    <template x-for="(item, idx) in sectorResults" :key="item.sector_code">
                                        <button type="button"
                                                @click="sectorSelect(item)"
                                                @mouseenter="sectorHighlighted = idx"
                                                :class="sectorHighlighted === idx ? 'bg-[#004D9D]/10 text-[#004D9D]' : 'text-gray-700'"
                                                class="w-full text-left px-3 py-2.5 text-sm hover:bg-[#004D9D]/5 transition-colors border-b border-gray-50 last:border-0">
                                            <span class="font-medium" x-text="item.sector_name"></span>
                                            <span class="text-xs text-gray-400 ml-1.5" x-text="item.hospital_name"></span>
                                        </button>
                                    </template>
                                </div>

                                <div x-show="sectorOpen && sectorResults.length === 0 && sectorQuery.length > 1 && !sectorLoading"
                                     class="absolute z-[9999] w-full mt-1 bg-white border border-gray-200 rounded shadow-lg px-3 py-2.5 text-sm text-gray-400">
                                    Nenhum setor encontrado.
                                </div>
                            </div>
                            <p x-show="errors.sector" x-text="errors.sector" class="text-xs text-red-500 mt-1.5"></p>
                        </div>
                    </div>

                    {{-- Turno --}}
                    <div class="shadow overflow-hidden sm:rounded-md bg-white">
                        <div class="px-4 py-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2.5">
                                Turno habitual de acesso <span class="text-red-500">*</span>
                            </label>
                            <div class="flex gap-2">
                                <template x-for="opt in shiftOptions" :key="opt.value">
                                    <button type="button" @click="form.shift = opt.value"
                                            :class="form.shift === opt.value
                                                ? 'bg-[#004D9D] text-white border-[#004D9D]'
                                                : 'bg-white text-gray-600 border-gray-300 hover:border-[#004D9D]/60 hover:text-[#004D9D]'"
                                            class="flex items-center gap-2 px-4 py-2 border rounded text-sm font-medium transition-colors">
                                        <i :class="opt.icon" class="fa-fw text-xs"></i>
                                        <span x-text="opt.label"></span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="errors.shift" x-text="errors.shift" class="text-xs text-red-500 mt-1.5"></p>
                        </div>
                    </div>

                    {{-- Avaliação --}}
                    <div class="shadow overflow-hidden sm:rounded-md bg-white">
                        <div class="px-4 py-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2.5">
                                Avaliação geral do sistema <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <template x-for="opt in ratingOptions" :key="opt.value">
                                    <button type="button" @click="form.rating = opt.value"
                                            :class="form.rating === opt.value
                                                ? 'bg-[#004D9D] text-white border-[#004D9D]'
                                                : 'bg-white text-gray-600 border-gray-300 hover:border-[#004D9D]/60 hover:text-[#004D9D]'"
                                            class="flex flex-col items-center gap-1.5 py-3 px-2 border rounded text-sm font-medium transition-colors">
                                        <i :class="opt.icon" class="fa-fw"></i>
                                        <span x-text="opt.label" class="text-xs font-medium"></span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="errors.rating" x-text="errors.rating" class="text-xs text-red-500 mt-1.5"></p>
                        </div>
                    </div>

                    {{-- Dificuldades --}}
                    <div class="shadow overflow-hidden sm:rounded-md bg-white">
                        <div class="px-4 py-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-0.5">
                                Dificuldades ou problemas encontrados <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-gray-400 mb-2">Onde ocorreu, como aconteceu e qual seria o comportamento esperado. Se não houve problemas, escreva "Nenhuma".</p>
                            <textarea x-model="form.difficulty" rows="4"
                                      placeholder="Descreva com o máximo de detalhes..."
                                      class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D] transition resize-none"
                                      :class="errors.difficulty ? 'border-red-400 bg-red-50' : ''"></textarea>
                            <div class="flex justify-between mt-1">
                                <p x-show="errors.difficulty" x-text="errors.difficulty" class="text-xs text-red-500"></p>
                                <p class="text-[10px] text-gray-400 ml-auto" x-text="(form.difficulty || '').length + '/5000'"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Sugestões --}}
                    <div class="shadow overflow-hidden sm:rounded-md bg-white">
                        <div class="px-4 py-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-0.5">
                                Sugestões ou comentários
                                <span class="text-xs font-normal text-gray-400">(opcional)</span>
                            </label>
                            <p class="text-xs text-gray-400 mb-2">Seja específico para facilitar a análise e implementação.</p>
                            <textarea x-model="form.suggestion" rows="3"
                                      placeholder="Descreva sua sugestão..."
                                      class="w-full border border-gray-300 rounded px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D] transition resize-none"></textarea>
                            <p class="text-[10px] text-gray-400 text-right mt-1" x-text="(form.suggestion || '').length + '/5000'"></p>
                        </div>
                    </div>

                    {{-- Rodapé: anonimato + submit --}}
                    <div class="shadow overflow-hidden sm:rounded-md bg-white">
                        <div class="px-4 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <div class="relative flex-shrink-0">
                                    <input type="checkbox" x-model="form.is_anonymous" class="sr-only peer">
                                    <div class="w-9 h-5 bg-gray-200 peer-checked:bg-[#004D9D] rounded-full transition-colors"></div>
                                    <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700">Enviar anonimamente</p>
                                    <p class="text-[11px] text-gray-400 leading-tight">Seu nome não será vinculado à resposta</p>
                                </div>
                            </label>

                            <button type="submit" :disabled="loading"
                                    class="inline-flex items-center gap-2 px-5 py-2 bg-[#004D9D] text-white rounded text-sm font-semibold hover:bg-[#003d7a] active:bg-[#002d5a] transition-colors disabled:opacity-60 disabled:cursor-not-allowed w-full sm:w-auto justify-center">
                                <template x-if="loading">
                                    <svg class="animate-spin w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </template>
                                <template x-if="!loading">
                                    <i class="fas fa-paper-plane fa-xs flex-shrink-0"></i>
                                </template>
                                <span x-text="loading ? 'Enviando...' : 'Enviar feedback'"></span>
                            </button>
                        </div>
                        <p x-show="errors.general" x-text="errors.general" class="text-xs text-red-500 text-center px-4 pb-3"></p>
                    </div>

                </form>
            </div>
        </div>
    </div>

    {{-- ── Confirmação de envio ────────────────────────────────────────────────── --}}
    <div x-show="submitted"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="shadow overflow-hidden sm:rounded-md bg-white px-6 py-12 text-center max-w-lg mx-auto mt-4">

        <div class="w-14 h-14 rounded-full bg-[#004D9D]/10 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-[#004D9D]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"
                      style="stroke-dasharray:30; stroke-dashoffset:30; animation: drawCheck .45s .1s ease forwards;"></path>
            </svg>
        </div>

        <h2 class="text-lg font-semibold text-gray-800 mb-1">Feedback registrado</h2>
        <p class="text-sm text-gray-500 mb-1">
            Recebido
            <span x-show="!form.is_anonymous"> de <strong class="text-gray-700" x-text="userName"></strong></span>
            <span x-show="form.is_anonymous">anonimamente</span>.
        </p>
        <p class="text-xs text-gray-400 mb-6">Analisamos cada retorno para melhorar o sistema.</p>

        <div class="flex gap-3 justify-center flex-wrap">
            <button @click="reset()"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-600 rounded text-sm font-medium hover:bg-gray-50 transition-colors">
                <i class="fas fa-plus fa-xs"></i>
                Novo feedback
            </button>
            <a href="{{ route('sbar.report') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-[#004D9D] text-white rounded text-sm font-semibold hover:bg-[#003d7a] transition-colors">
                <i class="fas fa-hospital fa-xs"></i>
                Voltar ao SBAR
            </a>
        </div>
    </div>

</div>

@push('scripts')
<script>
function feedbackForm() {
    return {
        submitted: false,
        loading: false,
        userName: '{{ addslashes(auth()->user()?->name ?? "") }}',
        form: { sector: '', shift: '', rating: '', difficulty: '', suggestion: '', is_anonymous: false },
        errors: {},

        // Sector search state
        sectorQuery: '',
        sectorResults: [],
        sectorLoading: false,
        sectorOpen: false,
        sectorHighlighted: -1,
        sectorSelected: false,

        shiftOptions: [
            { value: 'manha', label: 'Manhã',  icon: 'fas fa-sun' },
            { value: 'tarde', label: 'Tarde',  icon: 'fas fa-cloud-sun' },
            { value: 'noite', label: 'Noite',  icon: 'fas fa-moon' },
        ],
        ratingOptions: [
            { value: 'excelente', label: 'Excelente', icon: 'fas fa-face-grin-stars' },
            { value: 'bom',       label: 'Bom',       icon: 'fas fa-face-smile' },
            { value: 'regular',   label: 'Regular',   icon: 'fas fa-face-meh' },
            { value: 'ruim',      label: 'Ruim',      icon: 'fas fa-face-frown' },
        ],

        async sectorSearch() {
            if (this.sectorSelected) return;
            this.sectorLoading = true;
            this.sectorOpen = false;
            try {
                const url = '{{ route("feedback.sectors") }}?q=' + encodeURIComponent(this.sectorQuery);
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                this.sectorResults = await res.json();
                this.sectorOpen = this.sectorResults.length > 0;
                this.sectorHighlighted = -1;
            } catch (e) {
                this.sectorResults = [];
            } finally {
                this.sectorLoading = false;
            }
        },
        sectorSelect(item) {
            this.form.sector = item.sector_name + ' — ' + item.hospital_name;
            this.sectorQuery = item.sector_name + ' — ' + item.hospital_name;
            this.sectorSelected = true;
            this.sectorOpen = false;
            this.sectorResults = [];
            if (this.errors.sector) delete this.errors.sector;
        },
        sectorClear() {
            this.form.sector = '';
            this.sectorQuery = '';
            this.sectorSelected = false;
            this.sectorResults = [];
            this.sectorOpen = false;
        },
        sectorHighlightNext() {
            if (!this.sectorOpen) return;
            this.sectorHighlighted = Math.min(this.sectorHighlighted + 1, this.sectorResults.length - 1);
        },
        sectorHighlightPrev() {
            if (!this.sectorOpen) return;
            this.sectorHighlighted = Math.max(this.sectorHighlighted - 1, 0);
        },
        sectorSelectHighlighted() {
            if (this.sectorOpen && this.sectorHighlighted >= 0 && this.sectorResults[this.sectorHighlighted]) {
                this.sectorSelect(this.sectorResults[this.sectorHighlighted]);
            }
        },

        validate() {
            this.errors = {};
            if (!this.form.sector?.trim())      this.errors.sector = 'Selecione um setor da lista.';
            if (!this.form.shift)               this.errors.shift = 'Selecione um turno.';
            if (!this.form.rating)              this.errors.rating = 'Selecione uma avaliação.';
            if (!this.form.difficulty?.trim())  this.errors.difficulty = 'Descreva a dificuldade ou escreva "Nenhuma".';
            return Object.keys(this.errors).length === 0;
        },
        async submit() {
            if (!this.validate()) return;
            this.loading = true;
            this.errors = {};
            try {
                const res = await fetch('{{ route("feedback.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });
                if (!res.ok) {
                    const data = await res.json();
                    if (data.errors) {
                        Object.entries(data.errors).forEach(([k, v]) => {
                            this.errors[k] = Array.isArray(v) ? v[0] : v;
                        });
                    } else {
                        this.errors.general = 'Erro ao enviar. Tente novamente.';
                    }
                    return;
                }
                this.submitted = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                this.errors.general = 'Erro de conexão. Tente novamente.';
            } finally {
                this.loading = false;
            }
        },
        reset() {
            this.submitted = false;
            this.form = { sector: '', shift: '', rating: '', difficulty: '', suggestion: '', is_anonymous: false };
            this.errors = {};
            this.sectorQuery = '';
            this.sectorSelected = false;
            this.sectorResults = [];
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    };
}
</script>
@endpush

<style>
@keyframes drawCheck { to { stroke-dashoffset: 0; } }
[x-cloak] { display: none !important; }
</style>

@endsection
