<?php

namespace Tests\Feature;

use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetailProdukApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_produk_yang_ada_mengembalikan_200(): void
    {
        Produk::create([
            'sku' => 'HJB-001',
            'nama' => 'Produk Simulasi A',
            'harga' => 45000,
            'stok' => 20,
        ]);

        $this->getJson('/api/v1/produk/HJB-001')
            ->assertOk()
            ->assertJsonPath('data.sku', 'HJB-001')
            ->assertJsonPath('data.nama', 'Produk Simulasi A')
            ->assertJsonPath('data.harga', 45000)
            ->assertJsonPath('data.stok', 20)
            ->assertJsonStructure([
                'data' => [
                    'sku',
                    'nama',
                    'harga',
                    'stok',
                    'diperbarui_pada',
                ],
            ]);
    }

    public function test_sku_tidak_ditemukan_mengembalikan_404(): void
    {
        $this->getJson('/api/v1/produk/HJB-999')
            ->assertNotFound()
            ->assertHeader(
                'Content-Type',
                'application/problem+json'
            )
            ->assertJsonPath('status', 404)
            ->assertJsonStructure([
                'type',
                'title',
                'status',
                'detail',
                'instance',
            ]);
    }
}
