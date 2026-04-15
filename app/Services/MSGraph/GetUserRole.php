<?php

namespace App\Services\MSGraph;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait GetUserRole
{
    /** How many days the DB-cached role is considered fresh before re-fetching. */
    private const ROLE_CACHE_DAYS = 7;

    /**
     * Returns the user's role/title from Microsoft Graph.
     * The result is persisted in the users.role column so subsequent requests
     * do not hit the Graph API (same pattern as GetUserPhoto).
     */
    public function role(bool $forceRefresh = false): string
    {
        // Return DB-cached value if still fresh and not forcing a refresh
        if (! $forceRefresh && $this->hasValidCachedRole()) {
            return (string) ($this->role ?? '');
        }

        $username = trim((string) ($this->username ?? ''));

        if ($username === '') {
            return '';
        }

        $fetched = $this->fetchRoleFromGraph($username);

        $this->persistRole($fetched);

        return $fetched;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function hasValidCachedRole(): bool
    {
        if (! isset($this->role_synced_at) || $this->role_synced_at === null) {
            return false;
        }

        $syncedAt = $this->role_synced_at instanceof \DateTimeInterface
            ? Carbon::instance($this->role_synced_at)
            : Carbon::parse($this->role_synced_at);

        return $syncedAt->diffInDay(now()) < self::ROLE_CACHE_DAYS;
    }

    private function fetchRoleFromGraph(string $username): string
    {
        try {
            $token = AccessTokenGetter::get();

            if (empty($token)) {
                return '';
            }

            $response = Http::withToken($token)
                ->timeout(10)
                ->connectTimeout(5)
                ->get(
                    "https://graph.microsoft.com/v1.0/users/{$username}@santacasa.org.br",
                    ['$select' => 'jobTitle,department,companyName,officeLocation,employeeType']
                );

            if (! $response->successful()) {
                return '';
            }

            $payload = $response->json();

            return $this->resolveRoleLabel(is_array($payload) ? $payload : []);

        } catch (\Throwable $e) {
            Log::warning('MS Graph role lookup failed', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function persistRole(string $role): void
    {
        if (! $this instanceof Model) {
            return;
        }

        try {
            $this->role = $role;
            $this->role_synced_at = now();
            $this->saveQuietly();
        } catch (\Throwable $e) {
            Log::warning('Failed to persist user role', [
                'user_id' => $this->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function resolveRoleLabel(array $payload): string
    {
        foreach (['jobTitle', 'department', 'companyName', 'officeLocation', 'employeeType'] as $field) {
            $value = trim((string) ($payload[$field] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
