@extends( 'errors.default' )

@section( 'content' )

    <h1 class="font-bold text-white text-9xl">403</h1>

    <div>
        <h6 class="text-2xl font-bold text-center text-gray-100 md:text-3xl">
            Acesso negado
        </h6>
        <p class="text-center text-gray-200 md:text-lg">
            Você não tem acesso a essa aplicação.
        </p>
    </div>

    <a href="{{ url()->previous() }}"
       class="px-6 py-2 text-sm text-white rounded bg-sky-900 hover:bg-sky-700 shadow-sm">Voltar</a>

@endsection
