@props(['patient'])
<div class="h-full w-full flex flex-col min-h-0">
    <div class="flex-1 flex items-center justify-center min-w-0">
        <div class="w-full h-full flex flex-col bg-gradient-to-br from-gray-200 to-gray-300 p-4 rounded-xl overflow-hidden min-h-0">
            <div class="flex justify-between items-center mb-3 flex-shrink-0 min-w-0">
                <span class="bg-white/70 text-gray-700 text-sm font-bold px-3 py-1 rounded-full">
                    Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                </span>
            </div>
            <div class="flex-grow flex items-center justify-center">
                <div class="text-center">
                    <x-healthicons-o-inpatient class="mx-auto h-12 w-12 text-gray-400 mb-2" />
                    <p class="text-gray-500 text-base font-medium">Leito Vago</p>
                </div>
            </div>
        </div>
    </div>
</div>
