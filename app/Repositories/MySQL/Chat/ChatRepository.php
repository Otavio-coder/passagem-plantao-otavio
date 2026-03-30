<?php

namespace App\Repositories\MySQL\Chat;

use App\Models\System\Chat\ChatMessage;
use App\Models\System\Chat\ChatMessagePin;
use Illuminate\Support\Facades\DB;

class ChatRepository
{
    /**
     * Loads messages for a patient from the last 30 days, ordered by created_at.
     * Reactions are grouped and attached to each message.
     * Pin status comes from an active (unpinned_at IS NULL) record in chat_message_pins.
     */
    public function getAllMessages(string $nr_atendimento, int $limit = 300): \Illuminate\Support\Collection
    {
        try {
            $cutoff = now()->subDays(30);

            $messages = DB::table('chat_messages as cm')
                ->leftJoin('chat_message_pins as cmp', function ($join) {
                    $join->on('cmp.message_id', '=', 'cm.id')
                        ->whereNull('cmp.unpinned_at');
                })
                ->where('cm.nr_atendimento', $nr_atendimento)
                ->where('cm.created_at', '>=', $cutoff)
                ->select([
                    'cm.id',
                    'cm.content',
                    'cm.user_id',
                    'cm.created_at',
                    'cm.updated_at',
                    DB::raw('CASE WHEN cmp.id IS NOT NULL THEN 1 ELSE 0 END as is_pinned'),
                ])
                ->orderBy('cm.created_at', 'asc')
                ->limit($limit)
                ->get();

            if ($messages->isEmpty()) {
                return $messages;
            }

            $reactions = DB::table('chat_reactions as cr')
                ->join('users as u', 'cr.user_id', '=', 'u.id')
                ->whereIn('cr.message_id', $messages->pluck('id'))
                ->select(['cr.message_id', 'cr.user_id', 'u.name', 'u.photo'])
                ->get()
                ->groupBy('message_id');

            return $messages->map(function ($msg) use ($reactions) {
                $msg->reactions = $reactions->get($msg->id, collect())->values()->toArray();
                return $msg;
            });

        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Stores a new message. No session/shift tracking.
     */
    public function storeMessage(array $data): ChatMessage
    {
        $msg = ChatMessage::create([
            'nr_atendimento'  => $data['nr_atendimento'],
            'cd_pessoa_fisica' => $data['cd_pessoa_fisica'] ?? null,
            'user_id'         => $data['user_id'],
            'content'         => $data['content'],
        ]);

        $msg->load('user');

        event(new \App\Events\ChatMessageSent($msg));

        return $msg;
    }

    /**
     * Toggles pin state for a message using chat_message_pins table.
     * If already pinned → unpins (sets unpinned_at).
     * If not pinned → unpins any other active pin for the patient, creates new pin.
     * Returns the message with is_pinned property set.
     */
    public function pinMessage(int $messageId, int $pinnedBy): ChatMessage
    {
        $msg    = ChatMessage::findOrFail($messageId);
        $isPinned = false;

        DB::transaction(function () use ($msg, $messageId, $pinnedBy, &$isPinned) {
            $existingPin = ChatMessagePin::where('message_id', $messageId)
                ->whereNull('unpinned_at')
                ->first();

            if ($existingPin) {
                // Already pinned → unpin
                $existingPin->update([
                    'unpinned_at' => now(),
                    'unpinned_by' => $pinnedBy,
                ]);
                $isPinned = false;
            } else {
                // Unpin any other active pin for this patient
                ChatMessagePin::where('nr_atendimento', $msg->nr_atendimento)
                    ->whereNull('unpinned_at')
                    ->update([
                        'unpinned_at' => now(),
                        'unpinned_by' => $pinnedBy,
                    ]);

                ChatMessagePin::create([
                    'message_id'     => $messageId,
                    'nr_atendimento' => $msg->nr_atendimento,
                    'pinned_by'      => $pinnedBy,
                    'pinned_at'      => now(),
                ]);
                $isPinned = true;
            }
        });

        $msg->is_pinned = $isPinned;

        event(new \App\Events\ChatMessagePinned($msg, $isPinned));

        return $msg;
    }

    /**
     * Updates message content (edit). Updates updated_at automatically.
     */
    public function updateMessage(int $id, string $newContent): ChatMessage
    {
        $msg = ChatMessage::findOrFail($id);
        DB::table('chat_messages')
            ->where('id', $id)
            ->update([
                'content'    => $newContent,
                'updated_at' => now(),
            ]);
        $msg->content    = $newContent;
        $msg->updated_at = now();
        return $msg;
    }

    /**
     * Returns true if the patient has messages in chat_messages older than 30 days.
     */
    public function hasOlderMessages(string $nr_atendimento): bool
    {
        return DB::table('chat_messages')
            ->where('nr_atendimento', $nr_atendimento)
            ->where('created_at', '<', now()->subDays(30))
            ->exists();
    }

    /**
     * Returns true if the patient has an archived history entry in chat_messages_archive.
     */
    public function hasArchivedHistory(string $nr_atendimento): bool
    {
        return DB::table('chat_messages_archive')
            ->where('nr_atendimento', $nr_atendimento)
            ->exists();
    }

    /**
     * Toggles a check reaction for a user on a message.
     * Returns ['reacted' => bool, 'reactions' => array]
     */
    public function toggleReaction(int $messageId, int $userId): array
    {
        $existing = DB::table('chat_reactions')
            ->where('message_id', $messageId)
            ->where('user_id', $userId)
            ->exists();

        if ($existing) {
            DB::table('chat_reactions')
                ->where('message_id', $messageId)
                ->where('user_id', $userId)
                ->delete();
            $reacted = false;
        } else {
            DB::table('chat_reactions')->insert([
                'message_id' => $messageId,
                'user_id'    => $userId,
                'type'       => 'check',
                'created_at' => now(),
            ]);
            $reacted = true;
        }

        $reactions = DB::table('chat_reactions as cr')
            ->join('users as u', 'cr.user_id', '=', 'u.id')
            ->where('cr.message_id', $messageId)
            ->select(['cr.user_id', 'u.name', 'u.photo'])
            ->get()
            ->toArray();

        return ['reacted' => $reacted, 'reactions' => $reactions];
    }
}
