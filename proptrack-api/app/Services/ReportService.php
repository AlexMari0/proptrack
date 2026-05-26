<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReportService
{
    /**
     * Get overall financial summary.
     */
    public function getFinancialSummary(User $user, int $year, ?int $month = null): array
    {
        $invoicesQuery = Invoice::with('property')
            ->where('status', '!=', 'cancelled');

        // Scope by user roles
        if (!$user->hasRole('admin') && $user->hasRole('owner')) {
            $invoicesQuery->whereHas('property', function ($query) use ($user) {
                $query->where('owner_id', $user->id);
            });
        } elseif (!$user->hasRole('admin')) {
            $this->denyAccess();
        }

        $period = $this->applyPeriodFilter($invoicesQuery, $year, $month);
        $invoices = $invoicesQuery->get();

        $byProperty = [];
        $grouped = $invoices->groupBy('property_id');

        foreach ($grouped as $propertyId => $propertyInvoices) {
            $property = $propertyInvoices->first()->property;
            $propertyName = $property ? $property->name : 'Unknown Property';

            $metrics = $this->calculateMetrics($propertyInvoices);

            $byProperty[] = [
                'property_id'   => $propertyId,
                'property_name' => $propertyName,
                'invoiced'      => $metrics['invoiced'],
                'collected'     => $metrics['collected'],
                'outstanding'   => $metrics['outstanding'],
            ];
        }

        $overallMetrics = $this->calculateMetrics($invoices);

        return [
            'period'            => $period,
            'total_invoiced'    => $overallMetrics['invoiced'],
            'total_collected'   => $overallMetrics['collected'],
            'total_outstanding' => $overallMetrics['outstanding'],
            'collection_rate'   => $overallMetrics['collection_rate'],
            'by_property'       => $byProperty,
        ];
    }

    /**
     * Get financial summary for a specific property.
     */
    public function getPropertySummary(User $user, Property $property, int $year, ?int $month = null): array
    {
        // Ownership check for owners
        if (!$user->hasRole('admin') && $user->hasRole('owner')) {
            if ($property->owner_id !== $user->id) {
                throw new AccessDeniedHttpException('Unauthorized to view this property\'s financial report.');
            }
        } elseif (!$user->hasRole('admin')) {
            $this->denyAccess();
        }

        $invoicesQuery = Invoice::with('property')
            ->where('property_id', $property->id)
            ->where('status', '!=', 'cancelled');

        $period = $this->applyPeriodFilter($invoicesQuery, $year, $month);
        $invoices = $invoicesQuery->get();

        $metrics = $this->calculateMetrics($invoices);

        $byProperty = [[
            'property_id'   => $property->id,
            'property_name' => $property->name,
            'invoiced'      => $metrics['invoiced'],
            'collected'     => $metrics['collected'],
            'outstanding'   => $metrics['outstanding'],
        ]];

        return [
            'period'            => $period,
            'total_invoiced'    => $metrics['invoiced'],
            'total_collected'   => $metrics['collected'],
            'total_outstanding' => $metrics['outstanding'],
            'collection_rate'   => $metrics['collection_rate'],
            'by_property'       => $byProperty,
        ];
    }

    /**
     * Generate or retrieve report PDF path.
     */
    public function getOrGeneratePdf(array $data): string
    {
        $hash = md5(json_encode($data));
        $path = "reports/report-{$hash}.pdf";

        if (!Storage::disk('local')->exists($path)) {
            $this->generatePdf($data, $path);
        }

        return Storage::disk('local')->path($path);
    }

    /**
     * Generate a PDF report and write it to local storage.
     */
    public function generatePdf(array $data, string $storagePath): void
    {
        $pdf = Pdf::view('pdf.financial-report', [
            'data' => $data,
        ]);

        $absolutePath = Storage::disk('local')->path($storagePath);
        $dir = dirname($absolutePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->save($absolutePath);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Helper to apply period filters to an Eloquent builder.
     */
    private function applyPeriodFilter(Builder $query, int $year, ?int $month = null): string
    {
        if ($month) {
            $period = sprintf('%04d-%02d', $year, $month);
            $query->where('billing_month', $period);
        } else {
            $period = (string) $year;
            $query->where('billing_month', 'like', "{$year}-%");
        }

        return $period;
    }

    /**
     * Helper to calculate aggregate metrics from a collection of invoices.
     */
    private function calculateMetrics(Collection $invoices): array
    {
        $invoiced = $invoices->sum('amount');
        $collected = $invoices->where('status', 'paid')->sum('amount');
        $outstanding = $invoices->whereIn('status', ['unpaid', 'overdue'])->sum('amount');
        $collectionRate = $invoiced > 0 ? round(($collected / $invoiced) * 100, 1) : 0.0;

        return [
            'invoiced'        => $invoiced,
            'collected'       => $collected,
            'outstanding'     => $outstanding,
            'collection_rate' => $collectionRate,
        ];
    }

    /**
     * Helper to throw access denied exceptions.
     */
    private function denyAccess(): void
    {
        throw new AccessDeniedHttpException('Unauthorized to view financial reports.');
    }
}

