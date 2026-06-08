<div class="space-y-6">

    {{-- Poll só quando há run ativo --}}
    @if($hasActiveRun)
    <div wire:poll.3s="checkStatus" style="display:none"></div>
    @endif

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#004D9D]/10 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-terminal text-[#004D9D]"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">Central de Comandos</h1>
                <p class="text-sm text-gray-500 mt-0.5">Execute manutenção sem acesso ao terminal do servidor</p>
            </div>
        </div>
        <a href="{{ route('horizon.index') }}" target="_blank"
           class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-colors">
            <i class="fas fa-chart-bar text-[10px]"></i> Horizon
        </a>
    </div>

    {{-- ── Output ativo ─────────────────────────────────────────────────────── --}}
    @if($activeRun)
    @php
        $running = in_array($activeRun->status, ['pending','running']);
        $borderCls = match($activeRun->status) { 'done' => 'border-emerald-400', 'failed' => 'border-red-400', default => 'border-[#004D9D]' };
    @endphp
    <div class="bg-gray-950 rounded-xl border-l-4 {{ $borderCls }} overflow-hidden shadow-sm">
        <div class="px-4 py-2.5 bg-gray-900 border-b border-gray-800 flex items-center gap-2">
            @if($running)
            <span class="w-2 h-2 rounded-full bg-[#004D9D] animate-pulse flex-shrink-0"></span>
            <span class="text-xs font-semibold text-gray-200 flex-1">{{ $activeRun->label }}</span>
            <span class="text-[10px] text-gray-400"><i class="fas fa-circle-notch fa-spin mr-1"></i>Executando...</span>
            @else
            <span class="w-2 h-2 rounded-full {{ $activeRun->status === 'done' ? 'bg-emerald-500' : 'bg-red-500' }} flex-shrink-0"></span>
            <span class="text-xs font-semibold text-gray-200 flex-1">{{ $activeRun->label }}</span>
            <span class="text-[10px] text-gray-400">
                {{ $activeRun->status }}
                @if($activeRun->completed_at && $activeRun->started_at)
                · {{ round(\Carbon\Carbon::parse($activeRun->started_at)->diffInSeconds(\Carbon\Carbon::parse($activeRun->completed_at))) }}s
                @endif
            </span>
            <button wire:click="$set('activeRunId', null)" class="text-gray-500 hover:text-gray-300 ml-1 transition-colors">
                <i class="fas fa-times text-xs"></i>
            </button>
            @endif
        </div>
        @if($activeRun->output)
        <pre class="px-4 py-3 text-[11px] text-emerald-300 font-mono leading-relaxed overflow-x-auto whitespace-pre-wrap max-h-48">{{ $activeRun->output }}</pre>
        @elseif($running)
        <p class="px-4 py-3 text-[11px] text-gray-500 font-mono">Aguardando saída...</p>
        @endif
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Comandos ────────────────────────────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Grupos --}}
            @foreach($this->catalog() as $group => $commands)
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-[#004D9D]"></div>
                <h2 class="text-sm font-semibold text-gray-700">{{ $group }}</h2>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="divide-y divide-gray-100">
                    @foreach($commands as $cmd)
                    @php $lastRun = $recentRuns->where('command_id', $cmd['id'])->first(); @endphp
                    <div class="flex items-center gap-4 px-4 py-3 hover:bg-gray-50/50 transition-colors"
                         x-data="{ confirm: false }">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800">{{ $cmd['label'] }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $cmd['description'] }}</p>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            @if($lastRun)
                            <span class="text-[10px] text-gray-400 hidden sm:flex items-center gap-1">
                                {{ \Carbon\Carbon::parse($lastRun->created_at)->diffForHumans() }}
                                @if($lastRun->status === 'done') <i class="fas fa-circle-check text-emerald-500"></i>
                                @elseif($lastRun->status === 'failed') <i class="fas fa-circle-xmark text-red-400"></i>
                                @else <i class="fas fa-circle-notch fa-spin text-[#004D9D]"></i>
                                @endif
                            </span>
                            @endif
                            @if(!$cmd['sync'])
                            <span class="text-[9px] font-medium text-gray-400 hidden sm:block">async</span>
                            @endif
                            <template x-if="!confirm">
                                <button type="button"
                                        @click="{{ $cmd['confirm'] ? 'confirm = true' : '$wire.run(\''.$cmd['id'].'\')'  }}"
                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-[#004D9D] hover:text-white hover:border-[#004D9D] transition-all">
                                    Executar
                                </button>
                            </template>
                            <template x-if="confirm">
                                <div class="flex gap-1.5">
                                    <button @click="confirm = false"
                                            class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
                                        Cancelar
                                    </button>
                                    <button @click="confirm = false; $wire.run('{{ $cmd['id'] }}')"
                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-amber-500 text-white hover:bg-amber-600">
                                        Confirmar
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        {{-- ── Lateral ──────────────────────────────────────────────────────────── --}}
        <div class="space-y-4">

            {{-- Histórico --}}
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-gray-400"></div>
                <h2 class="text-sm font-semibold text-gray-700">Histórico</h2>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                @if($recentRuns->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-gray-400">Nenhum comando executado ainda.</p>
                @else
                <div class="divide-y divide-gray-50">
                    @foreach($recentRuns as $run)
                    <div class="px-3 py-2.5 flex items-start gap-2 cursor-pointer hover:bg-gray-50 transition-colors"
                         wire:click="$set('activeRunId', {{ $run->id }})">
                        <i class="fas {{ match($run->status) { 'done' => 'fa-circle-check text-emerald-500', 'failed' => 'fa-circle-xmark text-red-400', 'running','pending' => 'fa-circle-notch fa-spin text-[#004D9D]', default => 'fa-clock text-gray-300' } }} text-xs mt-0.5 flex-shrink-0"></i>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-700 truncate">{{ $run->label }}</p>
                            <p class="text-[10px] text-gray-400">
                                {{ $run->user_name }} · {{ \Carbon\Carbon::parse($run->created_at)->diffForHumans() }}
                                @if($run->completed_at && $run->started_at)
                                · {{ round(\Carbon\Carbon::parse($run->started_at)->diffInSeconds(\Carbon\Carbon::parse($run->completed_at))) }}s
                                @endif
                            </p>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Agendamentos --}}
            <div class="flex items-center gap-2">
                <div class="w-1 h-4 rounded-full bg-gray-400"></div>
                <h2 class="text-sm font-semibold text-gray-700">Agendamentos</h2>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="divide-y divide-gray-100">
                    @foreach($scheduleItems as $item)
                    <div class="px-4 py-2.5">
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <p class="text-xs font-semibold text-gray-700">{{ $item['label'] }}</p>
                            <span class="text-[9px] font-medium text-gray-500 flex-shrink-0">{{ $item['schedule'] }}</span>
                        </div>
                        <p class="text-[10px] text-gray-400 font-mono truncate">{{ $item['command'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>
