@props(['patient'])

<div class="flex flex-col items-center justify-center h-full p-4 text-center">
    <div class="w-12 h-12 rounded-full bg-white/60 flex items-center justify-center mb-3">
        <i class="fas fa-bed text-gray-400 text-lg"></i>
    </div>
    <span class="inline-flex items-center bg-white/70 text-gray-700 text-sm font-bold px-3 py-1 rounded-full shadow-sm">
        Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
    </span>
    <p class="text-xs text-gray-500 mt-2">Leito vago</p>
</div>
