<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Handlers;

use App\Modules\Payments\Application\Commands\CancelPaymentCommand;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class CancelPaymentHandler
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(CancelPaymentCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $paymentId = PaymentId::fromString($command->paymentId);
            $payment = $this->paymentRepository->findById($paymentId);
            if ($payment === null) {
                throw new DomainException('Payment not found');
            }
            $payment->cancel();
            $this->paymentRepository->save($payment);
        });
    }
}
