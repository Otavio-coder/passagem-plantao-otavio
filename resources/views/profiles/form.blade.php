<form action="{{ route( 'profiles.create' ) }}" method="POST">

    @csrf

    <div id="form-profile" class="shadow overflow-hidden sm:rounded-md mt-3 hidden">

        <div class="px-4 py-5 bg-white sm:p-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">

                <div class="">
                    <label for="name" class="block text-sm font-medium text-gray-700">Nome *</label>
                    <input type="text" placeholder="Insira um nome para o perfil" name="name" id="name" class="mt-1 border border-gray-300 px-3 py-2 rounded shadow-sm sm:text-sm block w-full md:w-1/2 focus:outline-none focus:border-blue-500 hover:border-blue-500">
                </div>

                <div class="">
                    <label for="permissions" class="block text-sm font-medium text-gray-700">Permissões *</label>
                    <select multiple class="mt-1 p-2 text-sm border border-solid border-gray-300 rounded shadow-sm hover:border-blue-500 w-full md:w-1/2" name="permissions[]" id="permissions">
                        <option value="" selected disabled>Selecione...</option>
                        @foreach( $permissions as $permission )
                            <option>{{ $permission->name }}</option>
                        @endforeach
                    </select>
                </div>

            </div>
        </div>
        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
            <button type="submit" class="inline-flex justify-center py-1 px-4 border border-transparent shadow-sm text-sm font-medium rounded text-white bg-santacasa-100 hover:bg-santacasa-200 focus:outline-none">
                Salvar
            </button>
        </div>
    </div>
</form>
