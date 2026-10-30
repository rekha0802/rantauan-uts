<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdukApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_produk_kosong_mengembalikan_200(): void
    {
        $this->getJson('/api/v1/produk')
            ->assertOk()
            ->assertExactJson([
                'data' => [],
            ]);
    }
}
