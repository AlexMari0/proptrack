<?php

namespace App\Jobs;

use App\Services\ReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateFinancialReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly array $data)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(ReportService $reportService): void
    {
        $hash = md5(json_encode($this->data));
        $path = "reports/report-{$hash}.pdf";

        try {
            $reportService->generatePdf($this->data, $path);
            Log::info("Financial report PDF generated asynchronously. Hash: {$hash}");
        } catch (\Throwable $e) {
            Log::error("Failed to generate financial report PDF", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
