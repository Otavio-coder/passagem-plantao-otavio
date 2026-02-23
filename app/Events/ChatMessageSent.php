<?php

namespace App\Events;

use App\Models\System\Chat\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(ChatMessage $message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("chat.{$this->message->nr_atendimento}");
    }

    public function broadcastWith()
    {
        $photo  = '';
        $author = 'Usuário';

        try {
            if (!$this->message->relationLoaded('user')) {
                $this->message->load('user');
            }

            if ($this->message->user) {
                $author = $this->message->user->name;
                if (method_exists($this->message->user, 'getUserPhoto')) {
                    $photo = $this->message->user->getUserPhoto();
                }
            } elseif ($this->message->user_id) {
                $authorDb = \App\Models\System\User::find($this->message->user_id);
                if ($authorDb) {
                    $author = $authorDb->name;
                    if (method_exists($authorDb, 'getUserPhoto')) {
                        $photo = $authorDb->getUserPhoto();
                    }
                }
            }

            if (!$photo && $this->message->user_id) {
                $photoData = DB::table('users')
                    ->where('id', $this->message->user_id)
                    ->value('photo');

                if ($photoData) {
                    if (!str_starts_with($photoData, 'data:')) {
                        $photo = $photoData;
                    } elseif (preg_match('/^data:image\/(\w+);base64,(.+)$/', $photoData, $matches)) {
                        $photo = $matches[2];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('[Chat] Erro ao obter dados do autor: ' . $e->getMessage());
        }

        $createdAt = null;
        try {
            $createdAt = $this->message->created_at
                ? $this->message->created_at->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s')
                : now()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            $createdAt = now()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        }

        return [
            'id'             => $this->message->id,
            'content'        => $this->message->content,
            'user_id'        => $this->message->user_id,
            'author'         => $author,
            'photo'          => $photo,
            'created_at'     => $createdAt,
            'is_pinned'      => false,
            'nr_atendimento' => $this->message->nr_atendimento,
        ];
    }

    public function broadcastAs()
    {
        return 'ChatMessageSent';
    }

    public function broadcastWhen()
    {
        return !empty($this->message->nr_atendimento);
    }
}
