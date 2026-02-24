<?php

declare(strict_types=1);

use App\ValueObjects\Money;
use InvalidArgumentException;

test('money stores amount in cents and currency', function (): void {
    $m = Money::fromCents(1000, 'USD');
    expect($m->amount)->toBe(1000);
    expect($m->currency)->toBe('USD');
});

test('money add same currency', function (): void {
    $a = Money::fromCents(1000, 'USD');
    $b = Money::fromCents(500, 'USD');
    $sum = $a->add($b);
    expect($sum->amount)->toBe(1500);
    expect($sum->currency)->toBe('USD');
});

test('money subtract same currency', function (): void {
    $a = Money::fromCents(1000, 'USD');
    $b = Money::fromCents(300, 'USD');
    $diff = $a->subtract($b);
    expect($diff->amount)->toBe(700);
});

test('money multiply', function (): void {
    $m = Money::fromCents(100, 'EUR');
    $doubled = $m->multiply(2);
    expect($doubled->amount)->toBe(200);
    expect($doubled->currency)->toBe('EUR');
});

test('money equals same amount and currency', function (): void {
    $a = Money::fromCents(1000, 'USD');
    $b = Money::fromCents(1000, 'USD');
    expect($a->equals($b))->toBeTrue();
});

test('money equals false for different amount', function (): void {
    $a = Money::fromCents(1000, 'USD');
    $b = Money::fromCents(999, 'USD');
    expect($a->equals($b))->toBeFalse();
});

test('money equals false for different currency', function (): void {
    $a = Money::fromCents(1000, 'USD');
    $b = Money::fromCents(1000, 'EUR');
    expect($a->equals($b))->toBeFalse();
});

test('currency mismatch on add throws', function (): void {
    $a = Money::fromCents(1000, 'USD');
    $b = Money::fromCents(500, 'EUR');
    $a->add($b);
})->throws(InvalidArgumentException::class, 'Currency mismatch');

test('currency mismatch on subtract throws', function (): void {
    $a = Money::fromCents(1000, 'USD');
    $b = Money::fromCents(500, 'GBP');
    $a->subtract($b);
})->throws(InvalidArgumentException::class, 'Currency mismatch');

test('format returns readable string', function (): void {
    $m = Money::fromCents(12345, 'USD');
    expect($m->format())->toBe('123.45 USD');
});

test('invalid currency length throws', function (): void {
    new Money(100, 'US');
})->throws(InvalidArgumentException::class);
