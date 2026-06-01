{{-- Skeleton placeholder matching patient card dimensions and grid layout --}}
<div class="relative w-full h-[400px] md:h-[520px] lg:h-[420px] xl:h-[430px] rounded-xl shadow-md overflow-hidden animate-pulse bg-white">

    {{-- Card top strip (matches gradient header) --}}
    <div class="h-16 md:h-20 lg:h-16 bg-gradient-to-r from-gray-200 to-gray-150 flex items-center justify-between px-3 md:px-4">
        <div class="flex items-center gap-2">
            <div class="h-6 w-12 bg-gray-300 rounded-md"></div>
            <div class="h-4 w-20 bg-gray-200 rounded"></div>
        </div>
        <div class="h-6 w-14 bg-gray-300 rounded-full"></div>
    </div>

    {{-- Patient name + prontuário --}}
    <div class="px-3 md:px-4 pt-3 pb-2 border-b border-gray-100">
        <div class="h-4 w-3/4 bg-gray-200 rounded mb-2"></div>
        <div class="flex gap-3">
            <div class="h-3 w-1/4 bg-gray-100 rounded"></div>
            <div class="h-3 w-1/3 bg-gray-100 rounded"></div>
        </div>
    </div>

    {{-- Demographics chips row --}}
    <div class="px-3 md:px-4 py-2.5 flex items-center gap-2">
        <div class="h-5 w-16 bg-gray-100 rounded-full"></div>
        <div class="h-5 w-20 bg-gray-100 rounded-full"></div>
        <div class="h-5 w-14 bg-gray-100 rounded-full"></div>
    </div>

    {{-- Content rows (pending events / scales area) --}}
    <div class="px-3 md:px-4 py-2 space-y-2.5">
        <div class="h-3 w-full bg-gray-100 rounded"></div>
        <div class="h-3 w-5/6 bg-gray-100 rounded"></div>
        <div class="h-3 w-4/6 bg-gray-100 rounded"></div>
        <div class="h-3 w-full bg-gray-100 rounded"></div>
        <div class="h-3 w-3/5 bg-gray-100 rounded"></div>
    </div>

    {{-- Second content block --}}
    <div class="px-3 md:px-4 py-2 space-y-2 hidden md:block lg:hidden">
        <div class="h-3 w-full bg-gray-100 rounded"></div>
        <div class="h-3 w-4/5 bg-gray-100 rounded"></div>
        <div class="h-3 w-2/3 bg-gray-100 rounded"></div>
    </div>

    {{-- Bottom button placeholder --}}
    <div class="absolute bottom-0 left-0 right-0 p-2 border-t border-gray-100">
        <div class="h-9 md:h-11 lg:h-9 w-full bg-gray-200 rounded-md"></div>
    </div>
</div>
