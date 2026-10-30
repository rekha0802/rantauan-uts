<?php

namespace Tests\Feature;

use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokApiTest extends TestCase
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

    private function payload(array $ubah = []): array
    {
        return array_replace([
            'operation_id' => '11111111-1111-4111-8111-111111111111',
            'perubahan' => -2,
            'alasan' => 'penjualan',
        ], $ubah);
    }

    public function test_penjualan_mengurangi_stok(): void
    {
        $produk = $this->produkContoh();

        $this->postJson(
            '/api/v1/produk/HJB-001/penyesuaian-stok',
            $this->payload()
        )->assertOk()->assertJsonPath('data.stok', 18);

        $this->assertSame(18, $produk->fresh()->stok);
    }

    public function test_stok_tidak_cukup_ditolak(): void
    {
        $produk = $this->produkContoh();

        $this->postJson(
            '/api/v1/produk/HJB-001/penyesuaian-stok',
            $this->payload(['perubahan' => -21])
        )->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409);

        $this->assertSame(20, $produk->fresh()->stok);
    }

    public function test_perubahan_nol_ditolak(): void
    {
        $produk = $this->produkContoh();

        $this->postJson(
            '/api/v1/produk/HJB-001/penyesuaian-stok',
            $this->payload(['perubahan' => 0])
        )->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422);

        $this->assertSame(20, $produk->fresh()->stok);
    }

    public function test_operasi_diulang_tidak_memotong_stok_lagi(): void
    {
        $produk = $this->produkContoh();
        $url = '/api/v1/produk/HJB-001/penyesuaian-stok';

        $this->postJson($url, $this->payload())
            ->assertOk()->assertJsonPath('data.stok', 18);

        $this->postJson($url, $this->payload())
            ->assertOk()->assertJsonPath('data.stok', 18);

        $this->assertSame(18, $produk->fresh()->stok);
    }

    public function test_operation_id_sama_dengan_payload_berbeda_ditolak(): void
    {
        $produk = $this->produkContoh();
        $url = '/api/v1/produk/HJB-001/penyesuaian-stok';

        $this->postJson($url, $this->payload())->assertOk();

        $this->postJson(
            $url,
            $this->payload(['perubahan' => -3])
        )->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 409);

        $this->assertSame(18, $produk->fresh()->stok);
    }
}
