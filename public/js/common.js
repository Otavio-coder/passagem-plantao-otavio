/**
 * common.js — Funções globais de modal Alpine.js (openModal/closeModal).
 *
 * Os modais do sistema (users, profiles, metrics) usam o padrão:
 *   <div id="modal-xxx" class="... hidden" x-data="{ open: false }" x-init="
 *       $el._show = () => { open = true; ... };
 *       $el._hide = () => { open = false; ... };
 *   ">
 *
 * openModal(id) remove a classe 'hidden' e chama _show() do Alpine.
 * closeModal(id) chama _hide() e depois adiciona 'hidden' após a animação.
 */

window.openModal = function (id) {
    var el = document.getElementById(id);
    if (!el) return;

    el.classList.remove('hidden');

    // Aguarda um frame para o Alpine processar a remoção do hidden antes de animar
    requestAnimationFrame(function () {
        if (typeof el._show === 'function') {
            el._show();
        }
    });
};

window.closeModal = function (id) {
    var el = document.getElementById(id);
    if (!el) return;

    if (typeof el._hide === 'function') {
        el._hide();
    }

    // Aguarda a animação de saída (200ms definida no x-transition:leave) antes de esconder
    setTimeout(function () {
        el.classList.add('hidden');
    }, 250);
};
