<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EProviderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return array_merge(parent::toArray($request),[
            'addresses' => $this->addresses
        ]);
    }
}
