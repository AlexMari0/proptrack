<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'period'            => $this['period'],
            'total_invoiced'    => $this['total_invoiced'],
            'total_collected'   => $this['total_collected'],
            'total_outstanding' => $this['total_outstanding'],
            'collection_rate'   => $this['collection_rate'],
            'by_property'       => $this['by_property'],
        ];
    }
}
