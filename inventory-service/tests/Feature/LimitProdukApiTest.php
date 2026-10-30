<?php

namespace Tests\Feature;

use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimitProdukApiTest extends TestCase
{
    use RefreshDatabase;

    private function siapkanProduk(): void
    {
        foreach (['HJB-002', 'HJB-001'] as $sku) {
            Produk::create([
                'sku' => $sku,
                'nama' => 'Produk Simulasi',
                'harga' => 45000,
                'stok' => 20,
            ]);
        }
    }

    public function test_limit_membatasi_produk_dan_tetap_urut_sku(): void
    {
        $this->siapkanProduk();

        $this->getJson('/api/v1/produk?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'HJB-001');
    }

    public function test_tanpa_limit_tetap_menampilkan_semua_produk(): void
    {
        $this->siapkanProduk();

        $this->getJson('/api/v1/produk')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_limit_tidak_valid_menghasilkan_422(): void
    {
        foreach (['0', '101', '-1', '1.5', 'teks'] as $limit) {
            $this->getJson('/api/v1/produk?limit='.$limit)
                ->assertStatus(422)
                ->assertHeader(
                    'Content-Type',
                    'application/problem+json'
                )
                ->assertJsonPath('status', 422)
                ->assertJsonStructure([
                    'errors' => ['limit'],
                ]);
        }
    }
}
