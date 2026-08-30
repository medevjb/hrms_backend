<?php

use App\Support\Money;

/**
 * docs/PRD.md §141 — BCMath, scale 4, half-up rounding only at the final step.
 */
test('arithmetic keeps four-place precision without float drift', function () {
    expect(Money::add('0.1', '0.2'))->toBe('0.300000')
        ->and(Money::sub('30000.0000', '1234.5600'))->toBe('28765.440000')
        ->and(Money::mul('1000.0000', '2'))->toBe('2000.000000')
        ->and(Money::sum(['100.0000', '200.5000', '0.2500']))->toBe('300.750000');
});

test('division of a monthly salary produces a repeating value that keeps four places', function () {
    // 30000 / 30 is clean; 30000 / 26 is not.
    expect(Money::round(Money::div('30000.0000', '30')))->toBe('1000.0000')
        ->and(Money::round(Money::div('30000.0000', '26')))->toBe('1153.8462'); // 1153.846153... → half-up
});

test('rounding is half-up and symmetric for negatives', function () {
    expect(Money::round('1.23455'))->toBe('1.2346')
        ->and(Money::round('1.23454'))->toBe('1.2345')
        ->and(Money::round('-1.23455'))->toBe('-1.2346')
        ->and(Money::round('1.00005'))->toBe('1.0001');
});

test('dividing by zero yields zero rather than an error', function () {
    expect(Money::div('1000.0000', '0'))->toBe(Money::ZERO);
});

test('comparison and sign helpers', function () {
    expect(Money::compare('10.0000', '9.9999'))->toBe(1)
        ->and(Money::isZero('0.0000'))->toBeTrue()
        ->and(Money::isNegative('-0.0001'))->toBeTrue()
        ->and(Money::isPositive('0.0001'))->toBeTrue();
});

test('non-numeric input is rejected', function () {
    expect(fn () => Money::add('abc', '1'))->toThrow(InvalidArgumentException::class);
});
