<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TambahProdukApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_produk_baru_mengembalikan_201_dan_location(): void
    {
        $this->postJson('/api/v1/produk', [
            'sku' => 'HJB-001',
            'nama' => 'Produk Simulasi A',
            'harga' => 45000,
        ])
            ->assertCreated()
            ->assertHeader(
                'Location',
                url('/api/v1/produk/HJB-001')
            )
            ->assertJsonPath('data.sku', 'HJB-001')
            ->assertJsonPath('data.nama', 'Produk Simulasi A')
            ->assertJsonPath('data.harga', 45000)
            ->assertJsonPath('data.stok', 0)
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

    public function test_produk_tanpa_nama_ditolak_dengan_422(): void
    {
        $this->postJson('/api/v1/produk', [
            'sku' => 'HJB-001',
            'harga' => 45000,
        ])
            ->assertStatus(422)
            ->assertHeader(
                'Content-Type',
                'application/problem+json'
            )
            ->assertJsonPath('status', 422)
            ->assertJsonStructure([
                'type',
                'title',
                'status',
                'detail',
                'instance',
                'errors' => ['nama'],
            ]);
    }
}
