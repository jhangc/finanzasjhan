<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Crea (o actualiza) el usuario dueño con los datos de OWNER_* del .env.
     */
    public function run(): void
    {
        $owner = config('app.owner');

        if (blank($owner['password'])) {
            throw new RuntimeException('Pon OWNER_PASSWORD en el .env antes de correr el seeder.');
        }

        User::updateOrCreate(
            ['email' => $owner['email']],
            [
                'name' => $owner['name'],
                'password' => $owner['password'],
                'email_verified_at' => now(),
                'currency_default' => 'PEN',
            ],
        );
    }
}
