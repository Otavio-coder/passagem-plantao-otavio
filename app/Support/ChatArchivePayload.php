<?php

namespace App\Support;

class ChatArchivePayload
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, array<string, mixed>>  $users
     */
    public static function encode(array $messages, array $users = []): string
    {
        $json = json_encode([
            'messages' => array_values($messages),
            'users' => (object) $users,
        ], JSON_UNESCAPED_UNICODE);

        return base64_encode(gzcompress($json, 6));
    }

    /**
     * @return array{messages: array<int, array<string, mixed>>, users: array<string, array<string, mixed>>}
     */
    public static function decode(?string $payload): array
    {
        if (empty($payload)) {
            return ['messages' => [], 'users' => []];
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return ['messages' => [], 'users' => []];
        }

        $json = @gzuncompress($decoded);
        if ($json === false) {
            $json = $decoded;
        }

        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data)) {
            return ['messages' => [], 'users' => []];
        }

        if (array_is_list($data)) {
            return ['messages' => array_values($data), 'users' => []];
        }

        $messages = $data['messages'] ?? [];
        $users = $data['users'] ?? [];

        return [
            'messages' => is_array($messages) ? array_values($messages) : [],
            'users' => is_array($users) ? $users : [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $existingMessages
     * @param  array<string, array<string, mixed>>  $existingUsers
     * @param  array<int, array<string, mixed>>  $newMessages
     * @param  array<string, array<string, mixed>>  $newUsers
     */
    public static function merge(
        array $existingMessages,
        array $existingUsers,
        array $newMessages,
        array $newUsers
    ): string {
        $messages = array_values(array_merge($existingMessages, $newMessages));
        usort($messages, static fn (array $left, array $right): int => ($left['ts'] ?? 0) <=> ($right['ts'] ?? 0));
        $messages = array_values(array_unique($messages, SORT_REGULAR));

        return self::encode($messages, $existingUsers + $newUsers);
    }
}
