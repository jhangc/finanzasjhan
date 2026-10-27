<?php

use App\Models\Commitment;
use App\Models\Period;
use App\Models\User;
use Database\Seeders\DemoSeeder;

it('redirects guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

it('shows the login and register screens', function () {
    $this->get('/login')->assertOk()->assertSee('Ingresar');
    $this->get('/register')->assertOk()->assertSee('Crear cuenta');
});

it('registers a new user and sends them to the dashboard', function () {
    $this->post('/register', [
        'name' => 'Jhan',
        'email' => 'jhan@example.com',
        'password' => 'secreto-largo-123',
        'password_confirmation' => 'secreto-largo-123',
    ])->assertRedirect('/');

    $this->assertAuthenticated();
    expect(User::where('email', 'jhan@example.com')->first()->currency_default)->toBe('PEN');
});

it('lets the demo user log in and sends them to set up the month', function () {
    $this->seed(DemoSeeder::class);

    $this->post('/login', ['email' => 'demo@fincontrol.test', 'password' => 'password'])
        ->assertRedirect('/');

    // Sin datos de ejemplo: lo primero es armar el mes.
    $this->get('/')->assertRedirect(route('periods.setup'));
    $this->get(route('periods.setup'))->assertOk()->assertSee('Guardar mes');
});

it('seeds only the demo user', function () {
    $this->seed(DemoSeeder::class);

    expect(User::count())->toBe(1)
        ->and(Period::count())->toBe(0)
        ->and(Commitment::count())->toBe(0);
});

it('rejects wrong credentials', function () {
    $this->seed(DemoSeeder::class);

    $this->post('/login', ['email' => 'demo@fincontrol.test', 'password' => 'incorrecta'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('lets a logged-in user create another user without logging out', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->from(route('settings'))->post(route('users.store'), [
        'name' => 'Otra Persona',
        'email' => 'otra@example.com',
        'password' => 'secreto-largo-123',
        'password_confirmation' => 'secreto-largo-123',
    ])->assertRedirect(route('settings'));

    expect(User::where('email', 'otra@example.com')->exists())->toBeTrue();
    $this->assertAuthenticatedAs($me);
    $this->get(route('settings'))->assertSee('otra@example.com');
});

it('rejects a duplicate email when creating another user', function () {
    $me = User::factory()->create();

    $this->actingAs($me)->from(route('settings'))->post(route('users.store'), [
        'name' => 'Copia',
        'email' => $me->email,
        'password' => 'secreto-largo-123',
        'password_confirmation' => 'secreto-largo-123',
    ])->assertSessionHasErrorsIn('newUser', 'email');
});
