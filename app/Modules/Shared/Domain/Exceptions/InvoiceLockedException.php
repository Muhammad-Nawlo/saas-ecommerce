<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Exceptions;

/**
 * Thrown when attempting to modify an issued/locked invoice's totals or snapshot.
 */
final class InvoiceLockedException extends DomainException
{
}
