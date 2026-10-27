<?php

namespace Database\Factories;

use App\Models\Envelope;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Envelope>
 */
class EnvelopeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'period_id' => fn (array $a) => Period::factory()->for(User::find($a['user_id'])),
            'name' => fake()->word(),
            'allocated' => '300.00',
            'sort_order' => 0,
        ];
    }
}
