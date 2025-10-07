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

    if (!$user) {
        Log::warning('[Chat] Acesso negado - usuário não autenticado', [
            'channel' => "chat.{$nr_atendimento}.{$turno_id}"
        ]);
        return false;
    }
    
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
        
        // AUTORIZAÇÃO SIMPLIFICADA: Por enquanto, qualquer usuário autenticado pode acessar
        // SUBSTITUA por suas regras de negócio específicas
        
        // Retornar dados do usuário para o canal (opcional)
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
// CANAL PRIVADO POR SESSÃO (Opcional)
// =============================================================================
Broadcast::channel('chat.sessao.{sessao_id}', function ($user, $sessao_id) {
  
    // Verificação básica
    if (!$user || empty($sessao_id)) {
        return false;
    }
    
    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});



// =============================================================================
// CANAL PRIVADO DO USUÁRIO (Para notificações pessoais)
// =============================================================================
Broadcast::channel('App.Models.System.User.{id}', function ($user, $id) {
    // Canal privado para notificações pessoais do usuário
    $canAccess = (int) $user->id === (int) $id;
    
    return $canAccess;
});
