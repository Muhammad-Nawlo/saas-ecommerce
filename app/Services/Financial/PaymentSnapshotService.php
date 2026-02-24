<?php

declare(strict_types=1);

namespace App\Services\Financial;

use App\Models\Currency\Currency;
use App\Modules\Payments\Domain\Entities\Payment;
use App\Modules\Payments\Infrastructure\Persistence\PaymentModel;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Services\Currency\CurrencyConversionService;
use App\Services\Currency\CurrencyService;

/**
 * Fills payment currency snapshot (payment_currency, payment_amount, exchange_rate_snapshot, payment_amount_base).
 * Call once on payment confirmation; snapshot must never be recalculated.
 */
final class PaymentSnapshotService
{
    public function __construct(
        private CurrencyConversionService $conversionService,
        private CurrencyService $currencyService,
    ) {}

    public function fillSnapshot(Payment $payment, string $tenantId): void
    {
        $model = PaymentModel::where('id', $payment->id()->value())->where('tenant_id', $tenantId)->first();
        if ($model === null) {
            return;
        }
        if ($model->exchange_rate_snapshot !== null && $model->payment_amount_base !== null) {
            return; // Already filled
        }

        $money = Money::fromMinorUnits($payment->amount()->getMinorUnits(), $payment->amount()->getCurrency());
        $baseCurrency = $this->currencyService->getTenantBaseCurrency($tenantId);

        $multiCurrency = function_exists('tenant_feature') && (bool) tenant_feature('multi_currency');
        if ($multiCurrency && $baseCurrency->code !== $money->getCurrency()) {
            $result = $this->conversionService->convertWithSnapshot($money, $baseCurrency);
            $model->payment_currency = $money->getCurrency();
            $model->payment_amount = $money->getMinorUnits();
            $model->exchange_rate_snapshot = $result['rate_snapshot'];
            $model->payment_amount_base = $result['converted']->getMinorUnits();
        } else {
            $model->payment_currency = $money->getCurrency();
            $model->payment_amount = $money->getMinorUnits();
            $model->exchange_rate_snapshot = [
                'base_code' => $baseCurrency->code,
                'target_code' => $money->getCurrency(),
                'rate' => 1.0,
                'source' => 'manual',
                'effective_at' => now()->toIso8601String(),
            ];
            $model->payment_amount_base = $money->getMinorUnits();
        }
        $model->saveQuietly();
    }
}
