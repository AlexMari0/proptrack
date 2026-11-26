<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Documents\Documents;
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

    /** Pre-render the PDF so the first download is instant. */
    public function handle(Documents $documents): void
    {
        try {
            $documents->contract($this->contract);
            Log::info("Contract PDF generated: {$this->contract->id}");
        } catch (\Throwable $e) {
            Log::error("Failed to generate contract PDF: {$this->contract->id}", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
