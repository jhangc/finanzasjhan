<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Por ahora solo el usuario demo; los datos de ejemplo llegan después.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@fincontrol.test'],
            [
                'name' => 'Demo',
                'password' => 'password',
                'email_verified_at' => now(),
                'currency_default' => 'PEN',
            ],
        );
    }
}
