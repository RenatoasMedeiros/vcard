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
            'username' => $this->username,
            'name' => $this->name,
            'email' => $this->email,
            'photo_url' => $this->photo_url,
            'blocked' => $this->blocked,
            'balance' => optional($this->vcard)->balance, // Access the balance from the vcard relationship
            'max_debit' => optional($this->vcard)->max_debit, // Access the max_debit from the vcard relationship
            'piggy_bank' => optional($this->vcard)->piggy_bank, // Access the piggy_bank from the vcard relationship
            'custom_options' => optional($this->vcard)->custom_options,
            'custom_data' => optional($this->vcard)->custom_data
        ];
    }
}