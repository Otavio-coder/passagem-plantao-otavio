@extends('layouts.app')
@section('title', 'Feedback')

@section('content')
<div class="max-w-2xl mx-auto" x-data="feedbackForm()" x-cloak>

    {{-- ── Formulário ─────────────────────────────────────────────────────── --}}
    <div x-show="!submitted"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-4">

        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-xl bg-[#004D9D]/10 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-comment-dots text-[#004D9D]"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">Feedback</h1>
                <p class="text-sm text-gray-500 mt-0.5">Sua opinião melhora o sistema para todos</p>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3 mb-5 text-xs text-blue-700 leading-relaxed">
            Para sugestões que envolvam novas informações do Tasy, descreva a tela, aba e campo onde os dados estão. Para dúvidas específicas:
            <a href="mailto:inovacao@santacasa.org.br" class="font-semibold underline">inovacao@santacasa.org.br</a> ou
            <a href="mailto:caio.pontes@santacasa.org.br" class="font-semibold underline">caio.pontes@santacasa.org.br</a>
        </div>

        <form @submit.prevent="submit" class="space-y-4">

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-4">
                <label class="block text-sm font-semibold text-gray-800 mb-1">
                    1. Qual setor/hospital você utiliza o sistema? <span class="text-red-400">*</span>
                </label>
                <input type="text" x-model="form.sector"
                       placeholder="Ex: UTI Adulto — Hospital Dom Vicente Scherer"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D]/50 transition"
                       :class="errors.sector ? 'border-red-300 bg-red-50' : ''">
                <p x-show="errors.sector" x-text="errors.sector" class="text-xs text-red-500 mt-1"></p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-4">
                <label class="block text-sm font-semibold text-gray-800 mb-2">
                    2. Em qual turno você costuma acessar o sistema? <span class="text-red-400">*</span>
                </label>
                <div class="flex gap-2 flex-wrap">
                    <template x-for="opt in shiftOptions" :key="opt.value">
                        <button type="button" @click="form.shift = opt.value"
                                :class="form.shift === opt.value ? opt.activeClass + ' ring-2 ring-offset-1 ' + opt.ringClass : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100'"
                                class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-all">
                            <span x-text="opt.emoji" class="text-base leading-none"></span>
                            <span x-text="opt.label"></span>
                        </button>
                    </template>
                </div>
                <p x-show="errors.shift" x-text="errors.shift" class="text-xs text-red-500 mt-1"></p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-4">
                <label class="block text-sm font-semibold text-gray-800 mb-2">
                    3. Como você avalia o sistema SBAR? <span class="text-red-400">*</span>
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <template x-for="opt in ratingOptions" :key="opt.value">
                        <button type="button" @click="form.rating = opt.value"
                                :class="form.rating === opt.value ? opt.activeClass + ' ring-2 ring-offset-1 ' + opt.ringClass : 'bg-gray-50 border-gray-200 hover:bg-gray-100'"
                                class="flex flex-col items-center gap-1 py-3 px-2 rounded-xl border text-sm font-medium transition-all">
                            <span x-text="opt.emoji" class="text-2xl leading-none"></span>
                            <span x-text="opt.label" class="text-xs" :class="form.rating === opt.value ? opt.textClass : 'text-gray-500'"></span>
                        </button>
                    </template>
                </div>
                <p x-show="errors.rating" x-text="errors.rating" class="text-xs text-red-500 mt-1"></p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-4">
                <label class="block text-sm font-semibold text-gray-800 mb-0.5">
                    4. Você teve alguma dificuldade ou encontrou algum problema? <span class="text-red-400">*</span>
                </label>
                <p class="text-[11px] text-gray-400 mb-2">Informe onde ocorreu, como aconteceu e qual seria o comportamento ideal. Se não houve, escreva "Nenhuma".</p>
                <textarea x-model="form.difficulty" rows="4"
                          placeholder="Descreva com o máximo de detalhes..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D]/50 transition resize-none"
                          :class="errors.difficulty ? 'border-red-300 bg-red-50' : ''"></textarea>
                <div class="flex justify-between mt-1">
                    <p x-show="errors.difficulty" x-text="errors.difficulty" class="text-xs text-red-500"></p>
                    <p class="text-[10px] text-gray-300 ml-auto" x-text="(form.difficulty || '').length + '/5000'"></p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-4">
                <label class="block text-sm font-semibold text-gray-800 mb-0.5">
                    5. Sugestões ou comentários
                    <span class="text-xs font-normal text-gray-400">(opcional)</span>
                </label>
                <p class="text-[11px] text-gray-400 mb-2">Seja o mais detalhista possível para facilitar a análise e implementação.</p>
                <textarea x-model="form.suggestion" rows="3"
                          placeholder="Sua sugestão aqui..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D]/50 transition resize-none"></textarea>
                <p class="text-[10px] text-gray-300 text-right mt-1" x-text="(form.suggestion || '').length + '/5000'"></p>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <label class="flex items-center gap-2.5 cursor-pointer select-none group">
                    <div class="relative">
                        <input type="checkbox" x-model="form.is_anonymous" class="sr-only peer">
                        <div class="w-9 h-5 bg-gray-200 peer-checked:bg-[#004D9D] rounded-full transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-4"></div>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Enviar anonimamente</p>
                        <p class="text-[10px] text-gray-400">Seu nome não será vinculado à resposta</p>
                    </div>
                </label>

                <button type="submit" :disabled="loading"
                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#004D9D] text-white rounded-lg text-sm font-semibold hover:bg-[#003d7a] active:bg-[#002d5a] transition-colors disabled:opacity-60 disabled:cursor-not-allowed w-full sm:w-auto justify-center">
                    <template x-if="loading">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </template>
                    <template x-if="!loading"><i class="fas fa-paper-plane fa-xs"></i></template>
                    <span x-text="loading ? 'Enviando...' : 'Enviar feedback'"></span>
                </button>
            </div>

            <p x-show="errors.general" x-text="errors.general" class="text-xs text-red-500 text-center px-1"></p>
        </form>
    </div>

    {{-- ── Tela de agradecimento ────────────────────────────────────────────── --}}
    <div x-show="submitted"
         x-transition:enter="transition ease-out duration-500"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         class="flex flex-col items-center justify-center py-16 px-4 text-center">

        <div class="relative mb-6">
            <div class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center">
                <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"
                          style="stroke-dasharray:30; stroke-dashoffset:30; animation: drawCheck .5s .2s ease forwards;"></path>
                </svg>
            </div>
            <div class="absolute inset-0 rounded-full bg-emerald-400/20 animate-ping" style="animation-duration:2s"></div>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 mb-2">Obrigado pelo feedback!</h2>
        <p class="text-gray-500 text-sm max-w-sm mb-1">
            Sua resposta foi registrada
            <span x-show="!form.is_anonymous"> como <strong class="text-gray-700" x-text="userName"></strong></span>
            <span x-show="form.is_anonymous"> anonimamente</span>.
        </p>
        <p class="text-gray-400 text-xs max-w-sm mb-8">Analisamos cada retorno para melhorar continuamente o SBAR.</p>

        <div class="flex items-center gap-2 px-4 py-2 rounded-full border mb-8"
             :class="{ 'bg-emerald-50 border-emerald-200': form.rating==='excelente', 'bg-sky-50 border-sky-200': form.rating==='bom', 'bg-amber-50 border-amber-200': form.rating==='regular', 'bg-red-50 border-red-200': form.rating==='ruim' }">
            <span x-text="ratingOptions.find(r=>r.value===form.rating)?.emoji" class="text-lg"></span>
            <span class="text-sm font-medium"
                  :class="{ 'text-emerald-700': form.rating==='excelente', 'text-sky-700': form.rating==='bom', 'text-amber-700': form.rating==='regular', 'text-red-700': form.rating==='ruim' }"
                  x-text="'Você avaliou como ' + ratingOptions.find(r=>r.value===form.rating)?.label"></span>
        </div>

        <div class="flex gap-3 flex-wrap justify-center">
            <button @click="reset()"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 text-gray-600 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                <i class="fas fa-plus fa-xs"></i> Enviar outro feedback
            </button>
            <a href="{{ route('sbar.report') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-[#004D9D] text-white rounded-lg text-sm font-semibold hover:bg-[#003d7a] transition-colors">
                <i class="fas fa-hospital fa-xs"></i> Voltar ao SBAR
            </a>
        </div>
    </div>
</div>

<style>
@keyframes drawCheck { to { stroke-dashoffset: 0; } }
[x-cloak] { display: none !important; }
</style>

@push('scripts')
<script>
function feedbackForm() {
    return {
        submitted: false,
        loading: false,
        userName: '{{ addslashes(auth()->user()?->name ?? "") }}',
        form: { sector: '', shift: '', rating: '', difficulty: '', suggestion: '', is_anonymous: false },
        errors: {},
        shiftOptions: [
            { value: 'manha', label: 'Manhã', emoji: '🌅', activeClass: 'bg-amber-50 text-amber-700 border-amber-300',   ringClass: 'ring-amber-300' },
            { value: 'tarde', label: 'Tarde', emoji: '☀️',  activeClass: 'bg-sky-50 text-sky-700 border-sky-300',         ringClass: 'ring-sky-300' },
            { value: 'noite', label: 'Noite', emoji: '🌙', activeClass: 'bg-indigo-50 text-indigo-700 border-indigo-300', ringClass: 'ring-indigo-300' },
        ],
        ratingOptions: [
            { value: 'excelente', label: 'Excelente', emoji: '🤩', activeClass: 'bg-emerald-50 border-emerald-300', textClass: 'text-emerald-700', ringClass: 'ring-emerald-300' },
            { value: 'bom',       label: 'Bom',       emoji: '😊', activeClass: 'bg-sky-50 border-sky-300',         textClass: 'text-sky-700',     ringClass: 'ring-sky-300' },
            { value: 'regular',   label: 'Regular',   emoji: '😐', activeClass: 'bg-amber-50 border-amber-300',     textClass: 'text-amber-700',   ringClass: 'ring-amber-300' },
            { value: 'ruim',      label: 'Ruim',      emoji: '😞', activeClass: 'bg-red-50 border-red-300',         textClass: 'text-red-700',     ringClass: 'ring-red-300' },
        ],
        validate() {
            this.errors = {};
            if (!this.form.sector?.trim())      this.errors.sector = 'Informe seu setor/hospital.';
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
                    if (data.errors) Object.entries(data.errors).forEach(([k,v]) => { this.errors[k] = Array.isArray(v) ? v[0] : v; });
                    else this.errors.general = 'Erro ao enviar. Tente novamente.';
                    return;
                }
                this.submitted = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch(e) {
                this.errors.general = 'Erro de conexão. Tente novamente.';
            } finally {
                this.loading = false;
            }
        },
        reset() {
            this.submitted = false;
            this.form = { sector: '', shift: '', rating: '', difficulty: '', suggestion: '', is_anonymous: false };
            this.errors = {};
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    };
}
</script>
@endpush
@endsection
