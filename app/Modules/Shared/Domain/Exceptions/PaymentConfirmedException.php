<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

/**
 * Thrown when attempting to modify amount or currency of a confirmed/succeeded payment.
 */
final class PaymentConfirmedException extends DomainException
{
}
