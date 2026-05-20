<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private readonly TenantService $tenantService)
    {
    }

    /**
     * GET /api/v1/tenants
     * List tenants with optional search by name or email.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $query = Tenant::latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $tenants = $query->paginate($perPage);

        return response()->json([
            'data' => TenantResource::collection($tenants),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'last_page'    => $tenants->lastPage(),
                'per_page'     => $tenants->perPage(),
                'total'        => $tenants->total(),
            ],
            'message' => 'Success',
        ]);
    }

    /**
     * POST /api/v1/tenants
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->createTenant($request->validated());

        return response()->json([
            'data'    => new TenantResource($tenant),
            'message' => 'Tenant created successfully',
        ], 201);
    }

    /**
     * GET /api/v1/tenants/{tenant}
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        return response()->json([
            'data'    => new TenantResource($tenant),
            'message' => 'Success',
        ]);
    }

    /**
     * PUT /api/v1/tenants/{tenant}
     */
    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenantService->updateTenant($tenant, $request->validated());

        return response()->json([
            'data'    => new TenantResource($tenant),
            'message' => 'Tenant updated successfully',
        ]);
    }

    /**
     * DELETE /api/v1/tenants/{tenant}
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->authorize('delete', $tenant);

        $this->tenantService->deleteTenant($tenant);

        return response()->json([
            'message' => 'Tenant deleted successfully',
        ]);
    }
}
