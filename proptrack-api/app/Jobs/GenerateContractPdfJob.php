<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateContractPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(private readonly Contract $contract)
    {
    }

    public function handle(ContractService $contractService): void
    {
        $path = "contracts/contract-{$this->contract->id}.pdf";

        try {
            $contractService->generatePdf($this->contract, $path);
            Log::info("Contract PDF generated: {$this->contract->id}");
        } catch (\Throwable $e) {
            Log::error("Failed to generate contract PDF: {$this->contract->id}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
