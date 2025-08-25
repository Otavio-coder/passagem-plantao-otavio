<?php
// =============================================================================
// ARQUIVO: routes/channels.php
// AÇÃO: SUBSTITUIR - Configuração corrigida para canais privados do chat
// =============================================================================

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// =============================================================================
// 🔐 CANAL PRIVADO DO CHAT - Para mensagens e fixações
// =============================================================================
Broadcast::channel('chat.{nr_atendimento}.{turno_id}', function ($user, $nr_atendimento, $turno_id) {
    // Log para debug da autenticação
    Log::info('[Chat] Autenticando canal privado', [
        'channel' => "chat.{$nr_atendimento}.{$turno_id}",
        'user_id' => $user?->id,
        'user_name' => $user?->name,
        'nr_atendimento' => $nr_atendimento,
        'turno_id' => $turno_id
    ]);
    
    // ✅ VERIFICAÇÃO BÁSICA: Usuário deve estar autenticado
    if (!$user) {
        Log::warning('[Chat] Acesso negado - usuário não autenticado', [
            'channel' => "chat.{$nr_atendimento}.{$turno_id}"
        ]);
        return false;
    }
    
    // ✅ VALIDAÇÃO DOS PARÂMETROS
    if (empty($nr_atendimento) || empty($turno_id)) {
        Log::warning('[Chat] Acesso negado - parâmetros inválidos', [
            'channel' => "chat.{$nr_atendimento}.{$turno_id}",
            'nr_atendimento' => $nr_atendimento,
            'turno_id' => $turno_id,
            'user_id' => $user->id
        ]);
        return false;
    }
    
    // 🔒 AUTORIZAÇÃO: Verificar se o usuário tem acesso ao paciente/turno
    // Aqui você pode implementar suas regras de negócio específicas
    
    try {
        // Exemplo 1: Verificar se o usuário está no turno atual
        // $currentShift = getCurrentShift(); // Implementar sua lógica
        // if ($turno_id !== $currentShift) {
        //     Log::warning('[Chat] Acesso negado - turno diferente', [
        //         'user_turno' => $currentShift,
        //         'requested_turno' => $turno_id,
        //         'user_id' => $user->id
        //     ]);
        //     return false;
        // }
        
        // Exemplo 2: Verificar se o usuário tem permissão para acessar o paciente
        // $hasAccess = checkPatientAccess($user->id, $nr_atendimento);
        // if (!$hasAccess) {
        //     Log::warning('[Chat] Acesso negado - sem permissão para o paciente', [
        //         'user_id' => $user->id,
        //         'nr_atendimento' => $nr_atendimento
        //     ]);
        //     return false;
        // }
        
        // Exemplo 3: Verificar role/permissões do usuário
        // if (!$user->hasRole(['medico', 'enfermeiro', 'admin'])) {
        //     Log::warning('[Chat] Acesso negado - role insuficiente', [
        //         'user_id' => $user->id,
        //         'user_roles' => $user->getRoleNames()
        //     ]);
        //     return false;
        // }
        
        // 🎯 AUTORIZAÇÃO SIMPLIFICADA: Por enquanto, qualquer usuário autenticado pode acessar
        // SUBSTITUA por suas regras de negócio específicas
        
        Log::info('[Chat] Acesso autorizado ao canal privado', [
            'channel' => "chat.{$nr_atendimento}.{$turno_id}",
            'user_id' => $user->id,
            'user_name' => $user->name
        ]);
        
        // ✅ Retornar dados do usuário para o canal (opcional)
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            // Adicione outros dados que precisar no frontend
        ];
        
        // OU simplesmente retorne true para autorizar
        // return true;
        
    } catch (\Exception $e) {
        Log::error('[Chat] Erro na autorização do canal', [
            'channel' => "chat.{$nr_atendimento}.{$turno_id}",
            'user_id' => $user->id,
            'error' => $e->getMessage()
        ]);
        
        return false;
    }
});

// =============================================================================
// 🔐 CANAL PRIVADO POR SESSÃO (Opcional)
// =============================================================================
Broadcast::channel('chat.sessao.{sessao_id}', function ($user, $sessao_id) {
    // Log para debug
    Log::info('[Chat] Autenticando canal de sessão', [
        'channel' => "chat.sessao.{$sessao_id}",
        'user_id' => $user?->id,
        'sessao_id' => $sessao_id
    ]);
    
    // Verificação básica
    if (!$user || empty($sessao_id)) {
        return false;
    }
    
    // Aqui você pode verificar se o usuário pertence à sessão
    // Exemplo: verificar se a sessão existe e se o usuário tem acesso
    
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});

// =============================================================================
// 🔓 CANAL PÚBLICO DE TESTE (Para desenvolvimento/debug)
// =============================================================================
// Broadcast::channel('chat.debug', function () {
//     // Canal público para testes - remover em produção
//     Log::info('[Chat] Acesso ao canal de debug (público)');
//     return true;
// });

// =============================================================================
// 📡 CANAL DE PRESENÇA (Para mostrar usuários online - opcional)
// =============================================================================
// Broadcast::channel('chat.presence.{nr_atendimento}.{turno_id}', function ($user, $nr_atendimento, $turno_id) {
//     if (!$user) {
//         return false;
//     }
//     
//     // Mesmo sistema de autorização do canal de chat
//     // ... suas verificações aqui
//     
//     return [
//         'id' => $user->id,
//         'name' => $user->name,
//         'photo' => $user->getUserPhoto() ?? '',
//         'online_since' => now()->toISOString()
//     ];
// });

// =============================================================================
// 🔐 CANAL PRIVADO DO USUÁRIO (Para notificações pessoais)
// =============================================================================
Broadcast::channel('App.Models.System.User.{id}', function ($user, $id) {
    // Canal privado para notificações pessoais do usuário
    $canAccess = (int) $user->id === (int) $id;
    
    Log::info('[Chat] Autenticando canal pessoal do usuário', [
        'channel' => "App.Models.System.User.{$id}",
        'user_id' => $user->id,
        'target_id' => $id,
        'authorized' => $canAccess
    ]);
    
    return $canAccess;
});

// =============================================================================
// 📋 EXPLICAÇÃO DAS CONFIGURAÇÕES:
// =============================================================================
/*
TIPOS DE CANAIS:

1. 🔐 PRIVADOS (Broadcast::channel): 
   - Requerem autenticação
   - Callback deve retornar true/false ou array com dados do usuário
   - Prefixo automático: "private-" no frontend

2. 🔓 PÚBLICOS (Broadcast::channel com callback que retorna true):
   - Não requerem autenticação
   - Qualquer um pode se conectar

3. 👥 PRESENÇA (Broadcast::presenceChannel):
   - Mostram lista de usuários conectados
   - Úteis para funcionalidades "quem está online"

AUTORIZAÇÃO:
- return true;        = Acesso liberado
- return false;       = Acesso negado  
- return array;       = Acesso liberado + dados do usuário
- throw Exception;    = Acesso negado

LOGS:
- Todos os acessos são logados para debug
- Verifique storage/logs/laravel.log para troubleshooting

FRONTEND:
- Use Echo.private('chat.123.tarde') para canais privados
- Use Echo.channel('chat.123.tarde') para canais públicos
- Use Echo.join('chat.presence.123.tarde') para canais de presença
*/