<?php

namespace Tests\Unit;

use App\Support\ChatImportUserPayload;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatImportUserPayloadTest extends TestCase
{
    #[Test]
    public function normalizes_csv_rows_for_user_inserts(): void
    {
        $payload = ChatImportUserPayload::fromCsvRow([
            'id' => '42',
            'username' => 'maria.souza',
            'name' => 'Maria Souza',
            'email' => 'maria.souza@example.com',
            'photo' => 'NULL',
            'password' => '$2y$12$hash',
            'status' => '',
            'created_at' => '2025-11-05 13:00:00',
            'updated_at' => 'NULL',
            'guid' => 'abc-123',
            'domain' => 'default',
        ]);

        $this->assertSame(42, $payload['id']);
        $this->assertSame('maria.souza', $payload['username']);
        $this->assertSame('Maria Souza', $payload['name']);
        $this->assertSame('maria.souza@example.com', $payload['email']);
        $this->assertNull($payload['photo']);
        $this->assertSame('A', $payload['status']);
        $this->assertSame('2025-11-05 13:00:00', $payload['created_at']);
        $this->assertSame('abc-123', $payload['guid']);
        $this->assertSame('default', $payload['domain']);
    }
}
