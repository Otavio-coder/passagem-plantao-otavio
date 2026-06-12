<?php

namespace Tests\Unit;

use App\Support\ChatArchiveShiftResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChatArchiveShiftResolverTest extends TestCase
{
    #[Test]
    public function infers_morning_shift_from_time(): void
    {
        $this->assertSame('manha', ChatArchiveShiftResolver::inferShift('2026-04-02 08:15:00'));
        $this->assertSame('Manhã', ChatArchiveShiftResolver::label(null, '2026-04-02 08:15:00'));
    }

    #[Test]
    public function infers_afternoon_and_night_shifts(): void
    {
        $this->assertSame('tarde', ChatArchiveShiftResolver::inferShift('2026-04-02 14:00:00'));
        $this->assertSame('Noite', ChatArchiveShiftResolver::label(null, '2026-04-02 21:00:00'));
    }

    #[Test]
    public function keeps_explicit_shift_labels(): void
    {
        $this->assertSame('Tarde', ChatArchiveShiftResolver::label('tarde'));
        $this->assertSame('—', ChatArchiveShiftResolver::label(null));
    }
}
