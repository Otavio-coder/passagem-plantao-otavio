/**
 * users/index.js — Lógica da tela de Gerenciamento de Usuários.
 *
 * Controla:
 *  - Botão "Adicionar" (toggle do formulário #form-user)
 *  - Typeahead de busca no Active Directory
 *  - Botão "Editar" (preenche o modal #modal-edit-user com os dados do usuário)
 *  - Botão "Acessar como" (preenche o modal #modal-access-as)
 */

$(document).ready(function () {

    // ── Adicionar Usuário (toggle do formulário) ────────────────────────
    $('#add-user').on('click', function (e) {
        e.preventDefault();
        $('#form-user').slideToggle(200);
    });

    // ── Typeahead: busca de usuários no AD ──────────────────────────────
    var userSearch = new Bloodhound({
        datumTokenizer: Bloodhound.tokenizers.obj.whitespace('name'),
        queryTokenizer: Bloodhound.tokenizers.whitespace,
        remote: {
            url: 'usuarios/search-user?q=%QUERY',
            wildcard: '%QUERY'
        }
    });

    $('#name').typeahead(
        { hint: true, highlight: true, minLength: 3 },
        {
            name: 'users',
            display: 'name',
            source: userSearch,
            templates: {
                suggestion: function (data) {
                    return '<div class="p-2 hover:bg-gray-100 cursor-pointer">' +
                        data.name +
                        '</div>';
                },
                notFound: '<div class="p-2 text-gray-400">Nenhum usuário encontrado no AD</div>'
            }
        }
    ).on('typeahead:select', function (ev, suggestion) {
        $('#username').val(suggestion.username);
        $('#email').val(suggestion.email);
    });

    // Fix: garante que o input do typeahead fique em largura total
    $('.twitter-typeahead').css({ 'width': '100%', 'display': 'block' });

    // ── Editar Usuário (preenche e abre o modal) ────────────────────────
    $(document).on('click', '.edit-user', function (e) {
        e.preventDefault();

        var $el = $(this);

        $('#user_id').val($el.data('id'));
        $('#edit_name').val($el.data('name'));
        $('#edit_username').val($el.data('username'));
        $('#edit_email').val($el.data('email'));
        $('#edit_status').val($el.data('status'));

        // Flag enfermeiro (checkbox toggle)
        var isNurse = $el.data('is-nurse');
        $('#edit_is_nurse').prop('checked', isNurse === 1 || isNurse === '1');

        // Perfis (multi-select): o data-profile vem como array JSON
        var profiles = $el.data('profile');
        var profileArray = [];

        if (typeof profiles === 'string') {
            try { profileArray = JSON.parse(profiles); } catch (ex) { profileArray = []; }
        } else if (Array.isArray(profiles)) {
            profileArray = profiles;
        }

        // Limpa e marca as opções corretas
        $('#edit_profile option').prop('selected', false);
        profileArray.forEach(function (p) {
            $('#edit_profile option').filter(function () {
                return $(this).val() === p;
            }).prop('selected', true);
        });

        openModal('modal-edit-user');
    });

    // ── Acessar Como (preenche e abre o modal) ──────────────────────────
    $(document).on('click', '.access-as', function (e) {
        e.preventDefault();

        var $el = $(this);
        $('#access-user').text($el.data('accessuser'));
        $('#access-user-id').val($el.data('accessuserid'));

        openModal('modal-access-as');
    });

});
