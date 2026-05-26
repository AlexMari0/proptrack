<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Services\PropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function __construct(private readonly PropertyService $propertyService)
    {
    }

    /**
     * GET /api/v1/properties
     * List all properties with pagination and optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Property::class);

        $query = Property::with('owner')->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Search by name or address
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('address', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by active lease of the current authenticated user
        if ($request->boolean('has_active_lease')) {
            $user = $request->user();
            if ($user) {
                $tenant = \App\Models\Tenant::where('email', $user->email)->first();
                if ($tenant) {
                    $query->whereHas('contracts', function ($q) use ($tenant) {
                        $q->where('tenant_id', $tenant->id)
                          ->where('status', 'active');
                    });
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $properties = $query->paginate($perPage);

        return response()->json([
            'data'    => PropertyResource::collection($properties),
            'meta'    => [
                'current_page' => $properties->currentPage(),
                'last_page'    => $properties->lastPage(),
                'per_page'     => $properties->perPage(),
                'total'        => $properties->total(),
            ],
            'message' => 'Success',
        ]);
    }

    /**
     * POST /api/v1/properties
     * Create a new property.
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = $this->propertyService->createProperty(
            $request->user(),
            $request->validated()
        );

        // Eager-load owner for the resource
        $property->load('owner');

        return response()->json([
            'data'    => new PropertyResource($property),
            'message' => 'Property created successfully',
        ], 201);
    }

    /**
     * GET /api/v1/properties/{property}
     * Show a single property with owner and photos.
     */
    public function show(Property $property): JsonResponse
    {
        $this->authorize('view', $property);

        $property->load('owner');

        return response()->json([
            'data'    => new PropertyResource($property),
            'message' => 'Success',
        ]);
    }

    /**
     * PUT /api/v1/properties/{property}
     * Update an existing property.
     */
    public function update(UpdatePropertyRequest $request, Property $property): JsonResponse
    {
        $property = $this->propertyService->updateProperty($property, $request->validated());
        $property->load('owner');

        return response()->json([
            'data'    => new PropertyResource($property),
            'message' => 'Property updated successfully',
        ]);
    }

    /**
     * DELETE /api/v1/properties/{property}
     * Delete a property.
     */
    public function destroy(Request $request, Property $property): JsonResponse
    {
        $this->authorize('delete', $property);

        $this->propertyService->deleteProperty($property);

        return response()->json([
            'message' => 'Property deleted successfully',
        ]);
    }

    /**
     * POST /api/v1/properties/{property}/photos
     * Upload a photo to the property.
     */
    public function uploadPhoto(Request $request, Property $property): JsonResponse
    {
        $this->authorize('uploadPhoto', $property);

        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $media = $this->propertyService->uploadPhoto($property, $request->file('photo'));

        return response()->json([
            'data' => [
                'id'            => $media->id,
                'url'           => $media->getUrl(),
                'thumbnail_url' => $media->hasGeneratedConversion('thumb')
                    ? $media->getUrl('thumb')
                    : $media->getUrl(),
            ],
            'message' => 'Photo uploaded successfully',
        ], 201);
    }

    /**
     * DELETE /api/v1/properties/{property}/photos/{mediaId}
     * Delete a specific photo from the property.
     */
    public function deletePhoto(Request $request, Property $property, int $mediaId): JsonResponse
    {
        $this->authorize('deletePhoto', $property);

        $deleted = $this->propertyService->deletePhoto($property, $mediaId);

        if (! $deleted) {
            return response()->json(['message' => 'Photo not found'], 404);
        }

        return response()->json([
            'message' => 'Photo deleted successfully',
        ]);
    }
}
