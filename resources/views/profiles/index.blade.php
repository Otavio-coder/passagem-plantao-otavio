@extends( 'layouts.app' )

@section('content')

    <div class="w-full px-3 my-2 text-gray-600">

        <div class="flex justify-between text-santacasa-100">
            <span class="text-2xl font-medium">
                <span class="mr-1 pb-3 text-xl"><i class="fas fa-users"></i></span>
                Gerenciamento de Perfis</span>
            <a href="#" onclick="event.preventDefault()" id="add-profile"
               class="inline-flex justify-center items-center pt-1 font-medium">
                <i class="fas fa-plus-circle pt-1 mr-1 text-xl md:text-base"></i>
                <span class="hidden md:block">Adicionar</span>
            </a>
        </div>

        @include( 'profiles.form' )

        <div class="shadow overflow-hidden sm:rounded-md mt-5 p-4 bg-white">
            <table id="table" class="display hover text-gray-500" style="width:100%">
                <thead>
                <tr>
                    <th>Id</th>
                    <th>Nome</th>
                    <th>Permissões</th>
                    <th>Criado em</th>
                    <th>Ação</th>
                </tr>
                </thead>
                <tbody>

                @foreach( $roles as $role )

                    <tr>
                        <td>{{ $role->id }}</td>
                        <td class="name">{{ $role->name }}</td>
                        <td>
                            @foreach( $role->permissions->pluck('name') as $permission )
                                <span
                                    class="px-2 border border-blue-400 bg-blue-100 text-gray-700 text-sm rounded-full">{{ $permission }}</span>
                            @endforeach
                        </td>
                        <td>{{ $role->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="#"
                               data-id="{{ $role->id }}"
                               data-name="{{ $role->name }}"
                               data-permissions="{{ $role->permissions->pluck('id') }}"
                               onclick="event.preventDefault(); openModal('modal-edit-profile')"
                               class="inline-flex justify-center pt-1 font-medium text-sky-600 edit-profile"
                               title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach

            </table>
        </div>

        @include( 'profiles.modals.edit-profile' )

    </div>

    @push( 'scripts' )

        <script src="https://twitter.github.io/typeahead.js/releases/latest/typeahead.bundle.js"></script>
        <script src="{{ asset( 'js/profiles/index.js' . preventCache() ) }}"></script>

        <script>
            $(document).ready(function () {

                let table = new DataTable( '#table', {
                    columnDefs: [
                        { target: 0, visible: false },
                        { className: 'dt-center', targets: '_all' }
                    ],
                    scrollX: true,
                    scrollY: true,
                    pageLength: 25,
                    scrollCollapse: true,
                    ordering: false,
                    responsive: true,
                    language: {
                        url: "//cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json"
                    },
                } );

            });
        </script>

    @endpush

@endsection
