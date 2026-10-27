<?php

namespace Database\Factories;

use App\Models\Commitment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commitment>
 */
class CommitmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => Commitment::TYPE_FIXED,
            'monthly_amount' => '120.00',
            'pay_day' => 15,
            'is_card' => false,
            'active' => true,
        ];
    }
}
