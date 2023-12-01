<?php

// app/Http/Resources/AuthenticationResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            //'user_type' => $this->user_type,
            'username' => $this->username,
            //'phone_number' => $this->phone_number,
            'name' => $this->name,
            'email' => $this->email,
            'photo_url' => $this->photo_url,
            'blocked' => $this->blocked,
            'balance' => $this->whenLoaded('vcard', function () {
                // Check if the 'vcard' relationship is loaded
                return $this->vcard->balance ?? null;
            }),
            'max_debit' => $this->max_debit,
            'custom_options' => $this->custom_options,
            'custom_data' => $this->custom_data,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}