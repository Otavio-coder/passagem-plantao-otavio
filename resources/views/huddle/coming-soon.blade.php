@extends('layouts.app')

@section('content')
<div class="grid place-items-center min-h-[calc(100vh-64px)] px-2">
    
    <div class="text-center max-w-md">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-[#004D9D]/10 mb-4">
            <svg class="w-8 h-8 text-[#004D9D]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
            </svg>
        </div>

        <h1 class="text-xl font-bold text-[#004D9D] mb-1">Huddle de Alta</h1>

        <p class="text-xs font-semibold uppercase tracking-widest text-slate-400 mb-3">
            Em breve
        </p>

        <p class="text-slate-500 text-sm">
            Esta funcionalidade está em desenvolvimento.
        </p>

        <a href="{{ route('home') }}"
           class="block mt-4 text-sm text-[#004D9D] hover:underline">
            ← Voltar
        </a>
    </div>

</div>
@endsection