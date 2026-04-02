<?php

namespace App\Support;

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
            if (! empty($user['username'])) {
                return (string) $user['username'];
            }

            if (! empty($user['name'])) {
                return (string) $user['name'];
            }
        }

        if (is_numeric($rawUser)) {
            $numericKey = (string) ((int) $rawUser);
            if (isset($usersByKey[$numericKey])) {
                $user = $usersByKey[$numericKey];
                if (! empty($user['username'])) {
                    return (string) $user['username'];
                }

                if (! empty($user['name'])) {
                    return (string) $user['name'];
                }
            }
        }

        return $rawKey;
    }
}
