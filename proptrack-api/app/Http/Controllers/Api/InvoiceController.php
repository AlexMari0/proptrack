<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    /**
     * GET /api/v1/invoices
     * Supports ?status=, ?property_id=, ?month= (YYYY-MM), ?tenant_id=
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::with(['tenant', 'property', 'contract'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('contract_id')) {
            $query->where('contract_id', $request->contract_id);
        }

        if ($request->filled('month')) {
            $query->where('billing_month', $request->month);
        }

        $perPage = (int) $request->get('per_page', 15);
        $invoices = $query->paginate($perPage);

        return response()->json([
            'data' => InvoiceResource::collection($invoices),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'per_page'     => $invoices->perPage(),
                'total'        => $invoices->total(),
            ],
            'message' => 'Success',
        ]);
    }

    /**
     * GET /api/v1/invoices/{invoice}
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load(['contract', 'tenant', 'property']);

        return response()->json([
            'data'    => new InvoiceResource($invoice),
            'message' => 'Success',
        ]);
    }

    /**
     * POST /api/v1/invoices/{invoice}/send
     * Placeholder for Phase 3.2 notification dispatch.
     */
    public function send(Invoice $invoice): JsonResponse
    {
        $this->authorize('send', $invoice);

        $invoice->loadMissing(['contract', 'tenant', 'property']);

        // Notify tenant
        $tenantUser = \App\Models\User::where('email', $invoice->tenant->email)->first();
        if ($tenantUser) {
            $tenantUser->notify(new \App\Notifications\InvoiceCreatedNotification($invoice));
        }

        return response()->json([
            'data'    => new InvoiceResource($invoice->fresh(['contract', 'tenant', 'property'])),
            'message' => 'Invoice notification queued successfully',
        ]);
    }

    /**
     * GET /api/v1/invoices/{invoice}/document
     * Returns PDF as binary file download.
     */
    public function document(Invoice $invoice): BinaryFileResponse
    {
        $this->authorize('view', $invoice);

        $pdfPath = $this->invoiceService->getOrGeneratePdf($invoice);

        return response()->download(
            $pdfPath,
            "invoice-{$invoice->invoice_number}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}
