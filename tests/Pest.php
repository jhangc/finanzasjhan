<?php

use App\Models\Envelope;
use App\Models\Period;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Crea un periodo con sobres para un usuario. $envelopes: ['Nombre' => 'asignado'].
 */
function makePeriod(?User $user = null, array $attributes = [], array $envelopes = []): Period
{
    $user ??= User::factory()->create();

    $period = Period::factory()->for($user)->create($attributes);

    $order = 0;
    foreach ($envelopes as $name => $allocated) {
        Envelope::factory()->for($user)->for($period)->create([
            'name' => $name,
            'allocated' => $allocated,
            'sort_order' => $order++,
        ]);
    }

    return $period->fresh();
}
