@extends('layouts.app')

@section('content')
<div class="flex flex-col items-center justify-center min-h-[60vh] bg-white rounded-xl shadow-lg p-8">
    <img src="{{ asset('images/santacasa-horizontal-branco.svg') }}" alt="Santa Casa" class="mb-6 w-64">
    <h1 class="text-2xl font-bold text-[#004D9D] mb-2">Você está offline</h1>
    <p class="text-gray-600 text-lg mb-4">Sem conexão com a internet no momento.</p>
    <p class="text-gray-400 text-sm">Assim que a conexão for restabelecida, o sistema voltará ao funcionamento normal.</p>
    <a href="/" class="mt-6 px-4 py-2 bg-[#004D9D] text-white rounded-lg shadow hover:bg-[#0071B9] transition">Tentar novamente</a>
</div>
@endsection