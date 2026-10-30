<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProdukResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'nama' => $this->nama,
            'harga' => $this->harga,
            'stok' => $this->stok,
            'diperbarui_pada' => $this->updated_at?->toIso8601String(),
        ];
    }
}
