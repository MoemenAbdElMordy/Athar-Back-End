<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'national_id' => $this->national_id,
            'date_of_birth' => optional($this->date_of_birth)->toDateString(),
            'id_document_path' => $this->id_document_path,
            'certification_document_path' => $this->certification_document_path,
            'profile_photo_path' => $this->profile_photo_path,
            'volunteer_languages' => $this->volunteer_languages,
            'volunteer_availability' => $this->volunteer_availability,
            'volunteer_motivation' => $this->volunteer_motivation,
            'disability_type' => $this->disability_type,
            'mobility_aids' => $this->mobility_aids,
            'assistance_needs' => $this->assistance_needs,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'password_changed_at' => optional($this->password_changed_at)->toIso8601String(),
            'role' => $this->role,
            'role_verified_at' => optional($this->role_verified_at)->toIso8601String(),
            'volunteer_status' => $this->role === 'volunteer'
                ? ($this->role_verified_at ? 'active' : 'pending_approval')
                : null,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
