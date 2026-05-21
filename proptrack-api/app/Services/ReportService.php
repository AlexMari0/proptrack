<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Property;
use App\Models\User;
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
            throw new AccessDeniedHttpException('Unauthorized to view financial reports.');
        }

        // Apply period filter
        if ($month) {
            $period = sprintf('%04d-%02d', $year, $month);
            $invoicesQuery->where('billing_month', $period);
        } else {
            $period = (string) $year;
            $invoicesQuery->where('billing_month', 'like', "{$year}-%");
        }

        $invoices = $invoicesQuery->get();

        $totalInvoiced = $invoices->sum('amount');
        $totalCollected = $invoices->where('status', 'paid')->sum('amount');
        $totalOutstanding = $invoices->whereIn('status', ['unpaid', 'overdue'])->sum('amount');
        $collectionRate = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0.0;

        $byProperty = [];
        $grouped = $invoices->groupBy('property_id');

        foreach ($grouped as $propertyId => $propertyInvoices) {
            $property = $propertyInvoices->first()->property;
            $propertyName = $property ? $property->name : 'Unknown Property';

            $propInvoiced = $propertyInvoices->sum('amount');
            $propCollected = $propertyInvoices->where('status', 'paid')->sum('amount');
            $propOutstanding = $propertyInvoices->whereIn('status', ['unpaid', 'overdue'])->sum('amount');

            $byProperty[] = [
                'property_id'   => $propertyId,
                'property_name' => $propertyName,
                'invoiced'      => $propInvoiced,
                'collected'     => $propCollected,
                'outstanding'   => $propOutstanding,
            ];
        }

        return [
            'period'            => $period,
            'total_invoiced'    => $totalInvoiced,
            'total_collected'   => $totalCollected,
            'total_outstanding' => $totalOutstanding,
            'collection_rate'   => $collectionRate,
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
            throw new AccessDeniedHttpException('Unauthorized to view financial reports.');
        }

        $invoicesQuery = Invoice::with('property')
            ->where('property_id', $property->id)
            ->where('status', '!=', 'cancelled');

        // Apply period filter
        if ($month) {
            $period = sprintf('%04d-%02d', $year, $month);
            $invoicesQuery->where('billing_month', $period);
        } else {
            $period = (string) $year;
            $invoicesQuery->where('billing_month', 'like', "{$year}-%");
        }

        $invoices = $invoicesQuery->get();

        $totalInvoiced = $invoices->sum('amount');
        $totalCollected = $invoices->where('status', 'paid')->sum('amount');
        $totalOutstanding = $invoices->whereIn('status', ['unpaid', 'overdue'])->sum('amount');
        $collectionRate = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0.0;

        $byProperty = [[
            'property_id'   => $property->id,
            'property_name' => $property->name,
            'invoiced'      => $totalInvoiced,
            'collected'     => $totalCollected,
            'outstanding'   => $totalOutstanding,
        ]];

        return [
            'period'            => $period,
            'total_invoiced'    => $totalInvoiced,
            'total_collected'   => $totalCollected,
            'total_outstanding' => $totalOutstanding,
            'collection_rate'   => $collectionRate,
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
}
