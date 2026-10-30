<?php

namespace Tests\Feature;

use App\Reporting\CatatPenjualan;
use App\Reporting\PesanTidakValid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatatPenjualanTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $ubah = []): array
    {
        return array_replace([
            'schema_version' => 1,
            'operation_id' => '11111111-1111-4111-8111-111111111111',
            'sku_internal' => 'HJB-001',
            'perubahan' => -2,
            'alasan' => 'penjualan',
            'stok_sesudah' => 18,
            'terjadi_pada' => '2026-10-26T09:00:00+07:00',
        ], $ubah);
    }

    public function test_event_penjualan_dicatat(): void
    {
        $baru = app(CatatPenjualan::class)(
            $this->event(),
            '55555555-5555-4555-8555-555555555555',
        );

        $this->assertTrue($baru);
        $this->assertDatabaseCount('inbox', 1);
        $this->assertDatabaseCount('jurnal_penjualan', 1);
        $this->assertDatabaseHas('jurnal_penjualan', [
            'sku_internal' => 'HJB-001',
            'jumlah' => 2,
        ]);
    }

    public function test_event_duplikat_tidak_dicatat_lagi(): void
    {
        $catat = app(CatatPenjualan::class);
        $id = '55555555-5555-4555-8555-555555555555';

        $this->assertTrue($catat($this->event(), $id));
        $this->assertFalse($catat($this->event(), $id));

        $this->assertDatabaseCount('inbox', 1);
        $this->assertDatabaseCount('jurnal_penjualan', 1);
    }

    public function test_payload_tanpa_field_wajib_ditolak(): void
    {
        $event = $this->event();
        unset($event['perubahan']);

        $this->expectException(PesanTidakValid::class);

        app(CatatPenjualan::class)(
            $event,
            '55555555-5555-4555-8555-555555555555',
        );
    }

    public function test_penjualan_dengan_perubahan_positif_ditolak(): void
    {
        $this->expectException(PesanTidakValid::class);

        app(CatatPenjualan::class)(
            $this->event(['perubahan' => 2]),
            '55555555-5555-4555-8555-555555555555',
        );
    }

    public function test_restok_tidak_dicatat_sebagai_penjualan(): void
    {
        $baru = app(CatatPenjualan::class)(
            $this->event([
                'alasan' => 'restok',
                'perubahan' => 2,
                'stok_sesudah' => 22,
            ]),
            '55555555-5555-4555-8555-555555555555',
        );

        $this->assertFalse($baru);
        $this->assertDatabaseCount('jurnal_penjualan', 0);
    }
}
