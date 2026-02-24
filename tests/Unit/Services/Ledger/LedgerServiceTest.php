<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ledger;

use App\Services\Ledger\LedgerService;
use PHPUnit\Framework\TestCase;

class LedgerServiceTest extends TestCase
{
    public function test_validate_balanced_accepts_debit_credit_equal(): void
    {
        $service = new LedgerService();
        $entries = [
            ['type' => 'debit', 'amount_cents' => 1000],
            ['type' => 'credit', 'amount_cents' => 1000],
        ];
        $service->validateBalanced($entries);
        $this->addToAssertionCount(1);
    }

    public function test_validate_balanced_rejects_unequal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unbalanced');
        $service = new LedgerService();
        $entries = [
            ['type' => 'debit', 'amount_cents' => 1000],
            ['type' => 'credit', 'amount_cents' => 500],
        ];
        $service->validateBalanced($entries);
    }

    public function test_validate_balanced_rejects_negative_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-negative');
        $service = new LedgerService();
        $entries = [
            ['type' => 'debit', 'amount_cents' => -100],
            ['type' => 'credit', 'amount_cents' => -100],
        ];
        $service->validateBalanced($entries);
    }
}
