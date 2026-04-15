<?php

namespace App\Support;

use App\Models\System\User;

class ChatArchiveUserResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, array<string, mixed>>  $usersByKey
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeMessages(array $messages, array $usersByKey = []): array
    {
        return array_map(static function (array $message) use ($usersByKey): array {
            $message['user'] = self::resolveUserLabel($message['user'] ?? null, $usersByKey);

            return $message;
        }, $messages);
    }

    /**
     * @param  array<string, array<string, mixed>>  $usersByKey
     */
    public static function resolveUserLabel(mixed $rawUser, array $usersByKey = []): string
    {
        if (is_array($rawUser)) {
            $rawUser = $rawUser['username'] ?? $rawUser['name'] ?? $rawUser['id'] ?? null;
        } elseif (is_object($rawUser)) {
            $rawUser = $rawUser->username ?? $rawUser->name ?? $rawUser->id ?? null;
        }

        if ($rawUser === null || $rawUser === '') {
            return '—';
        }

        $rawKey = (string) $rawUser;

        if (isset($usersByKey[$rawKey])) {
            $user = $usersByKey[$rawKey];
            $name = (string) ($user['name'] ?? $user['username'] ?? $rawKey);
            $role = trim((string) ($user['role'] ?? ''));

            return $role !== '' ? $name.' - '.$role : $name;
        }

        if (is_numeric($rawUser)) {
            $numericKey = (string) ((int) $rawUser);
            if (isset($usersByKey[$numericKey])) {
                $user = $usersByKey[$numericKey];
                $name = (string) ($user['name'] ?? $user['username'] ?? $rawKey);
                $role = trim((string) ($user['role'] ?? ''));

                return $role !== '' ? $name.' - '.$role : $name;
            }
        }

        // Fallback: look up by username in DB
        $dbUser = User::select(['id', 'name', 'role', 'role_synced_at'])
            ->where('username', $rawKey)
            ->first();

        if ($dbUser) {
            $name = trim((string) ($dbUser->name ?: $rawKey));
            $role = trim((string) $dbUser->getUserRole());

            return $role !== '' ? $name.' - '.$role : $name;
        }

        return $rawKey;
    }
}
