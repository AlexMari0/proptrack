<?php

namespace App\Services;

use App\Jobs\GenerateContractPdfJob;
use App\Models\Contract;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Get or generate the PDF path for a contract.
     * Returns the absolute path to the PDF file.
     */
    public function getOrGeneratePdf(Contract $contract): string
    {
        $path = "contracts/contract-{$contract->id}.pdf";

        if (! Storage::disk('local')->exists($path)) {
            // Generate synchronously for immediate download
            $this->generatePdf($contract, $path);
        }

        return Storage::disk('local')->path($path);
    }

    /**
     * Internal: generate the PDF and save to storage.
     */
    public function generatePdf(Contract $contract, string $storagePath): void
    {
        $contract->loadMissing(['tenant', 'property']);

        $pdf = \Spatie\LaravelPdf\Facades\Pdf::view('pdf.contract', [
            'contract' => $contract,
        ])->landscape(false);

        $absolutePath = Storage::disk('local')->path($storagePath);

        // Ensure directory exists
        $dir = dirname($absolutePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->save($absolutePath);
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
