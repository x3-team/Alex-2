<?php

namespace App\Payments\Gateway;

use App\Payments\Data\GatewayPayment;
use App\Payments\Data\GatewayRefund;
use App\Payments\Data\Receipt;
use App\Payments\PaymentGatewayException;

/** Платёжный шлюз. idempotenceKey — наш UUID операции: повтор не создаёт второй платёж. */
interface PaymentGateway
{
    /** @throws PaymentGatewayException */
    public function createPayment(string $idempotenceKey, int $amountKopecks, string $description, string $returnUrl, Receipt $receipt, array $metadata = []): GatewayPayment;

    /** @throws PaymentGatewayException */
    public function getPayment(string $paymentId): GatewayPayment;

    /** @throws PaymentGatewayException */
    public function createRefund(string $idempotenceKey, string $paymentId, int $amountKopecks, string $description, Receipt $receipt): GatewayRefund;

    /** @throws PaymentGatewayException */
    public function getRefund(string $refundId): GatewayRefund;

    /** Второй чек «полный расчёт» после оказания услуги (зачёт предоплаты). */
    public function createSettlementReceipt(string $idempotenceKey, string $paymentId, Receipt $receipt): string;
}
