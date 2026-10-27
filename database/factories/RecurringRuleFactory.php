<?php

namespace Database\Factories;

use App\Models\RecurringRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringRule>
 */
class RecurringRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Internet',
            'kind' => RecurringRule::KIND_ENVELOPE,
            'amount' => '120.00',
            'day_of_month' => 15,
            'active' => true,
        ];
    }
}
