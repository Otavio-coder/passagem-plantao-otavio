<?php

namespace Tests\Unit;

use App\Support\ChatArchivePayload;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatArchivePayloadTest extends TestCase
{
    #[Test]
    public function decodes_legacy_message_array_payloads(): void
    {
        $encoded = base64_encode(gzcompress(json_encode([
            ['ts' => 1, 'u' => 'user1', 'm' => 'Oi', 't' => 'manha'],
        ], JSON_UNESCAPED_UNICODE), 6));

        $decoded = ChatArchivePayload::decode($encoded);

        $this->assertCount(1, $decoded['messages']);
        $this->assertSame([], $decoded['users']);
    }

    #[Test]
    public function decodes_enveloped_payloads_with_users(): void
    {
        $encoded = ChatArchivePayload::encode(
            [['ts' => 1, 'u' => 'user1', 'm' => 'Oi', 't' => 'manha']],
            ['user1' => ['username' => 'user1', 'photo' => 'abc']]
        );

        $decoded = ChatArchivePayload::decode($encoded);

        $this->assertCount(1, $decoded['messages']);
        $this->assertArrayHasKey('user1', $decoded['users']);
        $this->assertSame('abc', $decoded['users']['user1']['photo']);
    }
}
