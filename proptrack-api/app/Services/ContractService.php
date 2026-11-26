<?php

namespace App\Services;

use App\Jobs\GenerateContractPdfJob;
use App\Models\Contract;
use Illuminate\Validation\ValidationException;

class ContractService
{
    /**
     * Create a new contract.
     * Enforces: one property cannot have two active contracts simultaneously.
     */
    public function createContract(array $data): Contract
    {
        $this->assertNoActiveContractForProperty($data['property_id']);

        $contract = Contract::create([
            'tenant_id'      => $data['tenant_id'],
            'property_id'    => $data['property_id'],
            'start_date'     => $data['start_date'],
            'end_date'       => $data['end_date'],
            'monthly_rent'   => $data['monthly_rent'],
            'deposit_amount' => $data['deposit_amount'],
            'billing_date'   => $data['billing_date'],
            'status'         => 'active',
        ]);

        $contract->load(['tenant', 'property']);

        // Queue PDF generation
        GenerateContractPdfJob::dispatch($contract);

        return $contract;
    }

    /**
     * Update an existing contract (only allowed while active).
     */
    public function updateContract(Contract $contract, array $data): Contract
    {
        if (! $contract->isActive()) {
            throw ValidationException::withMessages([
                'status' => 'Only active contracts can be updated.',
            ]);
        }

        $contract->update([
            'start_date'     => $data['start_date'],
            'end_date'       => $data['end_date'],
            'monthly_rent'   => $data['monthly_rent'],
            'deposit_amount' => $data['deposit_amount'],
            'billing_date'   => $data['billing_date'],
        ]);

        return $contract->fresh(['tenant', 'property']);
    }

    /**
     * Terminate an active contract before its end date.
     */
    public function terminateContract(Contract $contract): Contract
    {
        if (! $contract->isActive()) {
            throw ValidationException::withMessages([
                'status' => 'Only active contracts can be terminated.',
            ]);
        }

        $contract->update([
            'status'        => 'terminated',
            'terminated_at' => now(),
        ]);

        return $contract->fresh(['tenant', 'property']);
    }

    // ─── Private Guards ────────────────────────────────────────────────────────

    private function assertNoActiveContractForProperty(string $propertyId): void
    {
        $exists = Contract::where('property_id', $propertyId)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'property_id' => 'This property already has an active contract.',
            ]);
        }
    }
}
