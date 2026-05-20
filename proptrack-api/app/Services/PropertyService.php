<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PropertyService
{
    /**
     * Create a new property for the given owner.
     */
    public function createProperty(User $owner, array $data): Property
    {
        return Property::create([
            'owner_id'      => $owner->id,
            'name'          => $data['name'],
            'address'       => $data['address'],
            'type'          => $data['type'],
            'status'        => $data['status'] ?? 'available',
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'description'   => $data['description'] ?? null,
            'monthly_price' => $data['monthly_price'],
        ]);
    }

    /**
     * Update an existing property's details.
     */
    public function updateProperty(Property $property, array $data): Property
    {
        $property->update([
            'name'          => $data['name'],
            'address'       => $data['address'],
            'type'          => $data['type'],
            'status'        => $data['status'],
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'description'   => $data['description'] ?? null,
            'monthly_price' => $data['monthly_price'],
        ]);

        return $property->fresh();
    }

    /**
     * Delete a property and all its associated media.
     */
    public function deleteProperty(Property $property): bool
    {
        // Spatie Media Library will clean up media files automatically
        $property->clearMediaCollection('images');
        $property->delete();

        return true;
    }

    /**
     * Upload a photo to the property's image collection.
     */
    public function uploadPhoto(Property $property, mixed $file): Media
    {
        return $property
            ->addMedia($file)
            ->toMediaCollection('images');
    }

    /**
     * Delete a specific photo from the property.
     */
    public function deletePhoto(Property $property, int $mediaId): bool
    {
        $media = $property->getMedia('images')->firstWhere('id', $mediaId);

        if (!$media) {
            return false;
        }

        $media->delete();

        return true;
    }
}
