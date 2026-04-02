<?php

namespace App\Support;

use Carbon\Carbon;

class ChatImportUserPayload
{
    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function fromCsvRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'username' => self::nullableString($row['username'] ?? null),
            'name' => self::nullableString($row['name'] ?? null),
            'email' => self::nullableString($row['email'] ?? null),
            'photo' => self::nullableString($row['photo'] ?? null),
            'password' => self::nullableString($row['password'] ?? null),
            'status' => self::nullableString($row['status'] ?? null) ?: 'A',
            'created_at' => self::dateTimeString($row['created_at'] ?? null),
            'updated_at' => self::dateTimeString($row['updated_at'] ?? null),
            'guid' => self::nullableString($row['guid'] ?? null),
            'domain' => self::nullableString($row['domain'] ?? null),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || strtoupper($value) === 'NULL' ? null : $value;
    }

    private static function dateTimeString(mixed $value): string
    {
        $value = self::nullableString($value);

        if ($value === null) {
            return Carbon::now()->toDateTimeString();
        }

        return $value;
    }
}
