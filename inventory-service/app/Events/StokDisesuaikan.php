<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Str;

class StokDisesuaikan
{
    use Dispatchable;

    public readonly string $id;

    public readonly string $terjadiPada;

    public function __construct(
        public readonly string $operationId,
        public readonly string $sku,
        public readonly int $perubahan,
        public readonly string $alasan,
        public readonly int $stokSesudah,
        ?string $terjadiPada = null,
    ) {
        $this->id = (string) Str::uuid();
        $this->terjadiPada = $terjadiPada
            ?? now()->toIso8601String();
    }

    public function payload(): array
    {
        return [
            'schema_version' => 1,
            'operation_id' => $this->operationId,
            'sku_internal' => $this->sku,
            'perubahan' => $this->perubahan,
            'alasan' => $this->alasan,
            'stok_sesudah' => $this->stokSesudah,
            'terjadi_pada' => $this->terjadiPada,
        ];
    }
}
