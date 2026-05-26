<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'user'        => $this->user ? [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
                'roles' => $this->user->roles->pluck('name'),
            ] : null,
            'action'      => $this->action,
            'model_type'  => class_basename($this->model_type),
            'model_id'    => $this->model_id,
            'old_values'  => $this->old_values,
            'new_values'  => $this->new_values,
            'ip_address'  => $this->ip_address,
            'created_at'  => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
