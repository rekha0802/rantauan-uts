<?php

namespace Tests\Feature;

use App\Messaging\RabbitPublisher;
use App\Models\Outbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelayOutboxTest extends TestCase
{
    use RefreshDatabase;

    private function pesan(int $nomor = 1): Outbox
    {
        return Outbox::create([
            'message_id' => sprintf(
                '55555555-5555-4555-8555-%012d',
                $nomor
            ),
            'jenis' => 'StokDisesuaikan',
            'routing_key' => 'stok.penjualan.v1',
            'payload' => [
                'schema_version' => 1,
                'operation_id' => sprintf(
                    '77777777-7777-4777-8777-%012d',
                    $nomor
                ),
                'sku_internal' => 'HJB-001',
                'perubahan' => -2,
                'alasan' => 'penjualan',
                'stok_sesudah' => 18,
                'terjadi_pada' => '2026-10-26T09:00:00+07:00',
            ],
        ]);
    }

    public function test_relay_mengirim_lalu_menandai_pesan(): void
    {
        $pesan = $this->pesan();

        $this->mock(RabbitPublisher::class, function ($mock) use ($pesan) {
            $mock->shouldReceive('publish')
                ->once()
                ->with(
                    $pesan->routing_key,
                    $pesan->payload,
                    $pesan->message_id,
                    $pesan->jenis,
                );
        });

        $this->artisan('outbox:relay')->assertSuccessful();

        $this->assertNotNull($pesan->fresh()->dikirim_pada);
    }

    public function test_pesan_terkirim_tidak_dikirim_ulang(): void
    {
        $pesan = $this->pesan();
        $pesan->update(['dikirim_pada' => now()]);

        $this->mock(RabbitPublisher::class, function ($mock) {
            $mock->shouldNotReceive('publish');
        });

        $this->artisan('outbox:relay')->assertSuccessful();
    }

    public function test_broker_gagal_pesan_tetap_menunggu(): void
    {
        $this->pesan(1);
        $this->pesan(2);

        $this->mock(RabbitPublisher::class, function ($mock) {
            $mock->shouldReceive('publish')
                ->once()
                ->andThrow(new \RuntimeException(
                    'SIMULASI broker tidak tersedia'
                ));
        });

        $this->artisan('outbox:relay')->assertSuccessful();

        $this->assertSame(
            2,
            Outbox::whereNull('dikirim_pada')->count()
        );
    }
}
