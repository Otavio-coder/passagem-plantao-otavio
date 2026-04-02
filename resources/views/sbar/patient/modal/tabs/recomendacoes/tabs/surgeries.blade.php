<div x-show="activeRecomendacaoTab === 'tab-surg'" style="display:none;" class="pt-3">

    @if($surgCount > 0)
    <p class="text-[10px] font-bold text-[#7712C7] uppercase tracking-wider mb-2">Cirurgias Agendadas</p>
    <div class="space-y-2">
        @foreach($plan['surgery']['items'] as $surg)
        <div class="bg-white rounded-lg border shadow-sm px-3 py-2.5
                {{ $surg['is_urgent'] ? 'border-red-200 bg-red-50/20' : 'border-[#7712C7]/25 bg-[#7712C7]/[0.06]' }}">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-gray-800 leading-snug">{{ $surg['descricao_padronizada'] ?? $surg['procedimento'] ?? $surg['name'] }}</p>
                    <div class="flex flex-wrap items-center gap-1.5 mt-1">
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded
                                     {{ $surg['is_urgent'] ? 'bg-red-100 text-red-700 ring-1 ring-red-300' : 'bg-[#7712C7]/15 text-[#7712C7] ring-1 ring-[#7712C7]/30' }}">
                            {{ $surg['carater'] }}
                        </span>
                        @if(!empty($surg['status']))
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 ring-1 ring-blue-200">
                            {{ $surg['status'] }}
                        </span>
                        @endif
                        @if(!empty($surg['sector_name']) || !empty($surg['sector_code']))
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                            <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                            {{ !empty($surg['sector_name']) ? $surg['sector_name'] : ('Setor ' . $surg['sector_code']) }}
                        </span>
                        @endif
                        @if($surg['sala'])
                        <span class="text-[10px] text-gray-500 font-medium">{{ $surg['sala'] }}</span>
                        @endif
                        @if($surg['dt'])
                        <span class="text-[10px] font-mono text-gray-400">
                            <i class="fa-regular fa-calendar mr-0.5"></i>{{ $surg['dt'] }}
                        </span>
                        @endif
                    </div>
                    @php
                        $surgeryDetail = $surg['observacoes'] ?? $surg['observation'] ?? null;
                    @endphp
                    @if(!empty($surgeryDetail))
                    <p class="text-[10px] text-gray-500 italic mt-1.5">Obs: {{ $surgeryDetail }}</p>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="bg-white rounded-xl border border-[#7712C7]/20 py-10 flex flex-col items-center gap-2">
        <i class="fa-solid fa-user-doctor text-[#7712C7]/30" style="font-size:28px;"></i>
        <p class="text-sm text-[#7712C7]/70">Nenhuma cirurgia agendada.</p>
    </div>
    @endif

</div>