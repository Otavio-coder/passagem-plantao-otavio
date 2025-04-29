@extends( 'layouts.app' )

@section('content')

    <div class="w-full px-3 my-2 text-gray-600">

        <div class="flex justify-between">
            <span class="text-2xl font-medium text-santacasa-100">
                <span class="mr-1 pb-3 text-xl"><i class="fas fa-users"></i></span>
                Gerenciamento de Usuários</span>
            <a href="#" onclick="event.preventDefault()" id="add-user"
               class="inline-flex justify-center items-center pt-1 font-medium text-santacasa-100">
                <i class="fas fa-plus-circle pt-1 mr-1 text-xl md:text-base"></i>
                <span class="hidden md:block">Adicionar</span>
            </a>
        </div>

        @include( 'users.form-user' )
        @include( 'users.modal-access-as' )

        <div class="shadow overflow-hidden sm:rounded-md mt-5 p-4 bg-white">
            <div class="hidden border-teal-500 text-teal-500 border-red-500 text-red-500"></div>
            <table id="table" class="display table text-gray-500" style="width:100%">
                <thead>
                <tr>
                    <th>Id</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Criado em</th>
                    <th>Status</th>
                    <th>Perfil</th>
                    <th>Ação</th>
                </tr>
                </thead>
                <tbody>

                @foreach( $users as $user )
                    @php
                        $color = $user->status == 'A' ? 'teal' : 'red';
                    @endphp
                    <tr>
                        <td>{{ $user->id }}</td>
                        <td class="name">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <span
                                class="px-2 border border-{{ $color }}-500 bg-white text-{{ $color }}-500 font-medium rounded-full">{{ $user->status_name }}</span>
                        </td>
                        <td>
                            @foreach( $user->getRoleNames() as $profile )
                                <span class="px-2 border border-blue-400 bg-blue-100 text-gray-700 text-sm rounded-full">{{ $profile }}</span>
                            @endforeach
                        </td>
                        <td>
                            <div class="flex justify-center items-center gap-2">
                                @can( 'acessar como' )
                                    @if( auth()->user()->id != $user->id )
                                        <a
                                            href="#"
                                            onclick="event.preventDefault(); openModal('modal-access-as')"
                                            class="access-as"
                                            title="Acessar como"
                                            data-accessuser="{{ $user->name }}"
                                            data-accessuserid="{{ $user->id }}"
                                        >
                                            <i class="fa-solid fa-person-walking-arrow-right text-santacasa-200"></i>
                                        </a>
                                    @endif
                                @endcan
                                <a href="#"
                                   data-id="{{ $user->id }}"
                                   data-name="{{ $user->name }}"
                                   data-username="{{ $user->username }}"
                                   data-email="{{ $user->email }}"
                                   data-status="{{ $user->status }}"
                                   data-profile="{{ $user->getRoleNames() }}"
                                   onclick="event.preventDefault(); openModal('modal-edit-user')"
                                   class="inline-flex justify-center font-medium text-santacasa-200 edit-user"
                                   title="Editar usuário">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach

            </table>
        </div>

        @include( 'users.modal-edit-user' )

    </div>

    @push( 'scripts' )

        <script src="https://twitter.github.io/typeahead.js/releases/latest/typeahead.bundle.js"></script>
        <script src="{{ asset( 'js/users/index.js' . preventCache() ) }}"></script>

        <script>
            $(document).ready(function () {

                let table = new DataTable( '#table', {
                    columnDefs: [
                        { targets: 0, visible: false },
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
