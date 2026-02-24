<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

/**
 * Thrown when attempting to modify a financial order that is no longer in draft.
 */
final class FinancialOrderLockedException extends DomainException
{
}
