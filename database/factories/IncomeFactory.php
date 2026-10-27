<?php

namespace Database\Factories;

use App\Models\Income;
use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Income>
 */
class IncomeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'period_id' => fn (array $a) => Period::factory()->for(User::find($a['user_id'])),
            'amount' => '6500.00',
            'currency' => 'PEN',
            'source' => 'Sueldo',
            'received_at' => now()->toDateString(),
            'confirmed' => false,
        ];
    }
}
