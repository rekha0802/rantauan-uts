<?php

namespace Tests\Feature;

use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArahStokApiTest extends TestCase
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

    public function test_penjualan_positif_ditolak_tanpa_efek(): void
    {
        $produk = $this->produkContoh();

        $this->postJson(
            '/api/v1/produk/HJB-001/penyesuaian-stok',
            [
                'operation_id' => '11111111-1111-4111-8111-111111111111',
                'perubahan' => 2,
                'alasan' => 'penjualan',
            ]
        )
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['errors' => ['perubahan']]);

        $this->assertSame(20, $produk->fresh()->stok);
        $this->assertDatabaseCount('operasi_stok', 0);
        $this->assertDatabaseCount('outbox', 0);
    }

    public function test_restok_negatif_ditolak_tanpa_efek(): void
    {
        $produk = $this->produkContoh();

        $this->postJson(
            '/api/v1/produk/HJB-001/penyesuaian-stok',
            [
                'operation_id' => '11111111-1111-4111-8111-111111111111',
                'perubahan' => -2,
                'alasan' => 'restok',
            ]
        )
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['errors' => ['perubahan']]);

        $this->assertSame(20, $produk->fresh()->stok);
        $this->assertDatabaseCount('operasi_stok', 0);
        $this->assertDatabaseCount('outbox', 0);
    }

    public function test_restok_positif_diterima(): void
    {
        $produk = $this->produkContoh();

        $this->postJson(
            '/api/v1/produk/HJB-001/penyesuaian-stok',
            [
                'operation_id' => '11111111-1111-4111-8111-111111111111',
                'perubahan' => 5,
                'alasan' => 'restok',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.stok', 25);

        $this->assertSame(25, $produk->fresh()->stok);
        $this->assertDatabaseCount('operasi_stok', 1);
        $this->assertDatabaseHas('outbox', [
            'routing_key' => 'stok.restok.v1',
        ]);
    }
}
