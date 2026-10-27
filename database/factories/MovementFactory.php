<?php

namespace Database\Factories;

use App\Models\Envelope;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movement>
 */
class MovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'envelope_id' => fn (array $a) => Envelope::factory()->for(User::find($a['user_id'])),
            'amount' => '25.00',
            'spent_at' => now(),
            'note' => null,
        ];
    }
}
