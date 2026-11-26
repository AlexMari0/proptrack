<?php

namespace App\Payments;

final class Checkout
{
    public function __construct(
        public readonly string $token,
        public readonly string $redirectUrl,
        /** True when no real provider is involved (local payment simulator). */
        public readonly bool $simulated = false,
    ) {
    }
}
