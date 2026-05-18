<?php

namespace Database\Factories;

use App\Models\NurseHandoverBed;
use App\Models\System\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NurseHandoverBed>
 */
class NurseHandoverBedFactory extends Factory
{
    protected $model = NurseHandoverBed::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sector_id' => $this->faker->numberBetween(1, 100),
            'bed_code' => strtoupper($this->faker->randomLetter().$this->faker->numberBetween(1, 20)),
        ];
    }
}
