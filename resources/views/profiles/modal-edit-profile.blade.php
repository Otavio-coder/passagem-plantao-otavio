
<div id="modal-edit-profile" class="fixed z-50 inset-0 overflow-y-auto hidden">

    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:px-4">

        <div style="z-index: -1;" class="fixed inset-0 transition-opacity">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <!-- This element is to trick the browser into centering the modal contents. -->
        <span class="hidden sm:inline-block align-middle sm:h-screen"></span>&#8203;

        <div class="inline-block align-middle bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 w-full lg:w-1/2" role="dialog" aria-modal="true" aria-labelledby="modal-headline">

            <div id="modal-content" class="bg-white p-3">
                <p class="sm:text-sm text-gray-600 font-medium uppercase">Editar perfil</p>
            </div>

            <form action="{{ route( 'profiles.edit' ) }}" method="post">

                @csrf

                <input type="hidden" name="profile" id="profile_id" />

                <div class="w-full text-gray-600">

                    <div class="px-4 py-5 bg-white sm:px-6">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">

                            <div class="">
                                <label for="edit_name" class="block text-sm font-medium text-gray-700">Nome *</label>
                                <input type="text" name="name" id="edit_name" class="mt-1 border border-gray-300 px-3 py-2 rounded shadow-sm sm:text-sm block w-full focus:outline-none focus:border-blue-500 hover:border-blue-500">
                            </div>

                            <div class="">
                                <label for="edit_permissions" class="block text-sm font-medium text-gray-700">Permissões *</label>
                                <select multiple class="mt-1 p-2 text-sm border border-solid border-gray-300 rounded shadow-sm hover:border-blue-500 w-full md:w-1/2" name="permissions[]" id="edit_permissions">
                                    @foreach( $permissions as $permission )
                                        <option id="edit-permission-{{ $permission->id }}">{{ $permission->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="flex justify-center">
                    <div class="flex px-3 pt-2 pb-2">
                        <button type="submit" class="bg-santacasa-100 text-white px-4 py-1 mt-2 hover:bg-santacasa-200 rounded">
                            Atualizar
                        </button>
                    </div>
                    <div class="flex px-3 pt-2 pb-2">
                        <a href="#" onclick="event.preventDefault();closeModal('modal-edit-profile');" class="bg-gray-600 text-white px-4 py-1 mt-2 border border-gray-600 hover:bg-gray-800 rounded">
                            Fechar
                        </a>
                    </div>
                </div>

            </form>

        </div>

    </div>
</div>
