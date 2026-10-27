<?php

use App\Support\Money;

it('formats soles with thousands separator and two decimals', function (string|int|null $input, string $expected) {
    expect(Money::format($input))->toBe($expected);
})->with([
    ['1250', 'S/ 1,250.00'],
    ['1250.5', 'S/ 1,250.50'],
    ['85000.00', 'S/ 85,000.00'],
    ['0.99', 'S/ 0.99'],
    ['999999.99', 'S/ 999,999.99'],
    ['-45.10', '-S/ 45.10'],
    [0, 'S/ 0.00'],
    [null, 'S/ 0.00'],
]);

it('uses the dollar symbol for USD', function () {
    expect(Money::format('12.5', 'USD'))->toBe('$ 12.50');
});

it('adds and subtracts without float drift', function () {
    expect(Money::add('0.10', '0.20'))->toBe('0.30')
        ->and(Money::sub('100.00', '100.01'))->toBe('-0.01')
        ->and(Money::sum(['10.10', '20.20', '30.30']))->toBe('60.60');
});

it('divides rounding half up and never by zero', function () {
    expect(Money::div('100', '3'))->toBe('33.33')
        ->and(Money::div('2', '3'))->toBe('0.67')
        ->and(Money::div('-2', '3'))->toBe('-0.67')
        ->and(Money::div('50', '0'))->toBe('0.00');
});

it('computes percentages, comparisons and cents', function () {
    expect(Money::percent('150', '600'))->toBe(25)
        ->and(Money::percent('700', '600'))->toBe(116)
        ->and(Money::percent('10', '0'))->toBe(100)
        ->and(Money::percent('0', '0'))->toBe(0)
        ->and(Money::positive('-5'))->toBe('0.00')
        ->and(Money::isNegative('-0.01'))->toBeTrue()
        ->and(Money::toCents('12.34'))->toBe(1234)
        ->and(Money::round('-0.001'))->toBe('0.00');
});
