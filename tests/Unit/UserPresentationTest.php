<?php

namespace Tests\Unit;

use App\Models\System\User;
use App\Models\System\UserSectorPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_presentation_accessors_return_expected_values(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria da Silva',
            'status' => 'A',
            'photo' => base64_encode(random_bytes(128)),
        ]);

        UserSectorPreference::create([
            'user_id' => $user->id,
            'sector_code' => 'S1',
            'sector_name' => 'UTI Adulto',
            'hospital_code' => '1',
            'hospital_name' => 'Hospital Central',
        ]);

        UserSectorPreference::create([
            'user_id' => $user->id,
            'sector_code' => 'S2',
            'sector_name' => 'Clínica Médica',
            'hospital_code' => '1',
            'hospital_name' => 'Hospital Central',
        ]);

        $user->load('sectorPreferences');

        $this->assertSame('teal', $user->status_color);
        $this->assertNotEmpty($user->display_photo);
        $this->assertSame('MS', $user->avatar_initials);
        $this->assertStringStartsWith('#', $user->avatar_bg_color);
        $this->assertArrayHasKey('Hospital Central', $user->sectors_by_hospital);
        $this->assertTrue($user->has_sectors);
    }
}
