<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ChatSystem
{
    public $currentShift;
    public $currentDate;
    public $currentUser;
    public $selectedHistoryDate;
    public $selectedHistoryShift;
    public $viewingHistory = false;
    public $shiftMessages = [];
    public $newChatMessage = '';
    public $messageLoading = false;

    public function initialize()
    {
        $now = Carbon::now('America/Sao_Paulo');
        $this->currentDate = $now->format('Y-m-d');
        $this->currentShift = $this->calculateCurrentShift($now);
        $this->currentUser = $this->getCurrentUserInfo();
        $this->selectedHistoryDate = $this->currentDate;
        $this->selectedHistoryShift = $this->currentShift;
        $this->viewingHistory = false;
        Log::info("Chat system initialized", [
            'current_shift' => $this->currentShift,
            'current_date' => $this->currentDate,
            'user' => $this->currentUser['name']
        ]);
    }

    public function calculateCurrentShift($dateTime)
    {
        $hour = $dateTime->hour;
        if ($hour >= 7 && $hour <= 18) {
            return 'dia';
        } else {
            return 'noite';
        }
    }

    public function getCurrentUserInfo()
    {
        if (Auth::check()) {
            $user = Auth::user();
            return [
                'id' => $user->id,
                'name' => $user->name ?? 'Usuário Logado',
                'role' => $user->role ?? 'Profissional de Saúde',
                'department' => $user->department ?? 'Enfermagem'
            ];
        }
        return [
            'id' => 0,
            'name' => 'Usuário Demonstração',
            'role' => 'Enfermeiro(a)',
            'department' => 'Enfermagem'
        ];
    }

    public function updatedSelectedHistoryDate()
    {
        $this->updateHistoryView();
    }

    public function updatedSelectedHistoryShift()
    {
        $this->updateHistoryView();
    }

    public function updateHistoryView()
    {
        $selectedDate = Carbon::parse($this->selectedHistoryDate);
        $currentDate = Carbon::parse($this->currentDate);
        $this->viewingHistory = (
            $this->selectedHistoryDate !== $this->currentDate || 
            $this->selectedHistoryShift !== $this->currentShift
        );
        if ($selectedDate->isFuture()) {
            $this->selectedHistoryDate = $this->currentDate;
            $this->selectedHistoryShift = $this->currentShift;
            $this->viewingHistory = false;
            session()->flash('error', 'Não é possível consultar datas futuras.');
            return;
        }
        $this->loadShiftMessages();
        Log::info("History view updated", [
            'selected_date' => $this->selectedHistoryDate,
            'selected_shift' => $this->selectedHistoryShift,
            'viewing_history' => $this->viewingHistory
        ]);
    }

    public function returnToCurrentShift()
    {
        $this->selectedHistoryDate = $this->currentDate;
        $this->selectedHistoryShift = $this->currentShift;
        $this->viewingHistory = false;
        $this->loadShiftMessages();
    }

    public function loadShiftMessages()
    {
        $this->shiftMessages = $this->getShiftMessagesData(
            $this->selectedHistoryDate, 
            $this->selectedHistoryShift
        );
    }

    public function getShiftMessagesData($date, $shift)
    {
        $demoData = [
            '2024-01-15' => [
                'dia' => [
                    [
                        'id' => 1,
                        'author' => 'Enfª Maria Santos',
                        'role' => 'Enfermeira',
                        'user_id' => 101,
                        'time' => '07:30',
                        'message' => 'PASSAGEM DE PLANTÃO: Paciente estável, sinais vitais dentro dos parâmetros. Última medicação 06:00. Familiares orientados sobre horário de visitas.',
                        'is_pinned' => true,
                        'can_edit' => false,
                        'pinned_by' => 'Enfª Chefe Ana Silva',
                        'created_at' => '2024-01-15 07:30:00'
                    ],
                    [
                        'id' => 2,
                        'author' => 'Téc. João Silva',
                        'role' => 'Técnico Enfermagem',
                        'user_id' => 102,
                        'time' => '09:15',
                        'message' => 'Higiene matinal realizada. Paciente colaborativo. Curativo de acesso vascular trocado conforme protocolo. Sem intercorrências.',
                        'is_pinned' => false,
                        'can_edit' => true,
                        'pinned_by' => null,
                        'created_at' => '2024-01-15 09:15:00'
                    ],
                    [
                        'id' => 3,
                        'author' => 'Dr. Carlos Lima',
                        'role' => 'Médico',
                        'user_id' => 103,
                        'time' => '11:45',
                        'message' => 'Reavaliação médica: Evolução satisfatória. Manter conduta atual. Laboratórios solicitados para amanhã. Atenção especial à diurese.',
                        'is_pinned' => false,
                        'can_edit' => false,
                        'pinned_by' => null,
                        'created_at' => '2024-01-15 11:45:00'
                    ]
                ],
                'noite' => [
                    [
                        'id' => 4,
                        'author' => 'Enfº Pedro Oliveira',
                        'role' => 'Enfermeiro',
                        'user_id' => 104,
                        'time' => '19:30',
                        'message' => 'Recepção de plantão: Paciente consciente, orientado. PA 120x80, FC 72bpm, Tax 36.2°C. Sem queixas álgicas. Acompanhante presente.',
                        'is_pinned' => false,
                        'can_edit' => true,
                        'pinned_by' => null,
                        'created_at' => '2024-01-15 19:30:00'
                    ],
                    [
                        'id' => 5,
                        'author' => 'Téc. Ana Souza',
                        'role' => 'Técnico Enfermagem',
                        'user_id' => 105,
                        'time' => '22:00',
                        'message' => 'Medicação das 22h administrada conforme prescrição médica. Paciente em repouso. Controles noturnos agendados de 4/4h.',
                        'is_pinned' => false,
                        'can_edit' => true,
                        'pinned_by' => null,
                        'created_at' => '2024-01-15 22:00:00'
                    ]
                ]
            ],
            '2024-01-16' => [
                'dia' => [
                    [
                        'id' => 6,
                        'author' => 'Enfª Maria Santos',
                        'role' => 'Enfermeira',
                        'user_id' => 101,
                        'time' => '07:45',
                        'message' => 'Plantão noturno sem intercorrências. Paciente dormiu bem. Medicações em dia. Laboratórios coletados às 06:00.',
                        'is_pinned' => false,
                        'can_edit' => true,
                        'pinned_by' => null,
                        'created_at' => '2024-01-16 07:45:00'
                    ]
                ],
                'noite' => []
            ]
        ];
        return $demoData[$date][$shift] ?? [];
    }

    public function sendChatMessage($currentUser, $currentShift, $currentDate, $viewingHistory, $message, &$shiftMessages, &$messageLoading)
    {
        $message = trim($message);
        if (empty($message)) {
            session()->flash('error', 'Mensagem não pode estar vazia.');
            return;
        }
        if ($viewingHistory) {
            session()->flash('error', 'Não é possível enviar mensagens em turnos anteriores.');
            return;
        }
        if ($this->isCurrentShiftClosed($currentShift)) {
            session()->flash('error', 'Turno encerrado. Não é possível enviar mensagens fora do horário de plantão.');
            return;
        }
        if (strlen($message) > 1000) {
            session()->flash('error', 'Mensagem muito longa. Máximo de 1000 caracteres.');
            return;
        }
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $messageLoading = true;
        try {
            usleep(500000);
            $newMessage = [
                'id' => time() + rand(1000, 9999),
                'author' => $currentUser['name'],
                'role' => $currentUser['role'],
                'user_id' => $currentUser['id'],
                'time' => Carbon::now('America/Sao_Paulo')->format('H:i'),
                'message' => $message,
                'is_pinned' => false,
                'can_edit' => true,
                'pinned_by' => null,
                'created_at' => Carbon::now('America/Sao_Paulo')->toDateTimeString()
            ];
            $shiftMessages[] = $newMessage;
            Log::info("Message sent successfully", [
                'user' => $currentUser['name'],
                'shift' => $currentShift,
                'date' => $currentDate,
                'message_length' => strlen($message)
            ]);
            session()->flash('success', 'Mensagem registrada com sucesso.');
        } catch (\Exception $e) {
            Log::error("Error sending message: " . $e->getMessage());
            session()->flash('error', 'Erro ao enviar mensagem. Tente novamente.');
        }
        $messageLoading = false;
    }

    public function isCurrentShiftClosed($currentShift)
    {
        $now = Carbon::now('America/Sao_Paulo');
        $currentHour = $now->hour;
        if ($currentShift === 'dia') {
            return ($currentHour < 7 || $currentHour >= 19);
        } else {
            return ($currentHour >= 7 && $currentHour < 19);
        }
    }

    public function toggleMessagePin($messageId, &$shiftMessages, $viewingHistory, $currentShift, $currentUser)
    {
        if ($viewingHistory) {
            session()->flash('error', 'Não é possível alterar mensagens em turnos anteriores.');
            return;
        }
        if ($this->isCurrentShiftClosed($currentShift)) {
            session()->flash('error', 'Turno encerrado. Não é possível alterar mensagens fora do horário de plantão.');
            return;
        }
        foreach ($shiftMessages as $index => $message) {
            if ($message['id'] == $messageId) {
                $wasUnpinning = $message['is_pinned'];
                $shiftMessages[$index]['is_pinned'] = !$message['is_pinned'];
                $shiftMessages[$index]['pinned_by'] = $wasUnpinning ? null : $currentUser['name'];
                $action = $wasUnpinning ? 'desfixada' : 'fixada';
                session()->flash('success', "Mensagem {$action} com sucesso.");
                Log::info("Message pin toggled", [
                    'message_id' => $messageId,
                    'action' => $action,
                    'user' => $currentUser['name']
                ]);
                break;
            }
        }
    }
}