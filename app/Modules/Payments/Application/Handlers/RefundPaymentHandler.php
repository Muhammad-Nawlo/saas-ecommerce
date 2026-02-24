<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Handlers;

use App\Modules\Payments\Application\Commands\RefundPaymentCommand;
use App\Modules\Payments\Application\Services\PaymentService;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Shared\Domain\Exceptions\DomainException;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;

final readonly class RefundPaymentHandler
{
    public function __construct(
        private PaymentRepository $paymentRepository,
        private PaymentService $paymentService,
        private TransactionManager $transactionManager
    ) {
    }

    public function __invoke(RefundPaymentCommand $command): void
    {
        $this->transactionManager->run(function () use ($command): void {
            $paymentId = PaymentId::fromString($command->paymentId);
            $payment = $this->paymentRepository->findById($paymentId);
            if ($payment === null) {
                throw new DomainException('Payment not found');
            }
            $this->paymentService->refundPayment($payment);
            $payment->refund();
            $this->paymentRepository->save($payment);
        });
    }
}
