<?php

namespace App\Payments;

/**
 * An authenticated provider notification, in provider-neutral terms.
 */
final class PaymentNotification
{
    public const SETTLED = 'settled';
    public const PENDING = 'pending';
    public const FAILED  = 'failed';

    public function __construct(
        public readonly string $invoiceNumber,
        /** One of SETTLED, PENDING, FAILED. */
        public readonly string $outcome,
        /** Amount the provider reports, in whole rupiah. */
        public readonly int $amount,
        public readonly string $gateway,
    ) {
    }

    public function isSettled(): bool
    {
        return $this->outcome === self::SETTLED;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self($data['invoiceNumber'], $data['outcome'], (int) $data['amount'], $data['gateway']);
    }
}
