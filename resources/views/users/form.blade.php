<form action="{{ route( 'users.create' ) }}" method="POST">

    @csrf

    <div id="form-user" class="shadow overflow-hidden sm:rounded-md mt-3 hidden">

        <div class="px-4 py-5 bg-white sm:p-6">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">

                <div class="">
                    <label for="name" class="block text-sm font-medium text-gray-700">Nome *</label>
                    <input type="text" placeholder="Digite para pesquisar" name="name" id="name" class="mt-1 border border-gray-300 px-3 py-2 rounded shadow-sm sm:text-sm block w-full lg:w-2/3 focus:outline-none focus:border-blue-500 hover:border-blue-500">
                </div>

                <div class="">
                    <label for="username" class="block text-sm font-medium text-gray-700">Usuário *</label>
                    <input readonly type="text" name="username" id="username" class="mt-1 border bg-gray-100 border-gray-300 px-3 py-2 rounded shadow-sm sm:text-sm block w-full lg:w-2/3 focus:outline-none focus:border-blue-500 hover:border-blue-500">
                </div>

                <div class="">
                    <label for="email" class="block text-sm font-medium text-gray-700">E-mail *</label>
                    <input readonly type="email" name="email" id="email" class="mt-1 border bg-gray-100 border-gray-300 px-3 py-2 rounded shadow-sm sm:text-sm block w-full lg:w-2/3 focus:outline-none focus:border-blue-500 hover:border-blue-500">
                </div>

                <div class="">
                    <label for="profile" class="block text-sm font-medium text-gray-700">Perfil *</label>
                    <select multiple class="mt-1 p-2 text-sm border border-solid border-gray-300 rounded shadow-sm hover:border-blue-500 w-full md:w-1/2 focus:outline-none" name="profile[]" id="profile">
                        <option value="" selected disabled>Selecione...</option>
                        @foreach( $roles as $role )
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>

            </div>
        </div>
        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
            <button type="submit" class="inline-flex justify-center py-1 px-4 border border-transparent shadow-sm text-sm font-medium rounded text-white bg-santacasa-100 hover:bg-santacasa-200 focus:outline-none focus:ring-2 focus:ring-offset-2">
                Salvar
            </button>
        </div>
    </div>
</form>
