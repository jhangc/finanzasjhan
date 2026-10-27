<?php

namespace Database\Factories;

use App\Models\Period;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Period>
 */
class PeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'expected_income' => '6500.00',
            'savings_target' => '800.00',
        ];
    }
}
