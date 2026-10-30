<?php

namespace Tests\Unit;

use App\Events\StokDisesuaikan;
use Illuminate\Support\Str;
use Tests\TestCase;

class StokDisesuaikanTest extends TestCase
{
    public function test_payload_sesuai_kontrak_event(): void
    {
        $event = new StokDisesuaikan(
            operationId: '11111111-1111-4111-8111-111111111111',
            sku: 'HJB-001',
            perubahan: -2,
            alasan: 'penjualan',
            stokSesudah: 18,
            terjadiPada: '2026-10-27T09:00:00+07:00',
        );

        $this->assertTrue(Str::isUuid($event->id));

        $this->assertSame([
            'schema_version' => 1,
            'operation_id' => '11111111-1111-4111-8111-111111111111',
            'sku_internal' => 'HJB-001',
            'perubahan' => -2,
            'alasan' => 'penjualan',
            'stok_sesudah' => 18,
            'terjadi_pada' => '2026-10-27T09:00:00+07:00',
        ], $event->payload());
    }

    public function test_membaca_payload_tidak_mengganti_identitas_event(): void
    {
        $event = new StokDisesuaikan(
            operationId: '11111111-1111-4111-8111-111111111111',
            sku: 'HJB-001',
            perubahan: -2,
            alasan: 'penjualan',
            stokSesudah: 18,
            terjadiPada: '2026-10-27T09:00:00+07:00',
        );

        $idAwal = $event->id;
        $payloadAwal = $event->payload();

        $this->assertSame($payloadAwal, $event->payload());
        $this->assertSame($idAwal, $event->id);
    }
}
