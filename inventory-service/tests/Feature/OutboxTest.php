<?php

namespace Tests\Feature;

use App\Models\Outbox;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use RefreshDatabase;

    private function produkContoh(): Produk
    {
        return Produk::create([
            'sku' => 'HJB-001',
            'nama' => 'Produk Simulasi A',
            'harga' => 45000,
            'stok' => 20,
        ]);
    }

    private function jual(int $jumlah = 2)
    {
        return $this->postJson(
            '/api/v1/produk/HJB-001/penyesuaian-stok',
            [
                'operation_id' => '44444444-4444-4444-8444-444444444444',
                'perubahan' => -$jumlah,
                'alasan' => 'penjualan',
            ]
        );
    }

    public function test_penjualan_mencatat_event_tertunda(): void
    {
        $this->produkContoh();

        $this->jual()->assertOk();

        $this->assertDatabaseCount('outbox', 1);

        $pesan = Outbox::sole();

        $this->assertSame('StokDisesuaikan', $pesan->jenis);
        $this->assertSame('stok.penjualan.v1', $pesan->routing_key);
        $this->assertSame(1, $pesan->payload['schema_version']);
        $this->assertSame('HJB-001', $pesan->payload['sku_internal']);
        $this->assertSame(-2, $pesan->payload['perubahan']);
        $this->assertSame(18, $pesan->payload['stok_sesudah']);
        $this->assertSame(
            '44444444-4444-4444-8444-444444444444',
            $pesan->payload['operation_id']
        );
        $this->assertNotEmpty($pesan->message_id);
        $this->assertNull($pesan->dikirim_pada);
    }

    public function test_stok_tidak_cukup_tidak_mencatat_event(): void
    {
        $produk = $this->produkContoh();

        $this->jual(21)->assertStatus(409);

        $this->assertSame(20, $produk->fresh()->stok);
        $this->assertDatabaseCount('outbox', 0);
        $this->assertDatabaseCount('operasi_stok', 0);
    }

    public function test_operasi_diulang_tidak_membuat_event_baru(): void
    {
        $produk = $this->produkContoh();

        $this->jual()->assertOk();
        $this->jual()->assertOk();

        $this->assertSame(18, $produk->fresh()->stok);
        $this->assertDatabaseCount('outbox', 1);
        $this->assertDatabaseCount('operasi_stok', 1);
    }

    public function test_outbox_gagal_membatalkan_perubahan_stok(): void
    {
        $produk = $this->produkContoh();

        Outbox::creating(function () {
            throw new \RuntimeException(
                'SIMULASI pencatatan outbox gagal'
            );
        });

        $this->jual()->assertStatus(500);

        $this->assertSame(20, $produk->fresh()->stok);
        $this->assertDatabaseCount('outbox', 0);
        $this->assertDatabaseCount('operasi_stok', 0);
    }
}
