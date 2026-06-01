@extends('layouts.app')

@section('title', 'Histórico — At. ' . $nr)

@section('content')
<div class="max-w-3xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
        <div>
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-blue-500 hover:text-blue-700 flex items-center gap-1 mb-1">
                <i class="fas fa-arrow-left fa-xs"></i> Voltar ao histórico
            </a>
            <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-clock-rotate-left text-blue-500"></i>
                Atendimento {{ $nr }}
            </h1>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ number_format($summary['message_count'] ?? 0) }} anotações
                @if(!empty($summary['first_date']))
                    · de {{ $summary['first_date'] }}
                    até {{ $summary['last_date'] ?? '—' }}
                @endif
            </p>
        </div>
    </div>

    {{-- Mensagens --}}
    @if($messages->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <i class="fas fa-comment-slash text-4xl mb-3"></i>
            <p class="text-sm">Nenhuma anotação encontrada no arquivo.</p>
        </div>
    @else
        <div class="space-y-1">
            @foreach($timeline as $item)
                @if(($item['type'] ?? null) === 'separator')
                    <div class="flex items-center gap-2 my-3">
                        <div class="flex-1 h-px bg-gray-200"></div>
                        <span class="text-xs px-2 py-0.5 rounded-full border {{ $item['badge_class'] ?? 'bg-gray-50 border-gray-200 text-gray-500' }}">
                            {{ $item['label'] ?? '—' }}
                        </span>
                        <div class="flex-1 h-px bg-gray-200"></div>
                    </div>
                @elseif(($item['type'] ?? null) === 'message')
                    <div class="flex items-start gap-2.5 py-1.5">
                        <div class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs text-gray-600 font-semibold mt-0.5">
                            {{ $item['initial'] ?? '—' }}
                        </div>
                        <div class="flex-1 bg-white border border-gray-200 rounded-lg px-3 py-2 shadow-sm">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-medium text-gray-700">{{ $item['user'] ?? '—' }}</span>
                                <span class="text-xs text-gray-400">{{ $item['time'] ?? '—' }}</span>
                            </div>
                            <p class="text-xs text-gray-600 leading-relaxed whitespace-pre-line">{{ $item['text'] ?? '' }}</p>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

</div>
@endsection
