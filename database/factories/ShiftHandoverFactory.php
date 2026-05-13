<?php

namespace Database\Factories;

use App\Models\ShiftHandover;
use App\Models\System\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShiftHandover>
 */
class ShiftHandoverFactory extends Factory
{
    protected $model = ShiftHandover::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => ShiftHandover::STATUS_ACTIVE,
            'session_token' => Str::uuid()->toString(),
            'shift' => $this->faker->randomElement(['morning', 'afternoon', 'night']),
            'sector_ids' => [1],
            'sector_name' => $this->faker->word(),
            'bed_codes' => ['A01', 'A02', 'A03'],
            'beds_total' => 3,
            'beds_expected' => 3,
            'beds_visited' => 0,
            'started_at' => now(),
            'last_activity_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => ShiftHandover::STATUS_COMPLETED,
            'beds_visited' => 3,
            'finished_at' => now()->addMinutes(15),
            'duration_seconds' => 900,
            'completion_percentage' => 100.00,
            'forced_finish' => false,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn () => [
            'status' => ShiftHandover::STATUS_CANCELED,
            'canceled_at' => now()->addMinutes(5),
            'cancel_reason' => 'user_canceled',
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => [
            'status' => ShiftHandover::STATUS_ABANDONED,
            'last_activity_at' => now()->subMinutes(30),
            'abandoned_at' => now()->subMinutes(5),
        ]);
    }
}
