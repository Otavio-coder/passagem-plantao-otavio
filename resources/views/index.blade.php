@extends('layouts.app')

@section('content')
<div class="w-full px-3 my-2 text-[#004D9D] relative">
  <div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center">
        <h1 class="mt-2 text-3xl leading-8 font-bold tracking-tight text-[#004D9D] sm:text-4xl sm:tracking-tight">
          Bem-vindo ao {{ env('APP_NAME') }}
        </h1>
        <p class="mt-4 max-w-2xl text-xl text-gray-600 lg:mx-auto">
          Facilitando uma passagem de plantão segura, clara e padronizada para garantir a continuidade do cuidado.
        </p>
      </div>

      <div class="mt-16">
        <h2 class="text-lg text-[#004D9D] font-semibold text-center mb-10">
          Acesso rápido às funções do sistema
        </h2>

        @php
          // Monta array de cards somente com os acessíveis ao usuário
          $cards = [];

          if (auth()->user()->can('ver usuarios')) {
              $cards[] = [
                  'title' => 'Usuários',
                  'description' => 'Gerencie o cadastro de usuários que acessam este sistema',
                  'href' => route('users.index'),
                  'icon' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>',
              ];
          }

          if (auth()->user()->can('ver perfis')) {
              $cards[] = [
                  'title' => 'Perfis',
                  'description' => 'Gerencie o cadastro de perfis no sistema',
                  'href' => route('profiles.index'),
                  'icon' => '<svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3m-3 3h3m-3 3h3m-6 1c-.306-.613-.933-1-1.618-1H7.618c-.685 0-1.312.387-1.618 1M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/></svg>',
              ];
          }

          // SBAR sempre aparece
          $cards[] = [
              'title' => 'SBAR - Passagem de Plantão',
              'description' => 'Sistema de passagem de plantão estruturada para enfermeiros',
              'href' => route('sbar.report'),
              'icon' => '<svg width="24" height="24" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M32 9C31.4477 9 31 8.55228 31 8V6H29V8C29 9.30622 29.8348 10.4175 31 10.8293V12H30C29.4477 12 29 12.4477 29 13V19C29 19.5523 29.4477 20 30 20H34C34.5523 20 35 19.5523 35 19V13C35 12.4477 34.5523 12 34 12H33V11H38V23.0664H20.11C20.0285 23.0664 19.9502 23.0337 19.8925 22.9756L19.5261 22.6068L19.8945 22.2387C21.3678 20.7667 21.3686 18.3793 19.8963 16.9063L18.0961 15.1052C16.624 13.6323 14.2364 13.6315 12.7632 15.1034L12.0591 15.8069C10.7199 15.4263 9.22155 15.7669 8.16703 16.8285C6.61099 18.3951 6.61099 20.9376 8.16703 22.5042L9 23.3428V34H6V36H9.05051C8.40223 36.6353 8 37.5207 8 38.5C8 40.433 9.567 42 11.5 42C13.433 42 15 40.433 15 38.5C15 37.5207 14.5978 36.6353 13.9495 36H34.0505C33.4022 36.6353 33 37.5207 33 38.5C33 40.433 34.567 42 36.5 42C38.433 42 40 40.433 40 38.5C40 37.5207 39.5978 36.6353 38.9495 36H42V34H38V30.9997H38.0662C40.2388 30.9997 42 29.2238 42 27.033C42 25.5511 41.194 24.2589 40 23.5779V11C40 9.89543 39.1046 9 38 9H32ZM13.8263 16.8684L18.1167 21.1878L18.4809 20.8239C19.1727 20.1327 19.1731 19.0118 18.4818 18.3202L16.6816 16.5191C15.9902 15.8273 14.8687 15.8269 14.1768 16.5182L13.8263 16.8684ZM16.2752 30.6672C16.4867 30.8801 16.7733 30.9997 17.0722 30.9997H36V34H11V25.3563L16.2752 30.6672ZM18.4736 24.3851C18.905 24.8194 19.4932 25.0664 20.11 25.0664H38.0662C39.1185 25.0664 40 25.9311 40 27.033C40 28.1349 39.1186 28.9997 38.0662 28.9997H17.4379L9.586 21.0948C8.80467 20.3081 8.80467 19.0246 9.586 18.238C10.3564 17.4623 11.5973 17.4623 12.3677 18.238L18.4736 24.3851ZM13 38.5C13 39.3284 12.3284 40 11.5 40C10.6716 40 10 39.3284 10 38.5C10 37.6716 10.6716 37 11.5 37C12.3284 37 13 37.6716 13 38.5ZM36.5 40C37.3284 40 38 39.3284 38 38.5C38 37.6716 37.3284 37 36.5 37C35.6716 37 35 37.6716 35 38.5C35 39.3284 35.6716 40 36.5 40ZM31 18V14H33V18H31Z" fill="currentColor"/></svg>',
          ];
          @endphp

          @php
            $availableGroups = count($cards);
            $dlClass = $availableGroups === 1
              ? 'flex justify-center items-center min-h-[12rem]'
              : ($availableGroups === 0 ? 'hidden' : 'space-y-10 md:space-y-0 md:grid md:grid-cols-2 lg:grid-cols-3 md:gap-x-8 md:gap-y-10');
          @endphp

        <dl class="{{ $dlClass }}">
          @foreach ($cards as $card)
            <div class="group">
              <a
                href="{{ $card['href'] }}"
                class="quick-card relative flex flex-col h-36 bg-white border border-gray-200 shadow-lg rounded-xl p-6 hover:shadow-2xl hover:border-[#004D9D]/20 transition-all duration-300 transform hover:-translate-y-1 overflow-hidden"
              >
                <div class="flex items-start flex-1">
                  <div class="flex-shrink-0">
                    <div class="flex items-center justify-center h-12 w-12 rounded-xl bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white group-hover:shadow-lg transition-shadow">
                      {!! $card['icon'] !!}
                    </div>
                  </div>
                  <div class="ml-4 flex-1">
                    <h3 class="text-lg font-semibold text-[#004D9D] group-hover:text-[#0071B9] transition-colors mb-2">
                      {{ $card['title'] }}
                    </h3>
                    <p class="text-gray-600 text-sm leading-tight">
                      {{ $card['description'] }}
                    </p>
                  </div>
                </div>

                <div class="card-loading hidden absolute inset-0 bg-white/60 backdrop-blur-sm flex items-center justify-center z-10 rounded-xl">
                  <div class="flex items-center space-x-3">
                    <svg class="animate-spin h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span class="text-sm font-medium text-[#004D9D]">Carregando…</span>
                  </div>
                </div>
              </a>
            </div>
          @endforeach
        </dl>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('a.quick-card').forEach(function (cardLink) {
    cardLink.addEventListener('click', function (e) {
      // permitir abrir em nova aba / middle click / modifier keys
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || (e.button && e.button === 1)) {
        return; // deixa o comportamento do navegador acontecer
      }

      // prevenir navegação imediata para mostrar o overlay
      e.preventDefault();

      const href = cardLink.getAttribute('href');
      const target = cardLink.getAttribute('target');

      // mostra o overlay do próprio card
      const overlay = cardLink.querySelector('.card-loading');
      if (overlay) {
        overlay.classList.remove('hidden');
        cardLink.classList.add('pointer-events-none');
      }

      const redirect = function () {
        if (target === '_blank') {
          window.open(href, '_blank');
        } else {
          window.location.href = href;
        }
      };

      setTimeout(redirect, 120);
    });
  });
});
</script>
@endsection
