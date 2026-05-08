@extends('errors.default')

@section('content')

    <i class="fas fa-wifi-slash text-white text-9xl"></i>

    <div>
        <h6 class="text-2xl font-bold text-center text-gray-100 md:text-3xl">
            <span class="text-sky-900">Sem conexão</span>
        </h6>
        <p class="text-center text-gray-200 md:text-lg">
            Você está offline. Verifique sua conexão e tente novamente.
        </p>
    </div>

    <a href="/sbar" class="px-6 py-2 text-sm text-white rounded bg-sky-900 hover:bg-sky-700 shadow-sm">
        Tentar novamente
    </a>

@endsection
