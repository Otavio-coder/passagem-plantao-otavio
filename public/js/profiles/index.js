/**
 * profiles/index.js — Lógica da tela de Gerenciamento de Perfis.
 *
 * Controla:
 *  - Botão "Adicionar" (toggle do formulário #form-profile)
 *  - Botão "Editar" (preenche o modal #modal-edit-profile com dados do perfil)
 */

$(document).ready(function () {

    // ── Adicionar Perfil (toggle do formulário) ─────────────────────────
    $('#add-profile').on('click', function (e) {
        e.preventDefault();
        $('#form-profile').slideToggle(200);
    });

    // ── Editar Perfil (preenche e abre o modal) ─────────────────────────
    $(document).on('click', '.edit-profile', function (e) {
        e.preventDefault();

        var $el = $(this);

        $('#profile_id').val($el.data('id'));
        $('#edit_name').val($el.data('name'));

        // Permissões associadas ao perfil (array de IDs)
        var permissionIds = $el.data('permissions');
        var idArray = [];

        if (typeof permissionIds === 'string') {
            try { idArray = JSON.parse(permissionIds); } catch (ex) { idArray = []; }
        } else if (Array.isArray(permissionIds)) {
            idArray = permissionIds;
        }

        // Limpa e marca as opções corretas pelo ID do elemento
        $('#edit_permissions option').prop('selected', false);
        idArray.forEach(function (id) {
            $('#edit-permission-' + id).prop('selected', true);
        });

        openModal('modal-edit-profile');
    });

});
