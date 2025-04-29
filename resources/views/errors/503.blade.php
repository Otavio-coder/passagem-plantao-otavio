@extends( 'errors.default' )

@section( 'content' )

    <i class="fa fa-screwdriver-wrench text-white text-9xl"></i>

    <div>
        <h6 class="text-2xl font-bold text-center text-gray-100 md:text-3xl">
            <span class="text-sky-900">Oops,</span> site em manutenção
        </h6>
        <p class="text-center text-gray-200 md:text-lg">
            Este site está em manutenção, por favor aguarde.
        </p>
    </div>

    <a href="/" class="px-6 py-2 text-sm text-white rounded bg-sky-900 hover:bg-sky-700 shadow-sm">Atualizar</a>

@endsection
