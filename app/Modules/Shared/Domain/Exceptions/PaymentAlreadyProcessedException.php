<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

/**
 * Thrown when attempting to confirm or modify a payment that has already been processed.
 */
final class PaymentAlreadyProcessedException extends DomainException
{
}
