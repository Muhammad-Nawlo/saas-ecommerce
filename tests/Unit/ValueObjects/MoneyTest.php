<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Exceptions\CurrencyMismatchException;
use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;
use App\Modules\Shared\Domain\ValueObjects\Money;

test('money stores amount in minor units and currency', function (): void {
    $m = Money::fromMinorUnits(1000, 'USD');
    expect($m->getMinorUnits())->toBe(1000);
    expect($m->getCurrency())->toBe('USD');
});

test('money add same currency', function (): void {
    $a = Money::fromMinorUnits(1000, 'USD');
    $b = Money::fromMinorUnits(500, 'USD');
    $sum = $a->add($b);
    expect($sum->getMinorUnits())->toBe(1500);
    expect($sum->getCurrency())->toBe('USD');
});

test('money subtract same currency', function (): void {
    $a = Money::fromMinorUnits(1000, 'USD');
    $b = Money::fromMinorUnits(300, 'USD');
    $diff = $a->subtract($b);
    expect($diff->getMinorUnits())->toBe(700);
});

test('money multiply', function (): void {
    $m = Money::fromMinorUnits(100, 'EUR');
    $doubled = $m->multiply(2);
    expect($doubled->getMinorUnits())->toBe(200);
    expect($doubled->getCurrency())->toBe('EUR');
});

test('money equals same amount and currency', function (): void {
    $a = Money::fromMinorUnits(1000, 'USD');
    $b = Money::fromMinorUnits(1000, 'USD');
    expect($a->equals($b))->toBeTrue();
});

test('money equals false for different amount', function (): void {
    $a = Money::fromMinorUnits(1000, 'USD');
    $b = Money::fromMinorUnits(999, 'USD');
    expect($a->equals($b))->toBeFalse();
});

test('money equals false for different currency', function (): void {
    $a = Money::fromMinorUnits(1000, 'USD');
    $b = Money::fromMinorUnits(1000, 'EUR');
    expect($a->equals($b))->toBeFalse();
});

test('currency mismatch on add throws', function (): void {
    $a = Money::fromMinorUnits(1000, 'USD');
    $b = Money::fromMinorUnits(500, 'EUR');
    $a->add($b);
})->throws(CurrencyMismatchException::class);

test('currency mismatch on subtract throws', function (): void {
    $a = Money::fromMinorUnits(1000, 'USD');
    $b = Money::fromMinorUnits(500, 'GBP');
    $a->subtract($b);
})->throws(CurrencyMismatchException::class);

test('format returns readable string', function (): void {
    $m = Money::fromMinorUnits(12345, 'USD');
    expect($m->format())->toBe('123.45 USD');
});

test('toArray returns amount and currency', function (): void {
    $m = Money::fromMinorUnits(1000, 'EUR');
    expect($m->toArray())->toBe(['amount' => 1000, 'currency' => 'EUR']);
});

test('invalid currency length throws', function (): void {
    Money::fromMinorUnits(100, 'US');
})->throws(InvalidValueObject::class);
