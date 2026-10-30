<?php

namespace Tests\Feature;

use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KontrakStokApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_penyesuaian_sku_tidak_ada_menghasilkan_404(): void
    {
        $this->postJson(
            '/api/v1/produk/HJB-999/penyesuaian-stok',
            [
                'operation_id' => '11111111-1111-4111-8111-111111111111',
                'perubahan' => -1,
                'alasan' => 'penjualan',
            ]
        )
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 404);

        $this->assertDatabaseCount('operasi_stok', 0);
        $this->assertDatabaseCount('outbox', 0);
    }

    public function test_tanpa_operation_id_ditolak_tanpa_efek(): void
    {
        $produk = Produk::create([
            'sku' => 'HJB-001',
            'nama' => 'Produk Simulasi A',
            'harga' => 45000,
            'stok' => 20,
        ]);

        $this->postJson(
            '/api/v1/produk/HJB-001/penyesuaian-stok',
            [
                'perubahan' => -1,
                'alasan' => 'penjualan',
            ]
        )
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 422)
            ->assertJsonStructure([
                'errors' => ['operation_id'],
            ]);

        $this->assertSame(20, $produk->fresh()->stok);
        $this->assertDatabaseCount('operasi_stok', 0);
        $this->assertDatabaseCount('outbox', 0);
    }
}
