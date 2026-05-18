<?php

namespace App\Livewire;

use App\Models\ChatPatientTodo;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SbarChatTodoList extends Component
{
    public int $patientId;

    public string $newItem = '';

    public string $newItemShift = '';

    public bool $showShiftPicker = false;

    public array $todos = [];

    public int $perPage = 6;

    public int $currentPage = 1;

    public function mount(int $patientId): void
    {
        $this->patientId = $patientId;
        $this->newItemShift = ShiftService::getCurrentShift();
        $this->loadTodos();
    }

    public function loadTodos(): void
    {
        $this->todos = ChatPatientTodo::forPatient($this->patientId)
            ->with('createdBy:id,name')
            ->orderBy('is_done')
            ->orderByDesc('has_alert')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($todo) => [
                'id' => $todo->id,
                'content' => $todo->content,
                'is_done' => $todo->is_done,
                'has_alert' => $todo->has_alert,
                'shift' => $todo->shift,
                'author' => $todo->createdBy?->name ?? 'Desconhecido',
                'created_at' => $todo->created_at?->format('d/m H:i'),
            ])
            ->all();

        $totalPages = max(1, (int) ceil(count($this->todos) / $this->perPage));
        if ($this->currentPage > $totalPages) {
            $this->currentPage = $totalPages;
        }
    }

    public function nextPage(): void
    {
        $totalPages = max(1, (int) ceil(count($this->todos) / $this->perPage));
        if ($this->currentPage < $totalPages) {
            $this->currentPage++;
        }
    }

    public function prevPage(): void
    {
        if ($this->currentPage > 1) {
            $this->currentPage--;
        }
    }

    public function addItem(): void
    {
        $trimmed = trim($this->newItem);

        if ($trimmed === '') {
            return;
        }

        ChatPatientTodo::create([
            'nr_atendimento' => $this->patientId,
            'created_by_user_id' => Auth::id(),
            'content' => $trimmed,
            'shift' => $this->newItemShift ?: null,
        ]);

        $this->newItem = '';
        $this->currentPage = 1;
        $this->loadTodos();
        $this->dispatch('todo-updated', patientId: $this->patientId);
    }

    public function toggleDone(int $id): void
    {
        $todo = ChatPatientTodo::forPatient($this->patientId)->findOrFail($id);

        $todo->update([
            'is_done' => ! $todo->is_done,
            'done_by_user_id' => $todo->is_done ? null : Auth::id(),
            'done_at' => $todo->is_done ? null : now(),
        ]);

        $this->loadTodos();
        $this->dispatch('todo-updated', patientId: $this->patientId);
    }

    public function toggleAlert(int $id): void
    {
        $todo = ChatPatientTodo::forPatient($this->patientId)->findOrFail($id);
        $todo->update(['has_alert' => ! $todo->has_alert]);

        $this->loadTodos();
    }

    public function removeItem(int $id): void
    {
        ChatPatientTodo::forPatient($this->patientId)->findOrFail($id)->delete();

        $this->loadTodos();
        $this->dispatch('todo-updated', patientId: $this->patientId);
    }

    #[On('refresh-todos')]
    public function refresh(): void
    {
        $this->loadTodos();
    }

    public function render()
    {
        $totalCount = count($this->todos);
        $totalPages = max(1, (int) ceil($totalCount / $this->perPage));
        $start = ($this->currentPage - 1) * $this->perPage;

        return view('livewire.sbar-chat-todo-list', [
            'paginatedTodos' => array_slice($this->todos, $start, $this->perPage),
            'totalCount' => $totalCount,
            'totalPages' => $totalPages,
        ]);
    }
}
