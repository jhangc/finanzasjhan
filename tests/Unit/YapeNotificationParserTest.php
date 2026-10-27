<?php

use App\Services\Parsing\YapeNotificationParser;

beforeEach(fn () => $this->parser = new YapeNotificationParser);

it('parses yape and plin notifications', function (string $text, string $amount, string $counterpart) {
    expect($this->parser->parse($text))->toBe(['amount' => $amount, 'counterpart' => $counterpart]);
})->with([
    ['Yapeaste S/ 25.00 a Juan Pérez', '25.00', 'Juan Pérez'],
    ['yapeaste s/25 a Bodega Doña Rosa.', '25.00', 'Bodega Doña Rosa'],
    ['Plin: enviaste S/ 12.50 a María López', '12.50', 'María López'],
    ['PLIN enviaste S/. 1,250.00 a Inmobiliaria SAC', '1250.00', 'Inmobiliaria SAC'],
    ['Yapeaste S/ 8,50 a Taxi', '8.50', 'Taxi'],
]);

it('returns null when the text does not match', function (string $text) {
    expect($this->parser->parse($text))->toBeNull();
})->with([
    'Te yapearon S/ 25.00',
    '25 comida',
    'Yapeaste S/ 0.00 a Nadie',
    '',
]);
