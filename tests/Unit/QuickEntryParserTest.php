<?php

use App\Models\Envelope;
use App\Services\Parsing\QuickEntryParser;

function envelopes(string ...$names)
{
    return collect($names)->map(fn ($name, $i) => (new Envelope(['name' => $name]))->forceFill(['id' => $i + 1]));
}

beforeEach(function () {
    $this->parser = new QuickEntryParser;
    $this->envelopes = envelopes('Comida fuera', 'Supermercado', 'Salidas', 'Transporte', 'Antojos', 'Varios', 'Café');
});

it('extracts the amount in every accepted format', function (string $input, string $amount) {
    expect($this->parser->parse($input, $this->envelopes)->amount)->toBe($amount);
})->with([
    ['25 comida', '25.00'],
    ['25.50 comida', '25.50'],
    ['25,50 comida', '25.50'],
    ['s/25 comida', '25.00'],
    ['S/ 12.5 comida', '12.50'],
    ['S/. 8 comida', '8.00'],
    ['comida 40', '40.00'],
    ['1,250.50 varios', '1250.50'],
]);

it('returns no amount when there is no number', function () {
    $entry = $this->parser->parse('comida', $this->envelopes);

    expect($entry->amount)->toBeNull()
        ->and($entry->hasAmount())->toBeFalse()
        ->and($entry->envelope->name)->toBe('Comida fuera');
});

it('matches exactly, case and accent insensitive', function () {
    expect($this->parser->parse('25 SALIDAS', $this->envelopes)->envelope->name)->toBe('Salidas')
        ->and($this->parser->parse('7 cafe', $this->envelopes)->envelope->name)->toBe('Café')
        ->and($this->parser->parse('7 café', $this->envelopes)->envelope->name)->toBe('Café');
});

it('matches by prefix', function () {
    expect($this->parser->parse('80 super', $this->envelopes)->envelope->name)->toBe('Supermercado')
        ->and($this->parser->parse('25 comida', $this->envelopes)->envelope->name)->toBe('Comida fuera')
        ->and($this->parser->parse('25 trans', $this->envelopes)->envelope->name)->toBe('Transporte')
        ->and($this->parser->parse('25 fuera', $this->envelopes)->envelope->name)->toBe('Comida fuera');
});

it('matches by similarity above 70 percent', function () {
    expect($this->parser->parse('12 antojo', $this->envelopes)->envelope->name)->toBe('Antojos')
        ->and($this->parser->parse('12 tansporte', $this->envelopes)->envelope->name)->toBe('Transporte')
        ->and($this->parser->parse('12 supermecado', $this->envelopes)->envelope->name)->toBe('Supermercado');
});

it('returns a null envelope when nothing matches', function () {
    $entry = $this->parser->parse('25 gasolina', $this->envelopes);

    expect($entry->envelope)->toBeNull()
        ->and($entry->amount)->toBe('25.00')
        ->and($entry->text)->toBe('gasolina')
        ->and($entry->isComplete())->toBeFalse();

    expect($this->parser->parse('25', $this->envelopes)->envelope)->toBeNull();
});

it('detects tc or tarjeta at the end', function () {
    $tc = $this->parser->parse('120 super tc', $this->envelopes);
    $tarjeta = $this->parser->parse('45.90 salidas TARJETA', $this->envelopes);
    $none = $this->parser->parse('45 salidas', $this->envelopes);

    expect($tc->paidWithCard)->toBeTrue()
        ->and($tc->envelope->name)->toBe('Supermercado')
        ->and($tarjeta->paidWithCard)->toBeTrue()
        ->and($tarjeta->envelope->name)->toBe('Salidas')
        ->and($none->paidWithCard)->toBeFalse();
});

it('works with an empty envelope list', function () {
    $entry = $this->parser->parse('25 comida', collect());

    expect($entry->envelope)->toBeNull()->and($entry->amount)->toBe('25.00');
});
