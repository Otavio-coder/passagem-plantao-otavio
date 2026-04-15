<?php

namespace App\Services;

use App\Models\System\User;

class UserDisplayNameResolver
{
    /**
     * @var array<int, string>
     */
    private array $cache = [];

    public function fromUserId(?int $userId, ?string $fallbackName = null): string
    {
        if ($userId === null || $userId <= 0) {
            return $this->normalizeFallback($fallbackName);
        }

        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        $user = User::select(['id', 'name', 'username', 'role', 'role_synced_at'])->find($userId);
        if (! $user) {
            return $this->cache[$userId] = $this->normalizeFallback($fallbackName);
        }

        return $this->cache[$userId] = $this->fromUser($user, $fallbackName);
    }

    public function fromUser(?User $user, ?string $fallbackName = null): string
    {
        if (! $user) {
            return $this->normalizeFallback($fallbackName);
        }

        $name = trim((string) ($user->name ?: $fallbackName ?: 'Usuário'));
        $role = trim((string) $user->getUserRole());

        return $role !== '' ? $name.' - '.$role : $name;
    }

    public function fromName(?string $name): string
    {
        $fallbackName = trim((string) ($name ?: ''));
        if ($fallbackName === '') {
            return 'Usuário';
        }

        $user = User::query()
            ->select(['id', 'name', 'username', 'role', 'role_synced_at'])
            ->where('name', $fallbackName)
            ->orWhere('username', $fallbackName)
            ->first();

        return $this->fromUser($user, $fallbackName);
    }

    private function normalizeFallback(?string $fallbackName): string
    {
        $name = trim((string) ($fallbackName ?: 'Usuário'));

        return $name !== '' ? $name : 'Usuário';
    }
}
