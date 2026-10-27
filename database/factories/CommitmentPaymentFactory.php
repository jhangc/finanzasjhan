<?php

namespace Database\Factories;

use App\Models\Commitment;
use App\Models\CommitmentPayment;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommitmentPayment>
 */
class CommitmentPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'commitment_id' => fn (array $a) => Commitment::factory()->for(User::find($a['user_id'])),
            'period_id' => fn (array $a) => Period::factory()->for(User::find($a['user_id'])),
            'amount' => '120.00',
            'paid_at' => now()->toDateString(),
        ];
    }
}
