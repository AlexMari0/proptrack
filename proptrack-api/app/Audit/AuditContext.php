<?php

namespace App\Audit;

/**
 * Who is acting when there is no logged-in user: a payment gateway, a
 * scheduled command, a queued job. Audit entries record it as `source`.
 *
 *   app(AuditContext::class)->actingAs('gateway:midtrans', fn () => $invoice->markPaid(...));
 *
 * Without an explicit source, work outside an HTTP request is recorded as
 * 'system', so an audit entry is never anonymous.
 */
class AuditContext
{
    /** @var list<string> */
    private array $sources = [];

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public function actingAs(string $source, callable $callback): mixed
    {
        $this->sources[] = $source;

        try {
            return $callback();
        } finally {
            array_pop($this->sources);
        }
    }

    /**
     * The non-user actor for an audit entry, or null when a logged-in user acted.
     */
    public function source(?int $userId): ?string
    {
        if ($this->sources !== []) {
            return end($this->sources);
        }

        if ($userId !== null) {
            return null;
        }

        return app()->runningInConsole() ? 'system' : null;
    }
}
