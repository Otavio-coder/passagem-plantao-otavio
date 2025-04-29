
<div id="modal-access-as" class="fixed z-50 inset-0 overflow-y-auto hidden">

    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:px-4">

        <div style="z-index: -1;" class="fixed inset-0 transition-opacity">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>

        <!-- This element is to trick the browser into centering the modal contents. -->
        <span class="hidden sm:inline-block align-middle sm:h-screen"></span>&#8203;

        <div class="inline-block align-middle bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 w-full lg:w-1/4" role="dialog" aria-modal="true" aria-labelledby="modal-headline">

            <div id="modal-content" class="bg-white p-3">
                <p class="sm:text-sm text-gray-600 font-medium uppercase">Acessar como</p>
            </div>

            <form action="{{ route( 'users.access.as' ) }}" method="post">

                @csrf

                <input type="hidden" name="access_user_id" id="access-user-id" />

                <div class="w-full text-gray-600">

                    <div class="px-3 py-5 bg-white sm:px-6">

                        <div>Você tem certeza que deseja acessar com o usuário <span class="font-semibold text-santacasa-300" id="access-user"></span> ? </div>

                    </div>

                </div>

                <div class="flex justify-center">
                    <div class="flex px-3 pt-2 pb-2">
                        <button type="submit" class="bg-santacasa-100 text-white px-6 py-1 mt-2 hover:bg-santacasa-200 rounded">
                            Sim
                        </button>
                    </div>
                    <div class="flex px-3 pt-2 pb-2">
                        <a href="#" onclick="event.preventDefault();closeModal('modal-access-as');" class="bg-gray-600 text-white px-6 py-1 mt-2 border border-gray-600 hover:bg-gray-800 rounded">
                            Não
                        </a>
                    </div>
                </div>

            </form>

        </div>

    </div>
</div>

