<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    /**
     * POST /api/v1/payments/create-transaction
     * Authenticated. Creates a Midtrans Snap transaction for an invoice.
     */
    public function createTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_id' => ['required', 'uuid', 'exists:invoices,id'],
            'gateway'    => ['required', 'in:midtrans'],
        ]);

        $invoice = Invoice::with(['tenant', 'property'])->findOrFail($validated['invoice_id']);

        $this->authorize('view', $invoice);

        $result = $this->paymentService->createMidtransTransaction($invoice);

        return response()->json([
            'data' => [
                'transaction_token' => $result['transaction_token'],
                'redirect_url'      => $result['redirect_url'],
                'invoice_id'        => $invoice->id,
            ],
            'message' => 'Transaction created',
        ]);
    }

    /**
     * POST /api/v1/payments/webhook/midtrans
     * PUBLIC — no Sanctum auth. Midtrans posts here after payment.
     */
    public function midtransWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Verify signature before doing anything
        if (! $this->paymentService->verifyMidtransSignature($payload)) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $this->paymentService->dispatchMidtransWebhook($payload);

        return response()->json(['message' => 'Webhook received']);
    }

    /**
     * POST /api/v1/payments/webhook/xendit
     * Placeholder for Phase future — returns 200 to avoid Xendit retries.
     */
    public function xenditWebhook(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Acknowledged']);
    }

    /**
     * GET /api/v1/payments/{invoice}/status
     * Authenticated. Frontend polls this every 3 seconds on the payment page.
     */
    public function status(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return response()->json([
            'data'    => $this->paymentService->getInvoiceStatus($invoice),
            'message' => 'Success',
        ]);
    }
}
