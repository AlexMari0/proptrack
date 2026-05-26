<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Property;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    /**
     * GET /api/v1/reports/financial
     */
    public function financial(Request $request): JsonResponse
    {
        $request->validate([
            'year'  => 'required|integer|between:2020,2090',
            'month' => 'nullable|integer|between:1,12',
        ]);

        $summary = $this->reportService->getFinancialSummary(
            $request->user(),
            $request->integer('year'),
            $request->filled('month') ? $request->integer('month') : null
        );

        return response()->json([
            'data'    => new ReportResource($summary),
            'message' => 'Success',
        ]);
    }

    /**
     * GET /api/v1/reports/financial/{property}
     */
    public function propertyFinancial(Request $request, Property $property): JsonResponse
    {
        $request->validate([
            'year'  => 'required|integer|between:2020,2090',
            'month' => 'nullable|integer|between:1,12',
        ]);

        $summary = $this->reportService->getPropertySummary(
            $request->user(),
            $property,
            $request->integer('year'),
            $request->filled('month') ? $request->integer('month') : null
        );

        return response()->json([
            'data'    => new ReportResource($summary),
            'message' => 'Success',
        ]);
    }

    /**
     * GET /api/v1/reports/financial/export
     */
    public function export(Request $request): BinaryFileResponse
    {
        $request->validate([
            'year'        => 'required|integer|between:2020,2090',
            'month'       => 'nullable|integer|between:1,12',
            'property_id' => 'nullable|uuid|exists:properties,id',
        ]);

        if ($request->filled('property_id')) {
            $property = Property::findOrFail($request->property_id);
            $summary = $this->reportService->getPropertySummary(
                $request->user(),
                $property,
                $request->integer('year'),
                $request->filled('month') ? $request->integer('month') : null
            );
        } else {
            $summary = $this->reportService->getFinancialSummary(
                $request->user(),
                $request->integer('year'),
                $request->filled('month') ? $request->integer('month') : null
            );
        }

        $pdfPath = $this->reportService->getOrGeneratePdf($summary);

        return response()->download(
            $pdfPath,
            "financial-report-{$summary['period']}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}

