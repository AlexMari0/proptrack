<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContractRequest;
use App\Http\Resources\ContractResource;
use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContractController extends Controller
{
    public function __construct(private readonly ContractService $contractService)
    {
    }

    /**
     * GET /api/v1/contracts
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);

        $query = Contract::with(['tenant', 'property'])->latest();

        if ($request->user()->hasRole('tenant')) {
            $tenant = \App\Models\Tenant::where('email', $request->user()->email)->first();
            if ($tenant) {
                $query->where('tenant_id', $tenant->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        $perPage = (int) $request->get('per_page', 15);
        $contracts = $query->paginate($perPage);

        return response()->json([
            'data' => ContractResource::collection($contracts),
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'last_page'    => $contracts->lastPage(),
                'per_page'     => $contracts->perPage(),
                'total'        => $contracts->total(),
            ],
            'message' => 'Success',
        ]);
    }

    /**
     * POST /api/v1/contracts
     */
    public function store(StoreContractRequest $request): JsonResponse
    {
        $contract = $this->contractService->createContract($request->validated());

        return response()->json([
            'data'    => new ContractResource($contract),
            'message' => 'Contract created successfully',
        ], 201);
    }

    /**
     * GET /api/v1/contracts/{contract}
     */
    public function show(Contract $contract): JsonResponse
    {
        $this->authorize('view', $contract);

        $contract->load(['tenant', 'property']);

        return response()->json([
            'data'    => new ContractResource($contract),
            'message' => 'Success',
        ]);
    }

    /**
     * PUT /api/v1/contracts/{contract}
     */
    public function update(Request $request, Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);

        $validated = $request->validate([
            'start_date'     => ['required', 'date'],
            'end_date'       => ['required', 'date', 'after:start_date'],
            'monthly_rent'   => ['required', 'integer', 'min:1'],
            'deposit_amount' => ['required', 'integer', 'min:0'],
            'billing_date'   => ['required', 'integer', 'min:1', 'max:28'],
        ]);

        $contract = $this->contractService->updateContract($contract, $validated);

        return response()->json([
            'data'    => new ContractResource($contract),
            'message' => 'Contract updated successfully',
        ]);
    }

    /**
     * POST /api/v1/contracts/{contract}/terminate
     */
    public function terminate(Contract $contract): JsonResponse
    {
        $this->authorize('terminate', $contract);

        $contract = $this->contractService->terminateContract($contract);

        return response()->json([
            'data'    => new ContractResource($contract),
            'message' => 'Contract terminated successfully',
        ]);
    }

    /**
     * GET /api/v1/contracts/{contract}/document
     * Returns the PDF contract document as a download.
     */
    public function document(Contract $contract): BinaryFileResponse
    {
        $this->authorize('view', $contract);

        $pdfPath = $this->contractService->getOrGeneratePdf($contract);

        return response()->download(
            $pdfPath,
            "contract-{$contract->id}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}
