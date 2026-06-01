<?php

namespace Tests\Unit;

use App\Support\ChatArchiveUserResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatArchiveUserResolverTest extends TestCase
{
    #[Test]
    public function resolves_numeric_user_ids_to_usernames(): void
    {
        $messages = ChatArchiveUserResolver::normalizeMessages([
            ['user' => 42, 'text' => 'Olá'],
        ], [
            '42' => ['id' => 42, 'username' => 'enf.maria', 'name' => 'Maria Silva'],
        ]);

        $this->assertSame('Maria Silva', $messages[0]['user']);
    }

    #[Test]
    public function resolves_object_users_and_falls_back_to_name(): void
    {
        $label = ChatArchiveUserResolver::resolveUserLabel((object) [
            'name' => 'João Pereira',
        ]);

        $this->assertSame('João Pereira', $label);
    }

    #[Test]
    public function returns_dash_for_empty_user_values(): void
    {
        $this->assertSame('—', ChatArchiveUserResolver::resolveUserLabel(null));
        $this->assertSame('—', ChatArchiveUserResolver::resolveUserLabel(''));
    }
}
