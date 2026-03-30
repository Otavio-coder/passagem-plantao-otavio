@extends( 'layouts.app' )

@section('content')

    <div class="w-full px-3 my-2 text-gray-600">

        <div class="flex justify-between items-center mb-4">
            <span class="text-2xl font-medium text-santacasa-100">
                <i class="fas fa-users mr-1"></i>
                Gerenciamento de Usuários
            </span>
            <a href="#" onclick="event.preventDefault()" id="add-user"
               class="inline-flex justify-center items-center pt-1 font-medium text-santacasa-100">
                <i class="fas fa-plus-circle pt-1 mr-1 text-xl md:text-base"></i>
                <span class="hidden md:block">Adicionar</span>
            </a>
        </div>

        @include( 'users.form' )
        @include( 'users.modals.access-as' )
        @include( 'users.modals.block-user' )
        @include( 'users.modals.user-sectors' )

        <div class="shadow overflow-hidden sm:rounded-md mt-5 p-4 bg-white">
            <table id="table" class="display table text-gray-500" style="width:100%">
                <thead>
                <tr>
                    <th>Id</th>
                    <th></th>{{-- foto --}}
                    <th>Nome</th>
                    <th>Usuário</th>
                    <th>Criado em</th>
                    <th>Último acesso</th>
                    <th>Status</th>
                    <th>Perfil</th>
                    <th>Ação</th>
                </tr>
                </thead>
                <tbody>

                @foreach( $users as $user )
                    @php
                        $color   = $user->status == 'A' ? 'teal' : 'red';

                        // avatar
                        $validPhoto = $user->hasValidPhoto() ? $user->photo : null;
                        $words      = preg_split('/[\s.]+/', trim($user->name ?: 'U'));
                        $initials   = strtoupper(substr($words[0] ?? 'U', 0, 1));
                        if (count($words) > 1) $initials .= strtoupper(substr(end($words), 0, 1));
                        $palette    = ['4F46E5','0891B2','059669','B45309','7C3AED','0284C7','BE185D','0F766E'];
                        $avatarBg   = '#' . $palette[abs(crc32($user->name ?: 'U')) % count($palette)];

                        // setores agrupados por hospital
                        $sectorsByHospital = $user->sectorPreferences
                            ->groupBy('hospital_name')
                            ->map(fn($prefs) => $prefs->pluck('sector_name')->filter()->unique()->values())
                            ->toArray();
                        $hasSectors = !empty($sectorsByHospital);
                    @endphp
                    <tr>
                        <td>{{ $user->id }}</td>

                        {{-- Avatar --}}
                        <td class="text-center">
                            @if($validPhoto)
                                <img src="data:image/jpeg;base64,{{ $validPhoto }}"
                                     alt="{{ $user->name }}"
                                     class="w-8 h-8 rounded-full object-cover mx-auto"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-8 h-8 rounded-full items-center justify-center mx-auto text-white text-[10px] font-bold select-none hidden"
                                     style="background-color: {{ $avatarBg }}">{{ $initials }}</div>
                            @else
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mx-auto text-white text-[10px] font-bold select-none"
                                     style="background-color: {{ $avatarBg }}">{{ $initials }}</div>
                            @endif
                        </td>

                        <td>{{ $user->name }}</td>
                        <td class="font-mono text-xs">{{ $user->username }}</td>
                        <td data-order="{{ $user->created_at?->timestamp ?? 0 }}">
                            {{ $user->created_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td data-order="{{ $user->last_access_at?->timestamp ?? 0 }}">
                            {{ $user->last_access_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>

                        <td>
                            <span class="px-2 border border-{{ $color }}-500 bg-white text-{{ $color }}-500 font-medium rounded-full text-xs">
                                {{ $user->status_name }}
                            </span>
                        </td>

                        <td>
                            @foreach( $user->getRoleNames() as $profile )
                                <span class="px-2 border border-blue-400 bg-blue-100 text-gray-700 text-xs rounded-full whitespace-nowrap">{{ $profile }}</span>
                            @endforeach
                        </td>

                        <td>
                            <div class="flex justify-center items-center gap-3">

                                {{-- Setores --}}
                                <button
                                    type="button"
                                    title="{{ $hasSectors ? 'Ver setores configurados' : 'Sem setores configurados' }}"
                                    class="sectors-btn text-sky-500 hover:text-sky-700 transition-colors {{ !$hasSectors ? 'opacity-30 cursor-default' : '' }}"
                                    data-username="{{ $user->name }}"
                                    data-sectors="{{ json_encode($sectorsByHospital) }}"
                                    {{ !$hasSectors ? 'disabled' : '' }}
                                >
                                    <i class="fas fa-hospital text-sm"></i>
                                </button>

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

                                @can( 'editar usuarios' )
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
                                @endcan

                                @can( 'bloquear usuarios' )
                                    @if( auth()->user()->id !== $user->id )
                                        @if( !$user->hasRole('Administrador') || auth()->user()->hasRole('Administrador') )
                                            <button
                                                type="button"
                                                title="{{ $user->status === 'A' ? 'Bloquear usuário' : 'Desbloquear usuário' }}"
                                                class="block-user-btn {{ $user->status === 'A' ? 'text-red-400 hover:text-red-600' : 'text-green-500 hover:text-green-700' }} transition-colors"
                                                data-userid="{{ $user->id }}"
                                                data-username="{{ $user->name }}"
                                                data-status="{{ $user->status }}"
                                            >
                                                @if($user->status === 'A')
                                                    <i class="fas fa-ban"></i>
                                                @else
                                                    <i class="fas fa-check-circle"></i>
                                                @endif
                                            </button>
                                        @endif
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach

            </table>
        </div>

        @include( 'users.modals.edit-user' )

    </div>

    @push( 'scripts' )

        <script src="https://twitter.github.io/typeahead.js/releases/latest/typeahead.bundle.js"></script>
        <script src="{{ asset( 'js/users/index.js' . preventCache() ) }}"></script>

        <script>
            $(document).ready(function () {

                new DataTable( '#table', {
                    columnDefs: [
                        { targets: 0, visible: false },
                        { targets: 1, orderable: false, searchable: false, width: '40px' },
                        { targets: 8, orderable: false, searchable: false },
                        { className: 'dt-center', targets: '_all' }
                    ],
                    scrollX: true,
                    pageLength: 25,
                    ordering: true,
                    responsive: true,
                    language: {
                        url: "//cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json"
                    },
                });

            });

            // Modal de setores
            $(document).on('click', '.sectors-btn:not([disabled])', function () {
                const name    = $(this).data('username');
                const sectors = $(this).data('sectors'); // object: { hospital: [sector, ...] }

                $('#sectors-modal-title').text('Setores — ' + name);

                let html = '';
                const hospitals = Object.keys(sectors);

                if (hospitals.length === 0) {
                    html = '<p class="text-gray-400 text-sm text-center py-4">Nenhum setor configurado.</p>';
                } else {
                    hospitals.forEach(hospital => {
                        const sectorList = sectors[hospital] || [];
                        html += `<div class="mb-3 last:mb-0">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5">
                                <i class="fas fa-hospital-symbol mr-1"></i>${$('<div>').text(hospital).html()}
                            </p>
                            <div class="flex flex-wrap gap-1.5">`;
                        sectorList.forEach(sector => {
                            html += `<span class="px-2 py-0.5 bg-sky-50 text-sky-700 border border-sky-200 text-xs rounded-full">${$('<div>').text(sector).html()}</span>`;
                        });
                        html += `</div></div>`;
                    });
                }

                $('#sectors-modal-content').html(html);
                openModal('modal-user-sectors');
            });

            // Modal de bloquear/desbloquear usuário
            $(document).on('click', '.block-user-btn', function () {
                const userId  = $(this).data('userid');
                const name    = $(this).data('username');
                const isBlock = $(this).data('status') === 'A';

                $('#block-user-id').val(userId);
                $('#block-user-name').text(name);
                $('#block-action-verb').text(isBlock ? 'bloquear' : 'desbloquear');
                $('#block-modal-title').text(isBlock ? 'Bloquear Usuário' : 'Desbloquear Usuário');
                $('#block-modal-subtext').text(isBlock
                    ? 'O usuário não conseguirá acessar o sistema enquanto estiver bloqueado.'
                    : 'O usuário voltará a ter acesso ao sistema.');

                $('#block-icon-wrap').toggleClass('bg-red-50', isBlock).toggleClass('bg-green-50', !isBlock);
                $('#block-icon-ban').toggleClass('hidden', !isBlock);
                $('#block-icon-check').toggleClass('hidden', isBlock);

                $('#block-modal-confirm-btn')
                    .toggleClass('bg-red-600 hover:bg-red-700', isBlock)
                    .toggleClass('bg-green-600 hover:bg-green-700', !isBlock)
                    .text(isBlock ? 'Bloquear' : 'Desbloquear');

                openModal('modal-block-user');
            });

        </script>

    @endpush

@endsection
